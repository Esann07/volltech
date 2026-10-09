<?php
require_once __DIR__ . '/includes/auth.php';
$user = requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_gear_id'])) {
    verifyCsrf();
    $gearId = inInt($_POST, 'buy_gear_id', 0, 2147483647);
    $stmt = $db->prepare("SELECT * FROM gear WHERE id = ?");
    $stmt->execute([$gearId]);
    $item = $stmt->fetch();

    if (!$item) {
        flash('That item does not exist.', 'error');
    } else {
        $price = (int)$item['price'];
        // Everything below happens in ONE transaction, and the credit check is part
        // of the UPDATE itself ("credits >= price"), so two taps or two devices at the
        // same moment can never spend the same credits twice or buy the same item twice.
        $db->beginTransaction();
        try {
            $pay = $db->prepare("UPDATE users SET credits = credits - ? WHERE id = ? AND credits >= ?");
            $pay->execute([$price, $user['id'], $price]);

            if ($pay->rowCount() !== 1) {
                $db->rollBack();
                flash("Not enough credits for {$item['name']}.", 'error');
            } else {
                $owned = $db->prepare("SELECT id FROM inventory WHERE user_id = ? AND gear_id = ?");
                $owned->execute([$user['id'], $gearId]);
                if ($owned->fetch()) {
                    $db->rollBack();   // also undoes the payment
                    flash('You already own that item.', 'error');
                } else {
                    $db->prepare("INSERT INTO inventory (user_id, gear_id) VALUES (?, ?)")->execute([$user['id'], $gearId]);
                    $db->commit();
                    flash("Purchased {$item['name']} for {$price} credits.", 'success');
                }
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
    $back = in_array($_GET['category'] ?? '', ['suit', 'dampener', 'gadget'], true) ? '?category=' . urlencode($_GET['category']) : '';
    header('Location: marketplace.php' . $back);
    exit;
}

$category = inStr($_GET, 'category', 20) ?: 'all';
$sql = "SELECT * FROM gear";
$params = [];
if (in_array($category, ['suit', 'dampener', 'gadget'], true)) {
    $sql .= " WHERE category = ?";
    $params[] = $category;
}
$sql .= " ORDER BY category, price";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

$ownedStmt = $db->prepare("SELECT gear_id FROM inventory WHERE user_id = ?");
$ownedStmt->execute([$user['id']]);
$ownedIds = array_column($ownedStmt->fetchAll(), 'gear_id');

$pageTitle = 'Marketplace';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Gear Marketplace</h1>
    <p class="muted">Suits, dampeners, and gadgets — equip yourself for whatever's next.</p>
</div>

<div class="filter-bar">
    <a href="marketplace.php" class="chip <?= $category === 'all' ? 'chip-active' : '' ?>">All</a>
    <a href="marketplace.php?category=suit" class="chip <?= $category === 'suit' ? 'chip-active' : '' ?>">Suits</a>
    <a href="marketplace.php?category=dampener" class="chip <?= $category === 'dampener' ? 'chip-active' : '' ?>">Dampeners</a>
    <a href="marketplace.php?category=gadget" class="chip <?= $category === 'gadget' ? 'chip-active' : '' ?>">Gadgets</a>
</div>

<div class="grid">
    <?php foreach ($items as $item): $isOwned = in_array($item['id'], $ownedIds); ?>
    <div class="card">
        <span class="card-icon tag-<?= h($item['category']) ?>"><svg class="icon"><use href="#i-<?= h($item['category']) ?>"/></svg></span>
        <h3><?= h($item['name']) ?></h3>
        <span class="tag tag-<?= h($item['category']) ?>"><?= h($item['category']) ?></span>
        <p class="card-desc"><?= h($item['description']) ?></p>
        <div class="card-stats">
            <span><svg class="icon"><use href="#i-battery"/></svg><?= (int)$item['energy_cost'] ?> drain</span>
            <span><svg class="icon"><use href="#i-bolt"/></svg><?= (int)$item['power_rating'] ?> power</span>
        </div>
        <div class="card-footer">
            <span class="price"><?= (int)$item['price'] ?> cr</span>
            <?php if ($isOwned): ?>
                <button class="btn btn-owned" disabled>Owned</button>
            <?php else: ?>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="buy_gear_id" value="<?= (int)$item['id'] ?>">
                    <button type="submit" class="btn btn-primary" <?= $user['credits'] < $item['price'] ? 'disabled' : '' ?>>Buy</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
