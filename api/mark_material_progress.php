<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

$pdo = get_pdo_connection();
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success'=>false,'error'=>'auth']); exit;
}

$raw = $_POST ?? [];
$material_id = intval($raw['material_id'] ?? 0);
$progress = intval($raw['progress_percent'] ?? 0); // 0..100

if (!$material_id || $progress < 0 || $progress > 100) {
    echo json_encode(['success'=>false,'error'=>'invalid']); exit;
}

try {
    // upsert progress
    $stmt = $pdo->prepare("SELECT id, progress_percent FROM training_progress WHERE user_id = ? AND material_id = ?");
    $stmt->execute([$user_id, $material_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // update only if new progress greater
        if ($progress > (int)$row['progress_percent']) {
            $upd = $pdo->prepare("UPDATE training_progress SET progress_percent = ?, last_watched_at = NOW() WHERE id = ?");
            $upd->execute([$progress, $row['id']]);
        } else {
            // update timestamp only
            $upd = $pdo->prepare("UPDATE training_progress SET last_watched_at = NOW() WHERE id = ?");
            $upd->execute([$row['id']]);
        }
    } else {
        $ins = $pdo->prepare("INSERT INTO training_progress (user_id, material_id, progress_percent) VALUES (?, ?, ?)");
        $ins->execute([$user_id, $material_id, $progress]);

        // increment view_count once for first time view
        $inc = $pdo->prepare("UPDATE training_materials SET view_count = view_count + 1 WHERE id = ?");
        $inc->execute([$material_id]);
    }

    // calculate completion flag
    $completed = ($progress >= 100) ? 1 : 0;

    echo json_encode(['success'=>true, 'completed'=>$completed]);
} catch (PDOException $e) {
    error_log('mark_material_progress error: '.$e->getMessage());
    echo json_encode(['success'=>false,'error'=>'db']);
}
