<?php
/**
 * VoltTech - List all registered users (diagnostic tool)
 * Run from the terminal: php tools/list_users.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("This script can only be run from the command line.\n");
}

require_once __DIR__ . '/../config/database.php';

$db = getDB();
$users = $db->query("SELECT id, username, role, created_at FROM users ORDER BY id")->fetchAll();

if (empty($users)) {
    echo "No users found. Register an account in the app first, then re-run this.\n";
    exit(0);
}

echo "Users in this database:\n";
echo str_pad("ID", 5) . str_pad("Username", 25) . str_pad("Role", 12) . "Created\n";
echo str_repeat('-', 60) . "\n";
foreach ($users as $u) {
    echo str_pad($u['id'], 5) . str_pad($u['username'], 25) . str_pad($u['role'], 12) . $u['created_at'] . "\n";
}
