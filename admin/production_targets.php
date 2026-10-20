<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');

    if ($action === 'save') {
        $targetId = post('target_id');
        $lineId   = post('line_id');
        $date     = post('target_date');
        $type     = post('target_type');
        $qty      = (int)post('target_quantity', 0);
        $hours    = (float)post('expected_working_hours', 8);

        try {
            if ($targetId) {
                $pdo->prepare(
                    'UPDATE production_targets SET line_id=:l, target_date=:d, target_type=:t,
                     target_quantity=:q, expected_working_hours=:h WHERE target_id=:id'
                )->execute([
                    'l'  => $lineId,
                    'd'  => $date,
                    't'  => $type,
                    'q'  => $qty,
                    'h'  => $hours,
                    'id' => $targetId,
                ]);
                set_flash('success', 'Target updated successfully.');
            } else {
                $pdo->prepare(
                    'INSERT INTO production_targets (line_id, target_date, target_type, target_quantity, expected_working_hours, created_by)
                     VALUES (:l, :d, :t, :q, :h, :by)'
                )->execute([
                    'l'  => $lineId,
                    'd'  => $date,
                    't'  => $type,
                    'q'  => $qty,
                    'h'  => $hours,
                    'by' => $_SESSION['user_id'],
                ]);
                set_flash('success', 'Target created successfully.');
            }
            log_activity('save', 'production_targets', 'Saved target for line ' . $lineId);
        } catch (PDOException $e) {
            set_flash('danger', 'A target for this line/date/type already exists, or a database error occurred.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM production_targets WHERE target_id=:id')
            ->execute(['id' => post('target_id')]);
        set_flash('success', 'Target deleted.');
    }

    redirect(BASE_URL . '/admin/production_targets.php');
}

$targets = $pdo->query(
    "SELECT pt.*, pl.line_number FROM production_targets pt
     JOIN production_lines pl ON pl.line_id = pt.line_id
     ORDER BY pt.target_date DESC LIMIT 100"
)->fetchAll();

$lines = $pdo->query(
    "SELECT line_id, line_number FROM production_lines ORDER BY line_number"
)->fetchAll();

$pageTitle = 'Production Targets';
$activePage = 'targets';
$breadcrumbs = [['label' => 'Production Targets']];
include BASE_PATH . '/includes/layout_start.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">
        <i class="fa-solid fa-bullseye me-2"></i>Production Targets
    </h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#targetModal" onclick="resetTargetForm()">
        <i class="fa-solid fa-plus me-1"></i>Add Target
    </button>
</div>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th><i class="fa-solid fa-calendar me-1"></i>Date</th>
                    <th><i class="fa-solid fa-diagram-project me-1"></i>Line</th>
                    <th><i class="fa-solid fa-tag me-1"></i>Type</th>
                    <th><i class="fa-solid fa-bullseye me-1"></i>Target Qty</th>
                    <th><i class="fa-solid fa-clock me-1"></i>Expected Hours</th>
                    <th class="text-end"><i class="fa-solid fa-gear me-1"></i>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($targets as $t): ?>
                <tr>
                    <td><?= format_date($t['target_date']) ?></td>
                    <td class="fw-semibold"><?= e($t['line_number']) ?></td>
                    <td>
                        <span class="badge bg-info text-capitalize"><?= e($t['target_type']) ?></span>
                    </td>
                    <td><?= format_number($t['target_quantity']) ?></td>
                    <td><?= e($t['expected_working_hours']) ?>h</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" onclick='editTarget(<?= json_encode($t) ?>)'>
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirmDelete()">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="target_id" value="<?= $t['target_id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($targets)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        <i class="fa-solid fa-inbox fs-4 d-block mb-2"></i>No targets found.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="targetModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="target_id" id="target_id">

            <div class="modal-header">
                <h5 class="modal-title" id="targetModalTitle">
                    <i class="fa-solid fa-plus me-2"></i>Add Target
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label"><i class="fa-solid fa-diagram-project me-1"></i>Production Line *</label>
                    <select name="line_id" id="t_line_id" class="form-select" required>
                        <?php foreach ($lines as $l): ?>
                        <option value="<?= $l['line_id'] ?>"><?= e($l['line_number']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="fa-solid fa-calendar me-1"></i>Date *</label>
                    <input type="date" name="target_date" id="t_date" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="fa-solid fa-tag me-1"></i>Target Type *</label>
                    <select name="target_type" id="t_type" class="form-select" required>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="fa-solid fa-bullseye me-1"></i>Target Quantity *</label>
                    <input type="number" name="target_quantity" id="t_qty" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="fa-solid fa-clock me-1"></i>Expected Working Hours</label>
                    <input type="number" step="0.5" name="expected_working_hours" id="t_hours" class="form-control" value="8">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i>Cancel
                </button>
                <button class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk me-1"></i>Save Target
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const targetModal = new bootstrap.Modal(document.getElementById('targetModal'));

function resetTargetForm() {
    document.getElementById('targetModalTitle').innerHTML = '<i class="fa-solid fa-plus me-2"></i>Add Target';
    document.getElementById('target_id').value = '';
    document.getElementById('t_date').value = new Date().toISOString().slice(0, 10);
    document.getElementById('t_qty').value = '';
}

function editTarget(t) {
    document.getElementById('targetModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square me-2"></i>Edit Target';
    document.getElementById('target_id').value = t.target_id;
    document.getElementById('t_line_id').value = t.line_id;
    document.getElementById('t_date').value = t.target_date;
    document.getElementById('t_type').value = t.target_type;
    document.getElementById('t_qty').value = t.target_quantity;
    document.getElementById('t_hours').value = t.expected_working_hours;
    targetModal.show();
}
</script>

<?php include BASE_PATH . '/includes/layout_end.php'; ?>