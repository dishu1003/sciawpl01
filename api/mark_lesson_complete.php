<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
$pdo = get_pdo_connection();
$user_id = $_SESSION['user_id'] ?? null;
$raw = json_decode(file_get_contents('php://input'), true);
$lesson_id = intval($raw['lesson_id'] ?? 0);
if (!$user_id || !$lesson_id) { echo json_encode(['success'=>false]); exit; }
$stmt = $pdo->prepare("SELECT material_id FROM lessons WHERE id = ?");
$stmt->execute([$lesson_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) { echo json_encode(['success'=>false]); exit; }
$mid = intval($row['material_id']);
if (!$mid) {
    // no material - create a dummy progress entry as completed
    $ins = $pdo->prepare("INSERT INTO training_progress (user_id, material_id, progress_percent, last_watched_at) VALUES (?, 0, 100, NOW()) ON DUPLICATE KEY UPDATE progress_percent = 100, last_watched_at = NOW()");
    $ins->execute([$user_id]);
    echo json_encode(['success'=>true]);
    exit;
}
// call existing mark progress API or inline update
$upd = $pdo->prepare("INSERT INTO training_progress (user_id, material_id, progress_percent, last_watched_at) VALUES (?, ?, 100, NOW()) ON DUPLICATE KEY UPDATE progress_percent = 100, last_watched_at = NOW()");
$upd->execute([$user_id, $mid]);
echo json_encode(['success'=>true]);
