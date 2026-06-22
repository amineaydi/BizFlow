<?php
session_start();
require_once 'db.php';
require_once 'theme.php';

// If logged in, redirect to appropriate page
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? '';
    if (in_array($role, ['cashier', 'worker'])) {
        header("Location: pos.php");
    } else {
        header("Location: admin.php");
    }
    exit;
}

if (isset($_SESSION['super_admin_id'])) {
    header("Location: super_admin.php");
    exit;
}

// Otherwise, show landing/redirect to login
header("Location: login.php");
exit;
?>
