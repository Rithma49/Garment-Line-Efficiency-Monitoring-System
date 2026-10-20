<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$pdo = getDBConnection();

$search = get('search', '');
$statusFilter = get('status', '');

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(pl.line_number LIKE :s OR pl.style_number LIKE :s OR pl.product_name LIKE :s)';
    $params['s'] = "%$search%";
}
if ($statusFilter !== '') {
    $where[] = 'pl.status = :status';
    $params['status'] = $statusFilter;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM production_lines pl $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$p = paginate($total, 10);

$stmt = $pdo->prepare(
    "SELECT pl.*, b.buyer_name, u.full_name AS supervisor_name
     FROM production_lines pl
     LEFT JOIN buyers b ON b.buyer_id = pl.buyer_id
     LEFT JOIN users u ON u.user_id = pl.supervisor_id
     $whereSql
     ORDER BY pl.line_number
     LIMIT {$p['limit']} OFFSET {$p['offset']}"
);
$stmt->execute($params);
$lines = $stmt->fetchAll();

$buyers = $pdo->query(
    "SELECT buyer_id, buyer_name FROM buyers WHERE status='active' ORDER BY buyer_name"
)->fetchAll();

$supervisors = $pdo->query(
    "SELECT u.user_id, u.full_name FROM users u
     JOIN roles r ON r.role_id=u.role_id
     WHERE r.role_slug='line_supervisor' AND u.status='active'"
)->fetchAll();

$pageTitle = 'Production Lines';
$activePage = 'lines';
$breadcrumbs = [['label' => 'Production Lines']];
include BASE_PATH . '/includes/layout_start.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="fw-bold mb-0">
        <i class="fa-solid fa-diagram-project me-2"></i>Production Lines
    </h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#lineModal" onclick="resetLineForm()">
        <i class="fa-solid fa-plus me-1"></i>Add Production Line
    </button>
</div>

<div class="card mb-3 shadow-sm border-0">
    <div class="card-body py-2">
        <form class="row g-2 align-items-center" method="GET">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search line, style, product..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <?php foreach (['running', 'stopped', 'maintenance', 'idle'] as $st): ?>
                    <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary btn-sm w-100" type="submit">
                    <i class="fa-solid fa-filter me-1"></i>Filter
                </button>
            </div>
            <div class="col-md-2">
                <a href="production_lines.php" class="btn btn-outline-danger btn-sm w-100">
                    <i class="fa-solid fa-rotate-left me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th><i class="fa-solid fa-hashtag me-1"></i>Line #</th>
                    <th><i class="fa-solid fa-building me-1"></i>Floor / Dept</th>
                    <th><i class="fa-solid fa-user-tie me-1"></i>Buyer</th>
                    <th><i class="fa-solid fa-shirt me-1"></i>Style</th>
                    <th><i class="fa-solid fa-box me-1"></i>Product</th>
                    <th><i class="fa-solid fa-user-shield me-1"></i>Supervisor</th>
                    <th><i class="fa-solid fa-users me-1"></i>Operators</th>
                    <th><i class="fa-solid fa-gears me-1"></i>Machines</th>
                    <th><i class="fa-solid fa-circle-info me-1"></i>Status</th>
                    <th class="text-end"><i class="fa-solid fa-gear me-1"></i>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $l): ?>
                <tr>
                    <td class="fw-semibold"><?= e($l['line_number']) ?></td>
                    <td><?= e($l['floor']) ?> / <?= e($l['department']) ?></td>
                    <td><?= e($l['buyer_name'] ?? '-') ?></td>
                    <td><?= e($l['style_number']) ?></td>
                    <td><?= e($l['product_name']) ?></td>
                    <td><?= e($l['supervisor_name'] ?? '-') ?></td>
                    <td><?= e($l['operator_count']) ?></td>
                    <td><?= e($l['machine_count']) ?></td>
                    <td><?= status_badge($l['status']) ?></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" onclick='editLine(<?= json_encode($l) ?>)' title="Edit">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteLine(<?= (int)$l['line_id'] ?>)" title="Delete">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($lines)): ?>
                <tr>
                    <td colspan="10" class="text-center text-muted py-4">
                        <i class="fa-solid fa-inbox fs-4 d-block mb-2"></i>No production lines found.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($p['total_pages'] > 1): ?>
    <div class="card-footer bg-white d-flex justify-content-center">
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php for ($i = 1; $i <= $p['total_pages']; $i++): ?>
                <li class="page-item <?= $i === $p['page'] ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&search=<?= e($search) ?>&status=<?= e($statusFilter) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="lineModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="lineForm" action="<?= BASE_URL ?>/ajax/production_lines_save.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="line_id" id="line_id">

            <div class="modal-header">
                <h5 class="modal-title" id="lineModalTitle">
                    <i class="fa-solid fa-plus me-2"></i>Add Production Line
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-hashtag me-1"></i>Line Number *</label>
                        <input type="text" name="line_number" id="line_number" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-building me-1"></i>Floor</label>
                        <input type="text" name="floor" id="floor" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-sitemap me-1"></i>Department</label>
                        <input type="text" name="department" id="department" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-user-tie me-1"></i>Buyer</label>
                        <select name="buyer_id" id="buyer_id" class="form-select">
                            <option value="">-- Select Buyer --</option>
                            <?php foreach ($buyers as $b): ?>
                            <option value="<?= $b['buyer_id'] ?>"><?= e($b['buyer_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-shirt me-1"></i>Style Number</label>
                        <input type="text" name="style_number" id="style_number" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-box me-1"></i>Product Name</label>
                        <input type="text" name="product_name" id="product_name" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-user-shield me-1"></i>Supervisor</label>
                        <select name="supervisor_id" id="supervisor_id" class="form-select">
                            <option value="">-- Select Supervisor --</option>
                            <?php foreach ($supervisors as $s): ?>
                            <option value="<?= $s['user_id'] ?>"><?= e($s['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-clock me-1"></i>Working Hours</label>
                        <input type="number" step="0.5" name="working_hours" id="working_hours" class="form-control" value="8">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><i class="fa-solid fa-users me-1"></i>Operator Count</label>
                        <input type="number" name="operator_count" id="operator_count" class="form-control" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><i class="fa-solid fa-gears me-1"></i>Machine Count</label>
                        <input type="number" name="machine_count" id="machine_count" class="form-control" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><i class="fa-solid fa-circle-info me-1"></i>Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="idle">Idle</option>
                            <option value="running">Running</option>
                            <option value="stopped">Stopped</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i>Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check me-1"></i>Save Line
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$extraScripts = '<script src="' . BASE_URL . '/assets/js/production_lines.js"></script>';
include BASE_PATH . '/includes/layout_end.php';
?>