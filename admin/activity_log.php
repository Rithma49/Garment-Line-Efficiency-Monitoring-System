<?php
require_once dirname(__DIR__) . '/config/config.php';
require_role(['admin']);
$pdo = getDBConnection();

$count = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
$p = paginate($count, 25);

$logs = $pdo->query(
    "SELECT al.*, u.full_name FROM activity_logs al LEFT JOIN users u ON u.user_id=al.user_id
     ORDER BY al.created_at DESC LIMIT {$p['limit']} OFFSET {$p['offset']}"
)->fetchAll();

$pageTitle = 'Activity Log'; $activePage = 'activitylog'; $breadcrumbs = [['label' => 'Activity Log']];
include BASE_PATH . '/includes/layout_start.php';
?>
<h4 class="fw-bold mb-3">System Activity Log &amp; Audit Trail</h4>
<div class="card"><div class="table-responsive">
<table class="table table-hover mb-0 align-middle">
<thead><tr><th>Date/Time</th><th>User</th><th>Action</th><th>Module</th><th>Description</th><th>IP Address</th></tr></thead>
<tbody>
<?php foreach ($logs as $l): ?>
<tr>
    <td><?= format_date($l['created_at'], 'd M Y, h:i:s A') ?></td>
    <td><?= e($l['full_name'] ?? 'System') ?></td>
    <td><span class="badge bg-secondary text-capitalize"><?= e(str_replace('_',' ',$l['action'])) ?></span></td>
    <td><?= e($l['module']) ?></td>
    <td><?= e($l['description']) ?></td>
    <td class="text-muted small"><?= e($l['ip_address']) ?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($logs)): ?><tr><td colspan="6" class="text-center text-muted py-4">No activity recorded yet.</td></tr><?php endif; ?>
</tbody></table>
</div>
<?php if ($p['total_pages'] > 1): ?>
<div class="card-footer d-flex justify-content-center">
<nav><ul class="pagination pagination-sm mb-0">
<?php for ($i=1;$i<=$p['total_pages'];$i++): ?><li class="page-item <?= $i===$p['page']?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a></li><?php endfor; ?>
</ul></nav>
</div>
<?php endif; ?>
</div>
<?php include BASE_PATH . '/includes/layout_end.php'; ?>
