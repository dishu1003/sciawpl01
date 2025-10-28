<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lead_id = $_POST['lead_id'];
    $reminder_date = $_POST['reminder_date'];
    $reminder_time = $_POST['reminder_time'];
    $reminder_datetime = $reminder_date . ' ' . $reminder_time;
    $user_id = $_SESSION['user_id'];

    try {
        $pdo = get_pdo_connection();
        $stmt = $pdo->prepare("INSERT INTO reminders (lead_id, user_id, reminder_time) VALUES (?, ?, ?)");
        $stmt->execute([$lead_id, $user_id, $reminder_datetime]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create reminder.']);
    }
}
