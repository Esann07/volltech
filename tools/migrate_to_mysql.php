<?php
/**
 * VoltTech - copy your existing SQLite data into MySQL/MariaDB.
 *
 *   1. Create an empty MySQL database + user (see README, "Switching to MySQL").
 *   2. Put the MySQL details in config/db_credentials.php  ('mysql' => [...]).
 *      Leave 'driver' => 'sqlite' for now.
 *   3. Import the tables:   mysql -u USER -p DBNAME < config/mysql_schema.sql
 *   4. Run:                 php tools/migrate_to_mysql.php
 *   5. Check the summary, then set 'driver' => 'mysql' in db_credentials.php.
 *
 * Safety:
 *   - The SQLite file is only READ. It is never modified or deleted - keep it as a backup.
 *   - Original IDs are preserved, so inventory / energy history still point at the right rows.
 *   - Plain INSERTs, no "INSERT IGNORE": a row that does not fit is REPORTED, never silently
 *     truncated or dropped. Each table is copied in one transaction (all or nothing).
 *   - Re-running is safe: rows that already exist (same id) are skipped.
 *   - At the end the row counts of both databases are compared.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("This script can only be run from the command line.\n");
}

require_once __DIR__ . '/../config/database.php';

$sqlitePath = sqliteDbPath();
if (!is_file($sqlitePath)) {
    fwrite(STDERR, "No SQLite database found at {$sqlitePath}. Nothing to migrate.\n");
    exit(1);
}

$m = dbConfig()['mysql'];
if (($m['pass'] ?? '') === 'CHANGE_ME' || ($m['dbname'] ?? '') === '') {
    fwrite(STDERR, "Fill in the 'mysql' section of config/db_credentials.php first.\n");
    exit(1);
}

echo "Reading SQLite : {$sqlitePath}\n";
$sqlite = new PDO('sqlite:' . $sqlitePath);
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

echo "Writing MySQL  : {$m['user']}@{$m['host']}:{$m['port']}/{$m['dbname']}\n";
$mysql = new PDO(
    'mysql:host=' . $m['host'] . ';port=' . (int)$m['port'] . ';dbname=' . $m['dbname'] . ';charset=' . $m['charset'],
    (string)$m['user'],
    (string)$m['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// Dependency order: parents first, so foreign keys never point at a missing row.
$tables = [
    'users'           => ['id', 'username', 'password_hash', 'role', 'credits', 'max_energy', 'current_energy', 'created_at'],
    'gear'            => ['id', 'name', 'category', 'description', 'price', 'energy_cost', 'power_rating', 'icon'],
    'inventory'       => ['id', 'user_id', 'gear_id', 'equipped', 'acquired_at'],
    'energy_logs'     => ['id', 'user_id', 'change_amount', 'reason', 'created_at'],
    'admin_audit_log' => ['id', 'admin_id', 'action', 'target', 'created_at'],
];

// The destination tables must already exist (config/mysql_schema.sql).
foreach (array_keys($tables) as $t) {
    $exists = $mysql->query("SHOW TABLES LIKE " . $mysql->quote($t))->fetch();
    if (!$exists) {
        fwrite(STDERR, "MySQL table '{$t}' is missing. Import config/mysql_schema.sql first.\n");
        exit(1);
    }
}

$mysql->exec('SET FOREIGN_KEY_CHECKS = 0');
$failed = false;

foreach ($tables as $table => $columns) {
    $inSqlite = $sqlite->query("SELECT name FROM sqlite_master WHERE type='table' AND name=" . $sqlite->quote($table))->fetch();
    if (!$inSqlite) {
        echo str_pad($table, 17) . "not present in the SQLite file - skipped\n";
        continue;
    }

    $rows = $sqlite->query("SELECT * FROM {$table} ORDER BY id")->fetchAll();
    $sql  = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ")";
    $insert = $mysql->prepare($sql);

    $copied = 0;
    $already = 0;
    $problems = [];

    $mysql->beginTransaction();
    foreach ($rows as $row) {
        $values = [];
        foreach ($columns as $c) {
            $values[] = $row[$c] ?? null;
        }
        try {
            $insert->execute($values);
            $copied++;
        } catch (PDOException $e) {
            $msg = $e->errorInfo[2] ?? $e->getMessage();
            // Same primary key already there = this row was copied by an earlier run.
            if (($e->errorInfo[1] ?? 0) == 1062 && stripos($msg, 'PRIMARY') !== false) {
                $already++;
            } else {
                $problems[] = "id {$row['id']}: {$msg}";
            }
        }
    }

    if ($problems) {
        $mysql->rollBack();
        $failed = true;
        echo str_pad($table, 17) . "FAILED - nothing was written for this table:\n";
        foreach (array_slice($problems, 0, 10) as $p) {
            echo "    - {$p}\n";
        }
        if (count($problems) > 10) {
            echo "    ... and " . (count($problems) - 10) . " more\n";
        }
        break;   // later tables depend on this one
    }
    $mysql->commit();
    echo str_pad($table, 17) . "copied {$copied}, already present {$already}, source rows " . count($rows) . "\n";
}

$mysql->exec('SET FOREIGN_KEY_CHECKS = 1');

if ($failed) {
    fwrite(STDERR, "\nMigration stopped. Fix the rows listed above (e.g. a username longer than 30 characters,\n"
        . "or two usernames that differ only by capital letters) and run this script again.\n");
    exit(1);
}

// Next INSERT must not collide with a migrated id.
foreach (array_keys($tables) as $t) {
    $max = $mysql->query("SELECT COALESCE(MAX(id), 0) AS m FROM {$t}")->fetch();
    $mysql->exec("ALTER TABLE {$t} AUTO_INCREMENT = " . ((int)$max['m'] + 1));
}

// Verify: the row counts must match.
echo "\nVerification (SQLite rows vs MySQL rows):\n";
$allMatch = true;
foreach (array_keys($tables) as $t) {
    $inSqlite = $sqlite->query("SELECT name FROM sqlite_master WHERE type='table' AND name=" . $sqlite->quote($t))->fetch();
    if (!$inSqlite) {
        continue;
    }
    $a = (int)$sqlite->query("SELECT COUNT(*) AS c FROM {$t}")->fetch()['c'];
    $b = (int)$mysql->query("SELECT COUNT(*) AS c FROM {$t}")->fetch()['c'];
    $ok = ($a <= $b);
    $allMatch = $allMatch && $ok;
    echo '  ' . str_pad($t, 17) . str_pad((string)$a, 8) . str_pad((string)$b, 8) . ($ok ? 'OK' : 'MISMATCH') . "\n";
}

echo $allMatch
    ? "\nDone. Now set 'driver' => 'mysql' in config/db_credentials.php. The SQLite file was not touched - keep it as a backup.\n"
    : "\nCounts do not match - do NOT switch to MySQL yet. Re-run this script and read the messages above.\n";
exit($allMatch ? 0 : 1);
