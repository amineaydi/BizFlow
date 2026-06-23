<?php
session_start();

// Clear only admin session
unset($_SESSION['admin_user_id']);
unset($_SESSION['admin_user_name']);
unset($_SESSION['admin_user_email']);
unset($_SESSION['admin_user_role']);
unset($_SESSION['admin_business_id']);
unset($_SESSION['admin_business_name']);
unset($_SESSION['admin_business_logo']);
unset($_SESSION['admin_login_time']);
unset($_SESSION['admin_csrf']);

// If no POS session either, clear generic
if (!isset($_SESSION['pos_user_id'])) {
    unset($_SESSION['user_id']);
    unset($_SESSION['user_name']);
    unset($_SESSION['user_role']);
    unset($_SESSION['business_id']);
    unset($_SESSION['business_name']);
}

header("Location: admin_login.php");
exit;
