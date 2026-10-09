<?php
// Always ask the session who is logged in (never trust a page-level variable).
$admin = currentAdmin();
$currentPage = basename($_SERVER['PHP_SELF']);

// Navigation items (label, file, icon). Same destinations as before.
$navItems = [
    ['Dashboard',   'index.php',       'i-dashboard'],
    ['Users',       'users.php',       'i-users'],
    ['Gear',        'gear.php',        'i-wrench'],
    ['Inventories', 'inventories.php', 'i-package'],
    ['Energy Logs', 'energy_logs.php', 'i-activity'],
    ['Audit Log',   'audit_log.php',   'i-scroll'],
    ['Player App',  '../',             'i-bolt'],
];
// The user-detail page belongs to the Users section.
$activePage = ($currentPage === 'user.php') ? 'users.php' : $currentPage;
$initial = ($admin && preg_match('/./u', $admin['username'], $m)) ? strtoupper($m[0]) : '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#070b14">
<?php if ($admin): ?><meta name="csrf-token" content="<?= h(csrfToken()) ?>"><?php endif; ?>
<title>VoltTech Admin<?= isset($pageTitle) ? ' · ' . h($pageTitle) : '' ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect width='24' height='24' rx='6' fill='%23facc15'/%3E%3Cpath fill='%23111827' d='M13.2 3 5.5 13.4h5.3L10 21l8-10.6h-5.4z'/%3E%3C/svg%3E">
<script src="../js/theme-init.js"></script>
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="../css/admin.css">
</head>
<body>
<?php require __DIR__ . '/../../includes/icons.php'; ?>
<?php if ($admin): ?>
<div class="app">
    <input type="checkbox" id="nav-toggle" class="nav-toggle-checkbox sr-only" aria-label="Toggle navigation menu">
    <label for="nav-toggle" class="scrim" aria-hidden="true"></label>

    <aside class="sidebar">
        <a href="index.php" class="brand" aria-label="VoltTech Admin home">
            <span class="brand-mark"><svg class="icon"><use href="#i-bolt"/></svg></span>
            <span class="brand-name">Volt<span>Tech</span></span>
            <span class="brand-sub">Admin</span>
        </a>
        <nav class="nav" aria-label="Main">
            <div class="nav-label">Management</div>
            <?php foreach ($navItems as [$label, $file, $icon]): ?>
            <a href="<?= h($file) ?>" class="<?= $activePage === $file ? 'active' : '' ?>"<?= $activePage === $file ? ' aria-current="page"' : '' ?>>
                <svg class="icon"><use href="#<?= $icon ?>"/></svg><?= $label ?>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-foot">
            <span class="avatar" aria-hidden="true"><?= h($initial) ?></span>
            <span class="who">
                <span class="username"><?= h($admin['username']) ?></span>
                <span class="role">Administrator</span>
            </span>
            <form method="post" action="logout.php" class="logout-form"><?= csrfField() ?><button type="submit" class="logout-link" title="Log out" aria-label="Log out"><svg class="icon"><use href="#i-logout"/></svg></button></form>
        </div>
    </aside>

    <div class="content">
        <header class="topbar">
            <label for="nav-toggle" class="icon-btn nav-toggle-btn" aria-label="Open menu"><svg class="icon"><use href="#i-menu"/></svg></label>
            <div class="topbar-title"><?= isset($pageTitle) ? h($pageTitle) : 'Admin' ?></div>
            <div class="user-chip">
                <button type="button" class="icon-btn theme-toggle" data-theme-toggle aria-label="Switch light/dark theme" title="Switch light/dark theme">
                    <svg class="icon i-moon"><use href="#i-moon"/></svg><svg class="icon i-sun"><use href="#i-sun"/></svg>
                </button>
            </div>
        </header>
        <main class="page" id="main">
<?php else: ?>
<div class="auth-shell">
    <aside class="auth-hero">
        <a href="login.php" class="brand" aria-label="VoltTech Admin">
            <span class="brand-mark"><svg class="icon"><use href="#i-bolt"/></svg></span>
            <span class="brand-name">Volt<span>Tech</span></span>
            <span class="brand-sub">Admin</span>
        </a>
        <div>
            <h2>Admin <em>console</em></h2>
            <p>Manage users, the gear catalog, inventories and energy logs. Every action is recorded in the audit log.</p>
        </div>
        <ul class="auth-features">
            <li><span class="icon-tile"><svg class="icon"><use href="#i-users"/></svg></span>Users, roles, credits and energy</li>
            <li><span class="icon-tile"><svg class="icon"><use href="#i-wrench"/></svg></span>Gear catalog management</li>
            <li><span class="icon-tile"><svg class="icon"><use href="#i-scroll"/></svg></span>Searchable audit trail</li>
        </ul>
    </aside>
    <div class="auth-main">
        <button type="button" class="icon-btn theme-toggle theme-corner" data-theme-toggle aria-label="Switch light/dark theme" title="Switch light/dark theme">
            <svg class="icon i-moon"><use href="#i-moon"/></svg><svg class="icon i-sun"><use href="#i-sun"/></svg>
        </button>
        <main class="page page-auth" id="main">
<?php endif; ?>
<?php foreach (getFlashes() as $f): ?>
    <div class="flash flash-<?= h($f['type']) ?>" role="status"><?= h($f['msg']) ?></div>
<?php endforeach; ?>
