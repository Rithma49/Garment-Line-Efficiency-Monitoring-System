<?php
require_once dirname(__DIR__) . '/config/config.php';
require_role(['admin']);
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $keys = [
        'company_name',
        'company_address',
        'company_phone',
        'company_email',
        'currency',
        'efficiency_warning_threshold',
        'timezone',
    ];

    foreach ($keys as $k) {
        $val = post($k, '');
        $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value=:v2'
        )->execute(['k' => $k, 'v' => $val, 'v2' => $val]);
    }

    log_activity('update', 'settings', 'Updated system settings');
    set_flash('success', 'Settings saved successfully.');
    redirect(BASE_URL . '/admin/settings.php');
}

$rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
$settings = array_column($rows, 'setting_value', 'setting_key');

$pageTitle = 'Settings';
$activePage = 'settings';
$breadcrumbs = [['label' => 'Settings']];
include BASE_PATH . '/includes/layout_start.php';
?>

<h4 class="fw-bold mb-3">
    <i class="fa-solid fa-gear me-2"></i>System Settings
</h4>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header">
                <i class="fa-solid fa-building me-2"></i>Company Information
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label"><i class="fa-solid fa-building me-1"></i>Company Name</label>
                        <input type="text" name="company_name" class="form-control" value="<?= e($settings['company_name'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="fa-solid fa-location-dot me-1"></i>Company Address</label>
                        <textarea name="company_address" class="form-control" rows="2"><?= e($settings['company_address'] ?? '') ?></textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><i class="fa-solid fa-phone me-1"></i>Phone</label>
                            <input type="text" name="company_phone" class="form-control" value="<?= e($settings['company_phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fa-solid fa-envelope me-1"></i>Email</label>
                            <input type="email" name="company_email" class="form-control" value="<?= e($settings['company_email'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><i class="fa-solid fa-coins me-1"></i>Currency</label>
                            <input type="text" name="currency" class="form-control" value="<?= e($settings['currency'] ?? 'LKR') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><i class="fa-solid fa-gauge-high me-1"></i>Efficiency Warning Threshold (%)</label>
                            <input type="number" name="efficiency_warning_threshold" class="form-control" value="<?= e($settings['efficiency_warning_threshold'] ?? 75) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><i class="fa-solid fa-clock me-1"></i>Timezone</label>
                            <input type="text" name="timezone" class="form-control" value="<?= e($settings['timezone'] ?? 'Asia/Colombo') ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mt-4">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Save Settings
                    </button>
                </form>
            </div>
        </div>

        <div class="card mt-3 shadow-sm border-0">
            <div class="card-header">
                <i class="fa-solid fa-database me-2"></i>Database Backup
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Download a full SQL backup of the system database, or restore from a previously saved backup file.
                </p>
                <a href="<?= BASE_URL ?>/admin/db_backup.php" class="btn btn-outline-primary btn-sm">
                    <i class="fa-solid fa-download me-1"></i>Download Backup
                </a>
                <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#restoreModal">
                    <i class="fa-solid fa-upload me-1"></i>Restore Database
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="restoreModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="<?= BASE_URL ?>/admin/db_restore.php" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-upload me-2"></i>Restore Database
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-warning small">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    Restoring will overwrite all current data. This action cannot be undone.
                </div>
                <input type="file" name="backup_file" class="form-control" accept=".sql" required>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i>Cancel
                </button>
                <button class="btn btn-danger">
                    <i class="fa-solid fa-rotate-left me-1"></i>Restore
                </button>
            </div>
        </form>
    </div>
</div>

<?php include BASE_PATH . '/includes/layout_end.php'; ?>