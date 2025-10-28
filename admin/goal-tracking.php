<?php
/**
 * Goal Tracking - Admin Interface
 * Goal setting and tracking system for team members
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/security.php';

// Set security headers
SecurityHeaders::setAll();
require_admin();
check_session_timeout();

try {
    $pdo = get_pdo_connection();
    
    // Create goal tracking tables if they don't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS team_goals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            goal_type ENUM('leads', 'conversions', 'calls', 'meetings', 'revenue') NOT NULL,
            goal_target INT NOT NULL,
            goal_period ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly') NOT NULL,
            goal_start_date DATE NOT NULL,
            goal_end_date DATE NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS goal_progress (
            id INT AUTO_INCREMENT PRIMARY KEY,
            goal_id INT NOT NULL,
            progress_date DATE NOT NULL,
            progress_value INT NOT NULL,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (goal_id) REFERENCES team_goals(id) ON DELETE CASCADE
        )
    ");
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_goal':
                    $user_id = $_POST['user_id'];
                    $goal_type = $_POST['goal_type'];
                    $goal_target = $_POST['goal_target'];
                    $goal_period = $_POST['goal_period'];
                    $goal_start_date = $_POST['goal_start_date'];
                    $goal_end_date = $_POST['goal_end_date'];
                    
                    if ($user_id && $goal_target && $goal_start_date && $goal_end_date) {
                        $stmt = $pdo->prepare("
                            INSERT INTO team_goals (user_id, goal_type, goal_target, goal_period, goal_start_date, goal_end_date)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$user_id, $goal_type, $goal_target, $goal_period, $goal_start_date, $goal_end_date]);
                        $_SESSION['success_message'] = "Goal created successfully!";
                    }
                    break;
                    
                case 'update_goal':
                    $goal_id = $_POST['goal_id'];
                    $goal_target = $_POST['goal_target'];
                    $goal_start_date = $_POST['goal_start_date'];
                    $goal_end_date = $_POST['goal_end_date'];
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    $stmt = $pdo->prepare("
                        UPDATE team_goals 
                        SET goal_target = ?, goal_start_date = ?, goal_end_date = ?, is_active = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$goal_target, $goal_start_date, $goal_end_date, $is_active, $goal_id]);
                    $_SESSION['success_message'] = "Goal updated successfully!";
                    break;
                    
                case 'delete_goal':
                    $goal_id = $_POST['goal_id'];
                    $stmt = $pdo->prepare("DELETE FROM team_goals WHERE id = ?");
                    $stmt->execute([$goal_id]);
                    $_SESSION['success_message'] = "Goal deleted successfully!";
                    break;
            }
        }
    }
    
    // Get team members
    $team_stmt = $pdo->query("SELECT id, full_name, username FROM users WHERE role = 'team' AND status = 'active' ORDER BY full_name");
    $team_members = $team_stmt->fetchAll();
    
    // Get all goals with progress
    $goals_stmt = $pdo->query("
        SELECT tg.*, u.full_name, u.username,
               COALESCE(SUM(gp.progress_value), 0) as current_progress,
               CASE 
                   WHEN tg.goal_end_date < CURDATE() THEN 'completed'
                   WHEN tg.goal_start_date > CURDATE() THEN 'upcoming'
                   ELSE 'active'
               END as goal_status
        FROM team_goals tg
        JOIN users u ON tg.user_id = u.id
        LEFT JOIN goal_progress gp ON tg.id = gp.goal_id
        GROUP BY tg.id
        ORDER BY tg.created_at DESC
    ");
    $goals = $goals_stmt->fetchAll();
    
    // Calculate goal statistics
    $stats = [];
    $stats['total_goals'] = count($goals);
    $stats['active_goals'] = count(array_filter($goals, function($g) { return $g['is_active'] && $g['goal_status'] === 'active'; }));
    $stats['completed_goals'] = count(array_filter($goals, function($g) { return $g['goal_status'] === 'completed'; }));
    $stats['on_track_goals'] = count(array_filter($goals, function($g) { 
        return $g['goal_status'] === 'active' && $g['current_progress'] >= ($g['goal_target'] * 0.7); 
    }));
    
    // Get top performers
    $top_performers_stmt = $pdo->query("
        SELECT u.full_name, u.username,
               COUNT(tg.id) as total_goals,
               COUNT(CASE WHEN tg.goal_status = 'completed' THEN 1 END) as completed_goals,
               ROUND((COUNT(CASE WHEN tg.goal_status = 'completed' THEN 1 END) / COUNT(tg.id)) * 100, 2) as completion_rate
        FROM team_goals tg
        JOIN users u ON tg.user_id = u.id
        GROUP BY u.id, u.full_name, u.username
        HAVING total_goals > 0
        ORDER BY completion_rate DESC, completed_goals DESC
        LIMIT 5
    ");
    $top_performers = $top_performers_stmt->fetchAll();
    
    Logger::info('Goal tracking accessed', [
        'user_id' => $_SESSION['user_id']
    ]);

} catch (PDOException $e) {
    Logger::error('Database error in goal tracking', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id']
    ]);
    $team_members = $goals = $top_performers = [];
    $stats = ['total_goals' => 0, 'active_goals' => 0, 'completed_goals' => 0, 'on_track_goals' => 0];
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎯 Goal Tracking - Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2/?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .dashboard-nav {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            color: #333;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .dashboard-nav h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .dashboard-nav .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .dashboard-nav a {
            color: #555;
            text-decoration: none;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .dashboard-nav a:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            transform: translateY(-2px);
        }
        
        .dashboard-container {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
            text-align: center;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .stat-card.active::before { background: linear-gradient(90deg, #00d2d3, #54a0ff); }
        .stat-card.completed::before { background: linear-gradient(90deg, #feca57, #ff9ff3); }
        .stat-card.track::before { background: linear-gradient(90deg, #ff6b6b, #ee5a24); }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin: 0 auto 15px;
        }
        
        .stat-icon.primary { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-icon.active { background: linear-gradient(135deg, #00d2d3, #54a0ff); }
        .stat-icon.completed { background: linear-gradient(135deg, #feca57, #ff9ff3); }
        .stat-icon.track { background: linear-gradient(135deg, #ff6b6b, #ee5a24); }
        
        .stat-value {
            font-size: 36px;
            font-weight: 800;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }
        
        .section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .goals-grid {
            display: grid;
            gap: 20px;
        }
        
        .goal-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            border-left: 4px solid #667eea;
            transition: transform 0.3s ease;
        }
        
        .goal-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .goal-card.active {
            border-left-color: #00d2d3;
        }
        
        .goal-card.completed {
            border-left-color: #feca57;
        }
        
        .goal-card.upcoming {
            border-left-color: #54a0ff;
        }
        
        .goal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .goal-title {
            font-size: 18px;
            font-weight: 700;
            color: #333;
        }
        
        .goal-status {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .goal-status.active {
            background: #d4edda;
            color: #155724;
        }
        
        .goal-status.completed {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .goal-status.upcoming {
            background: #fff3cd;
            color: #856404;
        }
        
        .goal-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .goal-detail {
            font-size: 14px;
        }
        
        .goal-detail strong {
            color: #555;
        }
        
        .progress-container {
            margin-bottom: 15px;
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .progress-text {
            font-weight: 600;
            color: #333;
        }
        
        .progress-percentage {
            font-size: 14px;
            color: #666;
        }
        
        .progress-bar {
            width: 100%;
            height: 12px;
            background: #e9ecef;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 0.3s ease;
            border-radius: 6px;
        }
        
        .progress-fill.excellent {
            background: linear-gradient(90deg, #00d2d3, #54a0ff);
        }
        
        .progress-fill.good {
            background: linear-gradient(90deg, #feca57, #ff9ff3);
        }
        
        .progress-fill.poor {
            background: linear-gradient(90deg, #ff6b6b, #ee5a24);
        }
        
        .goal-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #00d2d3, #54a0ff);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f3f4;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 700;
            color: #333;
        }
        
        .close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #999;
        }
        
        .close:hover {
            color: #333;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .performance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .performance-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #e9ecef;
        }
        
        .performance-table td {
            padding: 15px;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .performance-table tr:hover {
            background: #f8f9fa;
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .goal-details {
                grid-template-columns: 1fr;
            }
            
            .dashboard-nav {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <nav class="dashboard-nav">
        <h1>🎯 Goal Tracking</h1>
        <div class="nav-links">
            <a href="/admin/index.php">Dashboard</a>
            <a href="/admin/leads.php">Leads</a>
            <a href="/admin/team.php">Team</a>
            <a href="/logout.php">Logout</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <!-- Success Message -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                ✅ <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-target"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_goals']; ?></div>
                <div class="stat-label">Total Goals</div>
            </div>

            <div class="stat-card active">
                <div class="stat-icon active">
                    <i class="fas fa-play"></i>
                </div>
                <div class="stat-value"><?php echo $stats['active_goals']; ?></div>
                <div class="stat-label">Active Goals</div>
            </div>

            <div class="stat-card completed">
                <div class="stat-icon completed">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['completed_goals']; ?></div>
                <div class="stat-label">Completed Goals</div>
            </div>

            <div class="stat-card track">
                <div class="stat-icon track">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value"><?php echo $stats['on_track_goals']; ?></div>
                <div class="stat-label">On Track</div>
            </div>
        </div>

        <!-- Create Goal -->
        <div class="section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h2 class="section-title">
                    <i class="fas fa-plus"></i>
                    Create New Goal
                </h2>
            </div>
            
            <form method="POST" id="goalForm">
                <input type="hidden" name="action" value="create_goal">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div class="form-group">
                        <label>Team Member</label>
                        <select name="user_id" class="form-control" required>
                            <option value="">Select Team Member</option>
                            <?php foreach ($team_members as $member): ?>
                                <option value="<?php echo $member['id']; ?>">
                                    <?php echo htmlspecialchars($member['full_name'] ?: $member['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Goal Type</label>
                        <select name="goal_type" class="form-control" required>
                            <option value="">Select Goal Type</option>
                            <option value="leads">Number of Leads</option>
                            <option value="conversions">Conversions</option>
                            <option value="calls">Phone Calls</option>
                            <option value="meetings">Meetings</option>
                            <option value="revenue">Revenue</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Target</label>
                        <input type="number" name="goal_target" class="form-control" required min="1">
                    </div>
                    
                    <div class="form-group">
                        <label>Period</label>
                        <select name="goal_period" class="form-control" required>
                            <option value="">Select Period</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="goal_start_date" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="goal_end_date" class="form-control" required>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Goal
                    </button>
                </div>
            </form>
        </div>

        <!-- Goals List -->
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-list"></i>
                Team Goals
            </h2>
            
            <?php if (empty($goals)): ?>
                <p style="text-align: center; color: #666; padding: 40px;">No goals created yet</p>
            <?php else: ?>
                <div class="goals-grid">
                    <?php foreach ($goals as $goal): ?>
                    <div class="goal-card <?php echo $goal['goal_status']; ?>">
                        <div class="goal-header">
                            <div class="goal-title">
                                <?php echo ucfirst($goal['goal_type']); ?> Goal - <?php echo htmlspecialchars($goal['full_name'] ?: $goal['username']); ?>
                            </div>
                            <div class="goal-status <?php echo $goal['goal_status']; ?>">
                                <?php echo ucfirst($goal['goal_status']); ?>
                            </div>
                        </div>
                        
                        <div class="goal-details">
                            <div class="goal-detail">
                                <strong>Target:</strong> <?php echo number_format($goal['goal_target']); ?> <?php echo $goal['goal_type']; ?>
                            </div>
                            <div class="goal-detail">
                                <strong>Period:</strong> <?php echo ucfirst($goal['goal_period']); ?>
                            </div>
                            <div class="goal-detail">
                                <strong>Start Date:</strong> <?php echo date('d M Y', strtotime($goal['goal_start_date'])); ?>
                            </div>
                            <div class="goal-detail">
                                <strong>End Date:</strong> <?php echo date('d M Y', strtotime($goal['goal_end_date'])); ?>
                            </div>
                        </div>
                        
                        <div class="progress-container">
                            <div class="progress-header">
                                <div class="progress-text">Progress</div>
                                <div class="progress-percentage">
                                    <?php 
                                    $percentage = $goal['goal_target'] > 0 ? round(($goal['current_progress'] / $goal['goal_target']) * 100, 1) : 0;
                                    echo $percentage . '%';
                                    ?>
                                </div>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill <?php 
                                    if ($percentage >= 100) echo 'excellent';
                                    elseif ($percentage >= 70) echo 'good';
                                    else echo 'poor';
                                ?>" style="width: <?php echo min($percentage, 100); ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="goal-actions">
                            <button onclick="editGoal(<?php echo $goal['id']; ?>)" class="btn btn-success">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button onclick="deleteGoal(<?php echo $goal['id']; ?>)" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Top Performers -->
        <?php if (!empty($top_performers)): ?>
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-trophy"></i>
                Top Goal Performers
            </h2>
            
            <table class="performance-table">
                <thead>
                    <tr>
                        <th>Team Member</th>
                        <th>Total Goals</th>
                        <th>Completed Goals</th>
                        <th>Completion Rate</th>
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_performers as $performer): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($performer['full_name'] ?: $performer['username']); ?></strong></td>
                        <td><?php echo $performer['total_goals']; ?></td>
                        <td><?php echo $performer['completed_goals']; ?></td>
                        <td><?php echo $performer['completion_rate']; ?>%</td>
                        <td>
                            <div class="progress-bar" style="height: 8px;">
                                <div class="progress-fill" style="width: <?php echo $performer['completion_rate']; ?>%"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Set default dates
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const nextMonth = new Date(today.getFullYear(), today.getMonth() + 1, today.getDate());
            
            document.querySelector('input[name="goal_start_date"]').value = today.toISOString().split('T')[0];
            document.querySelector('input[name="goal_end_date"]').value = nextMonth.toISOString().split('T')[0];
        });

        // Delete goal
        function deleteGoal(goalId) {
            if (confirm('Are you sure you want to delete this goal?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_goal">
                    <input type="hidden" name="goal_id" value="${goalId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Edit goal (placeholder function)
        function editGoal(goalId) {
            alert('Edit functionality will be implemented in the next version.');
        }

        // Form validation
        document.getElementById('goalForm').addEventListener('submit', function(e) {
            const startDate = new Date(document.querySelector('input[name="goal_start_date"]').value);
            const endDate = new Date(document.querySelector('input[name="goal_end_date"]').value);
            
            if (endDate <= startDate) {
                alert('End date must be after start date');
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
