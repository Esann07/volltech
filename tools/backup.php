<?php
/**
 * VoltTech - make a consistent backup of the SQLite database.
 *
 *     php tools/backup.php                 -> data/backups/volttech-YYYYMMDD-HHMMSS.sqlite
 *     php tools/backup.php /path/to/dir    -> same, in another folder (e.g. outside the web folder)
 *
 * Uses SQLite's "VACUUM INTO", which produces a complete, consistent copy even while the
 * site is running (copying the .sqlite file by hand can miss data that is still in the
 * -wal file). The newest 14 backups made by this tool are kept; older ones are removed.
 *
 * Run it daily from cron, for example:
 *     15 3 * * *  /usr/bin/php /home/you/volttech/tools/backup.php /home/you/volttech-backups
 *
 * MySQL: use mysqldump instead, e.g.
 *     mysqldump --single-transaction -u USER -p DBNAME > volttech-$(date +%F).sql
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("This script can only be run from the command line.\n");
}

require_once __DIR__ . '/../config/database.php';

if ((dbConfig()['driver'] ?? 'sqlite') !== 'sqlite') {
    fwrite(STDERR, "The database driver is MySQL. Use mysqldump (see the comment at the top of this file).\n");
    exit(1);
}
if (!is_file(sqliteDbPath())) {
    fwrite(STDERR, "No database found at " . sqliteDbPath() . "\n");
    exit(1);
}

$dir = $argv[1] ?? (appRoot() . '/data/backups');
if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
    fwrite(STDERR, "Cannot create backup folder: {$dir}\n");
    exit(1);
}
$dest = rtrim($dir, '/\\') . '/volttech-' . date('Ymd-His') . '.sqlite';

try {
    getDB()->prepare('VACUUM INTO ?')->execute([$dest]);
} catch (Throwable $e) {
    fwrite(STDERR, "Backup failed: " . $e->getMessage() . "\n(VACUUM INTO needs SQLite 3.27 or newer.)\n");
    exit(1);
}
echo "Backup written: {$dest} (" . number_format((int)filesize($dest)) . " bytes)\n";

// Keep only the newest 14 backups made by this tool.
$files = glob(rtrim($dir, '/\\') . '/volttech-*.sqlite') ?: [];
rsort($files);
foreach (array_slice($files, 14) as $old) {
    @unlink($old);
    echo "Removed old backup: {$old}\n";
}
