<?php
?><!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? APP_NAME) ?> - <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/@fontsource/inter@5.0.8/index.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1080" id="toastContainer"></div>
<script>
    const BASE_URL_JS = <?= json_encode(BASE_URL) ?>;
    const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
</script>
<div class="app-shell">
    <?php include BASE_PATH . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include BASE_PATH . '/includes/header.php'; ?>
        <div class="page-body">