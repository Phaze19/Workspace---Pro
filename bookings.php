<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';
require_role('admin');

$success = '';
$error   = '';

// Cancel any booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    $cancel_id = intval($_POST['cancel_id']);
    $check = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id FROM bookings WHERE id=$cancel_id AND status='active'"));
    if ($check) {
        mysqli_query($conn, "UPDATE bookings SET status='cancelled' WHERE id=$cancel_id");
        $success = 'Booking cancelled.';
    } else {
        $error = 'Booking not found or already cancelled.';
    }
}

// Filters
$filter      = $_GET['filter'] ?? 'active';
$search_user = trim($_GET['user'] ?? '');

$where = "WHERE 1=1";
if ($filter === 'active')    $where .= " AND b.status = 'active'";
if ($filter === 'cancelled') $where .= " AND b.status = 'cancelled'";
if ($search_user) {
    $s = mysqli_real_escape_string($conn, $search_user);
    $where .= " AND u.name LIKE '%$s%'";
}

$bookings = mysqli_query($conn,
    "SELECT b.*, r.name AS rname, r.type, r.location, u.name AS uname, u.email
     FROM bookings b
     JOIN resources r ON b.resource_id = r.id
     JOIN users u     ON b.user_id = r.id
     WHERE 1=1
     ORDER BY b.date DESC, b.start_time DESC");

// Redo with correct join
$bookings = mysqli_query($conn,
    "SELECT b.*, r.name AS rname, r.type, r.location, u.name AS uname, u.email
     FROM bookings b
     JOIN resources r ON b.resource_id = r.id
     JOIN users u     ON b.user_id = u.id
     $where
     ORDER BY b.date DESC, b.start_time DESC");

$active = 'bookings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>All Bookings – WorkSpace Pro</title>
    <link rel="stylesheet" href="/workspace_pro/assets/style.css">
</head>
<body>
<div class="page-wrap">
<?php include '../partials/navbar.php'; ?>
<div class="main-content">

    <div class="page-header">
        <h1>All Bookings</h1>
        <p>View and manage every booking across the organization.</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="GET" style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; align-items:center;">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <input type="text" name="user" placeholder="Search by employee name..."
               value="<?= htmlspecialchars($search_user) ?>"
               style="flex:1; min-width:200px; padding:9px 13px; border:1px solid var(--border); border-radius:8px; font-size:14px; font-family:inherit;">
        <button type="submit" class="btn btn-primary">Search</button>
        <a href="?filter=<?= $filter ?>" class="btn btn-ghost">Clear</a>
    </form>

    <!-- Status tabs -->
    <div style="display:flex; gap:8px; margin-bottom:20px;">
        <a href="?filter=active&user=<?= urlencode($search_user) ?>"    class="btn <?= $filter==='active'?'btn-primary':'btn-ghost' ?> btn-sm">Active</a>
        <a href="?filter=cancelled&user=<?= urlencode($search_user) ?>" class="btn <?= $filter==='cancelled'?'btn-primary':'btn-ghost' ?> btn-sm">Cancelled</a>
        <a href="?filter=all&user=<?= urlencode($search_user) ?>"       class="btn <?= $filter==='all'?'btn-primary':'btn-ghost' ?> btn-sm">All</a>
    </div>

    <?php if (mysqli_num_rows($bookings) === 0): ?>
        <div class="card">
            <div class="empty-state"><p>No bookings found.</p></div>
        </div>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Employee</th>
                <th>Resource</th>
                <th>Type</th>
                <th>Date</th>
                <th>Time</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($b = mysqli_fetch_assoc($bookings)): ?>
        <tr>
            <td class="text-muted"><?= $b['id'] ?></td>
            <td>
                <strong><?= htmlspecialchars($b['uname']) ?></strong><br>
                <span class="text-muted"><?= htmlspecialchars($b['email']) ?></span>
            </td>
            <td><?= htmlspecialchars($b['rname']) ?><br>
                <span class="text-muted"><?= htmlspecialchars($b['location']) ?></span></td>
            <td><span class="badge badge-<?= $b['type']==='desk'?'desk':'room' ?>"><?= $b['type']==='desk'?'Desk':'Room' ?></span></td>
            <td><?= $b['date'] ?></td>
            <td><?= substr($b['start_time'],0,5) ?> – <?= substr($b['end_time'],0,5) ?></td>
            <td><span class="badge badge-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
            <td>
                <?php if ($b['status'] === 'active'): ?>
                <form method="POST" onsubmit="return confirm('Cancel this booking?')">
                    <input type="hidden" name="cancel_id" value="<?= $b['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
                </form>
                <?php else: ?>
                <span class="text-muted">—</span>
                <?php endif; ?>
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
