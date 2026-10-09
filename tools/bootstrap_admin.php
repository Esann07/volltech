<?php
/**
 * Creates the first Render admin from environment variables, if configured.
 *
 * Required variables:
 *   VOLTTECH_ADMIN_USERNAME
 *   VOLTTECH_ADMIN_PASSWORD
 *
 * It is safe to run on every container start: it never changes an existing
 * user's password and only promotes the configured username to admin.
 */

require_once __DIR__ . '/../config/database.php';

$username = trim((string)getenv('VOLTTECH_ADMIN_USERNAME'));
$password = (string)getenv('VOLTTECH_ADMIN_PASSWORD');

if ($username === '' && $password === '') {
    exit(0);
}

if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $username)) {
    fwrite(STDERR, "[VoltTech] VOLTTECH_ADMIN_USERNAME must be 3-30 letters, numbers, dots, underscores, or hyphens.\n");
    exit(1);
}
if (strlen($password) < 8 || strlen($password) > 72) {
    fwrite(STDERR, "[VoltTech] VOLTTECH_ADMIN_PASSWORD must be 8-72 characters.\n");
    exit(1);
}

try {
    $db = getDB();
    $find = $db->prepare('SELECT id FROM users WHERE username = ?');
    $find->execute([$username]);
    $user = $find->fetch();

    if ($user) {
        $promote = $db->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
        $promote->execute([(int)$user['id']]);
        fwrite(STDOUT, "[VoltTech] configured admin account is ready.\n");
    } else {
        $create = $db->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'admin')");
        $create->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
        fwrite(STDOUT, "[VoltTech] first admin account created.\n");
    }
} catch (Throwable $e) {
    fwrite(STDERR, '[VoltTech] could not create admin account: ' . $e->getMessage() . "\n");
    exit(1);
}
