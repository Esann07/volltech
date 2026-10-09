
<?php
// Convert the exported JSON data into a MySQL SQL import file.
// This script does not connect to or modify either database.

if (PHP_SAPI !== 'cli') {
    exit("Run this script using CMD or PowerShell.\n");
}

$jsonFile = __DIR__ . '/volttech_data.json';
$sqlFile  = __DIR__ . '/volttech_import.sql';

if (!is_file($jsonFile)) {
    exit("Export JSON file not found.\n");
}

$data = json_decode(file_get_contents($jsonFile), true);

if (!is_array($data)) {
    exit("Could not read the JSON export.\n");
}

$tables = [
    'users',
    'gear',
    'inventory',
    'energy_logs',
    'admin_audit_log',
    'login_attempts'
];

$intColumns = [
    'id', 'admin_id', 'user_id', 'gear_id',
    'credits', 'max_energy', 'current_energy',
    'price', 'energy_cost', 'power_rating',
    'change_amount', 'equipped', 'attempted_at'
];

function sqlValue($value, $column, $intColumns) {
    if ($value === null) {
        return 'NULL';
    }

    if (in_array($column, $intColumns, true)) {
        if (!is_numeric($value)) {
            throw new RuntimeException(
                "Invalid number in column: $column"
            );
        }
        return (string)(int)$value;
    }

    // Escape text for a standard MySQL SQL import.
    $value = str_replace(
        ["\\", "\0", "\n", "\r", "'", "\x1a"],
        ["\\\\", "\\0", "\\n", "\\r", "\\'", "\\Z"],
        (string)$value
    );

    return "'" . $value . "'";
}

$sql = "SET NAMES utf8mb4;\n";
$sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
$total = 0;

try {
    foreach ($tables as $table) {
        if (!isset($data[$table]) || !is_array($data[$table])) {
            throw new RuntimeException("Missing table data: $table");
        }

        foreach ($data[$table] as $row) {
            if (!is_array($row) || !$row) {
                throw new RuntimeException("Invalid row in $table");
            }

            $columns = array_keys($row);

            $columnSql = implode(
                ', ',
                array_map(
                    fn($column) => '`' . str_replace('`', '``', $column) . '`',
                    $columns
                )
            );

            $values = [];
            foreach ($row as $column => $value) {
                $values[] = sqlValue($value, $column, $intColumns);
            }

            $sql .= "INSERT INTO `$table` ($columnSql) VALUES (";
            $sql .= implode(', ', $values);
            $sql .= ");\n";

            $total++;
        }

        echo "$table: " . count($data[$table]) . " records prepared\n";
    }

    $sql .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";

    if (file_put_contents($sqlFile, $sql) === false) {
        throw new RuntimeException("Could not write SQL file.");
    }

    echo "\nSQL file created: $sqlFile\n";
    echo "Total records prepared: $total\n";
    echo "No database was changed by this converter.\n";

} catch (Throwable $e) {
    exit("Conversion failed: " . $e->getMessage() . "\n");
}