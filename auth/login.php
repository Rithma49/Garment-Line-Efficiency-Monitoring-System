<?php
require_once dirname(__DIR__) . '/config/config.php';
if (is_logged_in()) redirect(BASE_URL . '/admin/dashboard.php');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $username = post('username');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $result = attempt_login($username, $password, $remember);
        if ($result === true) {
            redirect(BASE_URL . '/admin/dashboard.php');
        } else {
            $error = $result;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-side">
            <i class="fa-solid fa-shirt fa-3x mb-3 text-white"></i>
            <h3 class="fw-bold text-white">Garment Line Efficiency<br>Monitoring System</h3>
            <p class="text-white-50 mt-2">Real‑time visibility into production, quality, machines and workforce — built for Sri Lankan garment factories.</p>
        </div>
        <div class="auth-form-side">
            <h4 class="fw-bold mb-1">Welcome back</h4>
            <p class="text-muted mb-4">Sign in to your GLEMS account</p>
            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><i class="fa-solid fa-circle-exclamation me-1"></i> <?= e($error) ?></div>
            <?php endif; ?>
            <form method="POST" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Username or Email</label>
                    <input type="text" name="username" class="form-control form-control-lg" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" id="password" name="password" class="form-control form-control-lg" required>
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePwd()"><i class="fa-solid fa-eye" id="eyeIcon"></i></button>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label small" for="remember">Remember me</label>
                    </div>
                    <a href="forgot-password.php" class="small">Forgot password?</a>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="fa-solid fa-right-to-bracket me-1"></i> Sign In
                </button>
            </form>
            <p class="text-center text-muted small mt-4 mb-0">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &middot; v<?= e(APP_VERSION) ?></p>
        </div>
    </div>
</div>
<script>
function togglePwd() {
    const pwd = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    const isPwd = pwd.type === 'password';
    pwd.type = isPwd ? 'text' : 'password';
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
}
</script>
</body>
</html>