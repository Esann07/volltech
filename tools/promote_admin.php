<?php
/**
 * VoltTech - Promote a user to admin
 * Run from the terminal: php tools/promote_admin.php <username>
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("This script can only be run from the command line.\n");
}

require_once __DIR__ . '/../config/database.php';

$username = $argv[1] ?? null;
if (!$username) {
    fwrite(STDERR, "Usage: php tools/promote_admin.php <username>\n");
    exit(1);
}

$db = getDB();
$stmt = $db->prepare("SELECT id, username, role FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    fwrite(STDERR, "No user found with username '{$username}'. Register that account in the app first.\n");
    exit(1);
}

if ($user['role'] === 'admin') {
    echo "'{$username}' is already an admin.\n";
    exit(0);
}

$db->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$user['id']]);
echo "Done - '{$username}' is now an admin. Open the admin console at /admin/ and log in.\n";
