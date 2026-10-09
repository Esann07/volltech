<?php
require_once __DIR__ . '/includes/auth.php';
$admin = requireAdminLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && inStr($_POST, 'form_action', 30) === 'remove_item') {
    verifyCsrf();
    $invId = inInt($_POST, 'inventory_id', 0, 2147483647);
    $stmt = $db->prepare("
        SELECT i.id, u.username, g.name
        FROM inventory i JOIN users u ON u.id = i.user_id JOIN gear g ON g.id = i.gear_id
        WHERE i.id = ?
    ");
    $stmt->execute([$invId]);
    $row = $stmt->fetch();
    if ($row) {
        $db->prepare("DELETE FROM inventory WHERE id = ?")->execute([$invId]);
        logAudit((int)$admin['id'], 'remove_inventory_item', "{$row['name']} from {$row['username']}");
        flash("Removed {$row['name']} from {$row['username']}'s inventory.", 'success');
    } else {
        flash('That inventory item no longer exists.', 'error');
    }
    header('Location: inventories.php');
    exit;
}

$query = inStr($_GET, 'q', 60);
$from  = " FROM inventory i JOIN users u ON u.id = i.user_id JOIN gear g ON g.id = i.gear_id";
$where = " WHERE 1=1";
$params = [];
if ($query !== '') {
    $where   .= " AND (u.username LIKE ? OR g.name LIKE ?)";
    $params[] = '%' . $query . '%';
    $params[] = '%' . $query . '%';
}

$perPage = 25;
$page    = pageNumber();
$cnt = $db->prepare("SELECT COUNT(*) AS c" . $from . $where);
$cnt->execute($params);
$total  = (int)$cnt->fetch()['c'];
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("
    SELECT i.id AS inventory_id, i.equipped, u.id AS user_id, u.username, g.name, g.category, g.energy_cost"
    . $from . $where . "
    ORDER BY u.username, g.category, g.name
    LIMIT " . (int)$perPage . " OFFSET " . (int)$offset);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'Inventories';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>Monitor Inventories</h1>
    <p class="muted">Every item owned by every user across the shared database.</p>
</div>

<form method="get" class="filter-form">
    <input type="search" name="q" placeholder="Search by username or gear name..." value="<?= h($query) ?>" aria-label="Search inventories">
    <button type="submit" class="btn btn-secondary">Search</button>
</form>

<div class="table-wrap">
<table class="admin-table table-stack">
    <thead><tr><th>User</th><th>Gear</th><th>Category</th><th>Drain</th><th>Equipped</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td data-label="User"><a href="user.php?id=<?= (int)$r['user_id'] ?>"><?= h($r['username']) ?></a></td>
            <td data-label="Gear"><?= h($r['name']) ?></td>
            <td data-label="Category"><span class="tag tag-<?= h($r['category']) ?>"><?= h($r['category']) ?></span></td>
            <td data-label="Drain"><?= (int)$r['energy_cost'] ?></td>
            <td data-label="Equipped"><?= $r['equipped'] ? 'Yes' : 'No' ?></td>
            <td data-label="Action">
                <form method="post" data-confirm="Remove <?= h($r['name']) ?> from <?= h($r['username']) ?>'s inventory? No refund is given.">
                    <?= csrfField() ?>
                    <input type="hidden" name="form_action" value="remove_item">
                    <input type="hidden" name="inventory_id" value="<?= (int)$r['inventory_id'] ?>">
                    <button type="submit" class="btn btn-sell">Remove</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?><tr><td colspan="6" class="muted">No matching inventory items.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?= paginationNav($total, $page, $perPage) ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
