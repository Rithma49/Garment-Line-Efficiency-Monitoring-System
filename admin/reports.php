<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();
$pdo = getDBConnection();

$reportTypes = [
    'daily_production'    => 'Daily Production Report',
    'weekly_production'   => 'Weekly Production Report',
    'line_efficiency'     => 'Line Efficiency Report',
    'machine_downtime'    => 'Machine Downtime Report',
    'operator_performance'=> 'Operator Performance Report',
    'attendance'          => 'Attendance Report',
    'quality'             => 'Quality Report',
    'order_progress'      => 'Order Progress Report',
];

$pageTitle = 'Reports';
$activePage = 'reports';
$breadcrumbs = [['label' => 'Reports']];
include BASE_PATH . '/includes/layout_start.php';
?>
<h4 class="fw-bold mb-3">Reports</h4>
<div class="row g-3">
<?php foreach ($reportTypes as $key => $label): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="stat-icon mb-3" style="background:#DBEAFE;color:#1E3A8A;"><i class="fa-solid fa-file-lines"></i></div>
                <h6 class="fw-bold"><?= e($label) ?></h6>
                <form method="GET" action="<?= BASE_URL ?>/reports/generate.php" target="_blank" class="mt-3">
                    <input type="hidden" name="type" value="<?= e($key) ?>">
                    <div class="row g-2 mb-2">
                        <div class="col-6"><input type="date" name="from" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>"></div>
                        <div class="col-6"><input type="date" name="to" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
                    </div>
                    <div class="btn-group w-100 btn-group-sm">
                        <button type="submit" name="format" value="pdf" class="btn btn-outline-danger"><i class="fa-solid fa-file-pdf me-1"></i>PDF</button>
                        <button type="submit" name="format" value="excel" class="btn btn-outline-success"><i class="fa-solid fa-file-excel me-1"></i>Excel</button>
                        <button type="submit" name="format" value="print" class="btn btn-outline-secondary"><i class="fa-solid fa-print me-1"></i>Print</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php include BASE_PATH . '/includes/layout_end.php'; ?>
