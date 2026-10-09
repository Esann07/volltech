<?php
/**
 * VoltTech - environment and database self-test.
 *
 *     php tools/selftest.php
 *
 * Run it after installing or after any server change. It never modifies your real
 * data: the purchase/sell checks run on a temporary in-memory database.
 * (If the configured SQLite file does not exist yet, connecting creates it, exactly
 * as the first page visit would.)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("This script can only be run from the command line.\n");
}

require_once __DIR__ . '/../config/database.php';

$failures = 0;
$warnings = 0;
function st_ok(string $m): void   { echo "  [ OK ]  {$m}\n"; }
function st_warn(string $m): void { global $warnings; $warnings++; echo "  [WARN]  {$m}\n"; }
function st_fail(string $m): void { global $failures; $failures++; echo "  [FAIL]  {$m}\n"; }

echo "VoltTech self-test\n==================\n\n";

/* 1. PHP and extensions */
echo "1. PHP\n";
if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
    st_ok('PHP ' . PHP_VERSION);
} else {
    st_fail('PHP ' . PHP_VERSION . ' is too old. VoltTech needs PHP 8.0 or newer (8.2 / 8.3 recommended).');
}
$driver = dbConfig()['driver'] ?? 'sqlite';
$needed = ['pdo', 'json', 'session', ($driver === 'mysql' ? 'pdo_mysql' : 'pdo_sqlite')];
foreach ($needed as $ext) {
    extension_loaded($ext) ? st_ok("extension {$ext}") : st_fail("extension {$ext} is missing - enable it in php.ini");
}
extension_loaded('mbstring') ? st_ok('extension mbstring') : st_warn('extension mbstring is missing - the app still works, but non-English text is cut by bytes instead of characters');
function_exists('random_bytes') ? st_ok('random_bytes() available (secure CSRF tokens)') : st_fail('random_bytes() is missing');

$hash = password_hash('Test-pass-123', PASSWORD_DEFAULT);
(password_verify('Test-pass-123', $hash) && !password_verify('wrong', $hash))
    ? st_ok('password hashing and verification work')
    : st_fail('password_hash()/password_verify() do not behave correctly');

$sessionDir = session_save_path() !== '' ? session_save_path() : sys_get_temp_dir();
if (strpos($sessionDir, ';') !== false) {          // "N;/path" form
    $sessionDir = substr($sessionDir, strrpos($sessionDir, ';') + 1);
}
is_writable($sessionDir) ? st_ok("session folder is writable ({$sessionDir})") : st_warn("session folder is not writable from the command line ({$sessionDir}) - the web server user may still be able to write to it");

/* 2. Configuration */
echo "\n2. Configuration (config/db_credentials.php)\n";
$cfg = dbConfig();
st_ok("driver: {$driver}");
!empty($cfg['debug']) ? st_warn("'debug' is ON - fine on your own computer, but turn it OFF on a live server") : st_ok("'debug' is off");
!empty($cfg['force_https']) ? st_ok("'force_https' is on") : st_warn("'force_https' is off - turn it on once your SSL certificate works (live server only)");

/* 3. Real database (read-only checks) */
echo "\n3. Database\n";
try {
    if ($driver === 'sqlite') {
        $path = sqliteDbPath();
        echo "     file: {$path}\n";
        if (!is_file($path)) {
            st_warn('the database file does not exist yet - it will be created now, with the starter gear catalogue');
        }
        is_writable(dirname($path)) ? st_ok('data folder is writable') : st_fail('data folder is NOT writable - SQLite needs to create temporary files next to the database');
    }
    $db = getDB();
    st_ok('connected');
    foreach (['users', 'gear', 'inventory', 'energy_logs', 'admin_audit_log', 'login_attempts'] as $t) {
        try {
            $n = (int)$db->query("SELECT COUNT(*) AS c FROM {$t}")->fetch()['c'];
            st_ok(str_pad($t, 16) . "{$n} row(s)");
        } catch (Throwable $e) {
            st_fail("table {$t} is missing or unreadable: " . $e->getMessage());
        }
    }
    $admins = (int)$db->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'")->fetch()['c'];
    $admins > 0 ? st_ok("{$admins} admin account(s)") : st_warn('no admin account yet - register a user, then run: php tools/promote_admin.php <username>');
    if ($driver === 'sqlite' && is_file(sqliteDbPath()) && !is_writable(sqliteDbPath())) {
        st_fail('the database FILE is not writable by this user - logins, purchases and registrations would fail');
    }
} catch (Throwable $e) {
    st_fail('could not use the database: ' . $e->getMessage());
}

/* 4. Purchase / sell logic on a throw-away in-memory database */
echo "\n4. Purchase and sell logic (temporary in-memory database)\n";
try {
    $mem = new PDO('sqlite::memory:');
    $mem->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $mem->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $mem->exec('PRAGMA foreign_keys = ON');
    seedDatabase($mem);
    $mem->exec("INSERT INTO users (username, password_hash, credits) VALUES ('selftest', 'x', 250)");
    $uid = (int)$mem->lastInsertId();

    $buy = function (PDO $d, int $uid, int $gid, int $price): string {
        $d->beginTransaction();
        $pay = $d->prepare("UPDATE users SET credits = credits - ? WHERE id = ? AND credits >= ?");
        $pay->execute([$price, $uid, $price]);
        if ($pay->rowCount() !== 1) {
            $d->rollBack();
            return 'insufficient';
        }
        $own = $d->prepare("SELECT id FROM inventory WHERE user_id = ? AND gear_id = ?");
        $own->execute([$uid, $gid]);
        if ($own->fetch()) {
            $d->rollBack();
            return 'owned';
        }
        $d->prepare("INSERT INTO inventory (user_id, gear_id) VALUES (?, ?)")->execute([$uid, $gid]);
        $d->commit();
        return 'bought';
    };
    $credits = function (PDO $d, int $uid): int {
        $s = $d->prepare("SELECT credits FROM users WHERE id = ?");
        $s->execute([$uid]);
        return (int)$s->fetch()['credits'];
    };

    $r1 = $buy($mem, $uid, 1, 220);
    ($r1 === 'bought' && $credits($mem, $uid) === 30) ? st_ok('buying an item with enough credits works (250 -> 30)') : st_fail("purchase returned {$r1}, credits " . $credits($mem, $uid));
    $r2 = $buy($mem, $uid, 2, 180);
    ($r2 === 'insufficient' && $credits($mem, $uid) === 30) ? st_ok('buying without enough credits is refused and credits stay at 30') : st_fail("expected 'insufficient', got {$r2}");
    $mem->exec("UPDATE users SET credits = 500 WHERE id = {$uid}");
    $r3 = $buy($mem, $uid, 1, 220);
    ($r3 === 'owned' && $credits($mem, $uid) === 500) ? st_ok('buying an item you already own is refused and the payment is rolled back') : st_fail("expected 'owned', got {$r3}");
    $r4 = $buy($mem, $uid, 8, 0);
    $r4 === 'bought' ? st_ok('a 0-credit item can be bought (rowCount() counts matched rows)') : st_fail("0-credit purchase returned {$r4}");

    $sell = function (PDO $d, int $uid, int $inv, int $refund): string {
        $d->beginTransaction();
        $del = $d->prepare("DELETE FROM inventory WHERE id = ? AND user_id = ?");
        $del->execute([$inv, $uid]);
        if ($del->rowCount() === 1) {
            $d->prepare("UPDATE users SET credits = credits + ? WHERE id = ?")->execute([$refund, $uid]);
            $d->commit();
            return 'sold';
        }
        $d->rollBack();
        return 'gone';
    };
    $inv = (int)$mem->query("SELECT id FROM inventory WHERE user_id = {$uid} AND gear_id = 1")->fetch()['id'];
    $before = $credits($mem, $uid);
    $s1 = $sell($mem, $uid, $inv, 110);
    $s2 = $sell($mem, $uid, $inv, 110);
    ($s1 === 'sold' && $s2 === 'gone' && $credits($mem, $uid) === $before + 110)
        ? st_ok('selling pays the refund once, a repeated sale pays nothing')
        : st_fail("sell results {$s1}/{$s2}, credits " . $credits($mem, $uid));
} catch (Throwable $e) {
    st_fail('in-memory test crashed: ' . $e->getMessage());
}

echo "\n==================\n";
if ($failures === 0) {
    echo "RESULT: all checks passed" . ($warnings ? " ({$warnings} warning(s) above are advice, not errors)" : '') . ".\n";
    exit(0);
}
echo "RESULT: {$failures} check(s) FAILED - fix the [FAIL] lines above, then run this again.\n";
exit(1);
