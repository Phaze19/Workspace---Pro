<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';
require_role('employee');

$uid     = $_SESSION['user_id'];
$success = '';
$error   = '';

// ── CANCEL ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    $cancel_id = intval($_POST['cancel_id']);

    $check = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id FROM bookings WHERE id=$cancel_id AND user_id=$uid AND status='active'"));

    if ($check) {
        mysqli_query($conn, "UPDATE bookings SET status='cancelled' WHERE id=$cancel_id");
        $success = 'Booking cancelled successfully.';
    } else {
        $error = 'Could not cancel that booking.';
    }
}

// ── UPDATE (edit) ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $edit_id   = intval($_POST['edit_id']);
    $new_date  = $_POST['date'] ?? '';
    $new_start = $_POST['start_time'] ?? '';
    $new_end   = $_POST['end_time'] ?? '';

    // Make sure this booking belongs to this user and is still active
    $booking = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM bookings WHERE id=$edit_id AND user_id=$uid AND status='active'"));

    if (!$booking) {
        $error = 'Booking not found.';
    } elseif (!$new_date || !$new_start || !$new_end) {
        $error = 'Please fill in all fields.';
    } elseif ($new_start >= $new_end) {
        $error = 'End time must be after start time.';
    } elseif ($new_date < date('Y-m-d')) {
        $error = 'Cannot change booking to a past date.';
    } else {
        $resource_id = $booking['resource_id'];

        // Conflict check — exclude the current booking itself
        $conflict = mysqli_fetch_row(mysqli_query($conn,
            "SELECT COUNT(*) FROM bookings
             WHERE resource_id = $resource_id
               AND date = '$new_date'
               AND status = 'active'
               AND id != $edit_id
               AND start_time < '$new_end'
               AND end_time   > '$new_start'"
        ))[0];

        if ($conflict > 0) {
            $error = 'That time slot is already booked. Please choose a different time.';
        } else {
            $stmt = mysqli_prepare($conn,
                "UPDATE bookings SET date=?, start_time=?, end_time=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'sssi', $new_date, $new_start, $new_end, $edit_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = 'Booking updated successfully.';
            } else {
                $error = 'Something went wrong. Please try again.';
            }
        }
    }
}

// ── READ ──────────────────────────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'upcoming';

if ($filter === 'past') {
    $where = "AND b.date < CURDATE()";
} elseif ($filter === 'cancelled') {
    $where = "AND b.status = 'cancelled'";
} else {
    $filter = 'upcoming';
    $where  = "AND b.date >= CURDATE() AND b.status = 'active'";
}

$bookings = mysqli_query($conn,
    "SELECT b.*, r.name AS rname, r.type, r.location
     FROM bookings b
     JOIN resources r ON b.resource_id = r.id
     WHERE b.user_id = $uid $where
     ORDER BY b.date ASC, b.start_time ASC");

// Pre-load booking being edited
$editing = null;
if (isset($_GET['edit'])) {
    $eid     = intval($_GET['edit']);
    $editing = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT b.*, r.name AS rname FROM bookings b
         JOIN resources r ON b.resource_id = r.id
         WHERE b.id=$eid AND b.user_id=$uid AND b.status='active'"));
    if (!$editing) $error = 'Booking not found or already cancelled.';
}

$active = 'bookings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>My Bookings – WorkSpace Pro</title>
    <link rel="stylesheet" href="/workspace_pro/assets/style.css">
</head>
<body>
<div class="page-wrap">
<?php include '../partials/navbar.php'; ?>
<div class="main-content">

    <div class="page-header">
        <h1>My Bookings</h1>
        <p>View, edit, or cancel your desk and room reservations.</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- ── EDIT FORM — only shows when ?edit=ID is in the URL ───────────── -->
    <?php if ($editing): ?>
    <div class="card" style="margin-bottom:28px; border-left:4px solid var(--accent);" id="editForm">
        <div class="card-title">✏️ Editing Booking — <?= htmlspecialchars($editing['rname']) ?></div>
        <p class="text-muted" style="margin-bottom:16px; font-size:13px;">
            You can change the date or time slot. The resource stays the same.
            To book a different resource, cancel this booking and make a new one.
        </p>
        <form method="POST">
            <input type="hidden" name="edit_id" value="<?= $editing['id'] ?>">
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="date"
                           value="<?= $editing['date'] ?>"
                           min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Start Time</label>
                    <input type="time" name="start_time"
                           value="<?= substr($editing['start_time'], 0, 5) ?>" required>
                </div>
                <div class="form-group">
                    <label>End Time</label>
                    <input type="time" name="end_time"
                           value="<?= substr($editing['end_time'], 0, 5) ?>" required>
                </div>
            </div>
            <div style="display:flex; gap:10px; margin-top:4px;">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="/workspace_pro/employee/bookings.php" class="btn btn-ghost">Cancel Edit</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- ── FILTER TABS ───────────────────────────────────────────────────── -->
    <div style="display:flex; gap:8px; margin-bottom:20px;">
        <a href="?filter=upcoming"  class="btn <?= $filter==='upcoming'  ? 'btn-primary':'btn-ghost' ?> btn-sm">Upcoming</a>
        <a href="?filter=past"      class="btn <?= $filter==='past'      ? 'btn-primary':'btn-ghost' ?> btn-sm">Past</a>
        <a href="?filter=cancelled" class="btn <?= $filter==='cancelled' ? 'btn-primary':'btn-ghost' ?> btn-sm">Cancelled</a>
    </div>

    <!-- ── BOOKINGS TABLE ────────────────────────────────────────────────── -->
    <?php if (mysqli_num_rows($bookings) === 0): ?>
        <div class="card">
            <div class="empty-state">
                <p>No <?= $filter ?> bookings found.</p>
                <?php if ($filter === 'upcoming'): ?>
                    <a href="/workspace_pro/employee/resources.php"
                       class="btn btn-primary btn-sm" style="margin-top:12px;">
                        Browse Resources
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Resource</th>
                <th>Type</th>
                <th>Location</th>
                <th>Date</th>
                <th>Time</th>
                <th>Status</th>
                <?php if ($filter === 'upcoming'): ?><th>Actions</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php while ($b = mysqli_fetch_assoc($bookings)): ?>
        <tr>
            <td><strong><?= htmlspecialchars($b['rname']) ?></strong></td>
            <td>
                <span class="badge badge-<?= $b['type']==='desk' ? 'desk':'room' ?>">
                    <?= $b['type']==='desk' ? 'Desk':'Room' ?>
                </span>
            </td>
            <td class="text-muted"><?= htmlspecialchars($b['location']) ?></td>
            <td><?= date('d M Y', strtotime($b['date'])) ?></td>
            <td><?= substr($b['start_time'],0,5) ?> – <?= substr($b['end_time'],0,5) ?></td>
            <td>
                <span class="badge badge-<?= $b['status'] ?>">
                    <?= ucfirst($b['status']) ?>
                </span>
            </td>
            <?php if ($filter === 'upcoming'): ?>
            <td>
                <div style="display:flex; gap:6px;">
                    <a href="?edit=<?= $b['id'] ?>&filter=upcoming#editForm"
                       class="btn btn-warning btn-sm">Edit</a>
                    <form method="POST" onsubmit="return confirm('Cancel this booking?')">
                        <input type="hidden" name="cancel_id" value="<?= $b['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
                    </form>
                </div>
            </td>
            <?php endif; ?>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

</div>
</div>

<?php if ($editing): ?>
<script>
    window.onload = () => document.getElementById('editForm')
        .scrollIntoView({ behavior: 'smooth' });
</script>
<?php endif; ?>

</body>
</html>
