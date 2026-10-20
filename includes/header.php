<?php
$pageTitle   = $pageTitle ?? 'Dashboard';
$breadcrumbs = $breadcrumbs ?? [];

$pdo          = getDBConnection();
$unreadCount  = 0;
$recentNotifs = [];

try {
    $stmt = $pdo->prepare(
        'SELECT * FROM notifications WHERE (user_id = :uid OR user_id IS NULL) ORDER BY created_at DESC LIMIT 6'
    );
    $stmt->execute(['uid' => $_SESSION['user_id']]);
    $recentNotifs = $stmt->fetchAll();

    $countStmt = $pdo->prepare(
        'SELECT COUNT(*) c FROM notifications WHERE (user_id = :uid OR user_id IS NULL) AND is_read = 0'
    );
    $countStmt->execute(['uid' => $_SESSION['user_id']]);
    $unreadCount = (int)$countStmt->fetch()['c'];
} catch (Throwable $e) {
    error_log('Header notifications load failed: ' . $e->getMessage());
}
?>
<header class="topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="topbar-icon-btn" id="sidebarToggle" title="Toggle menu">
            <i class="fa-solid fa-bars"></i>
        </button>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="text-decoration-none">Home</a>
                </li>

                <?php foreach ($breadcrumbs as $bc): ?>
                    <li class="breadcrumb-item<?= empty($bc['url']) ? ' active' : '' ?>">
                        <?php if (empty($bc['url'])): ?>
                            <?= e($bc['label']) ?>
                        <?php else: ?>
                            <a href="<?= e($bc['url']) ?>" class="text-decoration-none"><?= e($bc['label']) ?></a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>

                <?php if (empty($breadcrumbs)): ?>
                    <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
                <?php endif; ?>
            </ol>
        </nav>
    </div>

    <div class="d-flex align-items-center gap-1">
        <button class="topbar-icon-btn" id="themeToggle" title="Toggle dark mode">
            <i class="fa-solid fa-moon"></i>
        </button>

        <div class="dropdown">
            <button class="topbar-icon-btn dropdown-toggle" data-bs-toggle="dropdown" style="position:relative;" id="notifBell">
                <i class="fa-solid fa-bell"></i>
                <?php if ($unreadCount > 0): ?>
                    <span class="notif-dot" id="notifDot"></span>
                <?php endif; ?>
            </button>

            <div class="dropdown-menu dropdown-menu-end p-0" style="width: 320px;">
                <div class="p-3 border-bottom fw-semibold d-flex justify-content-between">
                    <span>Notifications</span>
                    <span id="notifBadge" class="badge bg-danger"><?= $unreadCount ?> new</span>
                </div>

                <div style="max-height: 320px; overflow-y:auto;">
                    <?php if (empty($recentNotifs)): ?>
                        <div class="text-center text-muted py-4 small">
                            <i class="fa-regular fa-bell-slash me-1"></i> No notifications yet
                        </div>
                    <?php endif; ?>

                    <?php foreach ($recentNotifs as $n): ?>
                        <a href="<?= e($n['link'] ?: '#') ?>" class="dropdown-item py-2 border-bottom small <?= !$n['is_read'] ? 'bg-light' : '' ?>">
                            <div class="fw-semibold"><?= e($n['title']) ?></div>
                            <div class="text-muted"><?= e($n['message']) ?></div>
                            <div class="text-muted" style="font-size:.7rem;">
                                <i class="fa-regular fa-clock me-1"></i><?= format_date($n['created_at'], 'd M, h:i A') ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <a href="<?= BASE_URL ?>/admin/notifications.php" class="dropdown-item text-center py-2 fw-semibold text-primary">
                    <i class="fa-solid fa-list me-1"></i> View all
                </a>
            </div>
        </div>

        <div class="dropdown ms-1">
            <button class="btn btn-light d-flex align-items-center gap-2 dropdown-toggle" data-bs-toggle="dropdown">
                <img src="<?= !empty($_SESSION['photo']) ? UPLOAD_URL . '/operators/' . e($_SESSION['photo']) : 'https://ui-avatars.com/api/?background=1E3A8A&color=fff&name=' . urlencode($_SESSION['full_name']) ?>"
                     class="rounded-circle" width="32" height="32" alt="Profile">
                <span class="d-none d-md-inline small fw-semibold"><?= e($_SESSION['full_name']) ?></span>
            </button>

            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <span class="dropdown-item-text small text-muted">
                        <i class="fa-solid fa-user-shield me-1"></i><?= e($_SESSION['role_name']) ?>
                    </span>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item" href="<?= BASE_URL ?>/admin/profile.php">
                        <i class="fa-solid fa-user me-2"></i>My Profile
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="<?= BASE_URL ?>/auth/change-password.php">
                        <i class="fa-solid fa-key me-2"></i>Change Password
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="<?= BASE_URL ?>/auth/logout.php">
                        <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>