<?php
require_once 'includes/init.php';
require_once 'includes/auth.php';

$username = 'adminr';
$password = '12345678';

try {
    $ok = login_user($username, $password);
    echo "login_user returned: " . ($ok ? 'true' : 'false') . "\n";
    echo "Session active: " . session_status() . " id=" . session_id() . "\n";
    echo " \$_SESSION:\n";
    print_r($_SESSION);
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    error_log("Debug login exception: " . $e->getMessage());
}