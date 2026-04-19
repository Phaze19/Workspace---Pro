<?php
// config/auth.php  –  Session helpers. Include after session_start().

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: /workspace_pro/auth/login.php");
        exit();
    }
}

function require_role($role) {
    require_login();
    if ($_SESSION['role'] !== $role) {
        header("Location: /workspace_pro/auth/login.php?error=unauthorized");
        exit();
    }
}

function require_any_role(array $roles) {
    require_login();
    if (!in_array($_SESSION['role'], $roles)) {
        header("Location: /workspace_pro/auth/login.php?error=unauthorized");
        exit();
    }
}

function redirect_by_role() {
    switch ($_SESSION['role']) {
        case 'admin':    header("Location: /workspace_pro/admin/dashboard.php");    break;
        case 'staff':    header("Location: /workspace_pro/staff/dashboard.php");    break;
        case 'employee': header("Location: /workspace_pro/employee/dashboard.php"); break;
    }
    exit();
}
?>
