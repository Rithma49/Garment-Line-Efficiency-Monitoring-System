<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();
header('Content-Type: application/json');

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

$lineId = post('line_id');
if (!$lineId) {
    echo json_encode(['success' => false, 'message' => 'Missing line ID.']);
    exit;
}

try {
    $pdo = getDBConnection();

    $stmt = $pdo->prepare('DELETE FROM production_lines WHERE line_id = :id');
    $stmt->execute(['id' => $lineId]);

    log_activity('delete', 'production_lines', "Deleted line ID $lineId");

    echo json_encode(['success' => true, 'message' => 'Production line deleted successfully.']);
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Could not delete: this line may have related records.']);
}