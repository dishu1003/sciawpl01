<?php
require_once 'includes/init.php';
require_once 'config/database.php';

$username = 'admin';
$password = '12345678';

$stmt = $pdo->prepare("SELECT password FROM users WHERE username = ?");
$stmt->execute([$username]);
$row = $stmt->fetch();

if (!$row) {
    echo "User not found\n";
    exit;
}

$hash = $row['password'];
if (password_verify($password, $hash)) {
    echo "password_verify: OK\n";
} else {
    echo "password_verify: FAILED\n";
}

$rateLimiter = new RateLimiter($pdo, 'login_attempt');
if (!$rateLimiter->check(5, 900, 1800)) {
    // blocked
    return false;
}