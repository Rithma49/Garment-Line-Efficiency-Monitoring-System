<?php
/**
 * GLEMS - Authentication & Role-Based Access Control
 */

function attempt_login(string $username, string $password, bool $rememberMe = false)
{
    $pdo = getDBConnection();
    $attemptsKey = 'login_attempts_' . md5($username);
    $lockKey     = 'login_lock_' . md5($username);

    if (!empty($_SESSION[$lockKey]) && $_SESSION[$lockKey] > time()) {
        $wait = ceil(($_SESSION[$lockKey] - time()) / 60);
        return "Too many failed attempts. Please try again in {$wait} minute(s).";
    }

    $stmt = $pdo->prepare(
        'SELECT u.*, r.role_slug, r.role_name FROM users u
         JOIN roles r ON r.role_id = u.role_id
         WHERE u.username = :username OR u.email = :email LIMIT 1'
    );
    $stmt->execute(['username' => $username, 'email' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $_SESSION[$attemptsKey] = ($_SESSION[$attemptsKey] ?? 0) + 1;
        if ($_SESSION[$attemptsKey] >= MAX_LOGIN_ATTEMPTS) {
            $_SESSION[$lockKey] = time() + (LOGIN_LOCKOUT_MINUTES * 60);
            $_SESSION[$attemptsKey] = 0;
            return 'Too many failed attempts. Account temporarily locked for ' . LOGIN_LOCKOUT_MINUTES . ' minutes.';
        }
        return 'Invalid username or password.';
    }

    if ($user['status'] !== 'active') {
        return 'Your account is ' . $user['status'] . '. Please contact the administrator.';
    }

    unset($_SESSION[$attemptsKey], $_SESSION[$lockKey]);
    session_regenerate_id(true);

    $_SESSION['user_id']       = $user['user_id'];
    $_SESSION['full_name']     = $user['full_name'];
    $_SESSION['username']      = $user['username'];
    $_SESSION['role_slug']     = $user['role_slug'];
    $_SESSION['role_name']     = $user['role_name'];
    $_SESSION['photo']         = $user['profile_photo'];
    $_SESSION['last_activity'] = time();

    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE user_id = :id')
        ->execute(['id' => $user['user_id']]);

    if ($rememberMe) {
        $token = bin2hex(random_bytes(32));
        $pdo->prepare('UPDATE users SET remember_token = :t WHERE user_id = :id')
            ->execute(['t' => hash('sha256', $token), 'id' => $user['user_id']]);
        setcookie('glems_remember', $user['user_id'] . ':' . $token, [
            'expires'  => time() + (REMEMBER_ME_DAYS * 86400),
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    log_activity('login', 'auth', 'User logged in');
    return true;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function require_login(): void
{
    if (!is_logged_in() && !empty($_COOKIE['glems_remember'])) {
        attempt_login_via_remember_cookie();
    }

    if (!is_logged_in()) {
        redirect(BASE_URL . '/auth/login.php');
    }

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        logout_user('Your session has expired due to inactivity.');
    }

    $_SESSION['last_activity'] = time();
}

function attempt_login_via_remember_cookie(): void
{
    if (empty($_COOKIE['glems_remember'])) {
        return;
    }

    [$userId, $token] = array_pad(explode(':', $_COOKIE['glems_remember'], 2), 2, null);

    if (!$userId || !$token) {
        return;
    }

    $pdo = getDBConnection();
    $stmt = $pdo->prepare(
        'SELECT u.*, r.role_slug, r.role_name FROM users u
         JOIN roles r ON r.role_id = u.role_id
         WHERE u.user_id = :id AND u.remember_token = :t AND u.status = "active" LIMIT 1'
    );
    $stmt->execute(['id' => $userId, 't' => hash('sha256', $token)]);
    $user = $stmt->fetch();

    if (!$user) {
        return;
    }

    session_regenerate_id(true);

    $_SESSION['user_id']       = $user['user_id'];
    $_SESSION['full_name']     = $user['full_name'];
    $_SESSION['username']      = $user['username'];
    $_SESSION['role_slug']     = $user['role_slug'];
    $_SESSION['role_name']     = $user['role_name'];
    $_SESSION['photo']         = $user['profile_photo'];
    $_SESSION['last_activity'] = time();
}

function logout_user(?string $message = null): void
{
    log_activity('logout', 'auth', 'User logged out');

    if (!empty($_COOKIE['glems_remember'])) {
        setcookie('glems_remember', '', time() - 3600, '/');
    }

    $_SESSION = [];
    session_destroy();

    if ($message) {
        session_start();
        set_flash('warning', $message);
    }

    redirect(BASE_URL . '/auth/login.php');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function current_role(): ?string
{
    return $_SESSION['role_slug'] ?? null;
}

function has_permission(string $permissionKey): bool
{
    if (current_role() === 'admin') {
        return true;
    }

    static $cache = null;

    if ($cache === null) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare(
            'SELECT p.permission_key FROM role_permissions rp
             JOIN permissions p ON p.permission_id = rp.permission_id
             JOIN users u ON u.role_id = rp.role_id
             WHERE u.user_id = :uid'
        );
        $stmt->execute(['uid' => $_SESSION['user_id'] ?? 0]);
        $cache = array_column($stmt->fetchAll(), 'permission_key');
    }

    return in_array($permissionKey, $cache, true);
}

function require_role(array $allowedRoles): void
{
    require_login();

    if (!in_array(current_role(), $allowedRoles, true)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;text-align:center;margin-top:60px;">
                <i class="fas fa-ban" style="font-size:48px;color:#DC2626;"></i>
                <h2 style="color:#DC2626;">403 - Access Denied</h2>
                <p style="color:#64748B;font-size:16px;">You do not have permission to view this page.</p>
             </div>');
    }
}

function require_permission(string $permissionKey): void
{
    require_login();

    if (!has_permission($permissionKey)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;text-align:center;margin-top:60px;">
                <i class="fas fa-lock" style="font-size:48px;color:#DC2626;"></i>
                <h2 style="color:#DC2626;">403 - Access Denied</h2>
                <p style="color:#64748B;font-size:16px;">You do not have permission to perform this action.</p>
             </div>');
    }
}