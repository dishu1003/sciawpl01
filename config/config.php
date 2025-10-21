<?php
// config/config.php

// env.php is required for the env() function
require_once __DIR__ . '/../includes/env.php';

// --- Application Settings ---
define('SITE_NAME', env('SITE_NAME', 'Spartan Community'));
define('SITE_URL', env('SITE_URL', 'http://localhost'));
define('APP_DEBUG', env('APP_DEBUG', false));
define('TIMEZONE', env('TIMEZONE', 'Asia/Kolkata'));

// --- Database Credentials ---
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_NAME', env('DB_NAME', 'spartan'));

// --- Security Settings ---
define('ENCRYPTION_KEY', env('ENCRYPTION_KEY', '')); // Fallback to empty, but should be set in .env
define('WEBHOOK_SECRET', env('WEBHOOK_SECRET', ''));
define('SESSION_SECRET', env('SESSION_SECRET', ''));
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('LOGIN_ATTEMPT_LIMIT', 5);
define('LOGIN_ATTEMPT_WINDOW', 900); // 15 minutes
define('LOGIN_LOCKOUT_PERIOD', 1800); // 30 minutes

// Set timezone
date_default_timezone_set(TIMEZONE);

// --- Helper Functions ---

/**
 * Encrypt data
 * @param string $data
 * @return string
 */
function encrypt_data($data) {
    if (!ENCRYPTION_KEY) return $data; // Or throw an exception
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

/**
 * Decrypt data
 * @param string $data
 * @return string
 */
function decrypt_data($data) {
    if (!ENCRYPTION_KEY) return $data; // Or throw an exception
    try {
        list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
        if (!$encrypted_data || !$iv) {
            return false; // Invalid data format
        }
        return openssl_decrypt($encrypted_data, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
    } catch (Exception $e) {
        // Log the error
        error_log('Decryption failed: ' . $e->getMessage());
        return false;
    }
}
