<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';
require_role('staff');

$success = '';
$error   = '';
$uid     = $_SESSION['user_id'];

// ── DELETE a log entry ────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $log_id = intval($_GET['delete']);

    // Only allow deleting your own logs
    $check = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id FROM maintenance_logs WHERE id=$log_id AND staff_id=$uid"));

    if ($check) {
        mysqli_query($conn, "DELETE FROM maintenance_logs WHERE id=$log_id");
        $success = 'Log entry deleted.';
    } else {
        $error = 'You can only delete your own log entries.';
    }
}

// ── READ — fetch this staff member's logs ─────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$where  = "WHERE ml.staff_id = $uid";

if ($search) {
    $s = mysqli_real_escape_string($conn, $search);
    $where .= " AND (r.name LIKE '%$s%' OR ml.action LIKE '%$s%')";
}

$logs = mysqli_query($conn,
    "SELECT ml.*, r.name AS rname, r.type, r.location
     FROM maintenance_logs ml
     JOIN resources r ON ml.resource_id = r.id
     $where
     ORDER BY ml.logged_at DESC");

$total_logs = mysqli_fetch_row(mysqli_query($conn,
    "SELECT COUNT(*) FROM maintenance_logs WHERE staff_id=$uid"))[0];

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
        <p>A record of every status update you have made.</p>
    </div>

    <!-- Stat -->
    <div class="stats-row" style="margin-bottom:24px;">
        <div class="stat-card">
            <div class="stat-label">Total Actions Logged</div>
            <div class="stat-value"><?= $total_logs ?></div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Search -->
    <form method="GET" style="display:flex; gap:10px; margin-bottom:20px;">
        <input type="text" name="search" placeholder="Search by resource name or action..."
               value="<?= htmlspecialchars($search) ?>"
               style="flex:1; padding:9px 13px; border:1px solid var(--border);
                      border-radius:8px; font-size:14px; font-family:inherit; outline:none;">
        <button type="submit" class="btn btn-primary">Search</button>
        <a href="/workspace_pro/staff/logs.php" class="btn btn-ghost">Clear</a>
    </form>

    <!-- Logs table -->
    <?php if (mysqli_num_rows($logs) === 0): ?>
        <div class="card">
            <div class="empty-state">
                <p>No logs found.</p>
                <?php if (!$search): ?>
                    <p style="margin-top:8px; font-size:13px;">
                        Go to <a href="/workspace_pro/staff/queue.php">Queue</a>
                        and update a resource status to create your first log.
                    </p>
                <?php endif; ?>
            </div>
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
                <th>Delete</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($log = mysqli_fetch_assoc($logs)): ?>
        <tr>
            <td class="text-muted"><?= $log['id'] ?></td>
            <td><strong><?= htmlspecialchars($log['rname']) ?></strong></td>
            <td>
                <span class="badge badge-<?= $log['type']==='desk'?'desk':'room' ?>">
                    <?= $log['type']==='desk'?'Desk':'Room' ?>
                </span>
            </td>
            <td class="text-muted"><?= htmlspecialchars($log['location']) ?></td>
            <td><?= htmlspecialchars($log['action']) ?></td>
            <td class="text-muted">
                <?= date('d M Y, h:i A', strtotime($log['logged_at'])) ?>
            </td>
            <td>
                <a href="?delete=<?= $log['id'] ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Delete this log entry? This cannot be undone.')">
                    Delete
                </a>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

</div>
</div>
</body>
</html>
