<?php

require dirname(__DIR__) . '/bootstrap.php';

use App\Support\Database;

$schemaFile = dirname(__DIR__) . '/database/schema.sql';
$schema = file_get_contents($schemaFile);

if ($schema === false || trim($schema) === '') {
    fwrite(STDERR, "Could not read database/schema.sql\n");
    exit(1);
}

try {
    Database::connection()->exec($schema);
    echo "Football Predict database schema installed successfully.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Schema installation failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
