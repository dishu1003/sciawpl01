<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_team_access();

$pdo = get_pdo_connection();

$stmt = $pdo->prepare("SELECT * FROM goals ORDER BY end_date DESC");
$stmt->execute();
$goals = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Goal Tracking</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/team_sidebar.php'; ?>
    <h1>Goal Tracking</h1>

    <?php foreach ($goals as $goal): ?>
        <div>
            <h2><?php echo htmlspecialchars($goal['name']); ?></h2>
            <p><?php echo nl2br(htmlspecialchars($goal['description'])); ?></p>
            <p><strong>Target:</strong> <?php echo htmlspecialchars($goal['target_value']); ?></p>
            <p><strong>Current:</strong> <?php echo htmlspecialchars($goal['current_value']); ?></p>
            <p><strong>Progress:</strong> <?php echo round(($goal['current_value'] / $goal['target_value']) * 100); ?>%</p>
            <p><strong>Start Date:</strong> <?php echo htmlspecialchars($goal['start_date']); ?></p>
            <p><strong>End Date:</strong> <?php echo htmlspecialchars($goal['end_date']); ?></p>
        </div>
        <hr>
    <?php endforeach; ?>
</body>
</html>
