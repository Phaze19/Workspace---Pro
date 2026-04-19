<?php
// partials/navbar.php
// Usage: include this AFTER session_start() and require_login()
// Pass $active = 'dashboard' (or bookings / resources / maintenance / users) before including

$role = $_SESSION['role'] ?? '';
$name = $_SESSION['name'] ?? 'User';

$links = [];
if ($role === 'employee') {
    $links = [
        'dashboard' => ['label' => 'Dashboard', 'href' => '/workspace_pro/employee/dashboard.php'],
        'bookings'  => ['label' => 'My Bookings', 'href' => '/workspace_pro/employee/bookings.php'],
        'resources' => ['label' => 'Browse Resources', 'href' => '/workspace_pro/employee/resources.php'],
    ];
} elseif ($role === 'staff') {
    $links = [
        'dashboard'   => ['label' => 'Dashboard',  'href' => '/workspace_pro/staff/dashboard.php'],
        'maintenance' => ['label' => 'Queue',       'href' => '/workspace_pro/staff/queue.php'],
        'logs'        => ['label' => 'My Logs',     'href' => '/workspace_pro/staff/logs.php'],
    ];
} elseif ($role === 'admin') {
    $links = [
        'dashboard' => ['label' => 'Dashboard', 'href' => '/workspace_pro/admin/dashboard.php'],
        'resources' => ['label' => 'Resources',  'href' => '/workspace_pro/admin/resources.php'],
        'bookings'  => ['label' => 'Bookings',   'href' => '/workspace_pro/admin/bookings.php'],
        'users'     => ['label' => 'Users',      'href' => '/workspace_pro/admin/users.php'],
    ];
}

$role_label = ucfirst($role);
?>
<nav class="navbar">
    <span class="navbar-brand">WorkSpace Pro <span><?= htmlspecialchars($role_label) ?></span></span>
    <div class="navbar-links">
        <?php foreach ($links as $key => $link): ?>
            <a href="<?= $link['href'] ?>" class="<?= ($active ?? '') === $key ? 'active' : '' ?>">
                <?= $link['label'] ?>
            </a>
        <?php endforeach; ?>
        <a href="/workspace_pro/auth/logout.php" class="navbar-logout">Logout</a>
    </div>
</nav>
