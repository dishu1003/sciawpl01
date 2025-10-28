<?php
/**
 * Authentication System
 * Handles user login, logout, and access control
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/security.php';

// ✅ Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
 * ✅ Check if user is a TEAM member
 */
function require_team_access() {
    require_login(); // ensure user is logged in
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'team') {
        Logger::security('Non-team tried to access team area', [
            'user_id' => $_SESSION['user_id'] ?? 'Unknown',
            'role' => $_SESSION['role'] ?? 'Unknown',
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
        header("Location: /unauthorized.php");
        exit();
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
            'ip' => $_SERVER['REMOTE_ADDR']
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
            'user_id' => $_SESSION['user_id'] ?? 'Unknown',
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
    $ip = $_SERVER['REMOTE_ADDR'];
    $rateLimitKey = "login_attempt_" . $ip;
    $attempts = $_SESSION[$rateLimitKey] ?? 0;
    $lastAttempt = $_SESSION[$rateLimitKey . "_time"] ?? 0;

    // Reset after 15 min
    if (time() - $lastAttempt > 900) $attempts = 0;

    // Block if 5 attempts
    if ($attempts >= 5) {
        Logger::security('Login rate limit exceeded', ['ip' => $ip, 'username' => $username]);
        return false;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['referral_code'] = $user['referral_code'];
            $_SESSION['session_token'] = bin2hex(random_bytes(32));
            $_SESSION['login_time'] = $_SESSION['last_activity'] = time();

            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);

            unset($_SESSION[$rateLimitKey], $_SESSION[$rateLimitKey . "_time"]);

            Logger::info('User logged in successfully', [
                'user_id' => $user['id'], 'username' => $user['username'], 'role' => $user['role']
            ]);
            return true;
        }

        $_SESSION[$rateLimitKey] = $attempts + 1;
        $_SESSION[$rateLimitKey . "_time"] = time();
        Logger::warning('Failed login attempt', ['username' => $username, 'ip' => $ip]);
        return false;

    } catch (PDOException $e) {
        Logger::error('Database error during login', ['error' => $e->getMessage()]);
        return false;
    }
}

/**
 * Logout user and destroy session
 */
function logout_user() {
    if (isset($_SESSION['user_id'])) {
        Logger::info('User logged out', ['user_id' => $_SESSION['user_id']]);
    }

    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();

    header('Location: /login.php');
    exit;
}

/**
 * ✅ Check session timeout (1 hour inactivity)
 */
function check_session_timeout() {
    $timeout = 3600; // 1 hour
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        Logger::info('Session timeout', ['user_id' => $_SESSION['user_id'] ?? 'Unknown']);
        logout_user();
    }
    $_SESSION['last_activity'] = time();
}
?>
