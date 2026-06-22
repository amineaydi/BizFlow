<?php
session_start();

// Clear business user session
unset($_SESSION['user_id']);
unset($_SESSION['user_name']);
unset($_SESSION['user_email']);
unset($_SESSION['user_role']);
unset($_SESSION['business_id']);
unset($_SESSION['business_name']);
unset($_SESSION['business_logo']);
unset($_SESSION['login_time']);

header("Location: login.php");
exit;
