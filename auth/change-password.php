<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($current, $user['password_hash'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < PASSWORD_MIN_LENGTH) {
        $error = 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    } elseif ($new !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } else {
        $pdo->prepare('UPDATE users SET password_hash = :h WHERE user_id = :id')
            ->execute([
                'h'  => password_hash($new, PASSWORD_BCRYPT),
                'id' => $_SESSION['user_id'],
            ]);
        log_activity('password_changed', 'auth', 'User changed their own password');
        set_flash('success', 'Password updated successfully.');
        redirect(BASE_URL . '/admin/profile.php');
    }
}

$pageTitle = 'Change Password';
$activePage = '';
$breadcrumbs = [['label' => 'Change Password']];
include BASE_PATH . '/includes/layout_start.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-header">
                <i class="fa-solid fa-key me-2"></i>Change Password
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger py-2">
                    <i class="fa-solid fa-circle-exclamation me-1"></i><?= e($error) ?>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label"><i class="fa-solid fa-lock me-1"></i>Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="fa-solid fa-key me-1"></i>New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="fa-solid fa-key me-1"></i>Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-check me-1"></i>Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layout_end.php'; ?>