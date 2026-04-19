<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';
require_role('staff');

$uid = $_SESSION['user_id'];
$dirty    = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM resources WHERE status='dirty'"))[0];
$out      = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM resources WHERE status='out_of_service'"))[0];
$my_logs  = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM maintenance_logs WHERE staff_id=$uid"))[0];

$active = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Staff Dashboard – WorkSpace Pro</title>
    <link rel="stylesheet" href="/workspace_pro/assets/style.css">
</head>
<body>
<div class="page-wrap">
<?php include '../partials/navbar.php'; ?>
<div class="main-content">
    <div class="page-header">
        <h1>Maintenance Dashboard</h1>
        <p>Welcome, <?= htmlspecialchars($_SESSION['name']) ?>. Here's what needs attention today.</p>
    </div>
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">Dirty Resources</div>
            <div class="stat-value"><?= $dirty ?></div>
            <div class="stat-sub">Awaiting cleaning</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Out of Service</div>
            <div class="stat-value"><?= $out ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">My Actions (Total)</div>
            <div class="stat-value"><?= $my_logs ?></div>
        </div>
    </div>
    <div class="card">
        <p>Go to <a href="/workspace_pro/staff/queue.php"><strong>Queue</strong></a> to see and update resources that need attention.</p>
    </div>
</div>
</div>
</body>
</html>
