<?php
require_once __DIR__ . '/includes/auth.php';
$user = requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $current = (isset($_POST['current_password']) && is_string($_POST['current_password'])) ? $_POST['current_password'] : '';
    $new     = (isset($_POST['new_password']) && is_string($_POST['new_password'])) ? $_POST['new_password'] : '';
    $confirm = (isset($_POST['confirm_password']) && is_string($_POST['confirm_password'])) ? $_POST['confirm_password'] : '';

    if (throttleBlocked('pwchange', 6, 900)) {
        flash('Too many attempts. Please wait about 15 minutes and try again.', 'error');
    } elseif (!password_verify($current, (string)$user['password_hash'])) {
        throttleHit('pwchange');
        flash('Your current password is not correct.', 'error');
    } elseif (($problem = passwordProblem($new)) !== '') {
        flash($problem, 'error');
    } elseif ($new !== $confirm) {
        flash('The two new passwords do not match.', 'error');
    } elseif ($new === $current) {
        flash('Choose a new password that is different from the current one.', 'error');
    } else {
        getDB()->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
               ->execute([password_hash($new, PASSWORD_DEFAULT), (int)$user['id']]);
        throttleClear('pwchange');
        session_regenerate_id(true);
        flash('Password updated.', 'success');
    }
    header('Location: account.php');
    exit;
}

$pageTitle = 'Account';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>My Account</h1>
    <p class="muted">Your profile and password.</p>
</div>

<div class="two-col">
    <section class="panel">
        <h2><svg class="icon"><use href="#i-user"/></svg> Profile</h2>
        <dl class="detail-list">
            <div><dt>Username</dt><dd><?= h($user['username']) ?></dd></div>
            <div><dt>Role</dt><dd><span class="tag tag-<?= h($user['role']) ?>"><?= h($user['role']) ?></span></dd></div>
            <div><dt>Credits</dt><dd><?= (int)$user['credits'] ?></dd></div>
            <div><dt>Bio-energy</dt><dd><?= (int)$user['current_energy'] ?> / <?= (int)$user['max_energy'] ?></dd></div>
            <div><dt>Member since</dt><dd><?= h($user['created_at']) ?></dd></div>
        </dl>
    </section>

    <section class="panel">
        <h2><svg class="icon"><use href="#i-shield"/></svg> Change password</h2>
        <form method="post" class="stack">
            <?= csrfField() ?>
            <label>Current password
                <input type="password" name="current_password" required autocomplete="current-password">
            </label>
            <label>New password <span class="muted small">(at least 8 characters)</span>
                <input type="password" name="new_password" required minlength="8" maxlength="72" autocomplete="new-password">
            </label>
            <label>Confirm new password
                <input type="password" name="confirm_password" required minlength="8" maxlength="72" autocomplete="new-password">
            </label>
            <button type="submit" class="btn btn-primary">Update password</button>
        </form>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
