<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_admin();

$pdo = get_pdo_connection();

// Conversion rates
$total_leads = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
$step2_completed = $pdo->query("SELECT COUNT(*) FROM leads WHERE current_step >= 2")->fetchColumn();
$step3_completed = $pdo->query("SELECT COUNT(*) FROM leads WHERE current_step >= 3")->fetchColumn();
$step4_completed = $pdo->query("SELECT COUNT(*) FROM leads WHERE current_step = 4")->fetchColumn();

$step2_rate = $total_leads > 0 ? round(($step2_completed / $total_leads) * 100, 2) : 0;
$step3_rate = $total_leads > 0 ? round(($step3_completed / $total_leads) * 100, 2) : 0;
$step4_rate = $total_leads > 0 ? round(($step4_completed / $total_leads) * 100, 2) : 0;

// Team performance
$stmt = $pdo->query("
    SELECT u.name, u.id, 
           COUNT(l.id) as total_leads,
           SUM(CASE WHEN l.status = 'converted' THEN 1 ELSE 0 END) as converted
    FROM users u
    LEFT JOIN leads l ON u.id = l.assigned_to
    WHERE u.role = 'team'
    GROUP BY u.id
    ORDER BY converted DESC, total_leads DESC
");
$team_performance = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Analytics Dashboard</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <nav class="dashboard-nav">
        <h1><?php echo SITE_NAME; ?> - Analytics</h1>
        <a href="/admin/">← Back to Dashboard</a>
    </nav>
    
    <div class="main-content">
        <h2>Conversion Funnel</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Step 1 → Step 2</h3>
                <p class="stat-number"><?php echo $step2_rate; ?>%</p>
                <p><?php echo $step2_completed; ?> / <?php echo $total_leads; ?></p>
            </div>
            <div class="stat-card">
                <h3>Step 2 → Step 3</h3>
                <p class="stat-number"><?php echo $step3_rate; ?>%</p>
                <p><?php echo $step3_completed; ?> / <?php echo $total_leads; ?></p>
            </div>
            <div class="stat-card hot">
                <h3>Step 3 → Step 4</h3>
                <p class="stat-number"><?php echo $step4_rate; ?>%</p>
                <p><?php echo $step4_completed; ?> / <?php echo $total_leads; ?></p>
            </div>
        </div>
        
        <h2>Team Performance Leaderboard</h2>
        <table class="leads-table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Team Member</th>
                    <th>Total Leads</th>
                    <th>Converted</th>
                    <th>Conversion Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php $rank = 1; foreach ($team_performance as $member): ?>
                    <?php $conv_rate = $member['total_leads'] > 0 ? round(($member['converted'] / $member['total_leads']) * 100, 2) : 0; ?>
                    <tr>
                        <td><?php echo $rank++; ?></td>
                        <td><?php echo htmlspecialchars($member['name']); ?></td>
                        <td><?php echo $member['total_leads']; ?></td>
                        <td><?php echo $member['converted']; ?></td>
                        <td><?php echo $conv_rate; ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>