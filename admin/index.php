<?php
require_once __DIR__ . '/includes/auth.php';
$admin = requireAdminLogin();
$db = getDB();

$userCount     = (int)$db->query("SELECT COUNT(*) AS c FROM users")->fetch()['c'];
$adminCount    = adminCount();
$gearCount     = (int)$db->query("SELECT COUNT(*) AS c FROM gear")->fetch()['c'];
$invCount      = (int)$db->query("SELECT COUNT(*) AS c FROM inventory")->fetch()['c'];
$equippedCount = (int)$db->query("SELECT COUNT(*) AS c FROM inventory WHERE equipped = 1")->fetch()['c'];
$logCount      = (int)$db->query("SELECT COUNT(*) AS c FROM energy_logs")->fetch()['c'];
$totalCredits  = (int)$db->query("SELECT COALESCE(SUM(credits), 0) AS s FROM users")->fetch()['s'];
$roleCounts    = $db->query("SELECT role, COUNT(*) AS c FROM users GROUP BY role ORDER BY role")->fetchAll();

$topGear = $db->query("
    SELECT g.name, g.category, COUNT(i.id) AS owners
    FROM gear g LEFT JOIN inventory i ON i.gear_id = g.id
    GROUP BY g.id, g.name, g.category
    ORDER BY owners DESC, g.name
    LIMIT 5
")->fetchAll();

$recentUsers = $db->query("SELECT id, username, role, created_at FROM users ORDER BY id DESC LIMIT 5")->fetchAll();

$recentAudit = $db->query("
    SELECT a.action, a.target, a.created_at, u.username
    FROM admin_audit_log a JOIN users u ON u.id = a.admin_id
    ORDER BY a.id DESC LIMIT 5
")->fetchAll();

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>Admin Dashboard</h1>
    <p class="muted">Live statistics from the shared database &mdash; the exact data the player app reads and writes.</p>
</div>

<div class="stat-grid">
    <div class="stat-card"><span class="stat-icon"><svg class="icon"><use href="#i-users"/></svg></span><div class="stat-label">Total Users</div><div class="stat-value"><?= $userCount ?></div></div>
    <div class="stat-card"><span class="stat-icon"><svg class="icon"><use href="#i-shield"/></svg></span><div class="stat-label">Administrators</div><div class="stat-value"><?= $adminCount ?></div></div>
    <div class="stat-card"><span class="stat-icon"><svg class="icon"><use href="#i-wrench"/></svg></span><div class="stat-label">Gear Items</div><div class="stat-value"><?= $gearCount ?></div></div>
    <div class="stat-card"><span class="stat-icon"><svg class="icon"><use href="#i-package"/></svg></span><div class="stat-label">Inventory Rows</div><div class="stat-value"><?= $invCount ?></div></div>
    <div class="stat-card"><span class="stat-icon"><svg class="icon"><use href="#i-bolt"/></svg></span><div class="stat-label">Currently Equipped</div><div class="stat-value"><?= $equippedCount ?></div></div>
    <div class="stat-card"><span class="stat-icon"><svg class="icon"><use href="#i-coins"/></svg></span><div class="stat-label">Credits in Economy</div><div class="stat-value"><?= $totalCredits ?></div></div>
    <div class="stat-card"><span class="stat-icon"><svg class="icon"><use href="#i-activity"/></svg></span><div class="stat-label">Energy Log Entries</div><div class="stat-value"><?= $logCount ?></div></div>
</div>

<div class="two-col">
    <section class="panel">
        <h2><svg class="icon"><use href="#i-users"/></svg> Users by Role</h2>
        <ul class="plain-list">
            <?php foreach ($roleCounts as $r): ?>
            <li><span class="tag tag-<?= h($r['role']) ?>"><?= h(ucfirst($r['role'])) ?></span> <strong><?= (int)$r['c'] ?></strong></li>
            <?php endforeach; ?>
        </ul>
        <a class="btn btn-secondary" href="users.php">Manage Users</a>
    </section>

    <section class="panel">
        <h2><svg class="icon"><use href="#i-wrench"/></svg> Most Owned Gear</h2>
        <?php if (empty($topGear)): ?>
            <p class="muted">The catalogue is empty.</p>
        <?php else: ?>
        <ul class="plain-list">
            <?php foreach ($topGear as $g): ?>
            <li><span><?= h($g['name']) ?> <span class="tag tag-<?= h($g['category']) ?>"><?= h($g['category']) ?></span></span> <strong><?= (int)$g['owners'] ?> owner<?= (int)$g['owners'] === 1 ? '' : 's' ?></strong></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <a class="btn btn-secondary" href="gear.php">Manage Gear</a>
    </section>
</div>

<div class="two-col section-gap">
    <section class="panel">
        <h2><svg class="icon"><use href="#i-users"/></svg> Newest Accounts</h2>
        <ul class="plain-list">
            <?php foreach ($recentUsers as $u): ?>
            <li><span><a href="user.php?id=<?= (int)$u['id'] ?>"><?= h($u['username']) ?></a> <span class="tag tag-<?= h($u['role']) ?>"><?= h($u['role']) ?></span></span> <span class="muted small"><?= h($u['created_at']) ?></span></li>
            <?php endforeach; ?>
        </ul>
        <a class="btn btn-secondary" href="users.php">All Users</a>
    </section>

    <section class="panel">
        <h2><svg class="icon"><use href="#i-scroll"/></svg> Recent Admin Activity</h2>
        <?php if (empty($recentAudit)): ?>
            <p class="muted">No admin actions logged yet.</p>
        <?php else: ?>
            <ul class="plain-list">
                <?php foreach ($recentAudit as $a): ?>
                <li>
                    <span><strong><?= h($a['username']) ?></strong> <span class="tag"><?= h($a['action']) ?></span> <?= h($a['target']) ?></span>
                    <span class="muted small"><?= h($a['created_at']) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <a class="btn btn-secondary" href="audit_log.php">Full Audit Log</a>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
