<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';
require_role('staff');

$success = '';
$error   = '';
$uid     = $_SESSION['user_id'];

// ── UPDATE resource status + log the action ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resource_id'], $_POST['new_status'])) {
    $resource_id = intval($_POST['resource_id']);
    $new_status  = $_POST['new_status'];
    $note        = trim($_POST['note'] ?? '');

    $allowed = ['available', 'dirty', 'out_of_service'];
    if (!in_array($new_status, $allowed)) {
        $error = 'Invalid status selected.';
    } else {
        // Update the resource status
        mysqli_query($conn,
            "UPDATE resources SET status='$new_status' WHERE id=$resource_id");

        // Build action label
        $status_labels = [
            'available'     => 'Marked as Available',
            'dirty'         => 'Marked as Dirty',
            'out_of_service'=> 'Marked as Out of Service',
        ];
        $action = $status_labels[$new_status];
        if ($note) $action .= ' — ' . $note;

        // Log the action in maintenance_logs
        $stmt = mysqli_prepare($conn,
            "INSERT INTO maintenance_logs (resource_id, staff_id, action)
             VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'iis', $resource_id, $uid, $action);
        mysqli_stmt_execute($stmt);

        $success = 'Resource status updated and action logged.';
    }
}

// ── READ — fetch queue (dirty + out_of_service) ──────────────────────────────
$queue = mysqli_query($conn,
    "SELECT * FROM resources
     WHERE status IN ('dirty', 'out_of_service')
     ORDER BY status DESC, name ASC");

$queue_count = mysqli_num_rows($queue);

// Also fetch available resources so staff can mark them dirty/OOS if needed
$available = mysqli_query($conn,
    "SELECT * FROM resources WHERE status = 'available' ORDER BY name ASC");

$active = 'maintenance';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Maintenance Queue – WorkSpace Pro</title>
    <link rel="stylesheet" href="/workspace_pro/assets/style.css">
</head>
<body>
<div class="page-wrap">
<?php include '../partials/navbar.php'; ?>
<div class="main-content">

    <div class="page-header">
        <h1>Maintenance Queue</h1>
        <p>Resources that need your attention right now.</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- ── QUEUE ────────────────────────────────────────────────────────── -->
    <div class="section-header" style="margin-bottom:16px;">
        <h2>
            Needs Attention
            <?php if ($queue_count > 0): ?>
                <span style="background:var(--danger); color:#fff; font-size:12px;
                             padding:2px 8px; border-radius:20px; margin-left:8px;">
                    <?= $queue_count ?>
                </span>
            <?php endif; ?>
        </h2>
    </div>

    <?php if ($queue_count === 0): ?>
        <div class="card" style="margin-bottom:28px;">
            <div class="empty-state">
                <p>🎉 All clear! No resources need attention right now.</p>
            </div>
        </div>
    <?php else: ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:16px; margin-bottom:32px;">
        <?php while ($r = mysqli_fetch_assoc($queue)): ?>
        <div class="card" style="padding:20px; border-left: 4px solid <?= $r['status']==='dirty' ? 'var(--warning)' : 'var(--danger)' ?>;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                <div>
                    <div style="font-size:16px; font-weight:700;"><?= htmlspecialchars($r['name']) ?></div>
                    <div class="text-muted" style="margin-top:3px; font-size:13px;">
                        <?= htmlspecialchars($r['location']) ?>
                    </div>
                </div>
                <span class="badge badge-<?= $r['status'] === 'dirty' ? 'dirty' : 'out' ?>">
                    <?= $r['status'] === 'dirty' ? 'Dirty' : 'Out of Service' ?>
                </span>
            </div>

            <div style="font-size:13px; color:var(--muted); margin-bottom:14px;">
                Type: <strong><?= $r['type'] === 'desk' ? 'Desk' : 'Meeting Room' ?></strong>
                &nbsp;|&nbsp; Capacity: <strong><?= $r['capacity'] ?></strong>
            </div>

            <!-- Update status form -->
            <form method="POST">
                <input type="hidden" name="resource_id" value="<?= $r['id'] ?>">
                <div class="form-group" style="margin-bottom:10px;">
                    <label style="font-size:12px;">Add a note (optional)</label>
                    <input type="text" name="note" placeholder="e.g. Cleaned and sanitized"
                           style="padding:7px 10px; font-size:13px;">
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <?php if ($r['status'] !== 'available'): ?>
                    <button type="submit" name="new_status" value="available"
                            class="btn btn-success btn-sm">
                        ✓ Mark Available
                    </button>
                    <?php endif; ?>
                    <?php if ($r['status'] !== 'out_of_service'): ?>
                    <button type="submit" name="new_status" value="out_of_service"
                            class="btn btn-danger btn-sm">
                        Mark Out of Service
                    </button>
                    <?php endif; ?>
                    <?php if ($r['status'] !== 'dirty'): ?>
                    <button type="submit" name="new_status" value="dirty"
                            class="btn btn-warning btn-sm">
                        Mark Dirty
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>

    <!-- ── AVAILABLE RESOURCES ──────────────────────────────────────────── -->
    <?php if (mysqli_num_rows($available) > 0): ?>
    <div class="card">
        <div class="card-title" style="margin-bottom:4px;">All Available Resources</div>
        <p class="text-muted" style="font-size:13px; margin-bottom:16px;">
            Use this if you need to flag a currently available resource.
        </p>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Location</th>
                    <th>Update Status</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($r = mysqli_fetch_assoc($available)): ?>
            <tr>
                <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                <td><span class="badge badge-<?= $r['type']==='desk'?'desk':'room' ?>">
                    <?= $r['type']==='desk'?'Desk':'Room' ?>
                </span></td>
                <td><?= htmlspecialchars($r['location']) ?></td>
                <td>
                    <form method="POST" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                        <input type="hidden" name="resource_id" value="<?= $r['id'] ?>">
                        <input type="text" name="note" placeholder="Add note..."
                               style="padding:5px 9px; font-size:12px; border:1px solid var(--border);
                                      border-radius:6px; width:140px; font-family:inherit;">
                        <button type="submit" name="new_status" value="dirty"
                                class="btn btn-warning btn-sm">Dirty</button>
                        <button type="submit" name="new_status" value="out_of_service"
                                class="btn btn-danger btn-sm">Out of Service</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

</div>
</div>
</body>
</html>
