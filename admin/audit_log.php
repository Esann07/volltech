<?php
require_once __DIR__ . '/includes/auth.php';
$admin = requireAdminLogin();
$db = getDB();

$query        = inStr($_GET, 'q', 60);
$actionFilter = inStr($_GET, 'action', 50);

$from   = " FROM admin_audit_log a JOIN users u ON u.id = a.admin_id";
$where  = " WHERE 1=1";
$params = [];
if ($query !== '') {
    $where   .= " AND (u.username LIKE ? OR a.target LIKE ?)";
    $params[] = '%' . $query . '%';
    $params[] = '%' . $query . '%';
}
if ($actionFilter !== '' && $actionFilter !== 'all') {
    $where   .= " AND a.action = ?";
    $params[] = $actionFilter;
}

$perPage = 25;
$page    = pageNumber();
$cnt = $db->prepare("SELECT COUNT(*) AS c" . $from . $where);
$cnt->execute($params);
$total  = (int)$cnt->fetch()['c'];
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("SELECT a.id, a.action, a.target, a.created_at, u.username AS admin_username"
    . $from . $where . " ORDER BY a.id DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset);
$stmt->execute($params);
$entries = $stmt->fetchAll();

$actionTypes = $db->query("SELECT DISTINCT action FROM admin_audit_log ORDER BY action")->fetchAll();

$pageTitle = 'Audit Log';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>Audit Log</h1>
    <p class="muted">Who did what, and when. Entries are written automatically and cannot be edited from this console.</p>
</div>

<form method="get" class="filter-form">
    <input type="search" name="q" placeholder="Search admin or target..." value="<?= h($query) ?>" aria-label="Search audit log">
    <select name="action" data-autosubmit aria-label="Filter by action">
        <option value="all">All actions</option>
        <?php foreach ($actionTypes as $a): ?>
            <option value="<?= h($a['action']) ?>" <?= $actionFilter === $a['action'] ? 'selected' : '' ?>><?= h($a['action']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-secondary">Filter</button>
</form>

<div class="table-wrap">
<table class="admin-table table-stack">
    <thead><tr><th>Admin</th><th>Action</th><th>Target</th><th>When</th></tr></thead>
    <tbody>
    <?php foreach ($entries as $e): ?>
        <tr>
            <td data-label="Admin"><?= h($e['admin_username']) ?></td>
            <td data-label="Action"><span class="tag"><?= h($e['action']) ?></span></td>
            <td data-label="Target"><?= h($e['target']) ?></td>
            <td data-label="When" class="muted small"><?= h($e['created_at']) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($entries)): ?>
        <tr><td colspan="4" class="muted">No audit entries yet. Actions you take in this console will appear here.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>
<?= paginationNav($total, $page, $perPage) ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
