
<?php
// Run from the command line only.
if (PHP_SAPI !== 'cli') {
    exit("Run this script using Command Prompt.\n");
}

$source = __DIR__ . '/../data/powerforge.sqlite';

if (!is_file($source)) {
    exit("SQLite database not found: $source\n");
}

try {
    // Read-only connection: this script will not modify SQLite.
    $db = new PDO('sqlite:file:' . str_replace('\\', '/', realpath($source)) . '?mode=ro');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tables = [
        'users',
        'gear',
        'inventory',
        'energy_logs',
        'admin_audit_log',
        'login_attempts'
    ];

    $output = __DIR__ . '/../tools/volttech_data.json';
    $export = [];

    foreach ($tables as $table) {
        $stmt = $db->query("SELECT * FROM `$table`");
        $export[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo $table . ': ' . count($export[$table]) . " records\n";
    }

    $json = json_encode(
        $export,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );

    if ($json === false) {
        exit("JSON export failed.\n");
    }

    if (file_put_contents($output, $json) === false) {
        exit("Could not write export file.\n");
    }

    echo "\nExport created: $output\n";
    echo "Original SQLite database was opened read-only.\n";

} catch (Throwable $e) {
    exit("Export failed: " . $e->getMessage() . "\n");
}