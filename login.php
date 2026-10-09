<?php
require_once __DIR__ . '/includes/auth.php';

if (currentUser()) {
    header('Location: index.php');
    exit;
}

// A valid bcrypt hash of an unrelated public sample string (it protects nothing). It is verified when the
// username does not exist, so a wrong username and a wrong password take the
// same time and the page cannot be used to discover which usernames exist.
const VT_DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = inStr($_POST, 'username', 60);
    $password = (isset($_POST['password']) && is_string($_POST['password'])) ? $_POST['password'] : '';

    if (throttleBlocked('login', 8, 900)) {
        $error = 'Too many failed attempts. Please wait about 15 minutes and try again.';
    } else {
        $stmt = getDB()->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $account = $stmt->fetch();

        $hash = $account ? (string)$account['password_hash'] : VT_DUMMY_HASH;
        $passwordOk = password_verify($password, $hash);

        if ($account && $passwordOk) {
            throttleClear('login');
            // Upgrade old hashes to the current algorithm/cost the next time someone logs in.
            if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                getDB()->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                       ->execute([password_hash($password, PASSWORD_DEFAULT), (int)$account['id']]);
            }
            session_regenerate_id(true); // new session ID on every login: blocks session fixation
            $_SESSION['user_id'] = (int)$account['id'];
            flash("Welcome back, {$account['username']}.", 'success');
            header('Location: index.php');
            exit;
        }

        throttleHit('login');
        $error = 'Invalid username or password.';
    }
}

$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-card">
    <h1>Log in</h1>
    <p class="muted">Access your VoltTech loadout and inventory.</p>
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
    <p class="muted">No account yet? <a href="register.php">Register</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
