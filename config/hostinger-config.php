<?php
/**
 * Hostinger Configuration
 * Database settings for Hostinger hosting
 */

// Hostinger Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'u782093275_awpl');
define('DB_USER', 'u782093275_awpl');
define('DB_PASS', 'Vktmdp@2025');

// Site Configuration
define('SITE_URL', 'https://your-domain.com');  // Replace with your actual domain
define('SITE_NAME', 'SpartanCommunityIndia');
define('SITE_DOMAIN', 'your-domain.com');  // Replace with your actual domain

// Security
define('ENCRYPTION_KEY', 'your-secret-encryption-key-change-this-in-production');
define('WEBHOOK_SECRET', 'your-webhook-secret-here');

// App Configuration
define('APP_ENV', 'production');
define('APP_DEBUG', false);
define('TIMEZONE', 'Asia/Kolkata');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please contact support.");
}
?>
