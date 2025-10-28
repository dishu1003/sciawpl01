<?php
/**
 * Assignment Rules - Admin Interface
 * Configure automatic lead assignment rules
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
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_rule':
                    $rule_name = trim($_POST['rule_name']);
                    $rule_type = $_POST['rule_type'];
                    $rule_conditions = $_POST['rule_conditions'] ?? [];
                    $assignment_method = $_POST['assignment_method'];
                    $target_user = $_POST['target_user'] ?? null;
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    if ($rule_name && $rule_type && $assignment_method) {
                        $stmt = $pdo->prepare("
                            INSERT INTO assignment_rules (rule_name, rule_type, rule_conditions, assignment_method, target_user, is_active, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([$rule_name, $rule_type, json_encode($rule_conditions), $assignment_method, $target_user, $is_active]);
                        $_SESSION['success_message'] = "Assignment rule created successfully!";
                    }
                    break;
                    
                case 'update_rule':
                    $rule_id = $_POST['rule_id'];
                    $rule_name = trim($_POST['rule_name']);
                    $rule_type = $_POST['rule_type'];
                    $rule_conditions = $_POST['rule_conditions'] ?? [];
                    $assignment_method = $_POST['assignment_method'];
                    $target_user = $_POST['target_user'] ?? null;
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    $stmt = $pdo->prepare("
                        UPDATE assignment_rules 
                        SET rule_name = ?, rule_type = ?, rule_conditions = ?, assignment_method = ?, target_user = ?, is_active = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$rule_name, $rule_type, json_encode($rule_conditions), $assignment_method, $target_user, $is_active, $rule_id]);
                    $_SESSION['success_message'] = "Assignment rule updated successfully!";
                    break;
                    
                case 'delete_rule':
                    $rule_id = $_POST['rule_id'];
                    $stmt = $pdo->prepare("DELETE FROM assignment_rules WHERE id = ?");
                    $stmt->execute([$rule_id]);
                    $_SESSION['success_message'] = "Assignment rule deleted successfully!";
                    break;
                    
                case 'test_rule':
                    $rule_id = $_POST['rule_id'];
                    $stmt = $pdo->prepare("SELECT * FROM assignment_rules WHERE id = ?");
                    $stmt->execute([$rule_id]);
                    $rule = $stmt->fetch();
                    
                    if ($rule) {
                        $conditions = json_decode($rule['rule_conditions'], true);
                        $test_results = testAssignmentRule($pdo, $rule, $conditions);
                        $_SESSION['test_results'] = $test_results;
                    }
                    break;
            }
        }
    }
    
    // Create assignment_rules table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS assignment_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rule_name VARCHAR(100) NOT NULL,
            rule_type ENUM('source', 'location', 'time_based', 'workload', 'expertise') NOT NULL,
            rule_conditions TEXT NOT NULL,
            assignment_method ENUM('specific_user', 'round_robin', 'least_leads', 'random') NOT NULL,
            target_user INT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (target_user) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
    
    // Get all assignment rules
    $rules_stmt = $pdo->query("
        SELECT ar.*, u.full_name as target_user_name
        FROM assignment_rules ar
        LEFT JOIN users u ON ar.target_user = u.id
        ORDER BY ar.created_at DESC
    ");
    $rules = $rules_stmt->fetchAll();
    
    // Get team members
    $team_stmt = $pdo->query("SELECT id, full_name, username FROM users WHERE role = 'team' AND status = 'active' ORDER BY full_name");
    $team_members = $team_stmt->fetchAll();
    
    // Rule statistics
    $stats = [];
    $stats['total_rules'] = count($rules);
    $stats['active_rules'] = count(array_filter($rules, function($r) { return $r['is_active']; }));
    $stats['rules_by_type'] = [];
    foreach ($rules as $rule) {
        $stats['rules_by_type'][$rule['rule_type']] = ($stats['rules_by_type'][$rule['rule_type']] ?? 0) + 1;
    }
    
    // Get recent assignments
    $recent_stmt = $pdo->query("
        SELECT l.*, u.full_name as assigned_to_name, ar.rule_name
        FROM leads l
        JOIN users u ON l.assigned_to = u.id
        LEFT JOIN assignment_rules ar ON l.assignment_rule_id = ar.id
        WHERE l.assigned_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY l.assigned_at DESC
        LIMIT 10
    ");
    $recent_assignments = $recent_stmt->fetchAll();
    
    Logger::info('Assignment rules accessed', [
        'user_id' => $_SESSION['user_id']
    ]);

} catch (PDOException $e) {
    Logger::error('Database error in assignment rules', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id']
    ]);
    $rules = $team_members = $recent_assignments = [];
    $stats = ['total_rules' => 0, 'active_rules' => 0, 'rules_by_type' => []];
}

function testAssignmentRule($pdo, $rule, $conditions) {
    $results = [];
    
    // Build test query based on rule conditions
    $where_conditions = [];
    $params = [];
    
    foreach ($conditions as $condition) {
        if ($condition['field'] === 'source') {
            $where_conditions[] = "source = ?";
            $params[] = $condition['value'];
        } elseif ($condition['field'] === 'lead_score') {
            $where_conditions[] = "lead_score = ?";
            $params[] = $condition['value'];
        } elseif ($condition['field'] === 'created_at') {
            if ($condition['operator'] === 'today') {
                $where_conditions[] = "DATE(created_at) = CURDATE()";
            } elseif ($condition['operator'] === 'this_week') {
                $where_conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            }
        }
    }
    
    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Count matching leads
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads $where_clause");
    $stmt->execute($params);
    $results['matching_leads'] = $stmt->fetchColumn();
    
    // Count unassigned leads
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE assigned_to IS NULL $where_clause");
    $stmt->execute($params);
    $results['unassigned_leads'] = $stmt->fetchColumn();
    
    return $results;
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚙️ Assignment Rules - Admin Dashboard</title>
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
        .stat-card.source::before { background: linear-gradient(90deg, #feca57, #ff9ff3); }
        .stat-card.location::before { background: linear-gradient(90deg, #ff6b6b, #ee5a24); }
        
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
        .stat-icon.source { background: linear-gradient(135deg, #feca57, #ff9ff3); }
        .stat-icon.location { background: linear-gradient(135deg, #ff6b6b, #ee5a24); }
        
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
        
        .rules-grid {
            display: grid;
            gap: 20px;
        }
        
        .rule-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            border-left: 4px solid #667eea;
            transition: transform 0.3s ease;
        }
        
        .rule-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .rule-card.active {
            border-left-color: #00d2d3;
        }
        
        .rule-card.inactive {
            border-left-color: #ccc;
            opacity: 0.7;
        }
        
        .rule-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .rule-name {
            font-size: 18px;
            font-weight: 700;
            color: #333;
        }
        
        .rule-status {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .rule-status.active {
            background: #d4edda;
            color: #155724;
        }
        
        .rule-status.inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .rule-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .rule-detail {
            font-size: 14px;
        }
        
        .rule-detail strong {
            color: #555;
        }
        
        .rule-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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
        
        .conditions-container {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .condition-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
            margin-bottom: 15px;
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
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            font-size: 16px;
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
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .rule-details {
                grid-template-columns: 1fr;
            }
            
            .condition-row {
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
        <h1>⚙️ Assignment Rules</h1>
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

        <!-- Test Results -->
        <?php if (isset($_SESSION['test_results'])): ?>
            <div class="alert alert-info">
                <strong>Test Results:</strong><br>
                Matching Leads: <?php echo $_SESSION['test_results']['matching_leads']; ?><br>
                Unassigned Leads: <?php echo $_SESSION['test_results']['unassigned_leads']; ?>
            </div>
            <?php unset($_SESSION['test_results']); ?>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-cogs"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_rules']; ?></div>
                <div class="stat-label">Total Rules</div>
            </div>

            <div class="stat-card active">
                <div class="stat-icon active">
                    <i class="fas fa-play"></i>
                </div>
                <div class="stat-value"><?php echo $stats['active_rules']; ?></div>
                <div class="stat-label">Active Rules</div>
            </div>

            <div class="stat-card source">
                <div class="stat-icon source">
                    <i class="fas fa-tag"></i>
                </div>
                <div class="stat-value"><?php echo $stats['rules_by_type']['source'] ?? 0; ?></div>
                <div class="stat-label">Source Rules</div>
            </div>

            <div class="stat-card location">
                <div class="stat-icon location">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <div class="stat-value"><?php echo $stats['rules_by_type']['location'] ?? 0; ?></div>
                <div class="stat-label">Location Rules</div>
            </div>
        </div>

        <!-- Assignment Rules -->
        <div class="section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h2 class="section-title">
                    <i class="fas fa-robot"></i>
                    Assignment Rules
                </h2>
                <button onclick="openRuleModal()" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Rule
                </button>
            </div>
            
            <?php if (empty($rules)): ?>
                <div class="empty-state">
                    <i class="fas fa-robot"></i>
                    <h3>No Assignment Rules</h3>
                    <p>Create rules to automatically assign leads to team members</p>
                </div>
            <?php else: ?>
                <div class="rules-grid">
                    <?php foreach ($rules as $rule): ?>
                    <div class="rule-card <?php echo $rule['is_active'] ? 'active' : 'inactive'; ?>">
                        <div class="rule-header">
                            <div class="rule-name"><?php echo htmlspecialchars($rule['rule_name']); ?></div>
                            <div class="rule-status <?php echo $rule['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $rule['is_active'] ? 'Active' : 'Inactive'; ?>
                            </div>
                        </div>
                        
                        <div class="rule-details">
                            <div class="rule-detail">
                                <strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $rule['rule_type'])); ?>
                            </div>
                            <div class="rule-detail">
                                <strong>Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $rule['assignment_method'])); ?>
                            </div>
                            <div class="rule-detail">
                                <strong>Target:</strong> <?php echo htmlspecialchars($rule['target_user_name'] ?: 'Auto-assign'); ?>
                            </div>
                            <div class="rule-detail">
                                <strong>Created:</strong> <?php echo date('d M Y', strtotime($rule['created_at'])); ?>
                            </div>
                        </div>
                        
                        <div class="rule-actions">
                            <button onclick="openEditRuleModal(<?php echo $rule['id']; ?>)" class="btn btn-success btn-small">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button onclick="testRule(<?php echo $rule['id']; ?>)" class="btn btn-warning btn-small">
                                <i class="fas fa-vial"></i> Test
                            </button>
                            <button onclick="deleteRule(<?php echo $rule['id']; ?>)" class="btn btn-danger btn-small">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Assignments -->
        <?php if (!empty($recent_assignments)): ?>
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-clock"></i>
                Recent Assignments
            </h2>
            
            <div class="rules-grid">
                <?php foreach ($recent_assignments as $assignment): ?>
                <div class="rule-card">
                    <div class="rule-header">
                        <div class="rule-name"><?php echo htmlspecialchars($assignment['name']); ?></div>
                        <div class="rule-status active">Assigned</div>
                    </div>
                    
                    <div class="rule-details">
                        <div class="rule-detail">
                            <strong>Assigned To:</strong> <?php echo htmlspecialchars($assignment['assigned_to_name']); ?>
                        </div>
                        <div class="rule-detail">
                            <strong>Rule:</strong> <?php echo htmlspecialchars($assignment['rule_name'] ?: 'Manual'); ?>
                        </div>
                        <div class="rule-detail">
                            <strong>Score:</strong> <?php echo $assignment['lead_score']; ?>
                        </div>
                        <div class="rule-detail">
                            <strong>Date:</strong> <?php echo date('d M Y, H:i', strtotime($assignment['assigned_at'])); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Rule Modal -->
    <div id="ruleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="ruleModalTitle">Create Assignment Rule</h3>
                <span class="close" onclick="closeRuleModal()">&times;</span>
            </div>
            
            <form method="POST" id="ruleForm">
                <input type="hidden" name="action" value="create_rule" id="ruleAction">
                <input type="hidden" name="rule_id" id="ruleId">
                
                <div class="form-group">
                    <label>Rule Name</label>
                    <input type="text" name="rule_name" id="ruleName" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Rule Type</label>
                    <select name="rule_type" id="ruleType" class="form-control" required onchange="updateRuleConditions()">
                        <option value="">Select Type</option>
                        <option value="source">Source-based</option>
                        <option value="location">Location-based</option>
                        <option value="time_based">Time-based</option>
                        <option value="workload">Workload-based</option>
                        <option value="expertise">Expertise-based</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Assignment Method</label>
                    <select name="assignment_method" id="assignmentMethod" class="form-control" required onchange="updateAssignmentMethod()">
                        <option value="">Select Method</option>
                        <option value="specific_user">Specific User</option>
                        <option value="round_robin">Round Robin</option>
                        <option value="least_leads">Least Leads</option>
                        <option value="random">Random</option>
                    </select>
                </div>
                
                <div class="form-group" id="targetUserGroup" style="display: none;">
                    <label>Target User</label>
                    <select name="target_user" id="targetUser" class="form-control">
                        <option value="">Select User</option>
                        <?php foreach ($team_members as $member): ?>
                            <option value="<?php echo $member['id']; ?>">
                                <?php echo htmlspecialchars($member['full_name'] ?: $member['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_active" id="isActive" checked>
                        <label for="isActive">Active Rule</label>
                    </div>
                </div>
                
                <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="closeRuleModal()" class="btn" style="background: #6c757d; color: white;">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Rule
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openRuleModal() {
            document.getElementById('ruleModalTitle').textContent = 'Create Assignment Rule';
            document.getElementById('ruleAction').value = 'create_rule';
            document.getElementById('ruleForm').reset();
            document.getElementById('ruleId').value = '';
            document.getElementById('isActive').checked = true;
            document.getElementById('ruleModal').style.display = 'block';
        }

        function closeRuleModal() {
            document.getElementById('ruleModal').style.display = 'none';
        }

        function updateAssignmentMethod() {
            const method = document.getElementById('assignmentMethod').value;
            const targetUserGroup = document.getElementById('targetUserGroup');
            
            if (method === 'specific_user') {
                targetUserGroup.style.display = 'block';
                document.getElementById('targetUser').required = true;
            } else {
                targetUserGroup.style.display = 'none';
                document.getElementById('targetUser').required = false;
            }
        }

        function updateRuleConditions() {
            const ruleType = document.getElementById('ruleType').value;
            // This would dynamically update condition fields based on rule type
            // For now, we'll keep it simple
        }

        function openEditRuleModal(ruleId) {
            // This would populate the form with existing rule data
            // For now, we'll just open the modal
            document.getElementById('ruleModalTitle').textContent = 'Edit Assignment Rule';
            document.getElementById('ruleAction').value = 'update_rule';
            document.getElementById('ruleId').value = ruleId;
            document.getElementById('ruleModal').style.display = 'block';
        }

        function testRule(ruleId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="test_rule">
                <input type="hidden" name="rule_id" value="${ruleId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function deleteRule(ruleId) {
            if (confirm('Are you sure you want to delete this rule?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_rule">
                    <input type="hidden" name="rule_id" value="${ruleId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('ruleModal');
            if (event.target === modal) {
                closeRuleModal();
            }
        }
    </script>
</body>
</html>
