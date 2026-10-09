<?php
$db = new PDO('sqlite:' . __DIR__ . '/data/powerforge.sqlite');

$tables = $db->query(
    "SELECT name FROM sqlite_master
     WHERE type = 'table'
     AND name NOT LIKE 'sqlite_%'
     ORDER BY name"
)->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    $stmt = $db->query('SELECT COUNT(*) FROM "' . $table . '"');
    echo $table . ': ' . $stmt->fetchColumn() . " rows" . PHP_EOL;
}