<?php
/**
 * GLEMS - Database Restore (executes a raw .sql file, admin only)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['backup_file']['tmp_name'])) {
    set_flash('danger', 'Please choose a valid .sql backup file.');
    redirect(BASE_URL . '/admin/settings.php');
}
require_csrf();

$sql = file_get_contents($_FILES['backup_file']['tmp_name']);
if ($sql === false || trim($sql) === '') {
    set_flash('danger', 'Could not read the uploaded backup file.');
    redirect(BASE_URL . '/admin/settings.php');
}

try {
    $pdo = getDBConnection();
    // Split on statement-terminating semicolons (simple backups only; for complex dumps use a CLI import instead)
    $statements = array_filter(array_map('trim', explode(";\n", $sql)));
    $pdo->beginTransaction();
    foreach ($statements as $stmt) {
        if ($stmt === '' || str_starts_with($stmt, '--')) continue;
        $pdo->exec($stmt);
    }
    $pdo->commit();
    log_activity('restore', 'settings', 'Restored database from uploaded backup');
    set_flash('success', 'Database restored successfully.');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    set_flash('danger', 'Restore failed: the backup file may be invalid or incompatible.');
}
redirect(BASE_URL . '/admin/settings.php');
