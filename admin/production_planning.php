<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$pdo = getDBConnection();
$selectedDate = get('date', date('Y-m-d'));
$weekStart = date('Y-m-d', strtotime('monday this week', strtotime($selectedDate)));
$weekEnd = date('Y-m-d', strtotime('sunday this week', strtotime($selectedDate)));

$lines = $pdo->query(
    "SELECT line_id, line_number FROM production_lines ORDER BY line_number"
)->fetchAll();

$targets = [];
foreach ($lines as $line) {
    $stmt = $pdo->prepare(
        "SELECT target_date, target_quantity FROM production_targets
         WHERE line_id = :lid AND target_date BETWEEN :start AND :end AND target_type = 'daily'"
    );
    $stmt->execute(['lid' => $line['line_id'], 'start' => $weekStart, 'end' => $weekEnd]);
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $targets[$line['line_id']] = $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $lineId = post('line_id');
    $date   = post('date');
    $qty    = (int)post('quantity');
    $type   = post('type', 'daily');
    $hours  = (float)post('hours', 8);

    try {
        $pdo->prepare(
            "INSERT INTO production_targets (line_id, target_date, target_type, target_quantity, expected_working_hours, created_by)
             VALUES (:lid, :d, :t, :q, :h, :by)
             ON DUPLICATE KEY UPDATE target_quantity = :q2, expected_working_hours = :h2"
        )->execute([
            'lid' => $lineId,
            'd'   => $date,
            't'   => $type,
            'q'   => $qty,
            'h'   => $hours,
            'by'  => $_SESSION['user_id'],
            'q2'  => $qty,
            'h2'  => $hours,
        ]);
        set_flash('success', 'Target updated for ' . date('d M Y', strtotime($date)));
    } catch (Throwable $e) {
        set_flash('danger', 'Database error: ' . $e->getMessage());
    }

    redirect(BASE_URL . '/admin/production_planning.php?date=' . $selectedDate);
}

$pageTitle = 'Production Planning';
$activePage = 'planning';
$breadcrumbs = [['label' => 'Production Planning']];
include BASE_PATH . '/includes/layout_start.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="fw-bold mb-0">
        <i class="fa-solid fa-calendar-days me-2"></i>Production Planning (Weekly View)
    </h4>
    <form method="GET" class="d-flex gap-2">
        <input type="date" name="date" class="form-control form-control-sm" value="<?= e($selectedDate) ?>" onchange="this.form.submit()">
        <a href="?date=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-calendar-day me-1"></i>Today
        </a>
    </form>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header">
        <i class="fa-solid fa-calendar-week me-2"></i>
        Week of <?= format_date($weekStart) ?> &ndash; <?= format_date($weekEnd) ?>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle text-center mb-0">
            <thead class="table-light">
                <tr>
                    <th style="min-width:100px;"><i class="fa-solid fa-diagram-project me-1"></i>Line</th>
                    <?php
                    $dates = [];
                    for ($i = 0; $i < 7; $i++) {
                        $d = date('Y-m-d', strtotime("$weekStart +$i days"));
                        $dates[] = $d;
                        echo '<th>' . format_date($d, 'D d M') . '</th>';
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $line): ?>
                <tr>
                    <td class="fw-semibold"><?= e($line['line_number']) ?></td>
                    <?php foreach ($dates as $date):
                        $qty = $targets[$line['line_id']][$date] ?? '';
                    ?>
                    <td>
                        <form method="POST" class="d-flex gap-1 align-items-center justify-content-center">
                            <?= csrf_field() ?>
                            <input type="hidden" name="line_id" value="<?= $line['line_id'] ?>">
                            <input type="hidden" name="date" value="<?= $date ?>">
                            <input type="hidden" name="type" value="daily">
                            <input type="number" name="quantity" value="<?= e($qty) ?>" class="form-control form-control-sm" style="width:80px;" min="0">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="fa-solid fa-floppy-disk"></i>
                            </button>
                        </form>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_PATH . '/includes/layout_end.php'; ?>