<?php
/**
 * Training and Support System - Admin Interface
 * Comprehensive training materials and support system
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
    
    // Create training tables if they don't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS training_modules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            content LONGTEXT,
            module_type ENUM('video', 'document', 'quiz', 'assignment') NOT NULL,
            difficulty_level ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
            duration_minutes INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_by INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    $pdoLearning->exec("
        CREATE TABLE IF NOT EXISTS training_progress (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            module_id INT NOT NULL,
            status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
            progress_percentage INT DEFAULT 0,
            completed_at DATETIME NULL,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (module_id) REFERENCES training_modules(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_module (user_id, module_id)
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS support_tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            subject VARCHAR(200) NOT NULL,
            description TEXT NOT NULL,
            priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
            status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
            assigned_to INT NULL,
            resolution TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS training_certificates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            module_id INT NOT NULL,
            certificate_number VARCHAR(50) UNIQUE,
            issued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (module_id) REFERENCES training_modules(id) ON DELETE CASCADE
        )
    ");
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_training_module':
                    $title = trim($_POST['title']);
                    $description = trim($_POST['description']);
                    $content = trim($_POST['content']);
                    $module_type = $_POST['module_type'];
                    $difficulty_level = $_POST['difficulty_level'];
                    $duration_minutes = $_POST['duration_minutes'];
                    $created_by = $_SESSION['user_id'];
                    
                    if ($title && $content && $module_type) {
                        $stmt = $pdo->prepare("
                            INSERT INTO training_modules (title, description, content, module_type, difficulty_level, duration_minutes, created_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$title, $description, $content, $module_type, $difficulty_level, $duration_minutes, $created_by]);
                        $_SESSION['success_message'] = "Training module created successfully!";
                    }
                    break;
                    
                case 'update_training_module':
                    $module_id = $_POST['module_id'];
                    $title = trim($_POST['title']);
                    $description = trim($_POST['description']);
                    $content = trim($_POST['content']);
                    $module_type = $_POST['module_type'];
                    $difficulty_level = $_POST['difficulty_level'];
                    $duration_minutes = $_POST['duration_minutes'];
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    $stmt = $pdo->prepare("
                        UPDATE training_modules 
                        SET title = ?, description = ?, content = ?, module_type = ?, difficulty_level = ?, duration_minutes = ?, is_active = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $description, $content, $module_type, $difficulty_level, $duration_minutes, $is_active, $module_id]);
                    $_SESSION['success_message'] = "Training module updated successfully!";
                    break;
                    
                case 'delete_training_module':
                    $module_id = $_POST['module_id'];
                    $stmt = $pdo->prepare("DELETE FROM training_modules WHERE id = ?");
                    $stmt->execute([$module_id]);
                    $_SESSION['success_message'] = "Training module deleted successfully!";
                    break;
                    
                case 'resolve_ticket':
                    $ticket_id = $_POST['ticket_id'];
                    $resolution = trim($_POST['resolution']);
                    $status = $_POST['status'];
                    
                    $stmt = $pdo->prepare("
                        UPDATE support_tickets 
                        SET resolution = ?, status = ?, assigned_to = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$resolution, $status, $_SESSION['user_id'], $ticket_id]);
                    $_SESSION['success_message'] = "Support ticket updated successfully!";
                    break;
            }
        }
    }
    
    // Get training modules
    $modules_stmt = $pdo->query("
        SELECT tm.*, u.full_name as created_by_name,
               COUNT(tp.user_id) as enrolled_users,
               COUNT(CASE WHEN tp.status = 'completed' THEN 1 END) as completed_users
        FROM training_modules tm
        JOIN users u ON tm.created_by = u.id
        LEFT JOIN training_progress tp ON tm.id = tp.module_id
        GROUP BY tm.id
        ORDER BY tm.created_at DESC
    ");
    $training_modules = $modules_stmt->fetchAll();
    
    // Get support tickets
    $tickets_stmt = $pdo->query("
        SELECT st.*, u.full_name as user_name, a.full_name as assigned_to_name
        FROM support_tickets st
        JOIN users u ON st.user_id = u.id
        LEFT JOIN users a ON st.assigned_to = a.id
        ORDER BY st.created_at DESC
        LIMIT 50
    ");
    $support_tickets = $tickets_stmt->fetchAll();
    
    // Get team members
    $team_stmt = $pdo->query("SELECT id, full_name, username FROM users WHERE role = 'team' AND status = 'active' ORDER BY full_name");
    $team_members = $team_stmt->fetchAll();
    
    // Training statistics
    $stats = [];
    $stats['total_modules'] = count($training_modules);
    $stats['active_modules'] = count(array_filter($training_modules, function($m) { return $m['is_active']; }));
    $stats['total_tickets'] = count($support_tickets);
    $stats['open_tickets'] = count(array_filter($support_tickets, function($t) { return $t['status'] === 'open'; }));
    
    Logger::info('Training and support accessed', [
        'user_id' => $_SESSION['user_id']
    ]);

} catch (PDOException $e) {
    Logger::error('Database error in training and support', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id']
    ]);
    $training_modules = $support_tickets = $team_members = [];
    $stats = ['total_modules' => 0, 'active_modules' => 0, 'total_tickets' => 0, 'open_tickets' => 0];
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎓 Training & Support - Admin Dashboard</title>
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
            background: linear-gradient(135deg,单位#667eea 0%, #764ba2 100%);
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
        
        .stat-card.modules::before { background: linear-gradient(90deg, #00d2d3, #54a0ff); }
        .stat-card.active::before { background: linear-gradient(90deg, #feca57, #ff9ff3); }
        .stat-card.tickets::before { background: linear-gradient(90deg, #ff6b6b, #ee5a24); }
        
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
        .stat-icon.modules { background: linear-gradient(135deg, #00d2d3, #54a0ff); }
        .stat-icon.active { background: linear-gradient(135deg, #feca57, #ff9ff3); }
        .stat-icon.tickets { background: linear-gradient(135deg, #ff6b6b, #ee5a24); }
        
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
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #f1f3f4;
        }
        
        .tab {
            padding: 12px 24px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .module-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
            transition: transform 0.3s ease;
        }
        
        .module-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .module-card.video { border-left-color: #00d2d3; }
        .module-card.document { border-left-color: #feca57; }
        .module-card.quiz { border-left-color: #ff6b6b; }
        .module-card.assignment { border-left-color: #54a0ff; }
        
        .module-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .module-title {
            font-size: 18px;
            font-weight: 700;
            color: #333;
        }
        
        .module-type {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .module-type.video {
            background: #d4f1f4;
            color: #0c5460;
        }
        
        .module-type.document {
            background: #fff3cd;
            color: #856404;
        }
        
        .module-type.quiz {
            background: #f8d7da;
            color: #721c24;
        }
        
        .module-type.assignment {
            background: #cce5ff;
            color: #0066cc;
        }
        
        .module-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .module-detail {
            font-size: 14px;
        }
        
        .module-detail strong {
            color: #555;
        }
        
        .module-actions {
            display: flex;
            gap: 10px;
        }
        
        .ticket-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
            transition: transform 0.3s ease;
        }
        
        .ticket-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .ticket-card.high { border-left-color: #ff6b6b; }
        .ticket-card.medium { border-left-color: #feca57; }
        .ticket-card.low { border-left-color: #00d2d3; }
        
        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .ticket-title {
            font-weight: 700;
            color: #333;
        }
        
        .ticket-priority {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .ticket-priority.high {
            background: #f8d7da;
            color: #721c24;
        }
        
        .ticket-priority.medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .ticket-priority.low {
            background: #d4f1f4;
            color: #0c5460;
        }
        
        .btn {
            padding: 8px 16px;
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
        
        .btn-warning {
            background: linear-gradient(135deg, #feca57, #ff9ff3);
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
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
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
            margin: 2% auto;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-height: 90vh;
            overflow-y: auto;
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
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .module-details {
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
        <h1>🎓 Training & Support</h1>
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
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_modules']; ?></div>
                <div class="stat-label">Training Modules</div>
            </div>

            <div class="stat-card active">
                <div class="stat-icon active">
                    <i class="fas fa-play"></i>
                </div>
                <div class="stat-value"><?php echo $stats['active_modules']; ?></div>
                <div class="stat-label">Active Modules</div>
            </div>

            <div class="stat-card tickets">
                <div class="stat-icon tickets">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_tickets']; ?></div>
                <div class="stat-label">Support Tickets</div>
            </div>

            <div class="stat-card tickets">
                <div class="stat-icon tickets">
                    <i class="fas fa-exclamation"></i>
                </div>
                <div class="stat-value"><?php echo $stats['open_tickets']; ?></div>
                <div class="stat-label">Open Tickets</div>
            </div>
        </div>

        <!-- Training and Support Tabs -->
        <div class="section">
            <div class="tabs">
                <button class="tab active" onclick="switchTab('training')">
                    <i class="fas fa-graduation-cap"></i> Training Modules
                </button>
                <button class="tab" onclick="switchTab('support')">
                    <i class="fas fa-headset"></i> Support Tickets
                </button>
            </div>

            <!-- Training Modules Tab -->
            <div id="training" class="tab-content active">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                    <h2 class="section-title">
                        <i class="fas fa-book"></i>
                        Training Modules
                    </h2>
                    <button onclick="openTrainingModal()" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Module
                    </button>
                </div>
                
                <?php if (empty($training_modules)): ?>
                    <p style="text-align: center; color: #666; padding: 40px;">No training modules created yet</p>
                <?php else: ?>
                    <?php foreach ($training_modules as $module): ?>
                    <div class="module-card <?php echo $module['module_type']; ?>">
                        <div class="module-header">
                            <div class="module-title"><?php echo htmlspecialchars($module['title']); ?></div>
                            <div class="module-type <?php echo $module['module_type']; ?>">
                                <?php echo ucfirst($module['module_type']); ?>
                            </div>
                        </div>
                        
                        <div class="module-details">
                            <div class="module-detail">
                                <strong>Type:</strong> <?php echo ucfirst($module['module_type']); ?>
                            </div>
                            <div class="module-detail">
                                <strong>Level:</strong> <?php echo ucfirst($module['difficulty_level']); ?>
                            </div>
                            <div class="module-detail">
                                <strong>Duration:</strong> <?php echo $module['duration_minutes']; ?> minutes
                            </div>
                            <div class="module-detail">
                                <strong>Enrolled:</strong> <?php echo $module['enrolled_users']; ?> users
                            </div>
                            <div class="module-detail">
                                <strong>Completed:</strong> <?php echo $module['completed_users']; ?> users
                            </div>
                            <div class="module-detail">
                                <strong>Created by:</strong> <?php echo htmlspecialchars($module['created_by_name']); ?>
                            </div>
                        </div>
                        
                        <?php if ($module['description']): ?>
                        <div style="margin-bottom: 15px; color: #666;">
                            <?php echo htmlspecialchars(substr($module['description'], 0, 150)); ?>...
                        </div>
                        <?php endif; ?>
                        
                        <div class="module-actions">
                            <button onclick="editTrainingModule(<?php echo $module['id']; ?>)" class="btn btn-success btn-small">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button onclick="deleteTrainingModule(<?php echo $module['id']; ?>)" class="btn btn-danger btn-small">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Support Tickets Tab -->
            <div id="support" class="tab-content">
                <h2 class="section-title">
                    <i class="fas fa-headset"></i>
                    Support Tickets
                </h2>
                
                <?php if (empty($support_tickets)): ?>
                    <p style="text-align: center; color: #666; padding: 40px;">No support tickets yet</p>
                <?php else: ?>
                    <?php foreach ($support_tickets as $ticket): ?>
                    <div class="ticket-card <?php echo $ticket['priority']; ?>">
                        <div class="ticket-header">
                            <div class="ticket-title"><?php echo htmlspecialchars($ticket['subject']); ?></div>
                            <div class="ticket-priority <?php echo $ticket['priority']; ?>">
                                <?php echo strtoupper($ticket['priority']); ?>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 15px; color: #666;">
                            <?php echo htmlspecialchars(substr($ticket['description'], 0, 200)); ?>...
                        </div>
                        
                        <div class="module-details">
                            <div class="module-detail">
                                <strong>From:</strong> <?php echo htmlspecialchars($ticket['user_name']); ?>
                            </div>
                            <div class="module-detail">
                                <strong>Status:</strong> <?php echo ucfirst($ticket['status']); ?>
                            </div>
                            <div class="module-detail">
                                <strong>Assigned to:</strong> <?php echo htmlspecialchars($ticket['assigned_to_name'] ?: 'Unassigned'); ?>
                            </div>
                            <div class="module-detail">
                                <strong>Created:</strong> <?php echo date('d M Y, H:i', strtotime($ticket['created_at'])); ?>
                            </div>
                        </div>
                        
                        <div class="module-actions">
                            <button onclick="resolveTicket(<?php echo $ticket['id']; ?>)" class="btn btn-success btn-small">
                                <i class="fas fa-check"></i> Resolve
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Training Module Modal -->
    <div id="trainingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="trainingModalTitle">Create Training Module</h3>
                <span class="close" onclick="closeTrainingModal()">&times;</span>
            </div>
            
            <form method="POST" id="trainingForm">
                <input type="hidden" name="action" value="create_training_module" id="trainingAction">
                <input type="hidden" name="module_id" id="moduleId">
                
                <div class="form-group">
                    <label>Module Title</label>
                    <input type="text" name="title" id="moduleTitle" class="form-control" required placeholder="e.g., Introduction to Direct Selling">
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="moduleDescription" class="form-control" rows="3" placeholder="Brief description of the module..."></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div class="form-group">
                        <label>Module Type</label>
                        <select name="module_type" id="moduleType" class="form-control" required>
                            <option value="">Select Type</option>
                            <option value="video">Video</option>
                            <option value="document">Document</option>
                            <option value="quiz">Quiz</option>
                            <option value="assignment">Assignment</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Difficulty Level</label>
                        <select name="difficulty_level" id="difficultyLevel" class="form-control" required>
                            <option value="beginner">Beginner</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Duration (minutes)</label>
                        <input type="number" name="duration_minutes" id="durationMinutes" class="form-control" min="0" value="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Content</label>
                    <textarea name="content" id="moduleContent" class="form-control" rows="10" required placeholder="Detailed content for the training module..."></textarea>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="is_active" id="isActive" checked>
                    <label for="isActive">Active Module</label>
                </div>
                
                <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="closeTrainingModal()" class="btn" style="background: #6c757d; color: white;">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Module
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Support Ticket Modal -->
    <div id="supportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Resolve Support Ticket</h3>
                <span class="close" onclick="closeSupportModal()">&times;</span>
            </div>
            
            <form method="POST" id="supportForm">
                <input type="hidden" name="action" value="resolve_ticket">
                <input type="hidden" name="ticket_id" id="ticketId">
                
                <div class="form-group">
                    <label>Resolution</label>
                    <textarea name="resolution" class="form-control" rows="6" required placeholder="Describe the resolution..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control" required>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="closeSupportModal()" class="btn" style="background: #6c757d; color: white;">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Resolve Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Tab switching
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        // Training modal functions
        function openTrainingModal() {
            document.getElementById('trainingModalTitle').textContent = 'Create Training Module';
            document.getElementById('trainingAction').value = 'create_training_module';
            document.getElementById('trainingForm').reset();
            document.getElementById('moduleId').value = '';
            document.getElementById('isActive').checked = true;
            document.getElementById('trainingModal').style.display = 'block';
        }

        function closeTrainingModal() {
            document.getElementById('trainingModal').style.display = 'none';
        }

        function editTrainingModule(moduleId) {
            document.getElementById('trainingModalTitle').textContent = 'Edit Training Module';
            document.getElementById('trainingAction').value = 'update_training_module';
            document.getElementById('moduleId').value = moduleId;
            document.getElementById('trainingModal').style.display = 'block';
        }

        function deleteTrainingModule(moduleId) {
            if (confirm('Are you sure you want to delete this training module?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_training_module">
                    <input type="hidden" name="module_id" value="${moduleId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Support modal functions
        function resolveTicket(ticketId) {
            document.getElementById('ticketId').value = ticketId;
            document.getElementById('supportModal').style.display = 'block';
        }

        function closeSupportModal() {
            document.getElementById('supportModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const trainingModal = document.getElementById('trainingModal');
            const supportModal = document.getElementById('supportModal');
            
            if (event.target === trainingModal) {
                closeTrainingModal();
            }
            if (event.target === supportModal) {
                closeSupportModal();
            }
        }
    </script>
</body>
</html>
