<?php
require_once __DIR__ . '/includes/auth.php';

if (currentUser()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = inStr($_POST, 'username', 60);
    $password = (isset($_POST['password']) && is_string($_POST['password'])) ? $_POST['password'] : '';
    $confirm  = (isset($_POST['password_confirm']) && is_string($_POST['password_confirm'])) ? $_POST['password_confirm'] : '';
    $role     = inStr($_POST, 'role', 20);

    if (throttleBlocked('register', 10, 3600)) {
        $error = 'Too many accounts were created from your network recently. Please try again later.';
    } elseif (!preg_match(VT_USERNAME_PATTERN, $username)) {
        $error = 'Username must be 3-30 characters: letters, numbers, dot, dash or underscore.';
    } elseif (($problem = passwordProblem($password)) !== '') {
        $error = $problem;
    } elseif ($password !== $confirm) {
        $error = 'The two passwords do not match.';
    } elseif (!in_array($role, VT_ROLES_PUBLIC, true)) {
        $error = 'Please choose Hero, Villain or Civilian.';
    } else {
        $db = getDB();
        // Case-insensitive check, so "Alex" and "alex" cannot both exist.
        $stmt = $db->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?)");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'That username is already taken.';
        } else {
            try {
                $insert = $db->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
                $insert->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role]);
            } catch (PDOException $e) {
                if ((string)$e->getCode() === '23000') {   // two people grabbed the name at the same instant
                    $error = 'That username is already taken.';
                } else {
                    throw $e;
                }
            }
            if ($error === '') {
                throttleHit('register');
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$db->lastInsertId();
                flash("Welcome to VoltTech, {$username}. Your bio-energy reserves are fully charged.", 'success');
                header('Location: index.php');
                exit;
            }
        }
    }
}

$pageTitle = 'Register';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-card">
    <h1>Create your account</h1>
    <p class="muted">Register as a hero, villain, or civilian to start equipping gear.</p>
    <?php if ($error): ?><div class="flash flash-error" role="alert"><?= h($error) ?></div><?php endif; ?>
    <form method="post" class="stack">
        <?= csrfField() ?>
        <label>Username
            <input type="text" name="username" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_.\-]{3,30}" autocomplete="username" autocapitalize="none" spellcheck="false" title="3-30 characters: letters, numbers, dot, dash or underscore" value="<?= h($username ?? '') ?>">
        </label>
        <label>Password <span class="muted small">(at least 8 characters)</span>
            <input type="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password">
        </label>
        <label>Confirm password
            <input type="password" name="password_confirm" required minlength="8" maxlength="72" autocomplete="new-password">
        </label>
        <label>Role
            <select name="role">
                <?php foreach (VT_ROLES_PUBLIC as $r): ?>
                <option value="<?= h($r) ?>" <?= (($_POST['role'] ?? '') === $r) ? 'selected' : '' ?>><?= h(ucfirst($r)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="btn btn-primary">Create Account</button>
    </form>
    <p class="muted">Already registered? <a href="login.php">Log in</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
