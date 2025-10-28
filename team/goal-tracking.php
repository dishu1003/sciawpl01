<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_team_access();

$user_id = $_SESSION['user_id'];
$goals = [];

try {
    $pdo = get_pdo_connection();
    $stmt = $pdo->prepare("SELECT * FROM goals WHERE user_id = ? ORDER BY end_date DESC");
    $stmt->execute([$user_id]);
    $goals = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Goal tracking database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Goal Tracking</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/team_sidebar.php'; ?>
    <main class="main-content">
        <h1>Goal Tracking</h1>

        <?php if (empty($goals)): ?>
            <p>You have not set any goals yet.</p>
        <?php else: ?>
            <?php foreach ($goals as $goal): ?>
                <div>
                    <h2><?php echo htmlspecialchars($goal['name']); ?></h2>
                    <p><?php echo nl2br(htmlspecialchars($goal['description'])); ?></p>
                    <p><strong>Target:</strong> <?php echo htmlspecialchars($goal['target_value']); ?></p>
                    <p><strong>Current:</strong> <?php echo htmlspecialchars($goal['current_value']); ?></p>
                    <p><strong>Progress:</strong> <?php echo $goal['target_value'] > 0 ? round(($goal['current_value'] / $goal['target_value']) * 100) : 0; ?>%</p>
                    <p><strong>Start Date:</strong> <?php echo htmlspecialchars($goal['start_date']); ?></p>
                    <p><strong>End Date:</strong> <?php echo htmlspecialchars($goal['end_date']); ?></p>
                </div>
                <hr>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</body>
</html>
