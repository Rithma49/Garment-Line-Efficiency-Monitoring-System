<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();
header('Content-Type: application/json');

$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id = :uid OR user_id IS NULL) AND is_read = 0");
$stmt->execute(['uid' => $_SESSION['user_id']]);
echo json_encode(['count' => (int)$stmt->fetchColumn()]);