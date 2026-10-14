<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();
header('Content-Type: application/json');

$pdo = getDBConnection();
$todayProduction = (float)$pdo->query("SELECT COALESCE(SUM(produced_qty),0) FROM production_records WHERE record_date = CURDATE()")->fetchColumn();
$todayTarget = (float)$pdo->query("SELECT COALESCE(SUM(target_quantity),0) FROM production_targets WHERE target_date = CURDATE() AND target_type='daily'")->fetchColumn();
$efficiency = calc_efficiency($todayProduction, $todayTarget);

echo json_encode([
    'success' => true,
    'data' => [
        'today_production' => format_number($todayProduction),
        'today_target'      => format_number($todayTarget),
        'efficiency'        => $efficiency,
    ],
]);