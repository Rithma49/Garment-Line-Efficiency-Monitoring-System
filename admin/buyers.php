<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    if ($action === 'save') {
        $id = post('buyer_id');
        $data = ['name'=>post('buyer_name'),'contact'=>post('contact_person'),'email'=>post('email'),'phone'=>post('phone'),'country'=>post('country'),'address'=>post('address'),'status'=>post('status','active')];
        try {
            if ($id) {
                $data['id'] = $id;
                $pdo->prepare('UPDATE buyers SET buyer_name=:name, contact_person=:contact, email=:email, phone=:phone, country=:country, address=:address, status=:status WHERE buyer_id=:id')->execute($data);
                set_flash('success', 'Buyer updated.');
            } else {
                $pdo->prepare('INSERT INTO buyers (buyer_name, contact_person, email, phone, country, address, status) VALUES (:name,:contact,:email,:phone,:country,:address,:status)')->execute($data);
                set_flash('success', 'Buyer added.');
            }
            log_activity('save', 'buyers', 'Saved buyer ' . $data['name']);
        } catch (PDOException $e) {
            set_flash('danger', 'A database error occurred.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM buyers WHERE buyer_id=:id')->execute(['id' => post('buyer_id')]);
        set_flash('success', 'Buyer removed.');
    }
    redirect(BASE_URL . '/admin/buyers.php');
}

$buyers = $pdo->query("SELECT * FROM buyers ORDER BY buyer_name")->fetchAll();
$pageTitle = 'Buyers'; $activePage = 'buyers'; $breadcrumbs = [['label' => 'Buyers']];
include BASE_PATH . '/includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">Buyer Management</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bModal" onclick="resetBForm()"><i class="fa-solid fa-plus me-1"></i>Add Buyer</button>
</div>
<div class="card"><div class="table-responsive">
<table class="table table-hover mb-0 align-middle">
<thead><tr><th>Buyer</th><th>Contact Person</th><th>Email</th><th>Phone</th><th>Country</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
<tbody>
<?php foreach ($buyers as $b): ?>
<tr>
    <td class="fw-semibold"><?= e($b['buyer_name']) ?></td>
    <td><?= e($b['contact_person']) ?></td>
    <td><?= e($b['email']) ?></td>
    <td><?= e($b['phone']) ?></td>
    <td><?= e($b['country']) ?></td>
    <td><?= status_badge($b['status']) ?></td>
    <td class="text-end">
        <button class="btn btn-sm btn-outline-primary" onclick='editB(<?= json_encode($b) ?>)'><i class="fa-solid fa-pen"></i></button>
        <form method="POST" class="d-inline" onsubmit="return confirmDelete()">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="buyer_id" value="<?= $b['buyer_id'] ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($buyers)): ?><tr><td colspan="7" class="text-center text-muted py-4">No buyers found.</td></tr><?php endif; ?>
</tbody></table>
</div></div>

<div class="modal fade" id="bModal" tabindex="-1"><div class="modal-dialog">
<form class="modal-content" method="POST">
<?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="buyer_id" id="buyer_id">
<div class="modal-header"><h5 class="modal-title" id="bModalTitle">Add Buyer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
  <div class="mb-3"><label class="form-label">Buyer Name *</label><input type="text" name="buyer_name" id="b_name" class="form-control" required></div>
  <div class="mb-3"><label class="form-label">Contact Person</label><input type="text" name="contact_person" id="b_contact" class="form-control"></div>
  <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" id="b_email" class="form-control"></div>
  <div class="mb-3"><label class="form-label">Phone</label><input type="text" name="phone" id="b_phone" class="form-control"></div>
  <div class="mb-3"><label class="form-label">Country</label><input type="text" name="country" id="b_country" class="form-control"></div>
  <div class="mb-3"><label class="form-label">Address</label><textarea name="address" id="b_address" class="form-control" rows="2"></textarea></div>
  <div class="mb-3"><label class="form-label">Status</label><select name="status" id="b_status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Buyer</button></div>
</form>
</div></div>

<script>
const bModal = new bootstrap.Modal(document.getElementById('bModal'));
function resetBForm() { document.querySelector('#bModal form').reset(); document.getElementById('buyer_id').value=''; document.getElementById('bModalTitle').textContent='Add Buyer'; }
function editB(b) {
    document.getElementById('bModalTitle').textContent = 'Edit Buyer';
    document.getElementById('buyer_id').value = b.buyer_id;
    document.getElementById('b_name').value = b.buyer_name;
    document.getElementById('b_contact').value = b.contact_person || '';
    document.getElementById('b_email').value = b.email || '';
    document.getElementById('b_phone').value = b.phone || '';
    document.getElementById('b_country').value = b.country || '';
    document.getElementById('b_address').value = b.address || '';
    document.getElementById('b_status').value = b.status;
    bModal.show();
}
</script>
<?php include BASE_PATH . '/includes/layout_end.php'; ?>
