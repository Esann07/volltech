<?php
require_once __DIR__ . '/includes/auth.php';

if (currentAdmin()) {
    header('Location: index.php');
    exit;
}

// A valid bcrypt hash of an unrelated public sample string, verified when the username does not exist, so timing and the
// error message never reveal which usernames (or which admins) exist.
const VT_DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = inStr($_POST, 'username', 60);
    $password = (isset($_POST['password']) && is_string($_POST['password'])) ? $_POST['password'] : '';

    if (throttleBlocked('admin_login', 5, 900)) {
        $error = 'Too many failed attempts. Please wait about 15 minutes and try again.';
    } else {
        $stmt = getDB()->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $account = $stmt->fetch();

        $hash = $account ? (string)$account['password_hash'] : VT_DUMMY_HASH;
        $passwordOk = password_verify($password, $hash);

        // One generic message for: unknown user, wrong password, and "not an admin".
        if ($account && $passwordOk && $account['role'] === 'admin') {
            throttleClear('admin_login');
            session_regenerate_id(true);
            $_SESSION['admin_user_id'] = (int)$account['id'];
            logAudit((int)$account['id'], 'login', $account['username']);
            header('Location: index.php');
            exit;
        }
        throttleHit('admin_login');
        $error = 'Invalid username or password, or this account does not have admin access.';
    }
}

$pageTitle = 'Admin Login';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-card">
    <h1>Admin login</h1>
    <p class="muted">Use the username and password of an account that has been promoted to admin.</p>
    <?php if ($error): ?><div class="flash flash-error" role="alert"><?= h($error) ?></div><?php endif; ?>
    <form method="post" class="stack">
        <?= csrfField() ?>
        <label>Username
            <input type="text" name="username" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="60" value="<?= h($username ?? '') ?>">
        </label>
        <label>Password
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit" class="btn btn-primary">Log In</button>
    </form>
    <p class="hint">There is no sign-up here on purpose. The site owner grants admin rights from the server with <code>php tools/promote_admin.php &lt;username&gt;</code>.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
