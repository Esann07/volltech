<?php
require_once __DIR__ . '/includes/auth.php';
$admin = requireAdminLogin();
$db = getDB();

$userId = inInt($_GET, 'id', 0, 2147483647);
$load = $db->prepare("SELECT * FROM users WHERE id = ?");
$load->execute([$userId]);
$target = $load->fetch();
if (!$target) {
    flash('That user does not exist (it may have been deleted).', 'error');
    header('Location: users.php');
    exit;
}
$isSelf = ((int)$target['id'] === (int)$admin['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = inStr($_POST, 'form_action', 30);
    $back = 'user.php?id=' . $userId;

    if ($action === 'set_role') {
        $role = inStr($_POST, 'role', 20);
        if ($isSelf) {
            flash('You cannot change your own role. Ask another admin to do it.', 'error');
        } elseif (!in_array($role, VT_ROLES_ALL, true)) {
            flash('Invalid role.', 'error');
        } elseif ($role === $target['role']) {
            flash('Role unchanged.', 'info');
        } else {
            $db->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $userId]);
            logAudit((int)$admin['id'], 'set_role', "user #{$userId} ({$target['username']}): {$target['role']} -> {$role}");
            flash("{$target['username']} is now a {$role}.", 'success');
        }
    }

    elseif ($action === 'set_stats') {
        // Only fields the admin actually CHANGED are written. Otherwise saving a form that
        // was loaded a minute ago would overwrite credits the player has earned/spent since.
        $newCredits  = inInt($_POST, 'credits', 0, 2000000000, (int)$target['credits']);
        $newMax      = inInt($_POST, 'max_energy', 1, 1000000, (int)$target['max_energy']);
        $origCredits = inInt($_POST, 'orig_credits', 0, 2000000000, -1);
        $origMax     = inInt($_POST, 'orig_max_energy', 1, 1000000, -1);

        $sets = [];
        $vals = [];
        $notes = [];
        if ($newCredits !== $origCredits) {
            $sets[] = 'credits = ?';
            $vals[] = $newCredits;
            $notes[] = "credits {$target['credits']} -> {$newCredits}";
        }
        if ($newMax !== $origMax) {
            $sets[] = 'max_energy = ?';
            $vals[] = $newMax;
            $sets[] = 'current_energy = CASE WHEN current_energy > ? THEN ? ELSE current_energy END';
            $vals[] = $newMax;
            $vals[] = $newMax;
            $notes[] = "max_energy {$target['max_energy']} -> {$newMax}";
        }
        if (empty($sets)) {
            flash('No changes to save.', 'info');
        } else {
            $vals[] = $userId;
            $db->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE id = ?")->execute($vals);
            logAudit((int)$admin['id'], 'update_user', "user #{$userId} ({$target['username']}): " . implode(', ', $notes));
            flash('Saved.', 'success');
        }
    }

    elseif ($action === 'reset_password') {
        $pw = (isset($_POST['new_password']) && is_string($_POST['new_password'])) ? $_POST['new_password'] : '';
        if (($problem = passwordProblem($pw)) !== '') {
            flash($problem, 'error');
        } else {
            $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
               ->execute([password_hash($pw, PASSWORD_DEFAULT), $userId]);
            logAudit((int)$admin['id'], 'reset_password', "user #{$userId} ({$target['username']})");
            flash("Password reset for {$target['username']}. Tell them the new password privately.", 'success');
        }
    }

    elseif ($action === 'remove_item') {
        $invId = inInt($_POST, 'inventory_id', 0, 2147483647);
        $stmt = $db->prepare("
            SELECT i.id, g.name FROM inventory i JOIN gear g ON g.id = i.gear_id
            WHERE i.id = ? AND i.user_id = ?
        ");
        $stmt->execute([$invId, $userId]);
        $row = $stmt->fetch();
        if (!$row) {
            flash('That item is no longer in this inventory.', 'error');
        } else {
            $db->prepare("DELETE FROM inventory WHERE id = ? AND user_id = ?")->execute([$invId, $userId]);
            logAudit((int)$admin['id'], 'remove_inventory_item', "{$row['name']} from {$target['username']}");
            flash("Removed {$row['name']}.", 'success');
        }
    }

    elseif ($action === 'delete_user') {
        if ($isSelf) {
            flash("You can't delete the account you are logged in with.", 'error');
        } else {
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
            logAudit((int)$admin['id'], 'delete_user', "user #{$userId} ({$target['username']})");
            flash("Deleted {$target['username']} and their inventory and energy history.", 'success');
            $back = 'users.php';
        }
    }

    header('Location: ' . $back);
    exit;
}

$invStmt = $db->prepare("
    SELECT i.id AS inventory_id, i.equipped, i.acquired_at, g.name, g.category, g.energy_cost
    FROM inventory i JOIN gear g ON g.id = i.gear_id
    WHERE i.user_id = ?
    ORDER BY g.category, g.name
");
$invStmt->execute([$userId]);
$inventory = $invStmt->fetchAll();

$logStmt = $db->prepare("SELECT * FROM energy_logs WHERE user_id = ? ORDER BY id DESC LIMIT 25");
$logStmt->execute([$userId]);
$logs = $logStmt->fetchAll();

$drain = 0;
foreach ($inventory as $row) {
    if ($row['equipped']) {
        $drain += (int)$row['energy_cost'];
    }
}

$pageTitle = 'User: ' . $target['username'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1><?= h($target['username']) ?> <span class="tag tag-<?= h($target['role']) ?>"><?= h($target['role']) ?></span><?= $isSelf ? ' <span class="muted small">(you)</span>' : '' ?></h1>
    <p class="muted"><a href="users.php">&larr; All users</a></p>
</div>

<div class="two-col">
    <section class="panel">
        <h2><svg class="icon"><use href="#i-users"/></svg> Profile</h2>
        <dl class="detail-list">
            <div><dt>User ID</dt><dd>#<?= (int)$target['id'] ?></dd></div>
            <div><dt>Credits</dt><dd><?= (int)$target['credits'] ?></dd></div>
            <div><dt>Bio-energy</dt><dd><?= (int)$target['current_energy'] ?> / <?= (int)$target['max_energy'] ?></dd></div>
            <div><dt>Equipped drain</dt><dd><?= (int)$drain ?> / <?= (int)$target['max_energy'] ?></dd></div>
            <div><dt>Gear owned</dt><dd><?= count($inventory) ?></dd></div>
            <div><dt>Joined</dt><dd><?= h($target['created_at']) ?></dd></div>
        </dl>
    </section>

    <section class="panel">
        <h2><svg class="icon"><use href="#i-sliders"/></svg> Credits &amp; energy cap</h2>
        <form method="post" class="stack">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="set_stats">
            <input type="hidden" name="orig_credits" value="<?= (int)$target['credits'] ?>">
            <input type="hidden" name="orig_max_energy" value="<?= (int)$target['max_energy'] ?>">
            <div class="field-row">
                <label>Credits
                    <input type="number" name="credits" min="0" max="2000000000" required value="<?= (int)$target['credits'] ?>">
                </label>
                <label>Max bio-energy
                    <input type="number" name="max_energy" min="1" max="1000000" required value="<?= (int)$target['max_energy'] ?>">
                </label>
            </div>
            <button type="submit" class="btn btn-primary">Save changes</button>
        </form>
        <p class="hint">Only the fields you change are saved, so a player's live balance is never overwritten by an old form. To add or remove current energy use <a href="energy_logs.php?q=<?= h(urlencode($target['username'])) ?>">Energy Logs</a>.</p>
    </section>
</div>

<div class="two-col section-gap">
    <section class="panel">
        <h2><svg class="icon"><use href="#i-shield"/></svg> Role</h2>
        <form method="post" class="stack">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="set_role">
            <label>Role
                <select name="role" <?= $isSelf ? 'disabled' : '' ?>>
                    <?php foreach (VT_ROLES_ALL as $r): ?>
                        <option value="<?= h($r) ?>" <?= $target['role'] === $r ? 'selected' : '' ?>><?= h(ucfirst($r)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="btn btn-primary" <?= $isSelf ? 'disabled' : '' ?>>Change role</button>
        </form>
        <?php if ($isSelf): ?><p class="hint">You cannot change your own role, so the system can never be left without an admin.</p><?php endif; ?>
    </section>

    <section class="panel">
        <h2><svg class="icon"><use href="#i-shield"/></svg> Reset password</h2>
        <form method="post" class="stack" autocomplete="off">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="reset_password">
            <label>New password <span class="muted small">(at least 8 characters)</span>
                <input type="password" name="new_password" required minlength="8" maxlength="72" autocomplete="new-password">
            </label>
            <button type="submit" class="btn btn-primary" >Set new password</button>
        </form>
        <p class="hint">Use this when someone forgets their password. Existing logins stay valid until they sign out or time out.</p>
    </section>
</div>

<section class="panel section-gap">
    <h2><svg class="icon"><use href="#i-package"/></svg> Inventory (<?= count($inventory) ?>)</h2>
    <?php if (empty($inventory)): ?>
        <p class="muted">This user does not own any gear.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="admin-table table-stack">
        <thead><tr><th>Gear</th><th>Category</th><th>Drain</th><th>Equipped</th><th>Acquired</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($inventory as $r): ?>
            <tr>
                <td data-label="Gear"><?= h($r['name']) ?></td>
                <td data-label="Category"><span class="tag tag-<?= h($r['category']) ?>"><?= h($r['category']) ?></span></td>
                <td data-label="Drain"><?= (int)$r['energy_cost'] ?></td>
                <td data-label="Equipped"><?= $r['equipped'] ? 'Yes' : 'No' ?></td>
                <td data-label="Acquired" class="muted small"><?= h($r['acquired_at']) ?></td>
                <td data-label="Action">
                    <form method="post" data-confirm="Remove <?= h($r['name']) ?> from <?= h($target['username']) ?>'s inventory? No refund is given.">
                        <?= csrfField() ?>
                        <input type="hidden" name="form_action" value="remove_item">
                        <input type="hidden" name="inventory_id" value="<?= (int)$r['inventory_id'] ?>">
                        <button type="submit" class="btn btn-sell">Remove</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>

<section class="panel section-gap">
    <h2><svg class="icon"><use href="#i-activity"/></svg> Recent energy history</h2>
    <?php if (empty($logs)): ?>
        <p class="muted">No energy activity yet.</p>
    <?php else: ?>
        <ul class="log-list scroll">
            <?php foreach ($logs as $l): ?>
            <li>
                <span class="<?= $l['change_amount'] >= 0 ? 'gain' : 'loss' ?>"><?= $l['change_amount'] >= 0 ? '+' : '' ?><?= (int)$l['change_amount'] ?></span>
                <span><?= h($l['reason']) ?></span>
                <span class="muted small"><?= h($l['created_at']) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="panel section-gap">
    <h2>Delete account</h2>
    <p class="muted">Permanently removes <?= h($target['username']) ?> together with their inventory and energy history. This cannot be undone.</p>
    <form method="post" data-confirm="Permanently delete <?= h($target['username']) ?> and ALL of their data? This cannot be undone.">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="delete_user">
        <button type="submit" class="btn btn-danger" <?= $isSelf ? 'disabled' : '' ?>>Delete this account</button>
    </form>
    <?php if ($isSelf): ?><p class="hint">You cannot delete the account you are logged in with.</p><?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
