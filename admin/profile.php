<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name  = post('full_name');
    $phone = post('phone');
    $photo = !empty($_FILES['photo']['name'])
        ? handle_image_upload($_FILES['photo'], 'operators')
        : null;

    $sql = 'UPDATE users SET full_name=:name, phone=:phone';
    $params = [
        'name'  => $name,
        'phone' => $phone,
        'id'    => $_SESSION['user_id'],
    ];

    if ($photo) {
        $sql .= ', profile_photo=:photo';
        $params['photo'] = $photo;
    }
    $sql .= ' WHERE user_id=:id';

    $pdo->prepare($sql)->execute($params);

    $_SESSION['full_name'] = $name;
    if ($photo) {
        $_SESSION['photo'] = $photo;
    }

    set_flash('success', 'Profile updated successfully.');
    redirect(BASE_URL . '/admin/profile.php');
}

$stmt = $pdo->prepare(
    'SELECT u.*, r.role_name FROM users u
     JOIN roles r ON r.role_id=u.role_id
     WHERE u.user_id=:id'
);
$stmt->execute(['id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

$pageTitle = 'My Profile';
$activePage = '';
$breadcrumbs = [['label' => 'My Profile']];
include BASE_PATH . '/includes/layout_start.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header">
                <i class="fa-solid fa-user me-2"></i>My Profile
            </div>

            <div class="card-body text-center">
                <img src="<?= !empty($user['profile_photo']) ? UPLOAD_URL . '/operators/' . e($user['profile_photo']) : 'https://ui-avatars.com/api/?background=1E3A8A&color=fff&size=128&name=' . urlencode($user['full_name']) ?>"
                     class="rounded-circle mb-3" width="100" height="100" style="object-fit:cover;" alt="Profile photo">

                <form method="POST" enctype="multipart/form-data" class="text-start">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label"><i class="fa-solid fa-user me-1"></i>Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="fa-solid fa-at me-1"></i>Username</label>
                        <input type="text" class="form-control" value="<?= e($user['username']) ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="fa-solid fa-envelope me-1"></i>Email</label>
                        <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="fa-solid fa-phone me-1"></i>Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= e($user['phone']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="fa-solid fa-user-shield me-1"></i>Role</label>
                        <input type="text" class="form-control" value="<?= e($user['role_name']) ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="fa-solid fa-image me-1"></i>Profile Photo</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Update Profile
                    </button>
                    <a href="<?= BASE_URL ?>/auth/change-password.php" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-key me-1"></i>Change Password
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layout_end.php'; ?>