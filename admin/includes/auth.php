<?php
/**
 * VoltTech Admin - authentication helpers.
 * Uses the SAME users table and password hashes as the player app, but its
 * own session cookie, a shorter idle timeout (30 min) and stricter login
 * throttling. Admins are created with:  php tools/promote_admin.php <username>
 */
define('VT_APP', 'admin');
require_once __DIR__ . '/../../includes/bootstrap.php';

function currentAdmin(): ?array {
    if (!isset($_SESSION['admin_user_id'])) {
        return null;
    }
    $stmt = getDB()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int)$_SESSION['admin_user_id']]);
    $user = $stmt->fetch();

    // Re-checked on EVERY request, not just at login: if someone is demoted or
    // deleted while logged in, they lose admin access immediately.
    if (!$user || $user['role'] !== 'admin') {
        return null;
    }
    return $user;
}

function requireAdminLogin(): array {
    $admin = currentAdmin();
    if (!$admin) {
        header('Location: login.php');
        exit;
    }
    return $admin;
}

function adminCount(): int {
    $row = getDB()->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'")->fetch();
    return (int)($row['c'] ?? 0);
}

/** Records an admin action. Never lets a logging problem break the action itself. */
function logAudit(int $adminId, string $action, string $target = ''): void {
    try {
        $stmt = getDB()->prepare("INSERT INTO admin_audit_log (admin_id, action, target) VALUES (?, ?, ?)");
        $stmt->execute([$adminId, vtCut($action, 50), vtCut($target, 255)]);
    } catch (Throwable $e) {
        error_log('[VoltTech Admin] audit log write failed: ' . $e->getMessage());
    }
}
