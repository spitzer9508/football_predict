<?php

require dirname(__DIR__) . '/bootstrap.php';

use App\Support\Database;

try {
    $pdo = Database::connection();
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "Database connection successful.\n";
    echo "Server version: " . $version . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Database connection failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
