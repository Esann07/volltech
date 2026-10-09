<?php
require_once __DIR__ . '/includes/auth.php';
$admin = requireAdminLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && inStr($_POST, 'form_action', 30) === 'create_user') {
    verifyCsrf();
    $username = inStr($_POST, 'username', 60);
    $password = (isset($_POST['password']) && is_string($_POST['password'])) ? $_POST['password'] : '';
    $role     = inStr($_POST, 'role', 20);

    if (!preg_match(VT_USERNAME_PATTERN, $username)) {
        flash('Username must be 3-30 characters: letters, numbers, dot, dash or underscore.', 'error');
    } elseif (($problem = passwordProblem($password)) !== '') {
        flash($problem, 'error');
    } elseif (!in_array($role, VT_ROLES_ALL, true)) {
        flash('Invalid role.', 'error');
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?)");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            flash('That username is already taken.', 'error');
        } else {
            try {
                $db->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)")
                   ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role]);
                $newId = (int)$db->lastInsertId();
                logAudit((int)$admin['id'], 'create_user', "user #{$newId} ({$username}) as {$role}");
                flash("Created account {$username}.", 'success');
                header('Location: user.php?id=' . $newId);
                exit;
            } catch (PDOException $e) {
                if ((string)$e->getCode() === '23000') {
                    flash('That username is already taken.', 'error');
                } else {
                    throw $e;
                }
            }
        }
    }
    header('Location: users.php');
    exit;
}

$query      = inStr($_GET, 'q', 60);
$roleFilter = inStr($_GET, 'role', 20);

$where  = " WHERE 1=1";
$params = [];
if ($query !== '') {
    $where   .= " AND username LIKE ?";
    $params[] = '%' . $query . '%';
}
if (in_array($roleFilter, VT_ROLES_ALL, true)) {
    $where   .= " AND role = ?";
    $params[] = $roleFilter;
}

$perPage = 20;
$page    = pageNumber();
$cnt = $db->prepare("SELECT COUNT(*) AS c FROM users" . $where);
$cnt->execute($params);
$total  = (int)$cnt->fetch()['c'];
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("SELECT * FROM users" . $where . " ORDER BY created_at DESC, id DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Users';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>Manage Users</h1>
    <p class="muted">Search accounts, open a user for full details, or create a new account.</p>
</div>

<form method="get" class="filter-form">
    <input type="search" name="q" placeholder="Search username..." value="<?= h($query) ?>" aria-label="Search username">
    <select name="role" data-autosubmit aria-label="Filter by role">
        <option value="all">All roles</option>
        <?php foreach (VT_ROLES_ALL as $r): ?>
            <option value="<?= h($r) ?>" <?= $roleFilter === $r ? 'selected' : '' ?>><?= h(ucfirst($r)) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-secondary">Filter</button>
</form>

<div class="table-wrap">
<table class="admin-table table-stack">
    <thead><tr><th>Username</th><th>Role</th><th>Credits</th><th>Energy</th><th>Joined</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td data-label="Username"><a href="user.php?id=<?= (int)$u['id'] ?>"><?= h($u['username']) ?></a><?= (int)$u['id'] === (int)$admin['id'] ? ' <span class="muted small">(you)</span>' : '' ?></td>
            <td data-label="Role"><span class="tag tag-<?= h($u['role']) ?>"><?= h($u['role']) ?></span></td>
            <td data-label="Credits"><?= (int)$u['credits'] ?></td>
            <td data-label="Energy"><?= (int)$u['current_energy'] ?> / <?= (int)$u['max_energy'] ?></td>
            <td data-label="Joined" class="muted small"><?= h($u['created_at']) ?></td>
            <td data-label="Action"><a class="btn btn-secondary" href="user.php?id=<?= (int)$u['id'] ?>">Manage</a></td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($users)): ?>
        <tr><td colspan="6" class="muted">No users match.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>
<?= paginationNav($total, $page, $perPage) ?>

<section class="panel section-gap">
    <h2><svg class="icon"><use href="#i-users"/></svg> Create account</h2>
    <form method="post" class="stack">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="create_user">
        <div class="field-row">
            <label>Username
                <input type="text" name="username" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_.\-]{3,30}" autocomplete="off" autocapitalize="none" spellcheck="false">
            </label>
            <label>Temporary password
                <input type="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password">
            </label>
            <label>Role
                <select name="role">
                    <?php foreach (VT_ROLES_ALL as $r): ?>
                        <option value="<?= h($r) ?>"><?= h(ucfirst($r)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <button type="submit" class="btn btn-primary">Create account</button>
    </form>
    <p class="hint">New accounts start with 500 credits and 100 bio-energy, exactly like self-registered ones.</p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
