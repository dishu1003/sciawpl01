<?php
require_once __DIR__ . '/../includes/init.php';

try {
    $pdo = get_pdo_connection();
    $sql = file_get_contents(__DIR__ . '/../migrations/add_signup_tokens_table.sql');
    $pdo->exec($sql);
    echo "Migration 'add_signup_tokens_table.sql' applied successfully.\n";
} catch (PDOException $e) {
    // Check if the table already exists to avoid fatal errors on re-run
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "Migration 'add_signup_tokens_table.sql' was already applied.\n";
    } else {
        echo "Error applying migration: " . $e->getMessage() . "\n";
    }
}
