<?php
/**
 * VoltTech - player-app authentication helpers.
 * (Shared plumbing - sessions, CSRF, h(), flash, throttling - is in bootstrap.php.)
 */
require_once __DIR__ . '/bootstrap.php';

function currentUser(): ?array {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $stmt = getDB()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function requireLogin(): array {
    $user = currentUser();
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    return $user;
}

/** Sends non-admins away. (Real admin work happens in the admin/ folder.) */
function requireAdmin(): array {
    $user = requireLogin();
    if ($user['role'] !== 'admin') {
        flash('Admins only.', 'error');
        header('Location: index.php');
        exit;
    }
    return $user;
}

/** Applies an energy change (clamped to 0..max_energy) and records it in the history. */
function adjustEnergy(int $userId, int $amount, string $reason): void {
    $db = getDB();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE users SET current_energy = CASE WHEN current_energy + ? < 0 THEN 0 WHEN current_energy + ? > max_energy THEN max_energy ELSE current_energy + ? END WHERE id = ?");
        $stmt->execute([$amount, $amount, $amount, $userId]);
        $log = $db->prepare("INSERT INTO energy_logs (user_id, change_amount, reason) VALUES (?, ?, ?)");
        $log->execute([$userId, $amount, $reason]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}
