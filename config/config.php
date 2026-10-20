<?php
/**
 * GLEMS - Global Application Configuration
 */

// ---- Base paths / URL -------------------------------------------------
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '/project/Garment Line Efficiency Monitoring System'); // <-- CHANGE THIS to match your installation path

// ---- App info ---------------------------------------------------------
define('APP_NAME', 'GLEMS');
define('APP_FULL_NAME', 'Garment Line Efficiency Monitoring System');
define('APP_VERSION', '1.0.0');

// ---- Security ---------------------------------------------------------
define('SESSION_TIMEOUT', 1800);        // 30 minutes idle timeout
define('REMEMBER_ME_DAYS', 30);
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

// ---- Uploads ----------------------------------------------------------
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'webp']);

// ---- Efficiency thresholds (used for green / yellow / red indicators) -
define('EFFICIENCY_GOOD', 85);   // >=85%   => green
define('EFFICIENCY_WARN', 70);   // 70-84%  => yellow, below => red

// ---- Timezone ---------------------------------------------------------
date_default_timezone_set('Asia/Colombo');

// ---- Error display (set to 0 in production) --------------------------
ini_set('display_errors', 1);          // <-- TURN OFF in production
ini_set('log_errors', 1);
error_reporting(E_ALL);

// ---- Secure session cookie params (must run before session_start) ----
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // 'secure' => true, // enable once served over HTTPS
    ]);
    session_start();
}

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';