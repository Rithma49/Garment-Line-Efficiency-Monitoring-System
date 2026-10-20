<?php
require_once dirname(__DIR__) . '/config/config.php';
if (is_logged_in()) redirect(BASE_URL . '/admin/dashboard.php');

$message = null;
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $email = post('email');
    $pdo = getDBConnection();

    $stmt = $pdo->prepare(
        'SELECT user_id, full_name FROM users WHERE email = :e AND status = "active" LIMIT 1'
    );
    $stmt->execute(['e' => $email]);
    $user = $stmt->fetch();

    if ($user) {
        $token  = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', time() + 3600);

        $pdo->prepare('UPDATE users SET reset_token = :t, reset_token_expiry = :e WHERE user_id = :id')
            ->execute([
                't'  => hash('sha256', $token),
                'e'  => $expiry,
                'id' => $user['user_id'],
            ]);

        log_activity('password_reset_requested', 'auth', 'Reset requested for user_id ' . $user['user_id']);
    }

    $message = 'If an account exists with that email address, password reset instructions have been sent.';
    $messageType = 'success';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password - <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card" style="max-width:460px;">
        <div class="auth-form-side w-100">
            <h4 class="fw-bold mb-1">
                <i class="fa-solid fa-key text-primary me-1"></i> Forgot Password
            </h4>
            <p class="text-muted mb-4">
                Enter your account email and we'll send reset instructions.
            </p>

            <?php if ($message): ?>
            <div class="alert alert-<?= e($messageType) ?> py-2">
                <i class="fa-solid fa-circle-info me-1"></i><?= e($message) ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label">
                        <i class="fa-solid fa-envelope me-1"></i>Email Address
                    </label>
                    <input type="email" name="email" class="form-control form-control-lg" required>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="fa-solid fa-paper-plane me-1"></i>Send Reset Link
                </button>
            </form>

            <p class="text-center mt-4">
                <a href="login.php">
                    <i class="fa-solid fa-arrow-left me-1"></i>Back to login
                </a>
            </p>
        </div>
    </div>
</div>
</body>
</html>