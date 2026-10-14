<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    if ($action === 'save') {
        $id = post('operator_id');
        $data = [
            'emp' => post('employee_id'), 'name' => post('full_name'), 'nic' => post('nic'),
            'phone' => post('phone'), 'addr' => post('address'), 'dept' => post('department'),
            'line' => post('line_id') ?: null, 'pos' => post('position'),
            'joined' => post('date_joined') ?: null, 'status' => post('status', 'active'),
        ];
        $photo = null;
        if (!empty($_FILES['photo']['name'])) {
            $photo = handle_image_upload($_FILES['photo'], 'operators');
        }
        try {
            if ($id) {
                $sql = 'UPDATE operators SET employee_id=:emp, full_name=:name, nic=:nic, phone=:phone, address=:addr,
                        department=:dept, line_id=:line, position=:pos, date_joined=:joined, status=:status';
                if ($photo) $sql .= ', photo=:photo';
                $sql .= ' WHERE operator_id=:id';
                $data['id'] = $id;
                if ($photo) $data['photo'] = $photo;
                $pdo->prepare($sql)->execute($data);
                set_flash('success', 'Operator updated successfully.');
            } else {
                $sql = 'INSERT INTO operators (employee_id, full_name, nic, phone, address, department, line_id, position, date_joined, status, photo)
                        VALUES (:emp, :name, :nic, :phone, :addr, :dept, :line, :pos, :joined, :status, :photo)';
                $data['photo'] = $photo;
                $pdo->prepare($sql)->execute($data);
                set_flash('success', 'Operator added successfully.');
            }
            log_activity('save', 'operators', 'Saved operator ' . $data['name']);
        } catch (PDOException $e) {
            set_flash('danger', 'Employee ID already exists or a database error occurred.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM operators WHERE operator_id=:id')->execute(['id' => post('operator_id')]);
        set_flash('success', 'Operator removed.');
    }
    redirect(BASE_URL . '/admin/operators.php');
}

$search = get('search', '');
$where = '';
$params = [];
if ($search !== '') { $where = 'WHERE o.full_name LIKE :s OR o.employee_id LIKE :s OR o.nic LIKE :s'; $params['s'] = "%$search%"; }

$count = $pdo->prepare("SELECT COUNT(*) FROM operators o $where");
$count->execute($params);
$p = paginate((int)$count->fetchColumn(), 12);

$stmt = $pdo->prepare("SELECT o.*, pl.line_number FROM operators o LEFT JOIN production_lines pl ON pl.line_id = o.line_id $where ORDER BY o.full_name LIMIT {$p['limit']} OFFSET {$p['offset']}");
$stmt->execute($params);
$operators = $stmt->fetchAll();
$lines = $pdo->query("SELECT line_id, line_number FROM production_lines ORDER BY line_number")->fetchAll();

$pageTitle = 'Operators';
$activePage = 'operators';
$breadcrumbs = [['label' => 'Operators']];
include BASE_PATH . '/includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="fw-bold mb-0">Operator Management</h4>
    <div class="d-flex gap-2">
        <form method="GET"><input type="text" name="search" class="form-control form-control-sm" placeholder="Search operators..." value="<?= e($search) ?>"></form>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#opModal" onclick="resetOpForm()"><i class="fa-solid fa-plus me-1"></i>Add Operator</button>
    </div>
</div>

<div class="row g-3">
<?php foreach ($operators as $o): ?>
    <div class="col-sm-6 col-lg-4 col-xl-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <img src="<?= !empty($o['photo']) ? UPLOAD_URL . '/operators/' . e($o['photo']) : 'https://ui-avatars.com/api/?background=1E3A8A&color=fff&name=' . urlencode($o['full_name']) ?>" class="rounded-circle mb-2" width="72" height="72" style="object-fit:cover;">
                <div class="fw-semibold"><?= e($o['full_name']) ?></div>
                <div class="text-muted small"><?= e($o['employee_id']) ?> &middot; <?= e($o['position']) ?></div>
                <div class="small text-muted">Line: <?= e($o['line_number'] ?? '-') ?></div>
                <div class="mt-2"><?= status_badge($o['status']) ?></div>
                <div class="mt-2">
                    <button class="btn btn-sm btn-outline-primary" onclick='editOp(<?= json_encode($o) ?>)'><i class="fa-solid fa-pen"></i></button>
                    <form method="POST" class="d-inline" onsubmit="return confirmDelete()">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="operator_id" value="<?= $o['operator_id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php if (empty($operators)): ?><div class="col-12 text-center text-muted py-5">No operators found.</div><?php endif; ?>
</div>

<?php if ($p['total_pages'] > 1): ?>
<nav class="mt-3"><ul class="pagination pagination-sm justify-content-center">
    <?php for ($i = 1; $i <= $p['total_pages']; $i++): ?>
    <li class="page-item <?= $i === $p['page'] ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>&search=<?= e($search) ?>"><?= $i ?></a></li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<div class="modal fade" id="opModal" tabindex="-1"><div class="modal-dialog modal-lg">
<form class="modal-content" method="POST" enctype="multipart/form-data">
  <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="operator_id" id="operator_id">
  <div class="modal-header"><h5 class="modal-title" id="opModalTitle">Add Operator</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="row g-3">
      <div class="col-md-4"><label class="form-label">Employee ID *</label><input type="text" name="employee_id" id="op_emp" class="form-control" required></div>
      <div class="col-md-8"><label class="form-label">Full Name *</label><input type="text" name="full_name" id="op_name" class="form-control" required></div>
      <div class="col-md-6"><label class="form-label">NIC</label><input type="text" name="nic" id="op_nic" class="form-control"></div>
      <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" id="op_phone" class="form-control"></div>
      <div class="col-12"><label class="form-label">Address</label><input type="text" name="address" id="op_addr" class="form-control"></div>
      <div class="col-md-4"><label class="form-label">Department</label><input type="text" name="department" id="op_dept" class="form-control"></div>
      <div class="col-md-4"><label class="form-label">Production Line</label>
        <select name="line_id" id="op_line" class="form-select"><option value="">-- None --</option>
          <?php foreach ($lines as $l): ?><option value="<?= $l['line_id'] ?>"><?= e($l['line_number']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4"><label class="form-label">Position</label><input type="text" name="position" id="op_pos" class="form-control"></div>
      <div class="col-md-4"><label class="form-label">Date Joined</label><input type="date" name="date_joined" id="op_joined" class="form-control"></div>
      <div class="col-md-4"><label class="form-label">Status</label>
        <select name="status" id="op_status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option><option value="resigned">Resigned</option></select>
      </div>
      <div class="col-md-4"><label class="form-label">Photo</label><input type="file" name="photo" class="form-control" accept="image/*"></div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Operator</button></div>
</form>
</div></div>

<script>
const opModal = new bootstrap.Modal(document.getElementById('opModal'));
function resetOpForm() { document.querySelector('#opModal form').reset(); document.getElementById('operator_id').value=''; document.getElementById('opModalTitle').textContent='Add Operator'; }
function editOp(o) {
    document.getElementById('opModalTitle').textContent = 'Edit Operator';
    document.getElementById('operator_id').value = o.operator_id;
    document.getElementById('op_emp').value = o.employee_id || '';
    document.getElementById('op_name').value = o.full_name || '';
    document.getElementById('op_nic').value = o.nic || '';
    document.getElementById('op_phone').value = o.phone || '';
    document.getElementById('op_addr').value = o.address || '';
    document.getElementById('op_dept').value = o.department || '';
    document.getElementById('op_line').value = o.line_id || '';
    document.getElementById('op_pos').value = o.position || '';
    document.getElementById('op_joined').value = o.date_joined || '';
    document.getElementById('op_status').value = o.status || 'active';
    opModal.show();
}
</script>
<?php include BASE_PATH . '/includes/layout_end.php'; ?>
