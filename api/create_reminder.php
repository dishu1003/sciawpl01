<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_team_access();

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



header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success'=>false,'error'=>'unauth']); exit;
}

$reminder_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$date = $_POST['reminder_date'] ?? '';
$time = $_POST['reminder_time'] ?? '';
$lead_id = isset($_POST['lead_id']) ? (int)$_POST['lead_id'] : 0;

if (!$date || !$time || !$lead_id) {
    echo json_encode(['success'=>false,'error'=>'missing_fields']); exit;
}

// combine datetime
$dt = date('Y-m-d H:i:s', strtotime($date . ' ' . $time));

try {
    $pdo = get_pdo_connection();

    // ensure lead belongs to user or user is admin
    $stmt = $pdo->prepare("SELECT assigned_to FROM leads WHERE id = ?");
    $stmt->execute([$lead_id]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$lead) { echo json_encode(['success'=>false,'error'=>'lead_not_found']); exit; }
    if ($lead['assigned_to'] != $user_id && $_SESSION['role'] !== 'admin') {
        echo json_encode(['success'=>false,'error'=>'not_allowed']); exit;
    }

    if ($reminder_id) {
        // update
        $upd = $pdo->prepare("UPDATE reminders SET lead_id = ?, reminder_time = ?, is_sent = 0 WHERE id = ? AND user_id = ?");
        $upd->execute([$lead_id, $dt, $reminder_id, $user_id]);
    } else {
        $ins = $pdo->prepare("INSERT INTO reminders (lead_id, user_id, reminder_time, is_sent) VALUES (?, ?, ?, 0)");
        $ins->execute([$lead_id, $user_id, $dt]);
    }

    echo json_encode(['success'=>true]);
} catch (PDOException $e) {
    error_log('create_reminder error: ' . $e->getMessage());
    echo json_encode(['success'=>false,'error'=>'db']);
}
}