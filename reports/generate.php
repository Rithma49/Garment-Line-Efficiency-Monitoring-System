<?php
/**
 * GLEMS - Universal Report Generator
 * Handles: daily_production, weekly_production, line_efficiency, machine_downtime,
 *          operator_performance, attendance, quality, order_progress
 * Formats: pdf (mPDF), excel (CSV), print (HTML)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_permission('reports.view');

$pdo = getDBConnection();
$type = get('type', 'daily_production');
$from = get('from', date('Y-m-01'));
$to = get('to', date('Y-m-d'));
$format = get('format', 'print');

$reportTitles = [
    'daily_production'     => 'Daily Production Report',
    'weekly_production'    => 'Weekly Production Report',
    'line_efficiency'      => 'Line Efficiency Report',
    'machine_downtime'     => 'Machine Downtime Report',
    'operator_performance' => 'Operator Performance Report',
    'attendance'           => 'Attendance Report',
    'quality'              => 'Quality Report',
    'order_progress'       => 'Order Progress Report',
];
$title = $reportTitles[$type] ?? 'Report';

// ---------------------------------------------------------------
// FETCH DATA PER REPORT TYPE
// ---------------------------------------------------------------
$columns = [];
$rows = [];

switch ($type) {
    case 'daily_production':
    case 'weekly_production':
        $columns = ['Date', 'Line', 'Produced Qty', 'Rejected Qty', 'Target', 'Efficiency %'];
        $stmt = $pdo->prepare(
            "SELECT pr.record_date, pl.line_number, SUM(pr.produced_qty) produced, SUM(pr.rejected_qty) rejected,
                    COALESCE(pt.target_quantity,0) target
             FROM production_records pr
             JOIN production_lines pl ON pl.line_id = pr.line_id
             LEFT JOIN production_targets pt ON pt.line_id = pr.line_id AND pt.target_date = pr.record_date AND pt.target_type='daily'
             WHERE pr.record_date BETWEEN :from AND :to
             GROUP BY pr.record_date, pl.line_id
             ORDER BY pr.record_date, pl.line_number"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        foreach ($stmt->fetchAll() as $r) {
            $eff = calc_efficiency($r['produced'], $r['target']);
            $rows[] = [format_date($r['record_date']), $r['line_number'], $r['produced'], $r['rejected'], $r['target'], $eff . '%'];
        }
        break;

    case 'line_efficiency':
        $columns = ['Line', 'Total Produced', 'Total Target', 'Efficiency %', 'Status'];
        $stmt = $pdo->prepare(
            "SELECT pl.line_number, COALESCE(SUM(pr.produced_qty),0) produced, COALESCE(SUM(pt.target_quantity),0) target
             FROM production_lines pl
             LEFT JOIN production_records pr ON pr.line_id=pl.line_id AND pr.record_date BETWEEN :from AND :to
             LEFT JOIN production_targets pt ON pt.line_id=pl.line_id AND pt.target_date BETWEEN :from2 AND :to2 AND pt.target_type='daily'
             GROUP BY pl.line_id
             ORDER BY pl.line_number"
        );
        $stmt->execute(['from' => $from, 'to' => $to, 'from2' => $from, 'to2' => $to]);
        foreach ($stmt->fetchAll() as $r) {
            $eff = calc_efficiency($r['produced'], $r['target']);
            $rows[] = [$r['line_number'], $r['produced'], $r['target'], $eff . '%', efficiency_badge_label($eff)];
        }
        break;

    case 'machine_downtime':
        $columns = ['Machine', 'Problem', 'Start', 'End', 'Duration (min)', 'Reason', 'Cost (Rs.)', 'Status'];
        $stmt = $pdo->prepare(
            "SELECT md.*, m.machine_number FROM machine_downtime md
             JOIN machines m ON m.machine_id=md.machine_id
             WHERE DATE(md.start_time) BETWEEN :from AND :to
             ORDER BY md.start_time"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [
                $r['machine_number'],
                $r['problem'],
                format_date($r['start_time'], 'd M h:i A'),
                $r['end_time'] ? format_date($r['end_time'], 'd M h:i A') : '-',
                $r['duration_minutes'] ?? '-',
                $r['reason'],
                number_format($r['repair_cost'], 2),
                ucfirst($r['status']),
            ];
        }
        break;

    case 'operator_performance':
        $columns = ['Operator', 'Employee ID', 'Line', 'Days Present', 'Overtime Hrs', 'Defects Caused'];
        $stmt = $pdo->prepare(
            "SELECT o.full_name, o.employee_id, pl.line_number,
                    COUNT(DISTINCT CASE WHEN a.status='present' THEN a.attendance_date END) days_present,
                    COALESCE(SUM(a.overtime_hours),0) overtime,
                    COALESCE((SELECT SUM(qd.quantity) FROM quality_defects qd WHERE qd.operator_id=o.operator_id AND qd.defect_date BETWEEN :from3 AND :to3),0) defects
             FROM operators o
             LEFT JOIN production_lines pl ON pl.line_id=o.line_id
             LEFT JOIN attendance a ON a.operator_id=o.operator_id AND a.attendance_date BETWEEN :from AND :to
             GROUP BY o.operator_id
             ORDER BY o.full_name"
        );
        $stmt->execute(['from' => $from, 'to' => $to, 'from3' => $from, 'to3' => $to]);
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [$r['full_name'], $r['employee_id'], $r['line_number'] ?? '-', $r['days_present'], $r['overtime'], $r['defects']];
        }
        break;

    case 'attendance':
        $columns = ['Date', 'Employee', 'Check In', 'Check Out', 'Working Hrs', 'Late', 'Overtime', 'Status'];
        $stmt = $pdo->prepare(
            "SELECT a.*, o.full_name FROM attendance a
             JOIN operators o ON o.operator_id=a.operator_id
             WHERE a.attendance_date BETWEEN :from AND :to
             ORDER BY a.attendance_date, o.full_name"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [
                format_date($r['attendance_date']),
                $r['full_name'],
                $r['check_in'] ?: '-',
                $r['check_out'] ?: '-',
                $r['working_hours'],
                $r['late_arrival'] ? 'Yes' : 'No',
                $r['overtime_hours'],
                ucfirst($r['status']),
            ];
        }
        break;

    case 'quality':
        $columns = ['Date', 'Product', 'Line', 'Operator', 'Defect Type', 'Qty', 'Remarks'];
        $stmt = $pdo->prepare(
            "SELECT qd.*, pl.line_number, o.full_name AS operator_name FROM quality_defects qd
             JOIN production_lines pl ON pl.line_id=qd.line_id
             LEFT JOIN operators o ON o.operator_id=qd.operator_id
             WHERE qd.defect_date BETWEEN :from AND :to
             ORDER BY qd.defect_date"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [
                format_date($r['defect_date']),
                $r['product_name'],
                $r['line_number'],
                $r['operator_name'] ?? '-',
                $r['defect_type'],
                $r['quantity'],
                $r['remarks'],
            ];
        }
        break;

    case 'order_progress':
        $columns = ['PO Number', 'Buyer', 'Style', 'Order Qty', 'Delivery Date', 'Priority', 'Progress %', 'Status'];
        $stmt = $pdo->prepare(
            "SELECT o.*, b.buyer_name FROM orders o
             JOIN buyers b ON b.buyer_id=o.buyer_id
             WHERE o.delivery_date BETWEEN :from AND :to
             ORDER BY o.delivery_date"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [
                $r['po_number'],
                $r['buyer_name'],
                $r['style_number'],
                $r['order_quantity'],
                format_date($r['delivery_date']),
                ucfirst($r['priority']),
                $r['current_progress'] . '%',
                ucfirst($r['status']),
            ];
        }
        break;
}

log_activity('report_generated', 'reports', "$title ($format)");

// ---------------------------------------------------------------
// OUTPUT: CSV / EXCEL
// ---------------------------------------------------------------
if ($format === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/\s+/', '_', strtolower($title)) . '_' . date('Ymd') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, $columns);
    foreach ($rows as $r) {
        fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

// ---------------------------------------------------------------
// BUILD HTML (shared by print + PDF)
// ---------------------------------------------------------------
$companyName = 'Ceylon Apparel Exports (Pvt) Ltd';
try {
    $s = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='company_name'")->fetchColumn();
    if ($s) $companyName = $s;
} catch (Throwable $e) {}

ob_start();
?>
<div style="font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color:#1E293B;">
    <div style="text-align:center; margin-bottom: 16px;">
        <h2 style="color:#1E3A8A; margin-bottom:2px;"><?= e($companyName) ?></h2>
        <h4 style="margin:4px 0;"><?= e($title) ?></h4>
        <div style="color:#64748B;">
            Period: <?= e(format_date($from)) ?> to <?= e(format_date($to)) ?>
            &middot; Generated: <?= date('d M Y, h:i A') ?>
        </div>
    </div>

    <table style="width:100%; border-collapse: collapse;" border="1" cellpadding="6">
        <thead>
            <tr style="background:#1E3A8A; color:#fff;">
                <?php foreach ($columns as $c): ?>
                <th><?= e($c) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <?php foreach ($r as $cell): ?>
                <td><?= e((string)$cell) ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
            <tr>
                <td colspan="<?= count($columns) ?>" style="text-align:center;color:#64748B;">
                    No data found for this period.
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$html = ob_get_clean();

// ---------------------------------------------------------------
// OUTPUT: PDF (mPDF)
// ---------------------------------------------------------------
if ($format === 'pdf') {
    $autoload = BASE_PATH . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        echo '<div style="font-family:sans-serif;padding:2rem;">
              <p style="color:#DC2626;font-weight:bold;">mPDF library not found.</p>
              <p>Run <code>composer require mpdf/mpdf</code> inside the project root to enable PDF export, then try again.</p>
              <hr>' . $html . '</div>';
        exit;
    }
    require_once $autoload;
    $mpdf = new \Mpdf\Mpdf(['format' => 'A4-L', 'margin_top' => 15, 'margin_bottom' => 15]);
    $mpdf->SetTitle($title);
    $mpdf->WriteHTML($html);
    $mpdf->Output(preg_replace('/\s+/', '_', strtolower($title)) . '_' . date('Ymd') . '.pdf', 'D');
    exit;
}

// ---------------------------------------------------------------
// OUTPUT: PRINT-FRIENDLY HTML
// ---------------------------------------------------------------
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?= e($title) ?></title>
<style>
body { font-family: Arial, sans-serif; padding: 24px; }
table { width:100%; border-collapse: collapse; margin-top: 12px; }
th, td { border: 1px solid #E2E8F0; padding: 8px; font-size: 13px; text-align: left; }
th { background: #1E3A8A; color: #fff; }
.print-btn { margin-bottom: 16px; }
@media print { .print-btn { display: none; } }
</style>
</head>
<body>
<div class="print-btn">
    <button onclick="window.print()">
        <i class="fa-solid fa-print me-1"></i>Print This Report
    </button>
</div>
<?= $html ?>
</body>
</html>