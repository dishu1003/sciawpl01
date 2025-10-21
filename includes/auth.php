<?php
/**
 * Authentication System
 * Handles user login, logout, and access control
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/security.php';

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['session_token']);
}

/**
 * Require user to be logged in
 */
function require_login() {
    if (!is_logged_in()) {
        Logger::warning('Unauthorized access attempt', [
            'ip' => $_SERVER['REMOTE_ADDR'],
            'uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown'
        ]);
        header('Location: /login.php');
        exit;
    }
}

/**
 * Check if user is admin
 */
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Require user to be admin
 */
function require_admin() {
    require_login();
    if (!is_admin()) {
        Logger::security('Non-admin tried to access admin area', [
            'user_id' => $_SESSION['user_id'] ?? 'Unknown',
            'username' => $_SESSION['username'] ?? 'Unknown',
            'ip' => $_SERVER['REMOTE_ADDR'],
            'uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown'
        ]);
        http_response_code(403);
        die('Access denied. Admin only.');
    }
}

/**
 * Get current logged in user
 */
function get_logged_in_user() {
    if (!is_logged_in()) return null;

    try {
        $pdo = get_pdo_connection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        Logger::error('Error fetching current user', [
            'user_id' => $_SESSION['user_id'],
            'error' => $e->getMessage()
        ]);
        return null;
    }
}

/**
 * Login user with rate limiting and logging
 */
function login_user($username, $password) {
    $pdo = get_pdo_connection();

    // Rate limiting - 5 attempts per 15 minutes, block for 30 minutes
    $rateLimiter = new RateLimiter($pdo, 'login_attempt');
    if (!$rateLimiter->check(5, 900, 1800)) {
        Logger::security('Login rate limit exceeded', [
            'username' => $username,
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
        return false;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['unique_ref'] = $user['unique_ref'];
            $_SESSION['session_token'] = bin2hex(random_bytes(32));
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();

            // Update last login
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);

            Logger::info('User logged in successfully', [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);

            return true;
        } else {
            Logger::warning('Failed login attempt', [
                'username' => $username,
                'ip' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
            return false;
        }
    } catch (PDOException $e) {
        Logger::error('Database error during login', [
            'username' => $username,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Logout user and destroy session
 */
function logout_user() {
    if (isset($_SESSION['user_id'])) {
        Logger::info('User logged out', [
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? 'Unknown',
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
    }

    // Clear all session variables
    $_SESSION = array();

    // Delete session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    // Destroy session
    session_destroy();

    header('Location: /login.php');
    exit;
}

/**
 * Check session timeout (30 minutes of inactivity)
 */
function check_session_timeout() {
    $timeout = 1800; // 30 minutes

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        Logger::info('Session timeout', [
            'user_id' => $_SESSION['user_id'] ?? 'Unknown',
            'username' => $_SESSION['username'] ?? 'Unknown',
            'last_activity' => $_SESSION['last_activity']
        ]);
        logout_user();
    }

    $_SESSION['last_activity'] = time();
}