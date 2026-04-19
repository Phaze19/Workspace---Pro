<?php
session_start();
require_once 'config/auth.php';

if (is_logged_in()) {
    redirect_by_role();
} else {
    header("Location: /workspace_pro/auth/login.php");
    exit();
}
?>
