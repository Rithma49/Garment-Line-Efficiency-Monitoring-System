<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    if ($action === 'save') {
        $id = post('downtime_id');
        $machineId = post('machine_id');
        $problem = post('problem');
        $start = post('start_time');
        $end = post('end_time') ?: null;
        $reason = post('reason');
        $tech = post('technician');
        $cost = (float)post('repair_cost', 0);
        $solution = post('solution');
        $status = post('status', 'open');
        $duration = null;
        if ($start && $end) {
            $duration = round((strtotime($end) - strtotime($start)) / 60);
        }
        try {
            if ($id) {
                $pdo->prepare('UPDATE machine_downtime SET machine_id=:m, problem=:p, start_time=:s, end_time=:e,
                    duration_minutes=:d, reason=:r, technician=:t, repair_cost=:c, solution=:sol, status=:st WHERE downtime_id=:id')
                    ->execute(['m'=>$machineId,'p'=>$problem,'s'=>$start,'e'=>$end,'d'=>$duration,'r'=>$reason,'t'=>$tech,'c'=>$cost,'sol'=>$solution,'st'=>$status,'id'=>$id]);
                set_flash('success', 'Downtime record updated.');
            } else {
                $pdo->prepare('INSERT INTO machine_downtime (machine_id, problem, start_time, end_time, duration_minutes, reason, technician, repair_cost, solution, status)
                    VALUES (:m,:p,:s,:e,:d,:r,:t,:c,:sol,:st)')
                    ->execute(['m'=>$machineId,'p'=>$problem,'s'=>$start,'e'=>$end,'d'=>$duration,'r'=>$reason,'t'=>$tech,'c'=>$cost,'sol'=>$solution,'st'=>$status]);
                set_flash('success', 'Downtime record created.');

                // Sync machine's current status
                $pdo->prepare("UPDATE machines SET current_status = 'stopped' WHERE machine_id = :id")->execute(['id' => $machineId]);
                $mNum = $pdo->prepare('SELECT machine_number FROM machines WHERE machine_id=:id');
                $mNum->execute(['id' => $machineId]);
                push_notification(null, 'machine_breakdown', 'Machine Breakdown', 'Machine ' . $mNum->fetchColumn() . ' reported: ' . $problem, BASE_URL . '/admin/machine_downtime.php');
            }
            if ($status === 'resolved') {
                $pdo->prepare("UPDATE machines SET current_status = 'running' WHERE machine_id = :id")->execute(['id' => $machineId]);
            }
            log_activity('save', 'machine_downtime', 'Saved downtime for machine ' . $machineId);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            set_flash('danger', 'Failed to save downtime record.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM machine_downtime WHERE downtime_id=:id')->execute(['id' => post('downtime_id')]);
        set_flash('success', 'Downtime record deleted.');
    }
    redirect(BASE_URL . '/admin/machine_downtime.php');
}

$records = $pdo->query(
    "SELECT md.*, m.machine_number FROM machine_downtime md JOIN machines m ON m.machine_id=md.machine_id ORDER BY md.start_time DESC LIMIT 100"
)->fetchAll();
$machines = $pdo->query("SELECT machine_id, machine_number FROM machines ORDER BY machine_number")->fetchAll();

$pageTitle = 'Machine Downtime';
$activePage = 'downtime';
$breadcrumbs = [['label' => 'Machine Downtime']];
include BASE_PATH . '/includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">Machine Downtime</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#dModal" onclick="resetDForm()"><i class="fa-solid fa-plus me-1"></i>Report Downtime</button>
</div>
<div class="card"><div class="table-responsive">
<table class="table table-hover mb-0 align-middle">
<thead><tr><th>Machine</th><th>Problem</th><th>Start</th><th>End</th><th>Duration</th><th>Technician</th><th>Cost</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
<tbody>
<?php foreach ($records as $r): ?>
<tr>
    <td class="fw-semibold"><?= e($r['machine_number']) ?></td>
    <td><?= e($r['problem']) ?></td>
    <td><?= format_date($r['start_time'], 'd M, h:i A') ?></td>
    <td><?= $r['end_time'] ? format_date($r['end_time'], 'd M, h:i A') : '-' ?></td>
    <td><?= $r['duration_minutes'] !== null ? $r['duration_minutes'] . ' min' : '-' ?></td>
    <td><?= e($r['technician']) ?></td>
    <td>Rs. <?= format_number($r['repair_cost']) ?></td>
    <td><?= status_badge($r['status']) ?></td>
    <td class="text-end">
        <button class="btn btn-sm btn-outline-primary" onclick='editD(<?= json_encode($r) ?>)'><i class="fa-solid fa-pen"></i></button>
        <form method="POST" class="d-inline" onsubmit="return confirmDelete()">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="downtime_id" value="<?= $r['downtime_id'] ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($records)): ?><tr><td colspan="9" class="text-center text-muted py-4">No downtime records found.</td></tr><?php endif; ?>
</tbody></table>
</div></div>

<div class="modal fade" id="dModal" tabindex="-1"><div class="modal-dialog modal-lg">
<form class="modal-content" method="POST">
<?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="downtime_id" id="downtime_id">
<div class="modal-header"><h5 class="modal-title" id="dModalTitle">Report Downtime</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div class="row g-3">
  <div class="col-md-6"><label class="form-label">Machine *</label>
    <select name="machine_id" id="d_machine" class="form-select" required><?php foreach ($machines as $m): ?><option value="<?= $m['machine_id'] ?>"><?= e($m['machine_number']) ?></option><?php endforeach; ?></select>
  </div>
  <div class="col-md-6"><label class="form-label">Reason</label>
    <select name="reason" id="d_reason" class="form-select"><option>Mechanical</option><option>Electrical</option><option>Operator Error</option><option>Power Failure</option><option>Spare Part Shortage</option><option>Other</option></select>
  </div>
  <div class="col-12"><label class="form-label">Problem *</label><input type="text" name="problem" id="d_problem" class="form-control" required></div>
  <div class="col-md-6"><label class="form-label">Start Time *</label><input type="datetime-local" name="start_time" id="d_start" class="form-control" required></div>
  <div class="col-md-6"><label class="form-label">End Time</label><input type="datetime-local" name="end_time" id="d_end" class="form-control"></div>
  <div class="col-md-6"><label class="form-label">Technician</label><input type="text" name="technician" id="d_tech" class="form-control"></div>
  <div class="col-md-6"><label class="form-label">Repair Cost (Rs.)</label><input type="number" step="0.01" name="repair_cost" id="d_cost" class="form-control" value="0"></div>
  <div class="col-md-6"><label class="form-label">Status</label>
    <select name="status" id="d_status" class="form-select"><option value="open">Open</option><option value="in_progress">In Progress</option><option value="resolved">Resolved</option></select>
  </div>
  <div class="col-12"><label class="form-label">Solution</label><textarea name="solution" id="d_solution" class="form-control" rows="2"></textarea></div>
</div></div>
<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Record</button></div>
</form>
</div></div>

<script>
const dModal = new bootstrap.Modal(document.getElementById('dModal'));
function resetDForm() { document.querySelector('#dModal form').reset(); document.getElementById('downtime_id').value=''; document.getElementById('dModalTitle').textContent='Report Downtime'; }
function editD(r) {
    document.getElementById('dModalTitle').textContent = 'Edit Downtime Record';
    document.getElementById('downtime_id').value = r.downtime_id;
    document.getElementById('d_machine').value = r.machine_id;
    document.getElementById('d_reason').value = r.reason || 'Mechanical';
    document.getElementById('d_problem').value = r.problem || '';
    document.getElementById('d_start').value = (r.start_time || '').replace(' ', 'T').slice(0,16);
    document.getElementById('d_end').value = r.end_time ? r.end_time.replace(' ', 'T').slice(0,16) : '';
    document.getElementById('d_tech').value = r.technician || '';
    document.getElementById('d_cost').value = r.repair_cost || 0;
    document.getElementById('d_status').value = r.status || 'open';
    document.getElementById('d_solution').value = r.solution || '';
    dModal.show();
}
</script>
<?php include BASE_PATH . '/includes/layout_end.php'; ?>
