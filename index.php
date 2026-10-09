<?php
require_once __DIR__ . '/includes/auth.php';
$user = requireLogin();
$db = getDB();

$equipped = $db->prepare("
    SELECT g.* FROM inventory i
    JOIN gear g ON g.id = i.gear_id
    WHERE i.user_id = ? AND i.equipped = 1
    ORDER BY g.category
");
$equipped->execute([$user['id']]);
$equippedGear = $equipped->fetchAll();

$totalDrain = array_sum(array_column($equippedGear, 'energy_cost'));

$invCount = $db->prepare("SELECT COUNT(*) c FROM inventory WHERE user_id = ?");
$invCount->execute([$user['id']]);
$ownedCount = $invCount->fetch()['c'];

$recentLogs = $db->prepare("SELECT * FROM energy_logs WHERE user_id = ? ORDER BY id DESC LIMIT 5");
$recentLogs->execute([$user['id']]);
$logs = $recentLogs->fetchAll();

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="hero-banner">
    <h1>Welcome back, <?= h($user['username']) ?> <span class="role-badge role-<?= h($user['role']) ?>"><?= h(ucfirst($user['role'])) ?></span></h1>
    <p class="muted">Suit tech and dampeners keep your abilities in check — and on budget. Here's where you stand.</p>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <span class="stat-icon"><svg class="icon"><use href="#i-battery"/></svg></span>
        <div class="stat-label">Bio-Energy</div>
        <div class="stat-value"><?= (int)$user['current_energy'] ?> / <?= (int)$user['max_energy'] ?></div>
        <div class="bar"><div class="bar-fill" style="width: <?= min(100, round($user['current_energy'] / max(1,$user['max_energy']) * 100)) ?>%"></div></div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><svg class="icon"><use href="#i-coins"/></svg></span>
        <div class="stat-label">Credits</div>
        <div class="stat-value"><?= (int)$user['credits'] ?></div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><svg class="icon"><use href="#i-package"/></svg></span>
        <div class="stat-label">Gear Owned</div>
        <div class="stat-value"><?= (int)$ownedCount ?></div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><svg class="icon"><use href="#i-bolt"/></svg></span>
        <div class="stat-label">Loadout Drain</div>
        <div class="stat-value"><?= (int)$totalDrain ?> / equip</div>
    </div>
</div>

<div class="two-col">
    <section class="panel">
        <h2><svg class="icon"><use href="#i-sliders"/></svg> Current Loadout</h2>
        <?php if (empty($equippedGear)): ?>
            <p class="muted">Nothing equipped yet. Head to the <a href="customizer.php">Customizer</a> to build a loadout.</p>
        <?php else: ?>
            <ul class="gear-list">
                <?php foreach ($equippedGear as $g): ?>
                <li>
                    <span class="gear-name"><?= h($g['name']) ?></span>
                    <span class="tag tag-<?= h($g['category']) ?>"><?= h($g['category']) ?></span>
                    <span class="gear-cost">-<?= (int)$g['energy_cost'] ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <a class="btn btn-secondary" href="customizer.php">Edit Loadout</a>
    </section>

    <section class="panel">
        <h2><svg class="icon"><use href="#i-activity"/></svg> Recent Energy Activity</h2>
        <?php if (empty($logs)): ?>
            <p class="muted">No activity logged yet.</p>
        <?php else: ?>
            <ul class="log-list">
                <?php foreach ($logs as $l): ?>
                <li>
                    <span class="<?= $l['change_amount'] >= 0 ? 'gain' : 'loss' ?>">
                        <?= $l['change_amount'] >= 0 ? '+' : '' ?><?= (int)$l['change_amount'] ?>
                    </span>
                    <span><?= h($l['reason']) ?></span>
                    <span class="muted small"><?= h($l['created_at']) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <a class="btn btn-secondary" href="tracker.php">Open Tracker</a>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
