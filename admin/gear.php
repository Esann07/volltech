<?php
require_once __DIR__ . '/includes/auth.php';
$admin = requireAdminLogin();
$db = getDB();

const GEAR_CATEGORIES = ['suit', 'dampener', 'gadget'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = inStr($_POST, 'form_action', 30);

    if ($action === 'add_gear' || $action === 'edit_gear') {
        $name        = inStr($_POST, 'name', 60);
        $category    = inStr($_POST, 'category', 20);
        $description = inStr($_POST, 'description', 200);
        $price       = inInt($_POST, 'price', 0, 1000000, 0);
        $energyCost  = inInt($_POST, 'energy_cost', 0, 10000, 0);
        $powerRating = inInt($_POST, 'power_rating', 0, 100, 0);

        if ($name === '' || $description === '' || !in_array($category, GEAR_CATEGORIES, true)) {
            flash('Please fill in the name, description and category.', 'error');
        } elseif ($action === 'add_gear') {
            $db->prepare("INSERT INTO gear (name, category, description, price, energy_cost, power_rating) VALUES (?, ?, ?, ?, ?, ?)")
               ->execute([$name, $category, $description, $price, $energyCost, $powerRating]);
            logAudit((int)$admin['id'], 'add_gear', $name);
            flash("Added new gear: {$name}.", 'success');
        } else {
            $gearId = inInt($_POST, 'gear_id', 0, 2147483647);
            $exists = $db->prepare("SELECT id FROM gear WHERE id = ?");
            $exists->execute([$gearId]);
            if (!$exists->fetch()) {
                flash('That gear item no longer exists.', 'error');
            } else {
                $db->prepare("UPDATE gear SET name = ?, category = ?, description = ?, price = ?, energy_cost = ?, power_rating = ? WHERE id = ?")
                   ->execute([$name, $category, $description, $price, $energyCost, $powerRating, $gearId]);
                logAudit((int)$admin['id'], 'edit_gear', "gear #{$gearId} ({$name})");
                flash("Updated {$name}.", 'success');
            }
        }
    }

    elseif ($action === 'delete_gear') {
        $gearId = inInt($_POST, 'gear_id', 0, 2147483647);
        $stmt = $db->prepare("SELECT name FROM gear WHERE id = ?");
        $stmt->execute([$gearId]);
        $target = $stmt->fetch();
        if (!$target) {
            flash('That gear item no longer exists.', 'error');
        } else {
            $db->prepare("DELETE FROM gear WHERE id = ?")->execute([$gearId]);
            logAudit((int)$admin['id'], 'delete_gear', "gear #{$gearId} ({$target['name']})");
            flash("Removed {$target['name']} from the catalogue and from every player's inventory.", 'success');
        }
    }

    header('Location: gear.php');
    exit;
}

$query          = inStr($_GET, 'q', 60);
$categoryFilter = inStr($_GET, 'category', 20);
$editId         = inInt($_GET, 'edit', 0, 2147483647);

$where  = " WHERE 1=1";
$params = [];
if ($query !== '') {
    $where   .= " AND g.name LIKE ?";
    $params[] = '%' . $query . '%';
}
if (in_array($categoryFilter, GEAR_CATEGORIES, true)) {
    $where   .= " AND g.category = ?";
    $params[] = $categoryFilter;
}

$perPage = 15;
$page    = pageNumber();
$cnt = $db->prepare("SELECT COUNT(*) AS c FROM gear g" . $where);
$cnt->execute($params);
$total  = (int)$cnt->fetch()['c'];
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("
    SELECT g.*, (SELECT COUNT(*) FROM inventory i WHERE i.gear_id = g.id) AS owners
    FROM gear g" . $where . "
    ORDER BY g.category, g.name
    LIMIT " . (int)$perPage . " OFFSET " . (int)$offset);
$stmt->execute($params);
$gearList = $stmt->fetchAll();

$editItem = null;
if ($editId > 0) {
    $e = $db->prepare("SELECT * FROM gear WHERE id = ?");
    $e->execute([$editId]);
    $editItem = $e->fetch() ?: null;
}

$pageTitle = 'Gear';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>Manage Gear Catalogue</h1>
    <p class="muted">Add, edit or remove items in the marketplace that every player sees.</p>
</div>

<div class="two-col">
    <section class="panel">
        <h2><svg class="icon"><use href="#i-wrench"/></svg> <?= $editItem ? 'Edit ' . h($editItem['name']) : 'Add new gear' ?></h2>
        <form method="post" class="stack">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="<?= $editItem ? 'edit_gear' : 'add_gear' ?>">
            <?php if ($editItem): ?><input type="hidden" name="gear_id" value="<?= (int)$editItem['id'] ?>"><?php endif; ?>
            <label>Name<input type="text" name="name" required maxlength="60" value="<?= h($editItem['name'] ?? '') ?>"></label>
            <label>Category
                <select name="category">
                    <?php foreach (GEAR_CATEGORIES as $c): ?>
                        <option value="<?= h($c) ?>" <?= ($editItem['category'] ?? '') === $c ? 'selected' : '' ?>><?= h(ucfirst($c)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Description<input type="text" name="description" required maxlength="200" value="<?= h($editItem['description'] ?? '') ?>"></label>
            <div class="field-row">
                <label>Price (credits)<input type="number" name="price" min="0" max="1000000" required value="<?= (int)($editItem['price'] ?? 100) ?>"></label>
                <label>Energy cost<input type="number" name="energy_cost" min="0" max="10000" required value="<?= (int)($editItem['energy_cost'] ?? 10) ?>"></label>
                <label>Power (0-100)<input type="number" name="power_rating" min="0" max="100" required value="<?= (int)($editItem['power_rating'] ?? 50) ?>"></label>
            </div>
            <button type="submit" class="btn btn-primary"><?= $editItem ? 'Save changes' : 'Add gear' ?></button>
            <?php if ($editItem): ?><a href="gear.php" class="btn btn-secondary btn-block">Cancel</a><?php endif; ?>
        </form>
    </section>

    <section class="panel">
        <h2>Catalogue (<?= $total ?>)</h2>
        <form method="get" class="filter-form">
            <input type="search" name="q" placeholder="Search gear..." value="<?= h($query) ?>" aria-label="Search gear">
            <select name="category" data-autosubmit aria-label="Filter by category">
                <option value="all">All categories</option>
                <?php foreach (GEAR_CATEGORIES as $c): ?>
                    <option value="<?= h($c) ?>" <?= $categoryFilter === $c ? 'selected' : '' ?>><?= h(ucfirst($c)) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary">Search</button>
        </form>
        <ul class="log-list">
            <?php foreach ($gearList as $g): ?>
            <li>
                <span class="gear-name"><?= h($g['name']) ?> <span class="tag tag-<?= h($g['category']) ?>"><?= h($g['category']) ?></span></span>
                <span class="muted small"><?= (int)$g['price'] ?> cr &middot; <?= (int)$g['energy_cost'] ?> en &middot; <?= (int)$g['owners'] ?> owner<?= (int)$g['owners'] === 1 ? '' : 's' ?></span>
                <span class="inline-actions">
                    <a href="gear.php?edit=<?= (int)$g['id'] ?>" class="btn btn-secondary">Edit</a>
                    <form method="post" data-confirm="Delete <?= h($g['name']) ?>? <?= (int)$g['owners'] ?> player(s) own it and will lose it with no refund.">
                        <?= csrfField() ?>
                        <input type="hidden" name="form_action" value="delete_gear">
                        <input type="hidden" name="gear_id" value="<?= (int)$g['id'] ?>">
                        <button type="submit" class="btn btn-sell">Delete</button>
                    </form>
                </span>
            </li>
            <?php endforeach; ?>
            <?php if (empty($gearList)): ?><li class="muted">No gear matches.</li><?php endif; ?>
        </ul>
        <?= paginationNav($total, $page, $perPage) ?>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
