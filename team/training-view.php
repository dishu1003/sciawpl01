<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_team(); // only logged-in team users

$pdo = get_pdo_connection();

$material_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM training_materials WHERE id=?");
$stmt->execute([$material_id]);
$material = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$material) {
    die("Material not found!");
}

// Increase view count
$pdo->prepare("UPDATE training_materials SET view_count = view_count + 1 WHERE id=?")->execute([$material_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($material['title']) ?></title>
    <style>
        body{font-family:Arial;background:#f8f9fa;padding:20px;}
        video{max-width:100%;border-radius:8px;}
        button{margin-top:15px;background:#4CAF50;color:#fff;padding:10px 18px;border:none;border-radius:6px;cursor:pointer;}
        button:hover{background:#388E3C;}
    </style>
</head>
<body>
    <h1><?= htmlspecialchars($material['title']) ?></h1>
    <p><?= htmlspecialchars($material['description']) ?></p>

    <?php if ($material['type'] === 'video' && strpos($material['url'], '.mp4') !== false): ?>
        <video id="trainingVideo" controls>
            <source src="<?= htmlspecialchars($material['url']) ?>" type="video/mp4">
        </video>
    <?php else: ?>
        <a href="<?= htmlspecialchars($material['url']) ?>" target="_blank">📄 Open Material</a>
    <?php endif; ?>

    <button onclick="markComplete(<?= $material_id ?>)">✅ Mark as Complete</button>

    <script>
    // Send progress updates to backend
    function sendProgress(id, percent){
        fetch('/api/mark_material_progress.php', {
            method: 'POST',
            body: new URLSearchParams({
                material_id: id,
                progress_percent: percent
            })
        });
    }

    function markComplete(id){
        sendProgress(id, 100);
        alert("🎉 Great! Material marked complete.");
    }

    // If video present, auto-track watch progress
    const v = document.getElementById('trainingVideo');
    if(v){
        let lastSent = 0;
        v.addEventListener('timeupdate', ()=>{
            if (!v.duration) return;
            let percent = Math.round((v.currentTime / v.duration) * 100);
            if (percent - lastSent >= 10){
                sendProgress(<?= $material_id ?>, percent);
                lastSent = percent;
            }
        });
        v.addEventListener('ended', ()=>markComplete(<?= $material_id ?>));
    }
    </script>
</body>
</html>
