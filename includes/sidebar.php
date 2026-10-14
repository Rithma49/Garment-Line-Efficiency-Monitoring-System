<?php
$activePage = $activePage ?? '';
$role = current_role();

function navlink(string $page, string $url, string $icon, string $label, string $active): void {
    $isActive = ($page === $active) ? ' active' : '';
    echo '<li><a class="nav-link' . $isActive . '" href="' . e($url) . '"><i class="fa-solid ' . e($icon) . '"></i><span>' . e($label) . '</span></a></li>';
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="fa-solid fa-shirt"></i>
        <span><?= e(APP_NAME) ?></span>
    </div>
    <ul class="sidebar-nav">
        <?php navlink('dashboard', BASE_URL . '/admin/dashboard.php', 'fa-gauge-high', 'Dashboard', $activePage) ?>

        <li class="nav-section-label">Production</li>
        <?php navlink('lines', BASE_URL . '/admin/production_lines.php', 'fa-industry', 'Production Lines', $activePage) ?>
        <?php navlink('targets', BASE_URL . '/admin/production_targets.php', 'fa-bullseye', 'Production Targets', $activePage) ?>
        <?php navlink('planning', BASE_URL . '/admin/production_planning.php', 'fa-calendar-plus', 'Production Planning', $activePage) ?>
        <?php navlink('entry', BASE_URL . '/admin/production_entry.php', 'fa-keyboard', 'Live Production Entry', $activePage) ?>
        <?php navlink('orders', BASE_URL . '/admin/orders.php', 'fa-file-invoice', 'Orders', $activePage) ?>
        <?php navlink('buyers', BASE_URL . '/admin/buyers.php', 'fa-handshake', 'Buyers', $activePage) ?>

        <li class="nav-section-label">Workforce</li>
        <?php navlink('operators', BASE_URL . '/admin/operators.php', 'fa-users', 'Operators', $activePage) ?>
        <?php navlink('performance', BASE_URL . '/admin/operator_performance.php', 'fa-chart-line', 'Operator Performance', $activePage) ?>
        <?php navlink('attendance', BASE_URL . '/admin/attendance.php', 'fa-clock', 'Attendance', $activePage) ?>

        <li class="nav-section-label">Machines &amp; Quality</li>
        <?php navlink('machines', BASE_URL . '/admin/machines.php', 'fa-gears', 'Machines', $activePage) ?>
        <?php navlink('downtime', BASE_URL . '/admin/machine_downtime.php', 'fa-triangle-exclamation', 'Machine Downtime', $activePage) ?>
        <?php navlink('quality', BASE_URL . '/admin/quality_defects.php', 'fa-magnifying-glass', 'Quality Defects', $activePage) ?>

        <li class="nav-section-label">Insights</li>
        <?php navlink('reports', BASE_URL . '/admin/reports.php', 'fa-file-lines', 'Reports', $activePage) ?>
        <?php navlink('notifications', BASE_URL . '/admin/notifications.php', 'fa-bell', 'Notifications', $activePage) ?>

        <?php if ($role === 'admin'): ?>
        <li class="nav-section-label">Administration</li>
        <?php navlink('users', BASE_URL . '/admin/users.php', 'fa-user-shield', 'System Users', $activePage) ?>
        <?php navlink('activitylog', BASE_URL . '/admin/activity_log.php', 'fa-list-check', 'Activity Log', $activePage) ?>
        <?php navlink('settings', BASE_URL . '/admin/settings.php', 'fa-gear', 'Settings', $activePage) ?>
        <?php endif; ?>
    </ul>
</aside>