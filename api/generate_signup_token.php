<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

try {
    $pdo = get_pdo_connection();

    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 day'));

    $stmt = $pdo->prepare("INSERT INTO signup_tokens (token, expires_at) VALUES (?, ?)");
    $stmt->execute([$token, $expires_at]);

    echo json_encode(['success' => true, 'token' => $token]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to generate token.']);
}
