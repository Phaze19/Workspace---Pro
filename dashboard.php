<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';
require_role('admin');

$total_users     = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM users"))[0];
$total_resources = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM resources"))[0];
$total_bookings  = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM bookings WHERE status='active'"))[0];
$needs_attention = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM resources WHERE status != 'available'"))[0];

$active = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin Dashboard – WorkSpace Pro</title>
    <link rel="stylesheet" href="/workspace_pro/assets/style.css">
</head>
<body>
<div class="page-wrap">
<?php include '../partials/navbar.php'; ?>
<div class="main-content">
    <div class="page-header">
        <h1>Admin Overview</h1>
        <p>Full system visibility across all users and resources.</p>
    </div>
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">Total Users</div>
            <div class="stat-value"><?= $total_users ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Resources</div>
            <div class="stat-value"><?= $total_resources ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Active Bookings</div>
            <div class="stat-value"><?= $total_bookings ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Need Attention</div>
            <div class="stat-value"><?= $needs_attention ?></div>
            <div class="stat-sub">Dirty / Out of Service</div>
        </div>
    </div>
    <div class="card">
        <div class="section-header"><h2>Quick Actions</h2></div>
        <div class="flex gap-2">
            <a href="/workspace_pro/admin/resources.php" class="btn btn-primary">Manage Resources</a>
            <a href="/workspace_pro/admin/bookings.php"  class="btn btn-ghost">All Bookings</a>
            <a href="/workspace_pro/admin/users.php"     class="btn btn-ghost">Manage Users</a>
        </div>
    </div>
</div>
</div>
</body>
</html>
