<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$pdo = getDBConnection();
$today = date('Y-m-d');

$todayProduction = (float)$pdo->query("SELECT COALESCE(SUM(produced_qty),0) FROM production_records WHERE record_date = CURDATE()")->fetchColumn();
$todayTarget = (float)$pdo->query("SELECT COALESCE(SUM(target_quantity),0) FROM production_targets WHERE target_date = CURDATE() AND target_type='daily'")->fetchColumn();
$overallEfficiency = calc_efficiency($todayProduction, $todayTarget);
$runningLines = (int)$pdo->query("SELECT COUNT(*) FROM production_lines WHERE status='running'")->fetchColumn();
$totalLines   = (int)$pdo->query("SELECT COUNT(*) FROM production_lines")->fetchColumn();
$stoppedMachines = (int)$pdo->query("SELECT COUNT(*) FROM machines WHERE current_status IN ('stopped','maintenance')")->fetchColumn();
$downtimeMinutes = (int)$pdo->query("SELECT COALESCE(SUM(duration_minutes),0) FROM machine_downtime WHERE DATE(start_time) = CURDATE()")->fetchColumn();
$operatorsPresent = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE() AND status='present'")->fetchColumn();
$totalOperators   = (int)$pdo->query("SELECT COUNT(*) FROM operators WHERE status='active'")->fetchColumn();
$qualityDefects = (int)$pdo->query("SELECT COALESCE(SUM(quantity),0) FROM quality_defects WHERE defect_date = CURDATE()")->fetchColumn();

$hourlyStmt = $pdo->query("SELECT hour_slot, SUM(produced_qty) qty FROM production_records WHERE record_date = CURDATE() GROUP BY hour_slot ORDER BY hour_slot");
$hourlyRaw = $hourlyStmt->fetchAll();
$hourlyLabels = [];
$hourlyData = [];
foreach ($hourlyRaw as $row) {
    $hourlyLabels[] = 'H' . $row['hour_slot'];
    $hourlyData[] = (int)$row['qty'];
}

$weeklyStmt = $pdo->query("SELECT record_date, SUM(produced_qty) qty FROM production_records WHERE record_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY record_date ORDER BY record_date");
$weeklyRaw = $weeklyStmt->fetchAll();
$weeklyLabels = [];
$weeklyData = [];
foreach ($weeklyRaw as $row) {
    $weeklyLabels[] = format_date($row['record_date'], 'D');
    $weeklyData[] = (int)$row['qty'];
}

$lineEffStmt = $pdo->query("SELECT pl.line_number, COALESCE(SUM(pr.produced_qty),0) produced, COALESCE(pt.target_quantity,0) target FROM production_lines pl LEFT JOIN production_records pr ON pr.line_id = pl.line_id AND pr.record_date = CURDATE() LEFT JOIN production_targets pt ON pt.line_id = pl.line_id AND pt.target_date = CURDATE() AND pt.target_type='daily' GROUP BY pl.line_id");
$lineEffRaw = $lineEffStmt->fetchAll();
$lineLabels = [];
$lineEffData = [];
foreach ($lineEffRaw as $row) {
    $lineLabels[] = $row['line_number'];
    $lineEffData[] = calc_efficiency((float)$row['produced'], (float)$row['target']);
}

$downtimeStmt = $pdo->query("SELECT COALESCE(reason,'Unspecified') reason, SUM(COALESCE(duration_minutes,0)) mins FROM machine_downtime WHERE start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY reason");
$downtimeRaw = $downtimeStmt->fetchAll();
$downtimeLabels = [];
$downtimeData = [];
foreach ($downtimeRaw as $row) {
    $downtimeLabels[] = $row['reason'];
    $downtimeData[] = (int)$row['mins'];
}

$defectStmt = $pdo->query("SELECT defect_type, SUM(quantity) qty FROM quality_defects WHERE defect_date = CURDATE() GROUP BY defect_type");
$defectRaw = $defectStmt->fetchAll();
$defectLabels = [];
$defectData = [];
foreach ($defectRaw as $row) {
    $defectLabels[] = $row['defect_type'];
    $defectData[] = (int)$row['qty'];
}

$recentActivity = $pdo->query("SELECT al.*, u.full_name FROM activity_logs al LEFT JOIN users u ON u.user_id = al.user_id ORDER BY al.created_at DESC LIMIT 8")->fetchAll();

$linesStatus = $pdo->query("SELECT pl.*, b.buyer_name, COALESCE(SUM(pr.produced_qty),0) produced, COALESCE(pt.target_quantity,0) target FROM production_lines pl LEFT JOIN buyers b ON b.buyer_id = pl.buyer_id LEFT JOIN production_records pr ON pr.line_id = pl.line_id AND pr.record_date = CURDATE() LEFT JOIN production_targets pt ON pt.line_id = pl.line_id AND pt.target_date = CURDATE() AND pt.target_type='daily' GROUP BY pl.line_id ORDER BY pl.line_number")->fetchAll();

$pageTitle = 'Dashboard';
$activePage = 'dashboard';
include BASE_PATH . '/includes/layout_start.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">
            <i class="fa-solid fa-gauge me-2"></i>Welcome back, <?= e(explode(' ', $_SESSION['full_name'])[0]) ?>
        </h4>
        <p class="text-muted mb-0 small">
            <i class="fa-regular fa-calendar me-1"></i><?= date('l, d F Y') ?>
            &middot; <i class="fa-solid fa-rotate me-1"></i>Live data auto-refreshes every 60 seconds
        </p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>/admin/production_entry.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus me-1"></i>New Production Entry
        </a>
        <a href="<?= BASE_URL ?>/admin/reports.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-file-export me-1"></i>Reports
        </a>
    </div>
</div>

<div class="row g-3 mb-2" id="statCardsRow">
    <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#E0E7FF;color:#4F46E5;"><i class="fa-solid fa-industry"></i></div>
            <div>
                <div class="stat-value" id="statTodayProduction"><?= format_number($todayProduction) ?></div>
                <div class="stat-label">Today's Production</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#D1FAE5;color:#10B981;"><i class="fa-solid fa-bullseye"></i></div>
            <div>
                <div class="stat-value" id="statTodayTarget"><?= format_number($todayTarget) ?></div>
                <div class="stat-label">Today's Target</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#DBEAFE;color:#06B6D4;"><i class="fa-solid fa-gauge-high"></i></div>
            <div>
                <div class="stat-value" id="statEfficiency"><?= $overallEfficiency ?>%</div>
                <div class="stat-label">Overall Efficiency</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#D1FAE5;color:#10B981;"><i class="fa-solid fa-play"></i></div>
            <div>
                <div class="stat-value"><?= $runningLines ?>/<?= $totalLines ?></div>
                <div class="stat-label">Running Lines</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#FEE2E2;color:#EF4444;"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div>
                <div class="stat-value"><?= $stoppedMachines ?></div>
                <div class="stat-label">Stopped Machines</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#FEF3C7;color:#F59E0B;"><i class="fa-solid fa-hourglass-half"></i></div>
            <div>
                <div class="stat-value"><?= $downtimeMinutes ?>m</div>
                <div class="stat-label">Machine Downtime</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#E0E7FF;color:#4F46E5;"><i class="fa-solid fa-user-check"></i></div>
            <div>
                <div class="stat-value"><?= $operatorsPresent ?>/<?= $totalOperators ?></div>
                <div class="stat-label">Operators Present</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#FEE2E2;color:#EF4444;"><i class="fa-solid fa-magnifying-glass"></i></div>
            <div>
                <div class="stat-value"><?= $qualityDefects ?></div>
                <div class="stat-label">Quality Defects</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-chart-line me-2"></i>Production Trend</span>
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-secondary active" data-range="hourly">
                        <i class="fa-solid fa-clock me-1"></i>Hourly
                    </button>
                    <button class="btn btn-outline-secondary" data-range="weekly">
                        <i class="fa-solid fa-calendar-week me-1"></i>Weekly
                    </button>
                </div>
            </div>
            <div class="card-body">
                <canvas id="productionChart" height="90"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-chart-column me-2"></i>Line Efficiency Comparison</div>
            <div class="card-body">
                <canvas id="lineEffChart" height="140"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-screwdriver-wrench me-2"></i>Machine Downtime by Reason</div>
            <div class="card-body">
                <canvas id="downtimeChart" height="160"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-bug me-2"></i>Quality Defects (Today)</div>
            <div class="card-body">
                <canvas id="defectChart" height="160"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-clock-rotate-left me-2"></i>Recent Activity</div>
            <div class="card-body" style="max-height:280px; overflow-y:auto;">
                <ul class="list-unstyled mb-0 small">
                    <?php foreach ($recentActivity as $act): ?>
                    <li class="d-flex gap-2 mb-3">
                        <i class="fa-solid fa-circle text-primary mt-1" style="font-size:.5rem;"></i>
                        <div>
                            <div>
                                <strong><?= e($act['full_name'] ?? 'System') ?></strong>
                                <?= e(str_replace('_', ' ', $act['action'])) ?>
                            </div>
                            <div class="text-muted" style="font-size:.72rem;">
                                <i class="fa-regular fa-clock me-1"></i><?= format_date($act['created_at'], 'd M, h:i A') ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                    <?php if (empty($recentActivity)): ?>
                    <li class="text-muted">
                        <i class="fa-solid fa-inbox me-1"></i>No recent activity.
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-tower-broadcast me-2"></i>Live Production Line Status</span>
        <a href="<?= BASE_URL ?>/admin/production_lines.php" class="small">
            View all lines <i class="fa-solid fa-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th><i class="fa-solid fa-industry me-1"></i>Line</th>
                    <th><i class="fa-solid fa-user-tie me-1"></i>Buyer</th>
                    <th><i class="fa-solid fa-shirt me-1"></i>Style</th>
                    <th><i class="fa-solid fa-boxes-stacked me-1"></i>Produced</th>
                    <th><i class="fa-solid fa-bullseye me-1"></i>Target</th>
                    <th><i class="fa-solid fa-gauge-high me-1"></i>Efficiency</th>
                    <th><i class="fa-solid fa-circle-info me-1"></i>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($linesStatus as $l):
                    $eff = calc_efficiency((float)$l['produced'], (float)$l['target']);
                    $badge = efficiency_badge_class($eff);
                ?>
                <tr>
                    <td class="fw-semibold"><?= e($l['line_number']) ?></td>
                    <td><?= e($l['buyer_name'] ?? '-') ?></td>
                    <td><?= e($l['style_number'] ?? '-') ?></td>
                    <td><?= format_number($l['produced']) ?></td>
                    <td><?= format_number($l['target']) ?></td>
                    <td style="min-width:140px;">
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:8px;">
                                <div class="progress-bar bg-<?= $badge ?>" style="width:<?= min(100, $eff) ?>%"></div>
                            </div>
                            <span class="small fw-semibold"><?= $eff ?>%</span>
                        </div>
                    </td>
                    <td><?= status_badge($l['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$extraScripts = '<script>
const chartHourly = ' . json_encode(['labels' => $hourlyLabels, 'data' => $hourlyData]) . ';
const chartWeekly = ' . json_encode(['labels' => $weeklyLabels, 'data' => $weeklyData]) . ';
const chartLineEff = ' . json_encode(['labels' => $lineLabels, 'data' => $lineEffData]) . ';
const chartDowntime = ' . json_encode(['labels' => $downtimeLabels, 'data' => $downtimeData]) . ';
const chartDefects = ' . json_encode(['labels' => $defectLabels, 'data' => $defectData]) . ';
</script>
<script src="' . BASE_URL . '/assets/js/dashboard.js"></script>';
include BASE_PATH . '/includes/layout_end.php';
?>