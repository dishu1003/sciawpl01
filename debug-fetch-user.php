<?php
require_once 'includes/init.php'; // यह ensure करेगा $pdo और session loaded हैं

try {
    $stmt = $pdo->prepare("SELECT id, username, password, status FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "User not found or query returned no rows\n";
    } else {
        echo "User found:\n";
        print_r($user);
    }
} catch (PDOException $e) {
    echo "PDOException: " . htmlspecialchars($e->getMessage());
    error_log("DEBUG PDOException in debug-fetch-user: " . $e->getMessage());
}