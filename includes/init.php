<?php
// includes/init.php

// Load environment variables
require_once __DIR__ . '/env.php';

// Auto-detect HTTPS
$secure = false;
if (
    (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
) {
    $secure = true;
}

// Start session early with stable cookie settings
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Load main configuration
require_once __DIR__ . '/../config/config.php';

// Load database configuration
require_once __DIR__ . '/../config/database.php';

// Load security classes
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/validator.php';

// Utility function for HTML escaping
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Preserve referral ID
if (!empty($_GET['ref'])) {
    $_SESSION['ref'] = h($_GET['ref']);
}

// Development: show errors (only on dev server)
if (defined('APP_DEBUG') && APP_DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}
?>