<?php
require_once __DIR__ . '/includes/auth.php';
$admin = requireAdminLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && inStr($_POST, 'form_action', 30) === 'adjust_energy') {
    verifyCsrf();
    $username = inStr($_POST, 'username', 60);
    $amount   = inInt($_POST, 'amount', 0, 100000, 0);
    $type     = inStr($_POST, 'type', 10);
    $reason   = inStr($_POST, 'reason', 100);
    if ($reason === '') {
        $reason = 'Manual admin adjustment';
    }

    $stmt = $db->prepare("SELECT id, username FROM users WHERE LOWER(username) = LOWER(?)");
    $stmt->execute([$username]);
    $target = $stmt->fetch();

    if (!$target) {
        flash('No user with that username.', 'error');
    } elseif ($amount <= 0) {
        flash('Enter an amount greater than zero.', 'error');
    } elseif (!in_array($type, ['use', 'gain'], true)) {
        flash('Choose whether to add or deduct energy.', 'error');
    } else {
        $signed = ($type === 'gain') ? $amount : -$amount;
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE users SET current_energy = CASE WHEN current_energy + ? < 0 THEN 0 WHEN current_energy + ? > max_energy THEN max_energy ELSE current_energy + ? END WHERE id = ?")
               ->execute([$signed, $signed, $signed, (int)$target['id']]);
            $db->prepare("INSERT INTO energy_logs (user_id, change_amount, reason) VALUES (?, ?, ?)")
               ->execute([(int)$target['id'], $signed, "[Admin] {$reason}"]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        logAudit((int)$admin['id'], 'adjust_energy', "{$signed} to {$target['username']} ({$reason})");
        flash("Energy adjusted for {$target['username']}.", 'success');
    }
    header('Location: energy_logs.php');
    exit;
}

$query = inStr($_GET, 'q', 60);
$from  = " FROM energy_logs e JOIN users u ON u.id = e.user_id";
$where = " WHERE 1=1";
$params = [];
if ($query !== '') {
    $where   .= " AND (u.username LIKE ? OR e.reason LIKE ?)";
    $params[] = '%' . $query . '%';
    $params[] = '%' . $query . '%';
}

$perPage = 25;
$page    = pageNumber();
$cnt = $db->prepare("SELECT COUNT(*) AS c" . $from . $where);
$cnt->execute($params);
$total  = (int)$cnt->fetch()['c'];
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("SELECT e.id, e.change_amount, e.reason, e.created_at, u.id AS user_id, u.username"
    . $from . $where . " ORDER BY e.id DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$pageTitle = 'Energy Logs';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>Monitor Energy Logs</h1>
    <p class="muted">Every energy event across all users, plus manual adjustments (recorded with an [Admin] prefix).</p>
</div>

<div class="two-col">
    <section class="panel">
        <h2><svg class="icon"><use href="#i-activity"/></svg> Manual adjustment</h2>
        <form method="post" class="stack">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="adjust_energy">
            <label>Username
                <input type="text" name="username" required maxlength="60" autocomplete="off" autocapitalize="none" spellcheck="false" value="<?= h($query) ?>">
            </label>
            <div class="field-row">
                <label>Action
                    <select name="type">
                        <option value="use">Deduct energy</option>
                        <option value="gain">Add energy</option>
                    </select>
                </label>
                <label>Amount<input type="number" name="amount" min="1" max="100000" required></label>
            </div>
            <label>Reason (optional)<input type="text" name="reason" maxlength="100" placeholder="e.g. Correcting a mistake"></label>
            <button type="submit" class="btn btn-primary">Apply</button>
        </form>
        <p class="hint">Energy never goes below 0 or above the user's maximum.</p>
    </section>

    <section class="panel">
        <h2>Entries (<?= $total ?>)</h2>
        <form method="get" class="filter-form">
            <input type="search" name="q" placeholder="Search username or reason..." value="<?= h($query) ?>" aria-label="Search energy logs">
            <button type="submit" class="btn btn-secondary">Search</button>
        </form>
        <ul class="log-list">
            <?php foreach ($logs as $l): ?>
            <li>
                <span class="<?= $l['change_amount'] >= 0 ? 'gain' : 'loss' ?>"><?= $l['change_amount'] >= 0 ? '+' : '' ?><?= (int)$l['change_amount'] ?></span>
                <span><a href="user.php?id=<?= (int)$l['user_id'] ?>"><strong><?= h($l['username']) ?></strong></a> &mdash; <?= h($l['reason']) ?></span>
                <span class="muted small"><?= h($l['created_at']) ?></span>
            </li>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?><li class="muted">No entries match.</li><?php endif; ?>
        </ul>
        <?= paginationNav($total, $page, $perPage) ?>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
