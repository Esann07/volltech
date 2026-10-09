<?php
/**
 * VoltTech - database layer (shared by the player app and the admin app)
 *
 * One file decides how the whole project reaches its database:
 *   - 'sqlite' (default): a single file, data/powerforge.sqlite.
 *   - 'mysql'           : recommended when many people use the site at once.
 *
 * Settings come from config/db_credentials.php (copy it from
 * db_credentials.example.php). If that file does not exist the app falls
 * back to SQLite with errors hidden from visitors.
 *
 * IMPORTANT: this code never deletes or resets an existing database. A
 * brand-new database is only created (and the starter gear catalogue
 * inserted) when the SQLite file is missing or has no "users" table.
 * The internal file name "powerforge.sqlite" is intentionally unchanged.
 */

function appRoot(): string {
    return dirname(__DIR__);
}

function dbConfig(): array {
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $defaults = [
        'debug'       => false,   // true = show PHP errors on screen (local development only)
        'driver'      => 'sqlite',
        'sqlite_path' => null,    // null = <project>/data/powerforge.sqlite
        'force_https' => false,   // true = redirect http:// to https:// (turn on once SSL works)
        'trust_proxy' => false,   // true only if you are behind a proxy/CDN that sets X-Forwarded-*
        'mysql'       => [
            'host'    => '127.0.0.1',
            'port'    => 3306,
            'dbname'  => 'powerforge',
            'user'    => 'root',
            'pass'    => '',
            'charset' => 'utf8mb4',
        ],
    ];

    // The credentials file can live outside the web folder: point the
    // VOLTTECH_CONFIG environment variable at it.
    $path = getenv('VOLTTECH_CONFIG');
    if (!is_string($path) || $path === '') {
        $path = __DIR__ . '/db_credentials.php';
    }

    $loaded = is_file($path) ? require $path : [];
    if (!is_array($loaded)) {
        $loaded = [];
    }

    $config = array_replace($defaults, $loaded);
    $config['mysql'] = array_replace(
        $defaults['mysql'],
        (isset($loaded['mysql']) && is_array($loaded['mysql'])) ? $loaded['mysql'] : []
    );
    return $config;
}

function sqliteDbPath(): string {
    $p = dbConfig()['sqlite_path'];
    if (is_string($p) && $p !== '') {
        // Absolute path (Unix "/..." or Windows "C:\..." / "\\server") is used as-is.
        if (preg_match('#^([A-Za-z]:[\\\\/]|[\\\\/])#', $p)) {
            return $p;
        }
        return appRoot() . '/' . $p;
    }
    return appRoot() . '/data/powerforge.sqlite';
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $config = dbConfig();

    if (($config['driver'] ?? 'sqlite') === 'mysql') {
        $m = $config['mysql'];
        $dsn = 'mysql:host=' . $m['host'] . ';port=' . (int)$m['port']
             . ';dbname=' . $m['dbname'] . ';charset=' . $m['charset'];
        $pdo = new PDO($dsn, (string)$m['user'], (string)$m['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Make rowCount() report rows MATCHED (like SQLite), not only rows changed.
            PDO::MYSQL_ATTR_FOUND_ROWS   => true,
        ]);
        ensureExtraTables($pdo, 'mysql');
        return $pdo;
    }

    // ---- SQLite ----
    $dbPath = sqliteDbPath();
    $dir = dirname($dbPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');   // wait up to 5s instead of failing when another request is writing
    $pdo->exec('PRAGMA journal_mode = WAL');

    $hasUsers = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetch();
    if (!$hasUsers) {
        seedDatabase($pdo);
    }
    ensureExtraTables($pdo, 'sqlite');

    return $pdo;
}

/**
 * Additive-only tables used by the admin system and by login throttling.
 * Never touches users / gear / inventory / energy_logs. Failures are ignored
 * on purpose (e.g. a MySQL account without CREATE rights - in that case the
 * tables come from config/mysql_schema.sql).
 */
function ensureExtraTables(PDO $pdo, string $driver): void {
    try {
        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS admin_audit_log (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    admin_id INT UNSIGNED NOT NULL,
                    action VARCHAR(50) NOT NULL,
                    target VARCHAR(255),
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS login_attempts (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    scope VARCHAR(20) NOT NULL,
                    ip VARCHAR(45) NOT NULL,
                    attempted_at INT UNSIGNED NOT NULL,
                    INDEX idx_login_attempts (scope, ip, attempted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS admin_audit_log (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    admin_id INTEGER NOT NULL,
                    action TEXT NOT NULL,
                    target TEXT,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS login_attempts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    scope TEXT NOT NULL,
                    ip TEXT NOT NULL,
                    attempted_at INTEGER NOT NULL
                )
            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_login_attempts ON login_attempts (scope, ip, attempted_at)");
        }
    } catch (Throwable $e) {
        error_log('[VoltTech] could not ensure extra tables: ' . $e->getMessage());
    }
}

/** Creates the original schema + starter catalogue. Only runs on an empty database. */
function seedDatabase(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'hero',
            credits INTEGER NOT NULL DEFAULT 500,
            max_energy INTEGER NOT NULL DEFAULT 100,
            current_energy INTEGER NOT NULL DEFAULT 100,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
    ");

    $pdo->exec("
        CREATE TABLE gear (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            category TEXT NOT NULL,
            description TEXT NOT NULL,
            price INTEGER NOT NULL,
            energy_cost INTEGER NOT NULL,
            power_rating INTEGER NOT NULL,
            icon TEXT NOT NULL DEFAULT ''
        );
    ");

    $pdo->exec("
        CREATE TABLE inventory (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            gear_id INTEGER NOT NULL,
            equipped INTEGER NOT NULL DEFAULT 0,
            acquired_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (gear_id) REFERENCES gear(id) ON DELETE CASCADE
        );
    ");

    $pdo->exec("
        CREATE TABLE energy_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            change_amount INTEGER NOT NULL,
            reason TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");

    $gear = [
        ['Kinetic Weave Suit',   'suit',      'Full-body suit that redistributes impact force. Reduces damage taken in combat.', 220, 15, 78, ''],
        ['Thermal Flux Jacket',  'suit',      'Regulates body heat for fire/ice-based powers, preventing energy overdraw.',       180, 10, 65, ''],
        ['Nano-Plating Armor',   'suit',      'Self-repairing armor plates woven with power-conductive nanofiber.',             340, 22, 90, ''],
        ['Null Cuffs',           'dampener',  'Wrist restraints that suppress offensive powers by up to 80%. Standard issue for containment units.', 150, 5, 60, ''],
        ['Signal Collar',        'dampener',  'Neck-worn device that mutes telepathic and telekinetic broadcasts in a 10m radius.', 200, 8, 55, ''],
        ['Overload Choker',      'dampener',  'Emergency dampener that forcibly caps energy output to prevent self-harm from overdraw.', 260, 12, 70, ''],
        ['Grapple Gauntlets',    'gadget',    'Wrist-mounted grapple line launcher, useful for both heroes and villains on the move.', 95,  4, 40, ''],
        ['Bio-Energy Cell',      'gadget',    'Portable rechargeable cell that stores excess bio-energy for later use.',         130, 0,  50, ''],
        ['Signal Jammer Disc',   'gadget',    'Throwable disc that disrupts nearby comms and tracking devices for 60 seconds.',  75,  6, 35, ''],
        ['Adaptive Visor',       'gadget',    'HUD visor that reads opponents\' power signatures in real time.',                 210, 9, 72, ''],
    ];
    $stmt = $pdo->prepare("INSERT INTO gear (name, category, description, price, energy_cost, power_rating, icon) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($gear as $g) {
        $stmt->execute($g);
    }
}

// ---- Error display policy (driven by the 'debug' flag in db_credentials.php) ----
// Production must never show stack traces, file paths or SQL errors to visitors.
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', (PHP_SAPI === 'cli' || !empty(dbConfig()['debug'])) ? '1' : '0');
