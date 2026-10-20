<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (post('action') === 'mark_read') {
        $pdo->prepare('UPDATE notifications SET is_read=1 WHERE notification_id=:id')
            ->execute(['id' => post('notification_id')]);
    } elseif (post('action') === 'mark_all_read') {
        $pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=:uid OR user_id IS NULL')
            ->execute(['uid' => $_SESSION['user_id']]);
        set_flash('success', 'All notifications marked as read.');
    }

    redirect(BASE_URL . '/admin/notifications.php');
}

$stmt = $pdo->prepare(
    "SELECT * FROM notifications WHERE user_id=:uid OR user_id IS NULL ORDER BY created_at DESC LIMIT 100"
);
$stmt->execute(['uid' => $_SESSION['user_id']]);
$notifications = $stmt->fetchAll();

$pageTitle = 'Notifications';
$activePage = 'notifications';
$breadcrumbs = [['label' => 'Notifications']];
include BASE_PATH . '/includes/layout_start.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">
        <i class="fa-solid fa-bell me-2"></i>Notifications
    </h4>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="mark_all_read">
        <button class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-check-double me-1"></i>Mark all as read
        </button>
    </form>
</div>

<div class="card shadow-sm border-0">
    <?php foreach ($notifications as $n): ?>
    <div class="d-flex justify-content-between align-items-start p-3 border-bottom <?= !$n['is_read'] ? 'bg-light' : '' ?>">
        <div class="d-flex gap-3">
            <div class="stat-icon" style="width:40px;height:40px;background:#FEF3C7;color:#F59E0B;flex-shrink:0;">
                <i class="fa-solid fa-bell"></i>
            </div>
            <div>
                <div class="fw-semibold"><?= e($n['title']) ?></div>
                <div class="text-muted small"><?= e($n['message']) ?></div>
                <div class="text-muted" style="font-size:.72rem;">
                    <i class="fa-regular fa-clock me-1"></i><?= format_date($n['created_at'], 'd M Y, h:i A') ?>
                </div>
            </div>
        </div>
        <?php if (!$n['is_read']): ?>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="mark_read">
            <input type="hidden" name="notification_id" value="<?= $n['notification_id'] ?>">
            <button class="btn btn-sm btn-outline-primary">
                <i class="fa-solid fa-check me-1"></i>Mark read
            </button>
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (empty($notifications)): ?>
    <div class="text-center text-muted py-5">
        <i class="fa-solid fa-inbox fs-3 d-block mb-2"></i>No notifications.
    </div>
    <?php endif; ?>
</div>

<?php include BASE_PATH . '/includes/layout_end.php'; ?>