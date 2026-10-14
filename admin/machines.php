<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    if ($action === 'save') {
        $id = post('machine_id');
        $data = [
            'num' => post('machine_number'), 'name' => post('machine_name'), 'type' => post('machine_type'),
            'brand' => post('brand'), 'model' => post('model'), 'serial' => post('serial_number'),
            'purchase' => post('purchase_date') ?: null, 'warranty' => post('warranty_expiry') ?: null,
            'line' => post('line_id') ?: null, 'status' => post('current_status', 'idle'),
        ];
        $image = !empty($_FILES['machine_image']['name']) ? handle_image_upload($_FILES['machine_image'], 'machines') : null;
        try {
            if ($id) {
                $sql = 'UPDATE machines SET machine_number=:num, machine_name=:name, machine_type=:type, brand=:brand,
                        model=:model, serial_number=:serial, purchase_date=:purchase, warranty_expiry=:warranty,
                        line_id=:line, current_status=:status';
                if ($image) $sql .= ', machine_image=:image';
                $sql .= ' WHERE machine_id=:id';
                $data['id'] = $id; if ($image) $data['image'] = $image;
                $pdo->prepare($sql)->execute($data);
                set_flash('success', 'Machine updated successfully.');
            } else {
                $sql = 'INSERT INTO machines (machine_number, machine_name, machine_type, brand, model, serial_number,
                        purchase_date, warranty_expiry, line_id, current_status, machine_image)
                        VALUES (:num,:name,:type,:brand,:model,:serial,:purchase,:warranty,:line,:status,:image)';
                $data['image'] = $image;
                $pdo->prepare($sql)->execute($data);
                set_flash('success', 'Machine added successfully.');
            }
            log_activity('save', 'machines', 'Saved machine ' . $data['num']);
        } catch (PDOException $e) {
            set_flash('danger', 'Machine number already exists or a database error occurred.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM machines WHERE machine_id=:id')->execute(['id' => post('machine_id')]);
        set_flash('success', 'Machine removed.');
    }
    redirect(BASE_URL . '/admin/machines.php');
}

$machines = $pdo->query("SELECT m.*, pl.line_number FROM machines m LEFT JOIN production_lines pl ON pl.line_id=m.line_id ORDER BY m.machine_number")->fetchAll();
$lines = $pdo->query("SELECT line_id, line_number FROM production_lines ORDER BY line_number")->fetchAll();

$pageTitle = 'Machines';
$activePage = 'machines';
$breadcrumbs = [['label' => 'Machines']];
include BASE_PATH . '/includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">Machine Management</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#mModal" onclick="resetMForm()"><i class="fa-solid fa-plus me-1"></i>Add Machine</button>
</div>
<div class="card"><div class="table-responsive">
<table class="table table-hover mb-0 align-middle">
<thead><tr><th>Machine #</th><th>Name</th><th>Type</th><th>Brand/Model</th><th>Line</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
<tbody>
<?php foreach ($machines as $m): ?>
<tr>
    <td class="fw-semibold"><?= e($m['machine_number']) ?></td>
    <td><?= e($m['machine_name']) ?></td>
    <td><?= e($m['machine_type']) ?></td>
    <td><?= e($m['brand']) ?> <?= e($m['model']) ?></td>
    <td><?= e($m['line_number'] ?? '-') ?></td>
    <td><?= status_badge($m['current_status']) ?></td>
    <td class="text-end">
        <button class="btn btn-sm btn-outline-primary" onclick='editM(<?= json_encode($m) ?>)'><i class="fa-solid fa-pen"></i></button>
        <form method="POST" class="d-inline" onsubmit="return confirmDelete()">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="machine_id" value="<?= $m['machine_id'] ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($machines)): ?><tr><td colspan="7" class="text-center text-muted py-4">No machines found.</td></tr><?php endif; ?>
</tbody></table>
</div></div>

<div class="modal fade" id="mModal" tabindex="-1"><div class="modal-dialog modal-lg">
<form class="modal-content" method="POST" enctype="multipart/form-data">
<?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="machine_id" id="machine_id">
<div class="modal-header"><h5 class="modal-title" id="mModalTitle">Add Machine</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div class="row g-3">
  <div class="col-md-4"><label class="form-label">Machine Number *</label><input type="text" name="machine_number" id="m_num" class="form-control" required></div>
  <div class="col-md-8"><label class="form-label">Machine Name</label><input type="text" name="machine_name" id="m_name" class="form-control"></div>
  <div class="col-md-4"><label class="form-label">Type</label><input type="text" name="machine_type" id="m_type" class="form-control"></div>
  <div class="col-md-4"><label class="form-label">Brand</label><input type="text" name="brand" id="m_brand" class="form-control"></div>
  <div class="col-md-4"><label class="form-label">Model</label><input type="text" name="model" id="m_model" class="form-control"></div>
  <div class="col-md-6"><label class="form-label">Serial Number</label><input type="text" name="serial_number" id="m_serial" class="form-control"></div>
  <div class="col-md-6"><label class="form-label">Production Line</label>
    <select name="line_id" id="m_line" class="form-select"><option value="">-- None --</option>
      <?php foreach ($lines as $l): ?><option value="<?= $l['line_id'] ?>"><?= e($l['line_number']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-4"><label class="form-label">Purchase Date</label><input type="date" name="purchase_date" id="m_purchase" class="form-control"></div>
  <div class="col-md-4"><label class="form-label">Warranty Expiry</label><input type="date" name="warranty_expiry" id="m_warranty" class="form-control"></div>
  <div class="col-md-4"><label class="form-label">Status</label>
    <select name="current_status" id="m_status" class="form-select"><option value="idle">Idle</option><option value="running">Running</option><option value="stopped">Stopped</option><option value="maintenance">Maintenance</option></select>
  </div>
  <div class="col-md-6"><label class="form-label">Machine Image</label><input type="file" name="machine_image" class="form-control" accept="image/*"></div>
</div></div>
<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Machine</button></div>
</form>
</div></div>

<script>
const mModal = new bootstrap.Modal(document.getElementById('mModal'));
function resetMForm() { document.querySelector('#mModal form').reset(); document.getElementById('machine_id').value=''; document.getElementById('mModalTitle').textContent='Add Machine'; }
function editM(m) {
    document.getElementById('mModalTitle').textContent = 'Edit Machine';
    document.getElementById('machine_id').value = m.machine_id;
    document.getElementById('m_num').value = m.machine_number || '';
    document.getElementById('m_name').value = m.machine_name || '';
    document.getElementById('m_type').value = m.machine_type || '';
    document.getElementById('m_brand').value = m.brand || '';
    document.getElementById('m_model').value = m.model || '';
    document.getElementById('m_serial').value = m.serial_number || '';
    document.getElementById('m_line').value = m.line_id || '';
    document.getElementById('m_purchase').value = m.purchase_date || '';
    document.getElementById('m_warranty').value = m.warranty_expiry || '';
    document.getElementById('m_status').value = m.current_status || 'idle';
    mModal.show();
}
</script>
<?php include BASE_PATH . '/includes/layout_end.php'; ?>
