<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';
require_role('employee');

$uid = $_SESSION['user_id'];

// Stats
$total_bookings  = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM bookings WHERE user_id=$uid AND status='active'"))[0];
$upcoming = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM bookings WHERE user_id=$uid AND status='active' AND date >= CURDATE()"))[0];

// Recent bookings
$recent = mysqli_query($conn,
    "SELECT b.*, r.name AS rname, r.type, r.location
     FROM bookings b JOIN resources r ON b.resource_id = r.id
     WHERE b.user_id = $uid ORDER BY b.date DESC, b.start_time DESC LIMIT 5");

$active = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dashboard – WorkSpace Pro</title>
    <link rel="stylesheet" href="/workspace_pro/assets/style.css">
</head>
<body>
<div class="page-wrap">
<?php include '../partials/navbar.php'; ?>
<div class="main-content">
    <div class="page-header">
        <h1>Welcome back, <?= htmlspecialchars($_SESSION['name']) ?> </h1>
        <p>Here's a summary of your workspace activity.</p>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">Active Bookings</div>
            <div class="stat-value"><?= $total_bookings ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Upcoming</div>
            <div class="stat-value"><?= $upcoming ?></div>
            <div class="stat-sub">From today onward</div>
        </div>
    </div>

    <div class="card">
        <div class="section-header">
            <h2>Recent Bookings</h2>
            <a href="/workspace_pro/employee/bookings.php" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <?php if (mysqli_num_rows($recent) === 0): ?>
            <div class="empty-state">
                <p>No bookings yet. <a href="/workspace_pro/employee/resources.php">Browse resources</a> to make one.</p>
            </div>
        <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Resource</th><th>Type</th><th>Date</th><th>Time</th><th>Status</th></tr></thead>
            <tbody>
            <?php while ($b = mysqli_fetch_assoc($recent)): ?>
            <tr>
                <td><?= htmlspecialchars($b['rname']) ?><br><span class="text-muted"><?= htmlspecialchars($b['location']) ?></span></td>
                <td><span class="badge badge-<?= $b['type'] === 'desk' ? 'desk' : 'room' ?>"><?= $b['type'] === 'desk' ? 'Desk' : 'Room' ?></span></td>
                <td><?= $b['date'] ?></td>
                <td><?= substr($b['start_time'],0,5) ?> – <?= substr($b['end_time'],0,5) ?></td>
                <td><span class="badge badge-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>
</body>
</html>
