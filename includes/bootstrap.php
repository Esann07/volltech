<?php
/**
 * VoltTech - shared bootstrap for the player app AND the admin app.
 *
 * Every web page loads this (through includes/auth.php). It:
 *   1. connects the config / database layer
 *   2. installs a friendly error page (no stack traces for visitors)
 *   3. optionally forces HTTPS and sends security headers (incl. CSP)
 *   4. starts a hardened session (idle timeout, strict mode, SameSite)
 *   5. provides helpers: h(), CSRF, flash messages, input cleaning,
 *      login throttling and pagination
 *
 * Define VT_APP as 'admin' BEFORE including this file for admin pages
 * (admin/includes/auth.php does that). Default is 'user'.
 */

require_once __DIR__ . '/../config/database.php';

if (!defined('VT_APP')) {
    define('VT_APP', 'user');
}

/* ------------------------------------------------------------------
 * 1. Friendly error page
 * ------------------------------------------------------------------ */
set_exception_handler(function ($e) {
    error_log('[VoltTech] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (defined('VT_JSON')) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['ok' => false, 'error' => 'Server error. Please try again.']);
        return;
    }
    $detail = '';
    if (!empty(dbConfig()['debug'])) {
        $detail = '<pre style="white-space:pre-wrap;text-align:left;background:#111;color:#f88;padding:12px;border-radius:8px;">'
            . htmlspecialchars(get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8')
            . '</pre>';
    } else {
        $detail = '<p style="color:#8a99ba">If this keeps happening, ask the site owner to check the server error log '
            . '(or set <code>\'debug\' =&gt; true</code> in config/db_credentials.php on a local machine).</p>';
    }
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>VoltTech · Something went wrong</title></head>'
        . '<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#070b14;color:#eaf0fc;font-family:system-ui,Segoe UI,Arial,sans-serif;text-align:center;padding:24px">'
        . '<main style="max-width:520px"><h1 style="margin:0 0 8px">Something went wrong</h1>'
        . '<p>VoltTech hit an unexpected problem. Your data was not affected.</p>' . $detail
        . '<p><a href="./" style="color:#5cc8ff">Back to the start page</a></p></main></body></html>';
});

/* ------------------------------------------------------------------
 * 2. HTTPS detection / enforcement and security headers
 * ------------------------------------------------------------------ */
function isHttps(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty(dbConfig()['trust_proxy'])
        && strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    return false;
}

function clientIp(): string {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    if (!empty(dbConfig()['trust_proxy']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']);
        $first = trim($parts[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            $ip = $first;
        }
    }
    return substr($ip, 0, 45);
}

if (PHP_SAPI !== 'cli') {
    if (!empty(dbConfig()['force_https']) && !isHttps()) {
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host !== '' && preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) {
            header('Location: https://' . $host . (string)($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
            exit;
        }
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    // No inline <script> and no inline event handlers anywhere in the app,
    // so scripts are restricted to our own files. Inline style="" is still
    // allowed (progress bars use it).
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; "
        . "base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
    if (isHttps()) {
        header('Strict-Transport-Security: max-age=15552000');
    }
    header('Cache-Control: no-store, max-age=0');
}

/* ------------------------------------------------------------------
 * 3. Session (separate cookie per app, so player and admin never mix)
 * ------------------------------------------------------------------ */
function vtStartSession(): void {
    if (session_status() !== PHP_SESSION_NONE || PHP_SAPI === 'cli') {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name(VT_APP === 'admin' ? 'volttech_admin_sess' : 'volttech_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Idle timeout: 2 hours for players, 30 minutes for admins.
    $idleLimit = (VT_APP === 'admin') ? 1800 : 7200;
    $now = time();
    if (isset($_SESSION['vt_last']) && ($now - (int)$_SESSION['vt_last']) > $idleLimit) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['flash'][] = ['msg' => 'You were signed out after a period of inactivity. Please log in again.', 'type' => 'info'];
    }
    $_SESSION['vt_last'] = $now;
}
vtStartSession();

/** Fully ends the current session (used by logout). */
function vtLogout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => (bool)$p['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_destroy();
}

/* ------------------------------------------------------------------
 * 4. Output escaping, flash messages, CSRF
 * ------------------------------------------------------------------ */
function h($s): string {
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function flash(string $msg, string $type = 'info'): void {
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}

function getFlashes(): array {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($flashes) ? $flashes : [];
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">';
}

/** Checks the form token (or the X-CSRF-Token header for fetch() calls). */
function verifyCsrf(): void {
    $submitted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($submitted) || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $submitted)) {
        http_response_code(403);
        if (defined('VT_JSON')) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Security check failed. Please refresh the page and try again.']);
            exit;
        }
        die('Security check failed (invalid or expired form token). Please go back, refresh the page and try again.');
    }
}

/* ------------------------------------------------------------------
 * 5. Input cleaning (never trust the browser's maxlength/min/max)
 * ------------------------------------------------------------------ */
function vtLen(string $s): int {
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

function vtCut(string $s, int $max): string {
    if (vtLen($s) <= $max) {
        return $s;
    }
    return function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
}

/** Trimmed string from $_POST/$_GET-style arrays; arrays/garbage become ''. */
function inStr(array $src, string $key, int $max = 255): string {
    $v = $src[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    return vtCut(trim($v), $max);
}

/** Integer clamped to [$min, $max]; non-numeric input returns $default. */
function inInt(array $src, string $key, int $min, int $max, int $default = 0): int {
    $v = $src[$key] ?? null;
    if (is_int($v)) {
        $n = $v;
    } elseif (is_string($v) && preg_match('/^-?\d{1,12}$/', trim($v))) {
        $n = (int)trim($v);
    } else {
        return $default;
    }
    return max($min, min($max, $n));
}

const VT_USERNAME_PATTERN = '/^[A-Za-z0-9_.-]{3,30}$/';
const VT_ROLES_PUBLIC     = ['hero', 'villain', 'civilian'];
const VT_ROLES_ALL        = ['hero', 'villain', 'civilian', 'admin'];

/** Returns an error message, or '' when the password is acceptable. */
function passwordProblem(string $pw): string {
    $bytes = strlen($pw);
    if ($bytes < 8) {
        return 'Password must be at least 8 characters.';
    }
    if ($bytes > 72) {
        return 'Password is too long (maximum 72 characters).';
    }
    return '';
}

/* ------------------------------------------------------------------
 * 6. Login / registration throttling (stored in the login_attempts table)
 *    Fails OPEN: if the table is unavailable, users can still log in.
 * ------------------------------------------------------------------ */
function throttleBlocked(string $scope, int $limit, int $windowSeconds): bool {
    try {
        $stmt = getDB()->prepare("SELECT COUNT(*) AS c FROM login_attempts WHERE scope = ? AND ip = ? AND attempted_at > ?");
        $stmt->execute([$scope, clientIp(), time() - $windowSeconds]);
        $row = $stmt->fetch();
        return $row && (int)$row['c'] >= $limit;
    } catch (Throwable $e) {
        error_log('[VoltTech] throttle check failed: ' . $e->getMessage());
        return false;
    }
}

function throttleHit(string $scope): void {
    try {
        $db = getDB();
        $db->prepare("INSERT INTO login_attempts (scope, ip, attempted_at) VALUES (?, ?, ?)")
           ->execute([$scope, clientIp(), time()]);
        if (random_int(1, 50) === 1) {   // occasional housekeeping: drop entries older than a day
            $db->prepare("DELETE FROM login_attempts WHERE attempted_at < ?")->execute([time() - 86400]);
        }
    } catch (Throwable $e) {
        error_log('[VoltTech] throttle record failed: ' . $e->getMessage());
    }
}

function throttleClear(string $scope): void {
    try {
        getDB()->prepare("DELETE FROM login_attempts WHERE scope = ? AND ip = ?")->execute([$scope, clientIp()]);
    } catch (Throwable $e) {
        error_log('[VoltTech] throttle clear failed: ' . $e->getMessage());
    }
}

/* ------------------------------------------------------------------
 * 7. Pagination helpers
 *    LIMIT/OFFSET are cast to int and written into the SQL text because
 *    MySQL (native prepares) rejects quoted LIMIT parameters.
 * ------------------------------------------------------------------ */
function pageNumber(): int {
    $p = isset($_GET['page']) && is_string($_GET['page']) ? (int)$_GET['page'] : 1;
    return max(1, min($p, 100000));
}

function paginationNav(int $total, int $page, int $perPage): string {
    $pages = (int)ceil($total / max(1, $perPage));
    if ($pages <= 1) {
        return '';
    }
    $link = function (int $n) {
        $q = $_GET;
        $q['page'] = $n;
        return '?' . http_build_query($q);
    };
    $html = '<nav class="pagination" aria-label="Pagination">';
    $html .= ($page > 1)
        ? '<a class="btn btn-secondary" href="' . h($link($page - 1)) . '">Previous</a>'
        : '<span class="btn btn-secondary is-disabled" aria-disabled="true">Previous</span>';
    $html .= '<span class="pagination-info">Page ' . (int)$page . ' of ' . (int)$pages . ' &middot; ' . (int)$total . ' total</span>';
    $html .= ($page < $pages)
        ? '<a class="btn btn-secondary" href="' . h($link($page + 1)) . '">Next</a>'
        : '<span class="btn btn-secondary is-disabled" aria-disabled="true">Next</span>';
    $html .= '</nav>';
    return $html;
}
