<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';
require_role('employee');

$success = '';
$error   = '';

// Handle booking form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resource_id = intval($_POST['resource_id'] ?? 0);
    $date        = $_POST['date'] ?? '';
    $start_time  = $_POST['start_time'] ?? '';
    $end_time    = $_POST['end_time'] ?? '';
    $uid         = $_SESSION['user_id'];

    if (!$resource_id || !$date || !$start_time || !$end_time) {
        $error = 'Please fill in all fields.';
    } elseif ($start_time >= $end_time) {
        $error = 'End time must be after start time.';
    } elseif ($date < date('Y-m-d')) {
        $error = 'You cannot book a date in the past.';
    } else {
        // Check resource is available
        $res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM resources WHERE id=$resource_id"));
        if ($res['status'] !== 'available') {
            $error = 'This resource is not available for booking right now.';
        } else {
            // Conflict check — does another active booking overlap?
            $conflict = mysqli_fetch_row(mysqli_query($conn,
                "SELECT COUNT(*) FROM bookings
                 WHERE resource_id = $resource_id
                   AND date = '$date'
                   AND status = 'active'
                   AND start_time < '$end_time'
                   AND end_time   > '$start_time'"
            ))[0];

            if ($conflict > 0) {
                $error = 'This resource is already booked for that time slot. Please choose a different time.';
            } else {
                $stmt = mysqli_prepare($conn,
                    "INSERT INTO bookings (user_id, resource_id, date, start_time, end_time, status)
                     VALUES (?, ?, ?, ?, ?, 'active')");
                mysqli_stmt_bind_param($stmt, 'iisss', $uid, $resource_id, $date, $start_time, $end_time);
                if (mysqli_stmt_execute($stmt)) {
                    $success = 'Booking confirmed!';
                } else {
                    $error = 'Something went wrong. Please try again.';
                }
            }
        }
    }
}

// Filters
$type_filter   = $_GET['type'] ?? '';
$search_filter = trim($_GET['search'] ?? '');

$where = "WHERE status = 'available'";
if ($type_filter === 'desk' || $type_filter === 'meeting_room') {
    $where .= " AND type = '" . mysqli_real_escape_string($conn, $type_filter) . "'";
}
if ($search_filter) {
    $s = mysqli_real_escape_string($conn, $search_filter);
    $where .= " AND (name LIKE '%$s%' OR location LIKE '%$s%')";
}

$resources = mysqli_query($conn, "SELECT * FROM resources $where ORDER BY type, name");

$active = 'resources';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Browse Resources – WorkSpace Pro</title>
    <link rel="stylesheet" href="/workspace_pro/assets/style.css">
</head>
<body>
<div class="page-wrap">
<?php include '../partials/navbar.php'; ?>
<div class="main-content">

    <div class="page-header">
        <h1>Browse Resources</h1>
        <p>Find and book an available desk or meeting room.</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Filter bar -->
    <form method="GET" style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
        <input type="text" name="search" placeholder="Search by name or location..."
               value="<?= htmlspecialchars($search_filter) ?>"
               style="flex:1; min-width:200px; padding:9px 13px; border:1px solid var(--border); border-radius:8px; font-size:14px; font-family:inherit;">
        <select name="type" style="padding:9px 13px; border:1px solid var(--border); border-radius:8px; font-size:14px; font-family:inherit;">
            <option value="">All Types</option>
            <option value="desk"         <?= $type_filter==='desk'?'selected':'' ?>>Desks Only</option>
            <option value="meeting_room" <?= $type_filter==='meeting_room'?'selected':'' ?>>Meeting Rooms Only</option>
        </select>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="/workspace_pro/employee/resources.php" class="btn btn-ghost">Clear</a>
    </form>

    <!-- Resource grid -->
    <?php if (mysqli_num_rows($resources) === 0): ?>
        <div class="empty-state">
            <p>No available resources match your search.</p>
        </div>
    <?php else: ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px;">
        <?php while ($r = mysqli_fetch_assoc($resources)): ?>
        <div class="card" style="padding:20px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                <div>
                    <div style="font-size:16px; font-weight:700;"><?= htmlspecialchars($r['name']) ?></div>
                    <div class="text-muted" style="margin-top:3px;"><?= htmlspecialchars($r['location']) ?></div>
                </div>
                <span class="badge badge-<?= $r['type']==='desk'?'desk':'room' ?>">
                    <?= $r['type']==='desk'?'Desk':'Room' ?>
                </span>
            </div>
            <div style="font-size:13px; color:var(--muted); margin-bottom:16px;">
                Capacity: <strong><?= $r['capacity'] ?></strong> &nbsp;|&nbsp;
                <span class="badge badge-available">Available</span>
            </div>
            <button class="btn btn-primary btn-sm" style="width:100%; justify-content:center;"
                onclick="openBooking(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['name'])) ?>')">
                Book This
            </button>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>

</div>
</div>

<!-- Booking Modal -->
<div class="modal-overlay" id="bookingModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeBooking()">&times;</button>
        <div class="modal-title">Book: <span id="modalResourceName"></span></div>
        <form method="POST">
            <input type="hidden" name="resource_id" id="modalResourceId">
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="date" id="bookingDate"
                       min="<?= date('Y-m-d') ?>" required>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label>Start Time</label>
                    <input type="time" name="start_time" required>
                </div>
                <div class="form-group">
                    <label>End Time</label>
                    <input type="time" name="end_time" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">
                Confirm Booking
            </button>
        </form>
    </div>
</div>

<script>
function openBooking(id, name) {
    document.getElementById('modalResourceId').value = id;
    document.getElementById('modalResourceName').textContent = name;
    document.getElementById('bookingModal').classList.add('open');
}
function closeBooking() {
    document.getElementById('bookingModal').classList.remove('open');
}
// Close on outside click
document.getElementById('bookingModal').addEventListener('click', function(e) {
    if (e.target === this) closeBooking();
});
</script>
</body>
</html>
