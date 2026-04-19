<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';
require_role('staff');

$uid = $_SESSION['user_id'];

// Filter: all logs by this staff member
$filter = $_GET['filter'] ?? 'all';

if ($filter === 'today') {
    $where = "AND DATE(ml.logged_at) = CURDATE()";
} elseif ($filter === 'week') {
    $where = "AND ml.logged_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} else {
    $filter = 'all';
    $where  = "";
}

$logs = mysqli_query($conn,
    "SELECT ml.*, r.name AS rname, r.type, r.location
     FROM maintenance_logs ml
     JOIN resources r ON ml.resource_id = r.id
     WHERE ml.staff_id = $uid $where
     ORDER BY ml.logged_at DESC");

$total_logs = mysqli_fetch_row(mysqli_query($conn,
    "SELECT COUNT(*) FROM maintenance_logs WHERE staff_id = $uid"))[0];

$today_logs = mysqli_fetch_row(mysqli_query($conn,
    "SELECT COUNT(*) FROM maintenance_logs WHERE staff_id = $uid AND DATE(logged_at) = CURDATE()"))[0];

$active = 'logs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>My Logs – WorkSpace Pro</title>
    <link rel="stylesheet" href="/workspace_pro/assets/style.css">
</head>
<body>
<div class="page-wrap">
<?php include '../partials/navbar.php'; ?>
<div class="main-content">

    <div class="page-header">
        <h1>My Maintenance Logs</h1>
        <p>A record of every action you have taken on resources.</p>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">Total Actions</div>
            <div class="stat-value"><?= $total_logs ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Today's Actions</div>
            <div class="stat-value"><?= $today_logs ?></div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="card">
        <div class="section-header">
            <h2>Log History</h2>
            <div class="filter-tabs">
                <a href="?filter=all"   class="btn btn-sm <?= $filter === 'all'   ? 'btn-primary' : 'btn-ghost' ?>">All Time</a>
                <a href="?filter=week"  class="btn btn-sm <?= $filter === 'week'  ? 'btn-primary' : 'btn-ghost' ?>">This Week</a>
                <a href="?filter=today" class="btn btn-sm <?= $filter === 'today' ? 'btn-primary' : 'btn-ghost' ?>">Today</a>
            </div>
        </div>

        <?php if (mysqli_num_rows($logs) === 0): ?>
            <div class="empty-state">
                <p>No maintenance actions found for this period.</p>
            </div>
        <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Resource</th>
                    <th>Type</th>
                    <th>Location</th>
                    <th>Action Taken</th>
                    <th>Date &amp; Time</th>
                </tr>
            </thead>
            <tbody>
            <?php $i = 1; while ($log = mysqli_fetch_assoc($logs)): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($log['rname']) ?></strong></td>
                    <td><?= ucfirst(str_replace('_', ' ', $log['type'])) ?></td>
                    <td><?= htmlspecialchars($log['location']) ?></td>
                    <td><?= htmlspecialchars($log['action']) ?></td>
                    <td><?= date('d M Y, h:i A', strtotime($log['logged_at'])) ?></td>
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


