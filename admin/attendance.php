<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    if ($action === 'save') {
        $id = post('attendance_id');
        $operatorId = post('operator_id');
        $date = post('attendance_date');
        $checkIn = post('check_in') ?: null;
        $checkOut = post('check_out') ?: null;
        $status = post('status', 'present');

        $workingHours = 0;
        $overtime = 0;
        $late = 0;

        if ($checkIn && $checkOut) {
            $workingHours = round((strtotime($checkOut) - strtotime($checkIn)) / 3600, 2);
            $overtime = max(0, round($workingHours - 8, 2));
        }
        if ($checkIn && strtotime($checkIn) > strtotime('08:00:00')) {
            $late = 1;
        }

        try {
            if ($id) {
                $pdo->prepare(
                    'UPDATE attendance SET operator_id=:op, attendance_date=:d, check_in=:ci, check_out=:co,
                     working_hours=:wh, late_arrival=:la, overtime_hours=:ot, status=:st WHERE attendance_id=:id'
                )->execute([
                    'op' => $operatorId,
                    'd' => $date,
                    'ci' => $checkIn,
                    'co' => $checkOut,
                    'wh' => $workingHours,
                    'la' => $late,
                    'ot' => $overtime,
                    'st' => $status,
                    'id' => $id,
                ]);
                set_flash('success', 'Attendance updated.');
            } else {
                $pdo->prepare(
                    'INSERT INTO attendance (operator_id, attendance_date, check_in, check_out, working_hours, late_arrival, overtime_hours, status)
                     VALUES (:op, :d, :ci, :co, :wh, :la, :ot, :st)
                     ON DUPLICATE KEY UPDATE check_in=:ci2, check_out=:co2, working_hours=:wh2, late_arrival=:la2, overtime_hours=:ot2, status=:st2'
                )->execute([
                    'op' => $operatorId,
                    'd' => $date,
                    'ci' => $checkIn,
                    'co' => $checkOut,
                    'wh' => $workingHours,
                    'la' => $late,
                    'ot' => $overtime,
                    'st' => $status,
                    'ci2' => $checkIn,
                    'co2' => $checkOut,
                    'wh2' => $workingHours,
                    'la2' => $late,
                    'ot2' => $overtime,
                    'st2' => $status,
                ]);
                set_flash('success', 'Attendance recorded.');
            }
            log_activity('save', 'attendance', 'Saved attendance for operator ' . $operatorId);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            set_flash('danger', 'Failed to save attendance.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM attendance WHERE attendance_id=:id')
            ->execute(['id' => post('attendance_id')]);
        set_flash('success', 'Attendance record deleted.');
    }
    redirect(BASE_URL . '/admin/attendance.php?date=' . get('filter_date', date('Y-m-d')));
}

$filterDate = get('date', date('Y-m-d'));

$stmt = $pdo->prepare(
    "SELECT a.*, o.full_name, o.employee_id FROM attendance a JOIN operators o ON o.operator_id=a.operator_id
     WHERE a.attendance_date = :d ORDER BY o.full_name"
);
$stmt->execute(['d' => $filterDate]);
$attendance = $stmt->fetchAll();

$operators = $pdo->query(
    "SELECT operator_id, full_name, employee_id FROM operators WHERE status='active' ORDER BY full_name"
)->fetchAll();

$pageTitle = 'Attendance';
$activePage = 'attendance';
$breadcrumbs = [['label' => 'Attendance']];
include BASE_PATH . '/includes/layout_start.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="fw-bold mb-0">
        <i class="fa-solid fa-calendar-check me-2"></i>Attendance Management
    </h4>
    <div class="d-flex gap-2">
        <form method="GET">
            <input type="date" name="date" class="form-control form-control-sm" value="<?= e($filterDate) ?>" onchange="this.form.submit()">
        </form>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#aModal" onclick="resetAForm()">
            <i class="fa-solid fa-plus me-1"></i>Add Entry
        </button>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th><i class="fa-solid fa-user me-1"></i>Employee</th>
                    <th><i class="fa-solid fa-right-to-bracket me-1"></i>Check In</th>
                    <th><i class="fa-solid fa-right-from-bracket me-1"></i>Check Out</th>
                    <th><i class="fa-solid fa-hourglass-half me-1"></i>Working Hours</th>
                    <th><i class="fa-solid fa-clock me-1"></i>Late</th>
                    <th><i class="fa-solid fa-business-time me-1"></i>Overtime</th>
                    <th><i class="fa-solid fa-circle-info me-1"></i>Status</th>
                    <th class="text-end"><i class="fa-solid fa-gear me-1"></i>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attendance as $a): ?>
                <tr>
                    <td class="fw-semibold">
                        <?= e($a['full_name']) ?>
                        <span class="text-muted small">(<?= e($a['employee_id']) ?>)</span>
                    </td>
                    <td><?= $a['check_in'] ? date('h:i A', strtotime($a['check_in'])) : '-' ?></td>
                    <td><?= $a['check_out'] ? date('h:i A', strtotime($a['check_out'])) : '-' ?></td>
                    <td><?= e($a['working_hours']) ?>h</td>
                    <td><?= $a['late_arrival'] ? '<span class="badge bg-warning">Late</span>' : '-' ?></td>
                    <td><?= e($a['overtime_hours']) ?>h</td>
                    <td><?= status_badge($a['status']) ?></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" onclick='editA(<?= json_encode($a) ?>)'>
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirmDelete()">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="attendance_id" value="<?= $a['attendance_id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($attendance)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        <i class="fa-solid fa-inbox fs-4 d-block mb-2"></i>No attendance records for this date.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="aModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="attendance_id" id="attendance_id">

            <div class="modal-header">
                <h5 class="modal-title" id="aModalTitle">
                    <i class="fa-solid fa-calendar-plus me-2"></i>Add Attendance
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label"><i class="fa-solid fa-user me-1"></i>Operator *</label>
                    <select name="operator_id" id="a_operator" class="form-select" required>
                        <?php foreach ($operators as $o): ?>
                        <option value="<?= $o['operator_id'] ?>">
                            <?= e($o['full_name']) ?> (<?= e($o['employee_id']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label"><i class="fa-solid fa-calendar me-1"></i>Date *</label>
                    <input type="date" name="attendance_date" id="a_date" class="form-control" required value="<?= e($filterDate) ?>">
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label"><i class="fa-solid fa-right-to-bracket me-1"></i>Check In</label>
                        <input type="time" name="check_in" id="a_checkin" class="form-control">
                    </div>
                    <div class="col-6">
                        <label class="form-label"><i class="fa-solid fa-right-from-bracket me-1"></i>Check Out</label>
                        <input type="time" name="check_out" id="a_checkout" class="form-control">
                    </div>
                </div>

                <div class="mb-3 mt-3">
                    <label class="form-label"><i class="fa-solid fa-circle-info me-1"></i>Status</label>
                    <select name="status" id="a_status" class="form-select">
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="leave">Leave</option>
                        <option value="half_day">Half Day</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i>Cancel
                </button>
                <button class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk me-1"></i>Save
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const aModal = new bootstrap.Modal(document.getElementById('aModal'));

function resetAForm() {
    document.querySelector('#aModal form').reset();
    document.getElementById('attendance_id').value = '';
    document.getElementById('aModalTitle').innerHTML = '<i class="fa-solid fa-calendar-plus me-2"></i>Add Attendance';
    document.getElementById('a_date').value = '<?= e($filterDate) ?>';
}

function editA(a) {
    document.getElementById('aModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square me-2"></i>Edit Attendance';
    document.getElementById('attendance_id').value = a.attendance_id;
    document.getElementById('a_operator').value = a.operator_id;
    document.getElementById('a_date').value = a.attendance_date;
    document.getElementById('a_checkin').value = a.check_in || '';
    document.getElementById('a_checkout').value = a.check_out || '';
    document.getElementById('a_status').value = a.status;
    aModal.show();
}
</script>

<?php include BASE_PATH . '/includes/layout_end.php'; ?>