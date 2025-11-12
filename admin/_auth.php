<?php
require_once '../config/config.php';

function admin_require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . SITE_URL . '/admin/login.php');
        exit;
    }
    if (!hasPermission(ROLE_ADMIN)) {
        header('Location: ' . SITE_URL . '/pages/unauthorized.php');
        exit;
    }
}


