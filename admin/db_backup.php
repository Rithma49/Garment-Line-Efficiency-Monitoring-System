<?php
/**
 * GLEMS - Database Backup (pure PHP, no shell_exec/mysqldump dependency)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_role(['admin']);

$pdo = getDBConnection();
$filename = 'glems_backup_' . date('Ymd_His') . '.sql';

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

echo "-- GLEMS Database Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    $createStmt = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
    echo "DROP TABLE IF EXISTS `$table`;\n";
    echo $createStmt['Create Table'] . ";\n\n";

    $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $cols = array_map(fn($c) => "`$c`", array_keys($row));
        $vals = array_map(function ($v) use ($pdo) {
            return $v === null ? 'NULL' : $pdo->quote($v);
        }, array_values($row));
        echo "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
    }
    echo "\n";
}

echo "SET FOREIGN_KEY_CHECKS=1;\n";
log_activity('backup', 'settings', 'Downloaded database backup');
