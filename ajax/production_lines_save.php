<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}
if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
    exit;
}

$pdo = getDBConnection();

$lineId       = post('line_id');
$lineNumber   = post('line_number');
$floor        = post('floor');
$department   = post('department');
$buyerId      = post('buyer_id') ?: null;
$styleNumber  = post('style_number');
$productName  = post('product_name');
$supervisorId = post('supervisor_id') ?: null;
$operatorCnt  = (int)post('operator_count', 0);
$machineCnt   = (int)post('machine_count', 0);
$workingHours = (float)post('working_hours', 8);
$status       = post('status', 'idle');

if (empty($lineNumber)) {
    echo json_encode(['success' => false, 'message' => 'Line number is required.']);
    exit;
}

try {
    if ($lineId) {
        $stmt = $pdo->prepare(
            'UPDATE production_lines SET line_number=:ln, floor=:fl, department=:dp, buyer_id=:by, style_number=:sn,
             product_name=:pn, supervisor_id=:sup, operator_count=:oc, machine_count=:mc, working_hours=:wh, status=:st
             WHERE line_id=:id'
        );
        $stmt->execute([
            'ln'  => $lineNumber,
            'fl'  => $floor,
            'dp'  => $department,
            'by'  => $buyerId,
            'sn'  => $styleNumber,
            'pn'  => $productName,
            'sup' => $supervisorId,
            'oc'  => $operatorCnt,
            'mc'  => $machineCnt,
            'wh'  => $workingHours,
            'st'  => $status,
            'id'  => $lineId,
        ]);
        log_activity('update', 'production_lines', "Updated line $lineNumber");
        $message = 'Production line updated successfully.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO production_lines (line_number, floor, department, buyer_id, style_number, product_name,
             supervisor_id, operator_count, machine_count, working_hours, status)
             VALUES (:ln, :fl, :dp, :by, :sn, :pn, :sup, :oc, :mc, :wh, :st)'
        );
        $stmt->execute([
            'ln'  => $lineNumber,
            'fl'  => $floor,
            'dp'  => $department,
            'by'  => $buyerId,
            'sn'  => $styleNumber,
            'pn'  => $productName,
            'sup' => $supervisorId,
            'oc'  => $operatorCnt,
            'mc'  => $machineCnt,
            'wh'  => $workingHours,
            'st'  => $status,
        ]);
        log_activity('create', 'production_lines', "Created line $lineNumber");
        $message = 'Production line created successfully.';
    }

    echo json_encode(['success' => true, 'message' => $message]);
} catch (PDOException $e) {
    $msg = $e->getCode() === '23000'
        ? 'A line with this number already exists.'
        : 'Database error occurred.';
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => $msg]);
}