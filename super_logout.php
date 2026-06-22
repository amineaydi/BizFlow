<?php
session_start();

unset($_SESSION['super_admin_id']);
unset($_SESSION['super_admin_name']);
unset($_SESSION['super_admin_username']);
unset($_SESSION['super_login_time']);
unset($_SESSION['super_csrf']);

header("Location: super_login.php");
exit;
