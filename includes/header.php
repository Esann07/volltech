<?php
/** @var array|null $user */
// Always ask the session who is logged in. (Never reuse a page-level $user: on a failed login that
// variable can hold the row of the account whose password was guessed, which must never be shown.)
$user = currentUser();
$currentPage = basename($_SERVER['PHP_SELF']);

// Navigation items (label, file, icon). Same destinations as before.
$navItems = [
    ['Dashboard',      'index.php',       'i-dashboard'],
    ['Marketplace',    'marketplace.php', 'i-store'],
    ['Customizer',     'customizer.php',  'i-sliders'],
    ['Energy Tracker', 'tracker.php',     'i-activity'],
];
$navItems[] = ['Account', 'account.php', 'i-user'];
if ($user && $user['role'] === 'admin') {
    $navItems[] = ['Admin Console', 'admin/', 'i-shield'];
}
$initial = ($user && preg_match('/./u', $user['username'], $m)) ? strtoupper($m[0]) : '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#070b14">
<?php if ($user): ?><meta name="csrf-token" content="<?= h(csrfToken()) ?>"><?php endif; ?>
<title>VoltTech<?= isset($pageTitle) ? ' · ' . h($pageTitle) : '' ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect width='24' height='24' rx='6' fill='%232563eb'/%3E%3Cpath fill='%23facc15' d='M13.2 3 5.5 13.4h5.3L10 21l8-10.6h-5.4z'/%3E%3C/svg%3E">
<script src="js/theme-init.js"></script>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php require __DIR__ . '/icons.php'; ?>
<?php if ($user): ?>
<div class="app">
    <input type="checkbox" id="nav-toggle" class="nav-toggle-checkbox sr-only" aria-label="Toggle navigation menu">
    <label for="nav-toggle" class="scrim" aria-hidden="true"></label>

    <aside class="sidebar">
        <a href="index.php" class="brand" aria-label="VoltTech home">
            <span class="brand-mark"><svg class="icon"><use href="#i-bolt"/></svg></span>
            <span class="brand-name">Volt<span>Tech</span></span>
        </a>
        <nav class="nav" aria-label="Main">
            <div class="nav-label">Menu</div>
            <?php foreach ($navItems as [$label, $file, $icon]): ?>
            <a href="<?= h($file) ?>" class="<?= $currentPage === $file ? 'active' : '' ?>"<?= $currentPage === $file ? ' aria-current="page"' : '' ?>>
                <svg class="icon"><use href="#<?= $icon ?>"/></svg><?= $label ?>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-foot">
            <span class="avatar" aria-hidden="true"><?= h($initial) ?></span>
            <span class="who">
                <span class="username"><?= h($user['username']) ?></span>
                <span class="role"><?= h($user['role']) ?></span>
            </span>
            <form method="post" action="logout.php" class="logout-form"><?= csrfField() ?><button type="submit" class="logout-link" title="Log out" aria-label="Log out"><svg class="icon"><use href="#i-logout"/></svg></button></form>
        </div>
    </aside>

    <div class="content">
        <header class="topbar">
            <label for="nav-toggle" class="icon-btn nav-toggle-btn" aria-label="Open menu"><svg class="icon"><use href="#i-menu"/></svg></label>
            <div class="topbar-title"><?= isset($pageTitle) ? h($pageTitle) : 'VoltTech' ?></div>
            <div class="user-chip">
                <div class="energy-pill" title="Bio-Energy">
                    <svg class="icon"><use href="#i-battery"/></svg><?= (int)$user['current_energy'] ?>/<?= (int)$user['max_energy'] ?> <span class="pill-unit">EN</span>
                </div>
                <div class="credit-pill" title="Credits">
                    <svg class="icon"><use href="#i-coins"/></svg><?= (int)$user['credits'] ?> <span class="pill-unit">CR</span>
                </div>
                <button type="button" class="icon-btn theme-toggle" data-theme-toggle aria-label="Switch light/dark theme" title="Switch light/dark theme">
                    <svg class="icon i-moon"><use href="#i-moon"/></svg><svg class="icon i-sun"><use href="#i-sun"/></svg>
                </button>
            </div>
        </header>
        <main class="page" id="main">
<?php else: ?>
<div class="auth-shell">
    <aside class="auth-hero">
        <a href="login.php" class="brand" aria-label="VoltTech">
            <span class="brand-mark"><svg class="icon"><use href="#i-bolt"/></svg></span>
            <span class="brand-name">Volt<span>Tech</span></span>
        </a>
        <div>
            <h2>Gear up. <em>Power</em> through.</h2>
            <p>The gear marketplace, loadout customizer and bio-energy tracker for heroes, villains and civilians alike.</p>
        </div>
        <ul class="auth-features">
            <li><span class="icon-tile"><svg class="icon"><use href="#i-store"/></svg></span>Marketplace for suits, dampeners and gadgets</li>
            <li><span class="icon-tile"><svg class="icon"><use href="#i-sliders"/></svg></span>Loadout customizer with an energy budget</li>
            <li><span class="icon-tile"><svg class="icon"><use href="#i-activity"/></svg></span>Bio-energy tracker with full history</li>
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
