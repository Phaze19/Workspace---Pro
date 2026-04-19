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

    // Prevent admin from deleting themselves
    if ($id === intval($_SESSION['user_id'])) {
        $error = 'You cannot delete your own account.';
    } else {
        mysqli_query($conn, "DELETE FROM users WHERE id=$id");
        $success = 'User deleted successfully.';
    }
}

// ── CREATE ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';

    if (!$name || !$email || !$password || !$role) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check email not already taken
        $check = mysqli_fetch_row(mysqli_query($conn,
            "SELECT COUNT(*) FROM users WHERE email='".mysqli_real_escape_string($conn,$email)."'"))[0];
        if ($check > 0) {
            $error = 'A user with that email already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn,
                "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ssss', $name, $email, $hashed, $role);
            if (mysqli_stmt_execute($stmt)) {
                $success = "User \"$name\" created successfully.";
            } else {
                $error = 'Something went wrong. Please try again.';
            }
        }
    }
}

// ── UPDATE ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id       = intval($_POST['id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!$id || !$name || !$email || !$role) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check email not taken by another user
        $check = mysqli_fetch_row(mysqli_query($conn,
            "SELECT COUNT(*) FROM users WHERE email='".mysqli_real_escape_string($conn,$email)."' AND id != $id"))[0];
        if ($check > 0) {
            $error = 'That email is already used by another account.';
        } else {
            if ($password) {
                // Update with new password
                if (strlen($password) < 6) {
                    $error = 'New password must be at least 6 characters.';
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = mysqli_prepare($conn,
                        "UPDATE users SET name=?, email=?, role=?, password=? WHERE id=?");
                    mysqli_stmt_bind_param($stmt, 'ssssi', $name, $email, $role, $hashed, $id);
                    if (mysqli_stmt_execute($stmt)) {
                        $success = 'User updated successfully (password changed).';
                    } else {
                        $error = 'Something went wrong.';
                    }
                }
            } else {
                // Update without changing password
                $stmt = mysqli_prepare($conn,
                    "UPDATE users SET name=?, email=?, role=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'sssi', $name, $email, $role, $id);
                if (mysqli_stmt_execute($stmt)) {
                    $success = 'User updated successfully.';
                } else {
                    $error = 'Something went wrong.';
                }
            }
        }
    }
}

// ── READ ─────────────────────────────────────────────────────────────────────
$filter_role = $_GET['role'] ?? '';
$search      = trim($_GET['search'] ?? '');

$where = "WHERE 1=1";
if (in_array($filter_role, ['employee', 'staff', 'admin'])) {
    $where .= " AND role = '$filter_role'";
}
if ($search) {
    $s = mysqli_real_escape_string($conn, $search);
    $where .= " AND (name LIKE '%$s%' OR email LIKE '%$s%')";
}

$users = mysqli_query($conn,
    "SELECT * FROM users $where ORDER BY role, name");

// Count per role for stats
$count_emp   = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE role='employee'"))[0];
$count_staff = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE role='staff'"))[0];
$count_admin = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE role='admin'"))[0];

// Fetch user being edited
$editing = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $editing = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM users WHERE id=$edit_id"));
}

$active = 'users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Manage Users – WorkSpace Pro</title>
    <link rel="stylesheet" href="/workspace_pro/assets/style.css">
</head>
<body>
<div class="page-wrap">
<?php include '../partials/navbar.php'; ?>
<div class="main-content">

    <div class="page-header">
        <h1>Manage Users</h1>
        <p>Add, edit, change roles, or delete user accounts.</p>
    </div>

    <!-- Stats -->
    <div class="stats-row" style="margin-bottom:24px;">
        <div class="stat-card">
            <div class="stat-label">Employees</div>
            <div class="stat-value"><?= $count_emp ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Staff</div>
            <div class="stat-value"><?= $count_staff ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Admins</div>
            <div class="stat-value"><?= $count_admin ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Users</div>
            <div class="stat-value"><?= $count_emp + $count_staff + $count_admin ?></div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- ── ADD / EDIT FORM ──────────────────────────────────────────────── -->
    <div class="card" style="margin-bottom:28px;" id="userForm">
        <div class="card-title">
            <?= $editing ? '✏️ Edit User' : '➕ Add New User' ?>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
            <?php if ($editing): ?>
                <input type="hidden" name="id" value="<?= $editing['id'] ?>">
            <?php endif; ?>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name"
                           value="<?= htmlspecialchars($editing['name'] ?? '') ?>"
                           placeholder="e.g. Anurag Sharma" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email"
                           value="<?= htmlspecialchars($editing['email'] ?? '') ?>"
                           placeholder="user@example.com" required>
                </div>
                <div class="form-group">
                    <label>
                        <?= $editing ? 'New Password' : 'Password' ?>
                    </label>
                    <input type="password" name="password"
                           placeholder="<?= $editing ? 'Leave blank to keep current password' : 'Min. 6 characters' ?>"
                           <?= $editing ? '' : 'required' ?>>
                    <?php if ($editing): ?>
                        <div class="form-hint">Leave blank if you don't want to change the password.</div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" required>
                        <option value="">-- Select Role --</option>
                        <option value="employee"
                            <?= ($editing['role'] ?? '') === 'employee' ? 'selected' : '' ?>>
                            Employee
                        </option>
                        <option value="staff"
                            <?= ($editing['role'] ?? '') === 'staff' ? 'selected' : '' ?>>
                            Maintenance Staff
                        </option>
                        <option value="admin"
                            <?= ($editing['role'] ?? '') === 'admin' ? 'selected' : '' ?>>
                            Admin
                        </option>
                    </select>
                </div>
            </div>

            <div style="display:flex; gap:10px; margin-top:4px;">
                <button type="submit" class="btn btn-primary">
                    <?= $editing ? 'Save Changes' : 'Create User' ?>
                </button>
                <?php if ($editing): ?>
                    <a href="/workspace_pro/admin/users.php" class="btn btn-ghost">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ── FILTERS ──────────────────────────────────────────────────────── -->
    <form method="GET" style="display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap; align-items:center;">
        <input type="text" name="search" placeholder="Search by name or email..."
               value="<?= htmlspecialchars($search) ?>"
               style="flex:1; min-width:200px; padding:9px 13px; border:1px solid var(--border); border-radius:8px; font-size:14px; font-family:inherit; outline:none;">
        <select name="role" style="padding:9px 13px; border:1px solid var(--border); border-radius:8px; font-size:14px; font-family:inherit;">
            <option value="">All Roles</option>
            <option value="employee" <?= $filter_role==='employee'?'selected':'' ?>>Employees</option>
            <option value="staff"    <?= $filter_role==='staff'?'selected':'' ?>>Staff</option>
            <option value="admin"    <?= $filter_role==='admin'?'selected':'' ?>>Admins</option>
        </select>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="/workspace_pro/admin/users.php" class="btn btn-ghost">Clear</a>
    </form>

    <!-- ── USERS TABLE ──────────────────────────────────────────────────── -->
    <div class="section-header">
        <h2>All Users (<?= mysqli_num_rows($users) ?>)</h2>
    </div>

    <?php if (mysqli_num_rows($users) === 0): ?>
        <div class="card">
            <div class="empty-state"><p>No users found matching your search.</p></div>
        </div>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($u = mysqli_fetch_assoc($users)): ?>
        <tr>
            <td class="text-muted"><?= $u['id'] ?></td>
            <td>
                <strong><?= htmlspecialchars($u['name']) ?></strong>
                <?php if ($u['id'] == $_SESSION['user_id']): ?>
                    <span style="font-size:11px; color:var(--muted);"> (you)</span>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td>
                <span class="badge badge-<?= $u['role'] ?>">
                    <?= ucfirst($u['role']) ?>
                </span>
            </td>
            <td class="text-muted"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
            <td>
                <div style="display:flex; gap:6px;">
                    <a href="?edit=<?= $u['id'] ?>#userForm" class="btn btn-warning btn-sm">Edit</a>
                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                    <a href="?delete=<?= $u['id'] ?>"
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('Delete <?= htmlspecialchars(addslashes($u['name'])) ?>? This also removes all their bookings.')">
                        Delete
                    </a>
                    <?php else: ?>
                    <span class="btn btn-ghost btn-sm" style="opacity:0.4; cursor:not-allowed;">Delete</span>
                    <?php endif; ?>
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
    window.onload = () => document.getElementById('userForm').scrollIntoView({ behavior: 'smooth' });
</script>
<?php endif; ?>

</body>
</html>
