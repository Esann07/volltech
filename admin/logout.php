<?php
require_once __DIR__ . '/includes/auth.php';

// POST + CSRF only, so another website cannot sign an admin out.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if ($admin = currentAdmin()) {
        logAudit((int)$admin['id'], 'logout', $admin['username']);
    }
    vtLogout();
    header('Location: login.php');
    exit;
}
header('Location: index.php');
exit;
