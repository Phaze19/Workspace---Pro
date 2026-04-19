<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';

// Already logged in → redirect to their dashboard
if (is_logged_in()) redirect_by_role();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id, name, email, password, role FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];
            redirect_by_role();
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

$unauthorized = isset($_GET['error']) && $_GET['error'] === 'unauthorized';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – WorkSpace Pro</title>
    <link rel="stylesheet" href="/workspace_pro/assets/style.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-logo">
            <h1>WorkSpace Pro</h1>
            <p>Sign in to your account</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($unauthorized): ?>
            <div class="alert alert-warning">You don't have permission to access that page.</div>
        <?php endif; ?>
        <?php if (isset($_GET['registered'])): ?>
            <div class="alert alert-success">Account created! You can now log in.</div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="you@example.com" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:11px;">
                Sign In
            </button>
        </form>

        <div class="auth-footer">
            Don't have an account? <a href="/workspace_pro/auth/register.php">Register</a>
        </div>

        <!-- Dev shortcut credentials -->
        <div style="margin-top:24px; padding:14px; background:#F4F5F7; border-radius:8px; font-size:12px; color:#6B7280;">
            <strong style="display:block; margin-bottom:6px;">Test Credentials</strong>
            Admin: admin@workspace.com / admin123<br>
            Staff: staff@workspace.com / staff123<br>
            Employee: emp@workspace.com / emp123
        </div>
    </div>
</div>
</body>
</html>
