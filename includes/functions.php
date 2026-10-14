<?php
/**
 * GLEMS - Reusable Helper Functions
 */

// -------------------------------------------------------------------
// INPUT / OUTPUT SAFETY
// -------------------------------------------------------------------

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function clean_input($value)
{
    if (is_array($value)) {
        return array_map('clean_input', $value);
    }
    return trim(strip_tags($value ?? ''));
}

function post(string $key, $default = null)
{
    return isset($_POST[$key]) ? clean_input($_POST[$key]) : $default;
}

function get(string $key, $default = null)
{
    return isset($_GET[$key]) ? clean_input($_GET[$key]) : $default;
}

// -------------------------------------------------------------------
// CSRF PROTECTION
// -------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf(): void
{
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        die('Invalid or expired security token. Please refresh the page and try again.');
    }
}

// -------------------------------------------------------------------
// FLASH MESSAGES
// -------------------------------------------------------------------

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

// -------------------------------------------------------------------
// ACTIVITY LOG
// -------------------------------------------------------------------

function log_activity(string $action, string $module = '', string $description = ''): void
{
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO activity_logs (user_id, action, module, description, ip_address, user_agent)
             VALUES (:uid, :action, :module, :description, :ip, :ua)'
        );
        $stmt->execute([
            'uid'         => $_SESSION['user_id'] ?? null,
            'action'      => $action,
            'module'      => $module,
            'description' => $description,
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua'          => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (Throwable $e) {
        error_log('Activity log failed: ' . $e->getMessage());
    }
}

// -------------------------------------------------------------------
// EFFICIENCY / KPI CALCULATIONS
// -------------------------------------------------------------------

function calc_efficiency(float $producedQty, float $targetQty): float
{
    if ($targetQty <= 0) return 0.0;
    return round(($producedQty / $targetQty) * 100, 2);
}

function calc_achievement(float $producedQty, float $rejectedQty, float $targetQty): float
{
    if ($targetQty <= 0) return 0.0;
    return round((($producedQty - $rejectedQty) / $targetQty) * 100, 2);
}

function efficiency_badge_class(float $efficiency): string
{
    if ($efficiency >= EFFICIENCY_GOOD) return 'success';
    if ($efficiency >= EFFICIENCY_WARN) return 'warning';
    return 'danger';
}

function efficiency_badge_label(float $efficiency): string
{
    if ($efficiency >= EFFICIENCY_GOOD) return 'Good';
    if ($efficiency >= EFFICIENCY_WARN) return 'Average';
    return 'Low';
}

// -------------------------------------------------------------------
// NOTIFICATIONS
// -------------------------------------------------------------------

function push_notification(?int $userId, string $type, string $title, string $message, ?string $link = null): void
{
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO notifications (user_id, type, title, message, link) VALUES (:uid, :type, :title, :msg, :link)'
        );
        $stmt->execute(['uid' => $userId, 'type' => $type, 'title' => $title, 'msg' => $message, 'link' => $link]);
    } catch (Throwable $e) {
        error_log('Notification push failed: ' . $e->getMessage());
    }
}

// -------------------------------------------------------------------
// FORMATTING HELPERS
// -------------------------------------------------------------------

function format_date(?string $date, string $format = 'd M Y'): string
{
    if (empty($date) || $date === '0000-00-00') return '-';
    return date($format, strtotime($date));
}

function format_number($num): string
{
    return number_format((float)$num);
}

function status_badge(string $status): string
{
    $map = [
        'active'      => 'success', 'running'  => 'success', 'resolved' => 'success',
        'inactive'    => 'secondary', 'idle'    => 'secondary',
        'stopped'     => 'danger',  'suspended' => 'danger', 'absent'   => 'danger',
        'maintenance' => 'warning', 'open'     => 'warning', 'pending'  => 'warning',
        'in_progress' => 'info',    'present'  => 'success', 'leave'    => 'info',
        'completed'   => 'primary', 'delayed'  => 'danger', 'cancelled' => 'secondary',
    ];
    $class = $map[$status] ?? 'secondary';
    $label = ucwords(str_replace('_', ' ', $status));
    return '<span class="badge bg-' . $class . '">' . e($label) . '</span>';
}

function paginate(int $totalRows, int $perPage = 20): array
{
    $page = max(1, (int)get('page', 1));
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;
    return ['offset' => $offset, 'limit' => $perPage, 'page' => $page, 'total_pages' => $totalPages];
}

function handle_image_upload(array $file, string $subfolder): ?string
{
    if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMAGE_TYPES, true)) return null;
    if ($file['size'] > MAX_UPLOAD_SIZE) return null;

    $destDir = UPLOAD_PATH . '/' . trim($subfolder, '/');
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);

    $filename = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
    $destPath = $destDir . '/' . $filename;

    return move_uploaded_file($file['tmp_name'], $destPath) ? $filename : null;
}