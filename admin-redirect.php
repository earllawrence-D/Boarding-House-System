<?php
session_start();
$_SESSION['user_id'] = 1060; // Your admin user_id
$_SESSION['user_type'] = 'admin';
header("Location: administrator/dashboard.php");
exit();