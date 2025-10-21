<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_login();

$pdo = get_pdo_connection();
$stmt = $pdo->query("SELECT * FROM scripts WHERE visibility = 'all' ORDER BY type, title");
$scripts = $stmt->fetchAll();

$grouped = [];
foreach ($scripts as $script) {
    $grouped[$script['type']][] = $script;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Scripts Library</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <nav class="dashboard-nav">
        <h1><?php echo SITE_NAME; ?></h1>
        <a href="/team/">← Back to Dashboard</a>
    </nav>
    
    <div class="scripts-library">
        <h2>Scripts Library</h2>
        
        <?php foreach ($grouped as $type => $scripts): ?>
            <div class="script-category">
                <h3><?php echo ucfirst($type); ?> Scripts</h3>
                <?php foreach ($scripts as $script): ?>
                    <div class="script-card">
                        <h4><?php echo htmlspecialchars($script['title']); ?></h4>
                        <p><?php echo nl2br(htmlspecialchars($script['content'])); ?></p>
                        <button onclick="copyScript(this)">Copy Script</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <script>
        function copyScript(btn) {
            const text = btn.previousElementSibling.textContent;
            navigator.clipboard.writeText(text);
            btn.textContent = 'Copied!';
            setTimeout(() => btn.textContent = 'Copy Script', 2000);
        }
    </script>
</body>
</html>