
<?php
$db = new PDO('sqlite:' . __DIR__ . '/data/powerforge.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tables = $db->query(
    "SELECT name FROM sqlite_master
     WHERE type = 'table'
     AND name NOT LIKE 'sqlite_%'
     ORDER BY name"
)->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    echo "\nTABLE: " . $table . PHP_EOL;

    $columns = $db->query(
        'PRAGMA table_info("' . $table . '")'
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $column) {
        echo '  ' . $column['name']
            . ' (' . $column['type'] . ')' . PHP_EOL;
    }
}