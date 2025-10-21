<?php
// debug-login-flow.php  (DELETE THIS FILE AFTER DEBUGGING)

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: text/plain; charset=utf-8');

$user = $_GET['user'] ?? 'admin';
$pass = $_GET['pass'] ?? '12345678';
$do_run_login_user = isset($_GET['run']) && $_GET['run'] == '1';

echo "DEBUG LOGIN FLOW\n";
echo "================\n\n";

echo "1) Environment & Session\n";
echo "------------------------\n";
echo "PHP version: " . PHP_VERSION . "\n";
echo "APP_DEBUG: " . (function_exists('env') ? var_export(env('APP_DEBUG', 'false'), true) : "env() not available") . "\n";
echo "Session status: " . session_status() . " (PHP_SESSION_ACTIVE=" . PHP_SESSION_ACTIVE . ")\n";
echo "Session ID: " . session_id() . "\n";
echo "Session cookie params: \n";
print_r(session_get_cookie_params());
echo "\n";

echo "2) PDO / Database\n";
echo "------------------\n";
try {
    $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
    $dbUser = $pdo->query("SELECT USER()")->fetchColumn();
    echo "Connected database: {$dbName}\n";
    echo "Connected as user: {$dbUser}\n";
} catch (Exception $e) {
    echo "PDO fetch error: " . $e->getMessage() . "\n";
    exit;
}
echo "\n";

echo "3) Fetch user row for '{$user}'\n";
echo "--------------------------------\n";
try {
    $stmt = $pdo->prepare("SELECT id, username, password, status, role, unique_ref FROM users WHERE username = ?");
    $stmt->execute([$user]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
        echo "User not found in DB.\n\n";
    } else {
        echo "User found:\n";
        echo " id: " . $u['id'] . "\n";
        echo " username: " . $u['username'] . "\n";
        echo " status: " . $u['status'] . "\n";
        echo " role: " . $u['role'] . "\n";
        echo " unique_ref: " . ($u['unique_ref'] ?? '') . "\n";
        $hash = $u['password'] ?? '';
        echo " password hash (masked): " . ($hash ? substr($hash,0,6) . '...' . ' (len=' . strlen($hash) . ')' : '[empty]') . "\n\n";
    }
} catch (PDOException $e) {
    echo "PDOException during user fetch: " . $e->getMessage() . "\n";
    exit;
}

if (!$u) {
    echo "=> STOP: No user to verify. Create user or check username.\n";
    exit;
}

echo "4) password_verify test\n";
echo "------------------------\n";
$hash = $u['password'] ?? '';
if ($hash === '') {
    echo "Password hash is empty in DB.\n\n";
} else {
    $pv = password_verify($pass, $hash);
    echo "password_verify('" . $pass . "', hash) => " . ($pv ? "TRUE" : "FALSE") . "\n\n";
}

echo "5) RateLimiter check (if available)\n";
echo "-----------------------------------\n";
if (class_exists('RateLimiter')) {
    try {
        $rl = new RateLimiter($pdo, 'login_attempt_debug');
        $allowed = $rl->check(5, 900, 1800);
        echo "RateLimiter::check(5,900,1800) => " . ($allowed ? "ALLOWED" : "BLOCKED") . "\n";
    } catch (Throwable $e) {
        echo "RateLimiter threw exception: " . $e->getMessage() . "\n";
    }
} else {
    echo "RateLimiter class not found. (This may block actual login_user() if login_user expects it.)\n";
}
echo "\n";

echo "6) Optional: call login_user() (will attempt real login and update DB last_login)\n";
echo "-------------------------------------------------------------------------------\n";
if ($do_run_login_user) {
    // If RateLimiter class missing, optionally create a permissive stub for testing.
    if (!class_exists('RateLimiter')) {
        echo "Note: RateLimiter not found — creating temporary permissive stub for test (development only).\n";
        class RateLimiter {
            private $pdo; private $name;
            public function __construct($pdo, $name = '') { $this->pdo = $pdo; $this->name = $name; }
            public function check($maxAttempts, $windowSeconds, $blockSeconds) { return true; }
        }
    }

    try {
        $res = login_user($user, $pass);
        echo "login_user returned: " . ($res ? "TRUE" : "FALSE") . "\n";
        echo "Session after login:\n";
        print_r($_SESSION);
    } catch (Throwable $e) {
        echo "Exception when calling login_user(): " . $e->getMessage() . "\n";
        error_log("Debug: login_user exception: " . $e->getMessage());
    }
} else {
    echo "To run login_user(), re-open this URL with &run=1\n";
}
echo "\n\n";
echo "DEBUG COMPLETE. DELETE THIS FILE WHEN DONE.\n";