<?php
require_once __DIR__ . '/includes/auth.php';

// Logging out changes state, so it must be a POST with a valid CSRF token
// (a plain link or <img src="logout.php"> on another site cannot log you out).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    vtLogout();
    header('Location: login.php');
    exit;
}
header('Location: index.php');
exit;
