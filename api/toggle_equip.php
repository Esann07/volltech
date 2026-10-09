<?php
/**
 * Equip / unequip one owned item (called with fetch() from js/app.js).
 * POST + JSON body {"inventory_id": 12} + X-CSRF-Token header.
 */
define('VT_JSON', true);   // makes bootstrap answer errors as JSON instead of HTML
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => 'Use POST.']);
    exit;
}

$user = currentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not logged in.']);
    exit;
}

verifyCsrf();   // reads the X-CSRF-Token header; answers 403 JSON on failure

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}
$inventoryId = inInt($input, 'inventory_id', 0, 2147483647);

$db = getDB();
$stmt = $db->prepare("
    SELECT i.*, g.energy_cost, g.name
    FROM inventory i
    JOIN gear g ON g.id = i.gear_id
    WHERE i.id = ? AND i.user_id = ?
");
$stmt->execute([$inventoryId, $user['id']]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Item not found.']);
    exit;
}

$drainStmt = $db->prepare("
    SELECT COALESCE(SUM(g.energy_cost), 0) AS total
    FROM inventory i JOIN gear g ON g.id = i.gear_id
    WHERE i.user_id = ? AND i.equipped = 1
");

if ((int)$row['equipped'] === 0) {
    $drainStmt->execute([$user['id']]);
    $currentDrain = (int)$drainStmt->fetch()['total'];
    $newDrain = $currentDrain + (int)$row['energy_cost'];

    if ($newDrain > (int)$user['max_energy']) {
        echo json_encode([
            'ok' => false,
            'error' => "Equipping {$row['name']} would exceed your max bio-energy ({$newDrain}/{$user['max_energy']}).",
        ]);
        exit;
    }

    $db->prepare("UPDATE inventory SET equipped = 1 WHERE id = ? AND user_id = ?")->execute([$inventoryId, $user['id']]);
    echo json_encode(['ok' => true, 'equipped' => true, 'total_drain' => $newDrain]);
} else {
    $db->prepare("UPDATE inventory SET equipped = 0 WHERE id = ? AND user_id = ?")->execute([$inventoryId, $user['id']]);
    $drainStmt->execute([$user['id']]);
    $total = (int)$drainStmt->fetch()['total'];
    echo json_encode(['ok' => true, 'equipped' => false, 'total_drain' => $total]);
}
