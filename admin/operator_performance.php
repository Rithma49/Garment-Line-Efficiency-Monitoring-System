<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();
$pdo = getDBConnection();

$from = get('from', date('Y-m-01'));
$to = get('to', date('Y-m-d'));

$stmt = $pdo->prepare(
    "SELECT o.operator_id, o.full_name, o.employee_id, pl.line_number,
            COUNT(DISTINCT CASE WHEN a.status='present' THEN a.attendance_date END) days_present,
            COUNT(DISTINCT a.attendance_date) days_total,
            COALESCE(SUM(a.overtime_hours),0) overtime,
            COALESCE((SELECT SUM(qd.quantity) FROM quality_defects qd WHERE qd.operator_id=o.operator_id AND qd.defect_date BETWEEN :from3 AND :to3),0) defects
     FROM operators o
     LEFT JOIN production_lines pl ON pl.line_id = o.line_id
     LEFT JOIN attendance a ON a.operator_id = o.operator_id AND a.attendance_date BETWEEN :from AND :to
     WHERE o.status='active'
     GROUP BY o.operator_id
     ORDER BY days_present DESC, defects ASC"
);
$stmt->execute(['from' => $from, 'to' => $to, 'from3' => $from, 'to3' => $to]);
$performance = $stmt->fetchAll();

$rank = 1;
$labels = []; $attendanceRates = [];
foreach ($performance as &$row) {
    $row['rank'] = $rank++;
    $row['attendance_rate'] = $row['days_total'] > 0 ? round(($row['days_present'] / $row['days_total']) * 100, 1) : 0;
    $labels[] = $row['full_name'];
    $attendanceRates[] = $row['attendance_rate'];
}
unset($row);

$pageTitle = 'Operator Performance';
$activePage = 'performance';
$breadcrumbs = [['label' => 'Operator Performance']];
include BASE_PATH . '/includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="fw-bold mb-0">Operator Performance</h4>
    <form method="GET" class="d-flex gap-2">
        <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>">
        <input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
        <button class="btn btn-outline-secondary btn-sm">Apply</button>
    </form>
</div>

<div class="card mb-3">
    <div class="card-header">Attendance Rate by Operator</div>
    <div class="card-body"><canvas id="perfChart" height="90"></canvas></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr><th>Rank</th><th>Operator</th><th>Line</th><th>Days Present</th><th>Attendance %</th><th>Overtime Hrs</th><th>Defects Caused</th></tr></thead>
            <tbody>
            <?php foreach ($performance as $p): ?>
            <tr>
                <td>
                    <?php if ($p['rank'] <= 3): ?><span class="badge bg-warning">#<?= $p['rank'] ?></span><?php else: ?>#<?= $p['rank'] ?><?php endif; ?>
                </td>
                <td class="fw-semibold"><?= e($p['full_name']) ?> <span class="text-muted small">(<?= e($p['employee_id']) ?>)</span></td>
                <td><?= e($p['line_number'] ?? '-') ?></td>
                <td><?= e($p['days_present']) ?> / <?= e($p['days_total']) ?></td>
                <td><?= e($p['attendance_rate']) ?>%</td>
                <td><?= e($p['overtime']) ?>h</td>
                <td><?= e($p['defects']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($performance)): ?><tr><td colspan="7" class="text-center text-muted py-4">No performance data available for this period.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$extraScripts = '<script>
new Chart(document.getElementById("perfChart"), {
    type: "bar",
    data: {
        labels: ' . json_encode($labels) . ',
        datasets: [{ label: "Attendance %", data: ' . json_encode($attendanceRates) . ', backgroundColor: "#1E3A8A", borderRadius: 6 }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100 } } }
});
</script>';
include BASE_PATH . '/includes/layout_end.php';
?>
