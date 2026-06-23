<?php
session_start();

// Clear only POS session
unset($_SESSION['pos_user_id']);
unset($_SESSION['pos_user_name']);
unset($_SESSION['pos_user_role']);
unset($_SESSION['pos_business_id']);
unset($_SESSION['pos_business_name']);
unset($_SESSION['pos_business_logo']);
unset($_SESSION['pos_login_time']);
unset($_SESSION['pos_csrf']);

// If no admin session either, clear generic
if (!isset($_SESSION['admin_user_id'])) {
    unset($_SESSION['user_id']);
    unset($_SESSION['user_name']);
    unset($_SESSION['user_role']);
    unset($_SESSION['business_id']);
    unset($_SESSION['business_name']);
}

header("Location: pos_login.php");
exit;
