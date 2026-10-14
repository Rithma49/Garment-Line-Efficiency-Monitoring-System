<?php
/**
 * GLEMS - Entry point. Redirects to dashboard (or login if not authenticated).
 */
require_once __DIR__ . '/config/config.php';
redirect(is_logged_in() ? BASE_URL . '/admin/dashboard.php' : BASE_URL . '/auth/login.php');