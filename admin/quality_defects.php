<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();
$pdo = getDBConnection();

$defectTypes = ['Open Seam','Broken Stitch','Oil Mark','Needle Damage','Missing Stitch','Wrong Size','Fabric Damage','Loose Thread','Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    if ($action === 'save') {
        $id = post('defect_id');
        $data = [
            'product' => post('product_name'), 'line' => post('line_id'), 'operator' => post('operator_id') ?: null,
            'type' => post('defect_type'), 'qty' => (int)post('quantity', 1), 'remarks' => post('remarks'),
            'date' => post('defect_date') ?: date('Y-m-d'),
        ];
        try {
            if ($id) {
                $data['id'] = $id;
                $pdo->prepare('UPDATE quality_defects SET product_name=:product, line_id=:line, operator_id=:operator,
                    defect_type=:type, quantity=:qty, remarks=:remarks, defect_date=:date WHERE defect_id=:id')->execute($data);
                set_flash('success', 'Defect record updated.');
            } else {
                $data['by'] = $_SESSION['user_id'];
                $pdo->prepare('INSERT INTO quality_defects (product_name, line_id, operator_id, defect_type, quantity, remarks, recorded_by, defect_date)
                    VALUES (:product,:line,:operator,:type,:qty,:remarks,:by,:date)')->execute($data);
                set_flash('success', 'Defect recorded.');

                // High defect rate alert
                if ($data['qty'] >= 10) {
                    push_notification(null, 'high_defect_rate', 'High Defect Rate', $data['qty'] . ' x ' . $data['type'] . ' reported.', BASE_URL . '/admin/quality_defects.php');
                }
            }
            log_activity('save', 'quality_defects', 'Saved defect record');
        } catch (Throwable $e) {
            error_log($e->getMessage());
            set_flash('danger', 'Failed to save defect record.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM quality_defects WHERE defect_id=:id')->execute(['id' => post('defect_id')]);
        set_flash('success', 'Defect record deleted.');
    }
    redirect(BASE_URL . '/admin/quality_defects.php');
}

$defects = $pdo->query(
    "SELECT qd.*, pl.line_number, o.full_name AS operator_name FROM quality_defects qd
     JOIN production_lines pl ON pl.line_id=qd.line_id LEFT JOIN operators o ON o.operator_id=qd.operator_id
     ORDER BY qd.defect_date DESC, qd.defect_id DESC LIMIT 100"
)->fetchAll();
$lines = $pdo->query("SELECT line_id, line_number FROM production_lines ORDER BY line_number")->fetchAll();
$operators = $pdo->query("SELECT operator_id, full_name FROM operators WHERE status='active' ORDER BY full_name")->fetchAll();

$pageTitle = 'Quality Defects';
$activePage = 'quality';
$breadcrumbs = [['label' => 'Quality Defects']];
include BASE_PATH . '/includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">Quality Defect Management</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#qModal" onclick="resetQForm()"><i class="fa-solid fa-plus me-1"></i>Record Defect</button>
</div>
<div class="card"><div class="table-responsive">
<table class="table table-hover mb-0 align-middle">
<thead><tr><th>Date</th><th>Product</th><th>Line</th><th>Operator</th><th>Defect Type</th><th>Qty</th><th>Remarks</th><th class="text-end">Actions</th></tr></thead>
<tbody>
<?php foreach ($defects as $d): ?>
<tr>
    <td><?= format_date($d['defect_date']) ?></td>
    <td><?= e($d['product_name']) ?></td>
    <td><?= e($d['line_number']) ?></td>
    <td><?= e($d['operator_name'] ?? '-') ?></td>
    <td><span class="badge bg-danger"><?= e($d['defect_type']) ?></span></td>
    <td><?= e($d['quantity']) ?></td>
    <td><?= e($d['remarks']) ?></td>
    <td class="text-end">
        <button class="btn btn-sm btn-outline-primary" onclick='editQ(<?= json_encode($d) ?>)'><i class="fa-solid fa-pen"></i></button>
        <form method="POST" class="d-inline" onsubmit="return confirmDelete()">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="defect_id" value="<?= $d['defect_id'] ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($defects)): ?><tr><td colspan="8" class="text-center text-muted py-4">No quality defects recorded.</td></tr><?php endif; ?>
</tbody></table>
</div></div>

<div class="modal fade" id="qModal" tabindex="-1"><div class="modal-dialog modal-lg">
<form class="modal-content" method="POST">
<?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="defect_id" id="defect_id">
<div class="modal-header"><h5 class="modal-title" id="qModalTitle">Record Defect</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div class="row g-3">
  <div class="col-md-6"><label class="form-label">Product Name</label><input type="text" name="product_name" id="q_product" class="form-control"></div>
  <div class="col-md-6"><label class="form-label">Production Line *</label>
    <select name="line_id" id="q_line" class="form-select" required><?php foreach ($lines as $l): ?><option value="<?= $l['line_id'] ?>"><?= e($l['line_number']) ?></option><?php endforeach; ?></select>
  </div>
  <div class="col-md-6"><label class="form-label">Operator</label>
    <select name="operator_id" id="q_operator" class="form-select"><option value="">-- None --</option><?php foreach ($operators as $o): ?><option value="<?= $o['operator_id'] ?>"><?= e($o['full_name']) ?></option><?php endforeach; ?></select>
  </div>
  <div class="col-md-6"><label class="form-label">Defect Type *</label>
    <select name="defect_type" id="q_type" class="form-select" required><?php foreach ($defectTypes as $dt): ?><option><?= e($dt) ?></option><?php endforeach; ?></select>
  </div>
  <div class="col-md-4"><label class="form-label">Quantity *</label><input type="number" name="quantity" id="q_qty" class="form-control" value="1" min="1" required></div>
  <div class="col-md-4"><label class="form-label">Date</label><input type="date" name="defect_date" id="q_date" class="form-control"></div>
  <div class="col-12"><label class="form-label">Remarks</label><textarea name="remarks" id="q_remarks" class="form-control" rows="2"></textarea></div>
</div></div>
<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Record</button></div>
</form>
</div></div>

<script>
const qModal = new bootstrap.Modal(document.getElementById('qModal'));
function resetQForm() {
    document.querySelector('#qModal form').reset(); document.getElementById('defect_id').value='';
    document.getElementById('qModalTitle').textContent='Record Defect';
    document.getElementById('q_date').value = new Date().toISOString().slice(0,10);
}
function editQ(d) {
    document.getElementById('qModalTitle').textContent = 'Edit Defect Record';
    document.getElementById('defect_id').value = d.defect_id;
    document.getElementById('q_product').value = d.product_name || '';
    document.getElementById('q_line').value = d.line_id;
    document.getElementById('q_operator').value = d.operator_id || '';
    document.getElementById('q_type').value = d.defect_type;
    document.getElementById('q_qty').value = d.quantity;
    document.getElementById('q_date').value = d.defect_date;
    document.getElementById('q_remarks').value = d.remarks || '';
    qModal.show();
}
</script>
<?php include BASE_PATH . '/includes/layout_end.php'; ?>
