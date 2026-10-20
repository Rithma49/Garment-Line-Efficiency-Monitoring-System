<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $lineId   = post('line_id');
    $date     = post('record_date');
    $hour     = (int)post('hour_slot');
    $produced = (int)post('produced_qty', 0);
    $rejected = (int)post('rejected_qty', 0);
    $remarks  = post('remarks');

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO production_records (line_id, record_date, hour_slot, produced_qty, rejected_qty, remarks, entered_by)
             VALUES (:l, :d, :h, :p, :r, :rm, :by)
             ON DUPLICATE KEY UPDATE produced_qty=:p2, rejected_qty=:r2, remarks=:rm2, entered_by=:by2'
        );
        $stmt->execute([
            'l'   => $lineId,
            'd'   => $date,
            'h'   => $hour,
            'p'   => $produced,
            'r'   => $rejected,
            'rm'  => $remarks,
            'by'  => $_SESSION['user_id'],
            'p2'  => $produced,
            'r2'  => $rejected,
            'rm2' => $remarks,
            'by2' => $_SESSION['user_id'],
        ]);

        $targetRow = $pdo->prepare(
            "SELECT target_quantity FROM production_targets WHERE line_id=:l AND target_date=:d AND target_type='daily'"
        );
        $targetRow->execute(['l' => $lineId, 'd' => $date]);
        $target = (float)($targetRow->fetchColumn() ?: 0);

        $totalStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(produced_qty),0) t FROM production_records WHERE line_id=:l AND record_date=:d"
        );
        $totalStmt->execute(['l' => $lineId, 'd' => $date]);
        $totalToday = (float)$totalStmt->fetchColumn();
        $eff = calc_efficiency($totalToday, $target);

        if ($target > 0 && $eff < 75) {
            $lineNum = $pdo->prepare('SELECT line_number FROM production_lines WHERE line_id=:id');
            $lineNum->execute(['id' => $lineId]);
            push_notification(
                null,
                'efficiency_low',
                'Efficiency Below 75%',
                'Line ' . $lineNum->fetchColumn() . ' is at ' . $eff . '% efficiency today.',
                BASE_URL . '/admin/dashboard.php'
            );
        }

        log_activity('production_entry', 'production_records', "Line $lineId hour $hour: $produced pcs");
        set_flash('success', 'Production entry saved successfully.');
    } catch (Throwable $e) {
        error_log($e->getMessage());
        set_flash('danger', 'Failed to save production entry.');
    }

    redirect(BASE_URL . '/admin/production_entry.php?line_id=' . $lineId . '&date=' . $date);
}

$lines = $pdo->query(
    "SELECT line_id, line_number FROM production_lines ORDER BY line_number"
)->fetchAll();

$selectedLine = get('line_id', $lines[0]['line_id'] ?? '');
$selectedDate = get('date', date('Y-m-d'));

$target = 0;
if ($selectedLine) {
    $tStmt = $pdo->prepare(
        "SELECT target_quantity FROM production_targets WHERE line_id=:l AND target_date=:d AND target_type='daily'"
    );
    $tStmt->execute(['l' => $selectedLine, 'd' => $selectedDate]);
    $target = (float)($tStmt->fetchColumn() ?: 0);
}

$records = [];
if ($selectedLine) {
    $rStmt = $pdo->prepare(
        "SELECT * FROM production_records WHERE line_id=:l AND record_date=:d ORDER BY hour_slot"
    );
    $rStmt->execute(['l' => $selectedLine, 'd' => $selectedDate]);
    $records = $rStmt->fetchAll(PDO::FETCH_ASSOC);
}

$byHour = [];
foreach ($records as $r) {
    $byHour[$r['hour_slot']] = $r;
}

$totalProduced = array_sum(array_column($records, 'produced_qty'));
$totalRejected = array_sum(array_column($records, 'rejected_qty'));
$efficiency    = calc_efficiency($totalProduced, $target);
$achievement   = calc_achievement($totalProduced, $totalRejected, $target);
$remaining     = max(0, $target - $totalProduced);

$pageTitle = 'Live Production Entry';
$activePage = 'entry';
$breadcrumbs = [['label' => 'Live Production Entry']];
include BASE_PATH . '/includes/layout_start.php';
?>

<h4 class="fw-bold mb-3">
    <i class="fa-solid fa-keyboard me-2"></i>Live Production Entry
</h4>

<div class="card mb-3 shadow-sm border-0">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small"><i class="fa-solid fa-diagram-project me-1"></i>Production Line</label>
                <select name="line_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($lines as $l): ?>
                    <option value="<?= $l['line_id'] ?>" <?= $l['line_id'] == $selectedLine ? 'selected' : '' ?>>
                        <?= e($l['line_number']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small"><i class="fa-solid fa-calendar me-1"></i>Date</label>
                <input type="date" name="date" class="form-control" value="<?= e($selectedDate) ?>" onchange="this.form.submit()">
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#DBEAFE;color:#1E3A8A;"><i class="fa-solid fa-industry"></i></div>
            <div>
                <div class="stat-value"><?= format_number($totalProduced) ?></div>
                <div class="stat-label">Total Production</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#FEF3C7;color:#F59E0B;"><i class="fa-solid fa-hourglass-half"></i></div>
            <div>
                <div class="stat-value"><?= format_number($remaining) ?></div>
                <div class="stat-label">Remaining Target</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#DCFCE7;color:#16A34A;"><i class="fa-solid fa-gauge-high"></i></div>
            <div>
                <div class="stat-value"><?= $efficiency ?>%</div>
                <div class="stat-label">Efficiency</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#E0E7FF;color:#4338CA;"><i class="fa-solid fa-percent"></i></div>
            <div>
                <div class="stat-value"><?= $achievement ?>%</div>
                <div class="stat-label">Achievement</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header">
        <i class="fa-solid fa-clock me-2"></i>Hourly Entries &mdash; <?= e(format_date($selectedDate)) ?>
        &middot; Target: <?= format_number($target) ?> pcs
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th><i class="fa-solid fa-clock me-1"></i>Hour</th>
                    <th><i class="fa-solid fa-boxes-stacked me-1"></i>Produced Qty</th>
                    <th><i class="fa-solid fa-ban me-1"></i>Rejected Qty</th>
                    <th><i class="fa-solid fa-comment me-1"></i>Remarks</th>
                    <th><i class="fa-solid fa-layer-group me-1"></i>Cumulative</th>
                    <th><i class="fa-solid fa-gauge-high me-1"></i>Efficiency</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $cumulative = 0;
                for ($h = 1; $h <= 12; $h++):
                    $rec = $byHour[$h] ?? null;
                    $cumulative += $rec['produced_qty'] ?? 0;
                    $rowEff = calc_efficiency($cumulative, $target);
                ?>
                <tr>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="line_id" value="<?= e($selectedLine) ?>">
                        <input type="hidden" name="record_date" value="<?= e($selectedDate) ?>">
                        <input type="hidden" name="hour_slot" value="<?= $h ?>">
                        <td class="fw-semibold">Hour <?= $h ?></td>
                        <td style="width:130px;">
                            <input type="number" name="produced_qty" class="form-control form-control-sm" value="<?= e($rec['produced_qty'] ?? '') ?>" min="0">
                        </td>
                        <td style="width:130px;">
                            <input type="number" name="rejected_qty" class="form-control form-control-sm" value="<?= e($rec['rejected_qty'] ?? '') ?>" min="0">
                        </td>
                        <td>
                            <input type="text" name="remarks" class="form-control form-control-sm" value="<?= e($rec['remarks'] ?? '') ?>" placeholder="Optional">
                        </td>
                        <td><?= format_number($cumulative) ?></td>
                        <td>
                            <span class="efficiency-dot dot-<?= efficiency_badge_class($rowEff) ?>"></span><?= $rowEff ?>%
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" type="submit">
                                <i class="fa-solid fa-floppy-disk"></i>
                            </button>
                        </td>
                    </form>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_PATH . '/includes/layout_end.php'; ?>