<?php
/**
 * CSRF Protection Class
 * Generates and validates CSRF tokens
 */

class CSRF {
    private static $token_name = 'csrf_token';
    private static $token_time_name = 'csrf_token_time';
    private static $token_lifetime = 3600; // 1 hour

    /**
     * Generate CSRF token
     */
    public static function generateToken() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION[self::$token_name] = $token;
        $_SESSION[self::$token_time_name] = time();
        
        return $token;
    }

    /**
     * Get current CSRF token (generate if not exists)
     */
    public static function getToken() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION[self::$token_name]) || self::isTokenExpired()) {
            return self::generateToken();
        }

        return $_SESSION[self::$token_name];
    }

    /**
     * Check if token is expired
     */
    private static function isTokenExpired() {
        if (!isset($_SESSION[self::$token_time_name])) {
            return true;
        }

        return (time() - $_SESSION[self::$token_time_name]) > self::$token_lifetime;
    }

    /**
     * Validate CSRF token
     */
    public static function validateToken($token) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION[self::$token_name])) {
            return false;
        }

        if (self::isTokenExpired()) {
            return false;
        }

        return hash_equals($_SESSION[self::$token_name], $token);
    }

    /**
     * Validate token from POST request
     */
    public static function validateRequest() {
        $token = $_POST[self::$token_name] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        
        if (!self::validateToken($token)) {
            http_response_code(403);
            die('CSRF token validation failed. Please refresh the page and try again.');
        }

        return true;
    }

    /**
     * Generate hidden input field for forms
     */
    public static function inputField() {
        $token = self::getToken();
        return '<input type="hidden" name="' . self::$token_name . '" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Get token for AJAX requests
     */
    public static function getTokenForAjax() {
        return [
            'name' => self::$token_name,
            'value' => self::getToken()
        ];
    }
}
?>
