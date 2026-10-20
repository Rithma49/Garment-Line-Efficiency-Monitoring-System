<?php
require_once dirname(__DIR__) . '/config/config.php';
require_role(['admin']);
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');

    if ($action === 'save') {
        $id = post('user_id');
        $data = [
            'role'     => post('role_id'),
            'emp'      => post('employee_id'),
            'name'     => post('full_name'),
            'username' => post('username'),
            'email'    => post('email'),
            'phone'    => post('phone'),
            'status'   => post('status', 'active'),
        ];
        $password = $_POST['password'] ?? '';

        try {
            if ($id) {
                $sql = 'UPDATE users SET role_id=:role, employee_id=:emp, full_name=:name, username=:username,
                        email=:email, phone=:phone, status=:status';
                if (!empty($password)) {
                    $sql .= ', password_hash=:pwd';
                    $data['pwd'] = password_hash($password, PASSWORD_BCRYPT);
                }
                $sql .= ' WHERE user_id=:id';

                $data['id'] = $id;
                $pdo->prepare($sql)->execute($data);
                set_flash('success', 'User updated successfully.');
            } else {
                if (strlen($password) < PASSWORD_MIN_LENGTH) {
                    set_flash('danger', 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.');
                    redirect(BASE_URL . '/admin/users.php');
                }
                $data['pwd'] = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare(
                    'INSERT INTO users (role_id, employee_id, full_name, username, email, phone, password_hash, status)
                     VALUES (:role, :emp, :name, :username, :email, :phone, :pwd, :status)'
                )->execute($data);
                set_flash('success', 'User created successfully.');
            }
            log_activity('save', 'users', 'Saved user ' . $data['name']);
        } catch (PDOException $e) {
            set_flash('danger', 'Username or email already exists.');
        }
    } elseif ($action === 'delete') {
        if ((int)post('user_id') === (int)$_SESSION['user_id']) {
            set_flash('danger', 'You cannot delete your own account.');
        } else {
            $pdo->prepare('DELETE FROM users WHERE user_id=:id')
                ->execute(['id' => post('user_id')]);
            set_flash('success', 'User deleted.');
        }
    }

    redirect(BASE_URL . '/admin/users.php');
}

$users = $pdo->query(
    "SELECT u.*, r.role_name FROM users u
     JOIN roles r ON r.role_id=u.role_id
     ORDER BY u.full_name"
)->fetchAll();

$roles = $pdo->query(
    "SELECT role_id, role_name FROM roles ORDER BY role_name"
)->fetchAll();

$pageTitle = 'System Users';
$activePage = 'users';
$breadcrumbs = [['label' => 'System Users']];
include BASE_PATH . '/includes/layout_start.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">
        <i class="fa-solid fa-users-gear me-2"></i>System Users
    </h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uModal" onclick="resetUForm()">
        <i class="fa-solid fa-plus me-1"></i>Add User
    </button>
</div>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th><i class="fa-solid fa-user me-1"></i>Name</th>
                    <th><i class="fa-solid fa-at me-1"></i>Username</th>
                    <th><i class="fa-solid fa-envelope me-1"></i>Email</th>
                    <th><i class="fa-solid fa-user-shield me-1"></i>Role</th>
                    <th><i class="fa-solid fa-clock me-1"></i>Last Login</th>
                    <th><i class="fa-solid fa-circle-info me-1"></i>Status</th>
                    <th class="text-end"><i class="fa-solid fa-gear me-1"></i>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td class="fw-semibold"><?= e($u['full_name']) ?></td>
                    <td><?= e($u['username']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><span class="badge bg-primary"><?= e($u['role_name']) ?></span></td>
                    <td><?= $u['last_login'] ? format_date($u['last_login'], 'd M, h:i A') : 'Never' ?></td>
                    <td><?= status_badge($u['status']) ?></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" onclick='editU(<?= json_encode($u) ?>)'>
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <?php if ((int)$u['user_id'] !== (int)$_SESSION['user_id']): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirmDelete()">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="uModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="user_id" id="user_id">

            <div class="modal-header">
                <h5 class="modal-title" id="uModalTitle">
                    <i class="fa-solid fa-user-plus me-2"></i>Add User
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-user me-1"></i>Full Name *</label>
                        <input type="text" name="full_name" id="u_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-id-badge me-1"></i>Employee ID</label>
                        <input type="text" name="employee_id" id="u_emp" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-at me-1"></i>Username *</label>
                        <input type="text" name="username" id="u_username" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-envelope me-1"></i>Email *</label>
                        <input type="email" name="email" id="u_email" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-phone me-1"></i>Phone</label>
                        <input type="text" name="phone" id="u_phone" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-user-shield me-1"></i>Role *</label>
                        <select name="role_id" id="u_role" class="form-select" required>
                            <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['role_id'] ?>"><?= e($r['role_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fa-solid fa-key me-1"></i>Password
                            <span id="pwdHint" class="text-muted small">(leave blank to keep current)</span>
                        </label>
                        <input type="password" name="password" id="u_password" class="form-control" minlength="<?= PASSWORD_MIN_LENGTH ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-circle-info me-1"></i>Status</label>
                        <select name="status" id="u_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i>Cancel
                </button>
                <button class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk me-1"></i>Save User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const uModal = new bootstrap.Modal(document.getElementById('uModal'));

function resetUForm() {
    document.querySelector('#uModal form').reset();
    document.getElementById('user_id').value = '';
    document.getElementById('uModalTitle').innerHTML = '<i class="fa-solid fa-user-plus me-2"></i>Add User';
    document.getElementById('pwdHint').style.display = 'none';
    document.getElementById('u_password').required = true;
}

function editU(u) {
    document.getElementById('uModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square me-2"></i>Edit User';
    document.getElementById('user_id').value = u.user_id;
    document.getElementById('u_name').value = u.full_name;
    document.getElementById('u_emp').value = u.employee_id || '';
    document.getElementById('u_username').value = u.username;
    document.getElementById('u_email').value = u.email;
    document.getElementById('u_phone').value = u.phone || '';
    document.getElementById('u_role').value = u.role_id;
    document.getElementById('u_status').value = u.status;
    document.getElementById('pwdHint').style.display = 'inline';
    document.getElementById('u_password').required = false;
    document.getElementById('u_password').value = '';
    uModal.show();
}
</script>

<?php include BASE_PATH . '/includes/layout_end.php'; ?>