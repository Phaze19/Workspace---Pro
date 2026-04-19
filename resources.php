<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';
require_role('admin');

$success = '';
$error   = '';

// ── DELETE ───────────────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // Check if resource has active bookings
    $active = mysqli_fetch_row(mysqli_query($conn,
        "SELECT COUNT(*) FROM bookings WHERE resource_id=$id AND status='active'"))[0];
    if ($active > 0) {
        $error = 'Cannot delete — this resource has active bookings. Cancel those bookings first.';
    } else {
        mysqli_query($conn, "DELETE FROM resources WHERE id=$id");
        $success = 'Resource deleted.';
    }
}

// ── CREATE ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $name     = trim($_POST['name'] ?? '');
    $type     = $_POST['type'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $capacity = intval($_POST['capacity'] ?? 1);
    $status   = $_POST['status'] ?? 'available';

    if (!$name || !$type || !$location || $capacity < 1) {
        $error = 'Please fill in all fields correctly.';
    } else {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO resources (name, type, location, capacity, status)
             VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sssis', $name, $type, $location, $capacity, $status);
        if (mysqli_stmt_execute($stmt)) {
            $success = "Resource \"$name\" added successfully.";
        } else {
            $error = 'Something went wrong. Please try again.';
        }
    }
}

// ── UPDATE ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id       = intval($_POST['id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $type     = $_POST['type'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $capacity = intval($_POST['capacity'] ?? 1);
    $status   = $_POST['status'] ?? 'available';

    if (!$id || !$name || !$type || !$location || $capacity < 1) {
        $error = 'Please fill in all fields correctly.';
    } else {
        $stmt = mysqli_prepare($conn,
            "UPDATE resources SET name=?, type=?, location=?, capacity=?, status=?
             WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'sssisi', $name, $type, $location, $capacity, $status, $id);
        if (mysqli_stmt_execute($stmt)) {
            $success = "Resource updated successfully.";
        } else {
            $error = 'Something went wrong. Please try again.';
        }
    }
}

// ── READ — fetch all resources ───────────────────────────────────────────────
$resources = mysqli_query($conn,
    "SELECT * FROM resources ORDER BY type, name");

// If editing, fetch that one resource
$editing = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $editing = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM resources WHERE id=$edit_id"));
}

$active = 'resources';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Manage Resources – WorkSpace Pro</title>
    <link rel="stylesheet" href="/workspace_pro/assets/style.css">
</head>
<body>
<div class="page-wrap">
<?php include '../partials/navbar.php'; ?>
<div class="main-content">

    <div class="page-header">
        <h1>Manage Resources</h1>
        <p>Add, edit, or delete desks and meeting rooms.</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- ── ADD / EDIT FORM ─────────────────────────────────────────────── -->
    <div class="card" style="margin-bottom:28px;">
        <div class="card-title">
            <?= $editing ? '✏️ Edit Resource' : '➕ Add New Resource' ?>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
            <?php if ($editing): ?>
                <input type="hidden" name="id" value="<?= $editing['id'] ?>">
            <?php endif; ?>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label>Resource Name</label>
                    <input type="text" name="name"
                           value="<?= htmlspecialchars($editing['name'] ?? '') ?>"
                           placeholder="e.g. Desk A1 or Conference Room 1" required>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" required>
                        <option value="">-- Select Type --</option>
                        <option value="desk"
                            <?= ($editing['type'] ?? '') === 'desk' ? 'selected' : '' ?>>
                            Desk
                        </option>
                        <option value="meeting_room"
                            <?= ($editing['type'] ?? '') === 'meeting_room' ? 'selected' : '' ?>>
                            Meeting Room
                        </option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location"
                           value="<?= htmlspecialchars($editing['location'] ?? '') ?>"
                           placeholder="e.g. Floor 1 – Zone A" required>
                </div>
                <div class="form-group">
                    <label>Capacity</label>
                    <input type="number" name="capacity" min="1" max="100"
                           value="<?= $editing['capacity'] ?? 1 ?>" required>
                    <div class="form-hint">Use 1 for desks. For meeting rooms enter actual capacity.</div>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="available"
                            <?= ($editing['status'] ?? 'available') === 'available' ? 'selected' : '' ?>>
                            Available
                        </option>
                        <option value="dirty"
                            <?= ($editing['status'] ?? '') === 'dirty' ? 'selected' : '' ?>>
                            Dirty
                        </option>
                        <option value="out_of_service"
                            <?= ($editing['status'] ?? '') === 'out_of_service' ? 'selected' : '' ?>>
                            Out of Service
                        </option>
                    </select>
                </div>
            </div>

            <div style="display:flex; gap:10px; margin-top:4px;">
                <button type="submit" class="btn btn-primary">
                    <?= $editing ? 'Save Changes' : 'Add Resource' ?>
                </button>
                <?php if ($editing): ?>
                    <a href="/workspace_pro/admin/resources.php" class="btn btn-ghost">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ── RESOURCES TABLE ────────────────────────────────────────────── -->
    <div class="section-header">
        <h2>All Resources (<?= mysqli_num_rows($resources) ?>)</h2>
    </div>

    <?php if (mysqli_num_rows($resources) === 0): ?>
        <div class="card">
            <div class="empty-state"><p>No resources added yet. Use the form above to add one.</p></div>
        </div>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Type</th>
                <th>Location</th>
                <th>Capacity</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($r = mysqli_fetch_assoc($resources)): ?>
        <tr>
            <td class="text-muted"><?= $r['id'] ?></td>
            <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
            <td>
                <span class="badge badge-<?= $r['type'] === 'desk' ? 'desk' : 'room' ?>">
                    <?= $r['type'] === 'desk' ? 'Desk' : 'Room' ?>
                </span>
            </td>
            <td><?= htmlspecialchars($r['location']) ?></td>
            <td><?= $r['capacity'] ?></td>
            <td>
                <?php
                $badge = $r['status'] === 'available' ? 'available' :
                        ($r['status'] === 'dirty' ? 'dirty' : 'out');
                $label = $r['status'] === 'available' ? 'Available' :
                        ($r['status'] === 'dirty' ? 'Dirty' : 'Out of Service');
                ?>
                <span class="badge badge-<?= $badge ?>"><?= $label ?></span>
            </td>
            <td>
                <div style="display:flex; gap:6px;">
                    <a href="?edit=<?= $r['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                    <a href="?delete=<?= $r['id'] ?>"
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('Delete <?= htmlspecialchars(addslashes($r['name'])) ?>? This cannot be undone.')">
                        Delete
                    </a>
                </div>
            </td>
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
    // Auto scroll to form when editing
    window.onload = () => document.querySelector('.card').scrollIntoView({ behavior: 'smooth' });
</script>
<?php endif; ?>

</body>
</html>
