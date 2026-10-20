<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');

    if ($action === 'save') {
        $id = post('order_id');
        $data = [
            'buyer'    => post('buyer_id'),
            'po'       => post('po_number'),
            'style'    => post('style_number'),
            'qty'      => (int)post('order_quantity', 0),
            'delivery' => post('delivery_date') ?: null,
            'priority' => post('priority', 'medium'),
            'progress' => (int)post('current_progress', 0),
            'status'   => post('status', 'pending'),
        ];

        try {
            if ($id) {
                $data['id'] = $id;
                $pdo->prepare(
                    'UPDATE orders SET buyer_id=:buyer, po_number=:po, style_number=:style, order_quantity=:qty,
                     delivery_date=:delivery, priority=:priority, current_progress=:progress, status=:status
                     WHERE order_id=:id'
                )->execute($data);
                set_flash('success', 'Order updated successfully.');
            } else {
                $pdo->prepare(
                    'INSERT INTO orders (buyer_id, po_number, style_number, order_quantity, delivery_date, priority, current_progress, status)
                     VALUES (:buyer, :po, :style, :qty, :delivery, :priority, :progress, :status)'
                )->execute($data);
                set_flash('success', 'Order created successfully.');
            }
            log_activity('save', 'orders', 'Saved order ' . $data['po']);
        } catch (PDOException $e) {
            set_flash('danger', 'PO number already exists or a database error occurred.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM orders WHERE order_id=:id')
            ->execute(['id' => post('order_id')]);
        set_flash('success', 'Order deleted.');
    }

    redirect(BASE_URL . '/admin/orders.php');
}

$orders = $pdo->query(
    "SELECT o.*, b.buyer_name FROM orders o
     JOIN buyers b ON b.buyer_id=o.buyer_id
     ORDER BY o.delivery_date"
)->fetchAll();

$buyers = $pdo->query(
    "SELECT buyer_id, buyer_name FROM buyers ORDER BY buyer_name"
)->fetchAll();

$pageTitle = 'Orders';
$activePage = 'orders';
$breadcrumbs = [['label' => 'Orders']];
include BASE_PATH . '/includes/layout_start.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">
        <i class="fa-solid fa-clipboard-list me-2"></i>Order Management
    </h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#oModal" onclick="resetOForm()">
        <i class="fa-solid fa-plus me-1"></i>Add Order
    </button>
</div>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th><i class="fa-solid fa-hashtag me-1"></i>PO Number</th>
                    <th><i class="fa-solid fa-user-tie me-1"></i>Buyer</th>
                    <th><i class="fa-solid fa-shirt me-1"></i>Style</th>
                    <th><i class="fa-solid fa-boxes-stacked me-1"></i>Qty</th>
                    <th><i class="fa-solid fa-calendar me-1"></i>Delivery</th>
                    <th><i class="fa-solid fa-flag me-1"></i>Priority</th>
                    <th><i class="fa-solid fa-chart-simple me-1"></i>Progress</th>
                    <th><i class="fa-solid fa-circle-info me-1"></i>Status</th>
                    <th class="text-end"><i class="fa-solid fa-gear me-1"></i>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o):
                    $priorityClass = ['low' => 'secondary', 'medium' => 'info', 'high' => 'warning', 'urgent' => 'danger'][$o['priority']] ?? 'secondary';
                ?>
                <tr>
                    <td class="fw-semibold"><?= e($o['po_number']) ?></td>
                    <td><?= e($o['buyer_name']) ?></td>
                    <td><?= e($o['style_number']) ?></td>
                    <td><?= format_number($o['order_quantity']) ?></td>
                    <td><?= format_date($o['delivery_date']) ?></td>
                    <td>
                        <span class="badge bg-<?= $priorityClass ?> text-capitalize">
                            <?= e($o['priority']) ?>
                        </span>
                    </td>
                    <td style="min-width:120px;">
                        <div class="progress" style="height:8px;">
                            <div class="progress-bar bg-primary" style="width:<?= $o['current_progress'] ?>%"></div>
                        </div>
                        <span class="small"><?= $o['current_progress'] ?>%</span>
                    </td>
                    <td><?= status_badge($o['status']) ?></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" onclick='editO(<?= json_encode($o) ?>)'>
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirmDelete()">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        <i class="fa-solid fa-inbox fs-4 d-block mb-2"></i>No orders found.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="oModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="order_id" id="order_id">

            <div class="modal-header">
                <h5 class="modal-title" id="oModalTitle">
                    <i class="fa-solid fa-plus me-2"></i>Add Order
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-user-tie me-1"></i>Buyer *</label>
                        <select name="buyer_id" id="o_buyer" class="form-select" required>
                            <?php foreach ($buyers as $b): ?>
                            <option value="<?= $b['buyer_id'] ?>"><?= e($b['buyer_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-hashtag me-1"></i>PO Number *</label>
                        <input type="text" name="po_number" id="o_po" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-shirt me-1"></i>Style Number</label>
                        <input type="text" name="style_number" id="o_style" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-boxes-stacked me-1"></i>Order Quantity *</label>
                        <input type="number" name="order_quantity" id="o_qty" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-calendar me-1"></i>Delivery Date</label>
                        <input type="date" name="delivery_date" id="o_delivery" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-flag me-1"></i>Priority</label>
                        <select name="priority" id="o_priority" class="form-select">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-chart-simple me-1"></i>Progress (%)</label>
                        <input type="number" name="current_progress" id="o_progress" class="form-control" min="0" max="100" value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-circle-info me-1"></i>Status</label>
                        <select name="status" id="o_status" class="form-select">
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="delayed">Delayed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i>Cancel
                </button>
                <button class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk me-1"></i>Save Order
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const oModal = new bootstrap.Modal(document.getElementById('oModal'));

function resetOForm() {
    document.querySelector('#oModal form').reset();
    document.getElementById('order_id').value = '';
    document.getElementById('oModalTitle').innerHTML = '<i class="fa-solid fa-plus me-2"></i>Add Order';
}

function editO(o) {
    document.getElementById('oModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square me-2"></i>Edit Order';
    document.getElementById('order_id').value = o.order_id;
    document.getElementById('o_buyer').value = o.buyer_id;
    document.getElementById('o_po').value = o.po_number;
    document.getElementById('o_style').value = o.style_number || '';
    document.getElementById('o_qty').value = o.order_quantity;
    document.getElementById('o_delivery').value = o.delivery_date || '';
    document.getElementById('o_priority').value = o.priority;
    document.getElementById('o_progress').value = o.current_progress;
    document.getElementById('o_status').value = o.status;
    oModal.show();
}
</script>

<?php include BASE_PATH . '/includes/layout_end.php'; ?>