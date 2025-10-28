<?php
/**
 * Lead Activities - Admin Interface
 * Track all interactions and activities with leads
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
                case 'add_activity':
                    $lead_id = $_POST['lead_id'];
                    $activity_type = $_POST['activity_type'];
                    $description = trim($_POST['description']);
                    $user_id = $_SESSION['user_id'];
                    
                    if ($lead_id && $description) {
                        $stmt = $pdo->prepare("INSERT INTO lead_activities (lead_id, user_id, activity_type, description) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$lead_id, $user_id, $activity_type, $description]);
                        $_SESSION['success_message'] = "Activity added successfully!";
                    }
                    break;
                    
                case 'delete_activity':
                    $activity_id = $_POST['activity_id'];
                    $stmt = $pdo->prepare("DELETE FROM lead_activities WHERE id = ?");
                    $stmt->execute([$activity_id]);
                    $_SESSION['success_message'] = "Activity deleted successfully!";
                    break;
            }
        }
    }
    
    // Get filter parameters
    $lead_id = $_GET['lead_id'] ?? null;
    $activity_type = $_GET['activity_type'] ?? '';
    $user_id = $_GET['user_id'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    
    // Build query
    $where_conditions = [];
    $params = [];
    
    if ($lead_id) {
        $where_conditions[] = "la.lead_id = ?";
        $params[] = $lead_id;
    }
    
    if ($activity_type) {
        $where_conditions[] = "la.activity_type = ?";
        $params[] = $activity_type;
    }
    
    if ($user_id) {
        $where_conditions[] = "la.user_id = ?";
        $params[] = $user_id;
    }
    
    if ($date_from) {
        $where_conditions[] = "DATE(la.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where_conditions[] = "DATE(la.created_at) <= ?";
        $params[] = $date_to;
    }
    
    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get activities
    $activities_stmt = $pdo->prepare("
        SELECT la.*, l.name as lead_name, l.email as lead_email, l.phone as lead_phone,
               u.full_name as user_name, u.username as user_username
        FROM lead_activities la
        JOIN leads l ON la.lead_id = l.id
        JOIN users u ON la.user_id = u.id
        $where_clause
        ORDER BY la.created_at DESC
        LIMIT 100
    ");
    $activities_stmt->execute($params);
    $activities = $activities_stmt->fetchAll();
    
    // Get all leads for filter dropdown
    $leads_stmt = $pdo->query("SELECT id, name, email FROM leads ORDER BY name");
    $all_leads = $leads_stmt->fetchAll();
    
    // Get all users for filter dropdown
    $users_stmt = $pdo->query("SELECT id, full_name, username FROM users WHERE role = 'team' ORDER BY full_name");
    $all_users = $users_stmt->fetchAll();
    
    // Activity statistics
    $stats = [];
    $stats['total_activities'] = count($activities);
    $stats['calls'] = count(array_filter($activities, function($a) { return $a['activity_type'] === 'call'; }));
    $stats['emails'] = count(array_filter($activities, function($a) { return $a['activity_type'] === 'email'; }));
    $stats['meetings'] = count(array_filter($activities, function($a) { return $a['activity_type'] === 'meeting'; }));
    $stats['notes'] = count(array_filter($activities, function($a) { return $a['activity_type'] === 'note'; }));
    
    // Recent activities for dashboard
    $recent_stmt = $pdo->query("
        SELECT la.*, l.name as lead_name, u.full_name as user_name
        FROM lead_activities la
        JOIN leads l ON la.lead_id = l.id
        JOIN users u ON la.user_id = u.id
        ORDER BY la.created_at DESC
        LIMIT 10
    ");
    $recent_activities = $recent_stmt->fetchAll();
    
    Logger::info('Lead activities accessed', [
        'user_id' => $_SESSION['user_id']
    ]);

} catch (PDOException $e) {
    Logger::error('Database error in lead activities', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id']
    ]);
    $activities = $all_leads = $all_users = $recent_activities = [];
    $stats = ['total_activities' => 0, 'calls' => 0, 'emails' => 0, 'meetings' => 0, 'notes' => 0];
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📝 Lead Activities - Admin Dashboard</title>
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
        
        .stat-card.calls::before { background: linear-gradient(90deg, #00d2d3, #54a0ff); }
        .stat-card.emails::before { background: linear-gradient(90deg, #feca57, #ff9ff3); }
        .stat-card.meetings::before { background: linear-gradient(90deg, #ff6b6b, #ee5a24); }
        .stat-card.notes::before { background: linear-gradient(90deg, #54a0ff, #5f27cd); }
        
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
        .stat-icon.calls { background: linear-gradient(135deg, #00d2d3, #54a0ff); }
        .stat-icon.emails { background: linear-gradient(135deg, #feca57, #ff9ff3); }
        .stat-icon.meetings { background: linear-gradient(135deg, #ff6b6b, #ee5a24); }
        .stat-icon.notes { background: linear-gradient(135deg, #54a0ff, #5f27cd); }
        
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
        
        .filters {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #555;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
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
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .activity-item {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
            transition: transform 0.3s ease;
        }
        
        .activity-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .activity-item.call { border-left-color: #00d2d3; }
        .activity-item.email { border-left-color: #feca57; }
        .activity-item.meeting { border-left-color: #ff6b6b; }
        .activity-item.note { border-left-color: #54a0ff; }
        .activity-item.status_change { border-left-color: #5f27cd; }
        
        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .activity-type {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            color: #333;
        }
        
        .activity-type i {
            font-size: 16px;
        }
        
        .activity-date {
            color: #666;
            font-size: 14px;
        }
        
        .activity-content {
            margin-bottom: 15px;
        }
        
        .activity-description {
            color: #555;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        
        .activity-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            font-size: 14px;
            color: #666;
        }
        
        .activity-meta div {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .activity-actions {
            display: flex;
            gap: 10px;
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
            margin: 5% auto;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
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
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filters {
                grid-template-columns: 1fr;
            }
            
            .activity-meta {
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
        <h1>📝 Lead Activities</h1>
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
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_activities']; ?></div>
                <div class="stat-label">Total Activities</div>
            </div>

            <div class="stat-card calls">
                <div class="stat-icon calls">
                    <i class="fas fa-phone"></i>
                </div>
                <div class="stat-value"><?php echo $stats['calls']; ?></div>
                <div class="stat-label">Phone Calls</div>
            </div>

            <div class="stat-card emails">
                <div class="stat-icon emails">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-value"><?php echo $stats['emails']; ?></div>
                <div class="stat-label">Emails</div>
            </div>

            <div class="stat-card meetings">
                <div class="stat-icon meetings">
                    <i class="fas fa-handshake"></i>
                </div>
                <div class="stat-value"><?php echo $stats['meetings']; ?></div>
                <div class="stat-label">Meetings</div>
            </div>

            <div class="stat-card notes">
                <div class="stat-icon notes">
                    <i class="fas fa-sticky-note"></i>
                </div>
                <div class="stat-value"><?php echo $stats['notes']; ?></div>
                <div class="stat-label">Notes</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-filter"></i>
                Filter Activities
            </h2>
            
            <form method="GET" class="filters">
                <div class="filter-group">
                    <label>Lead</label>
                    <select name="lead_id">
                        <option value="">All Leads</option>
                        <?php foreach ($all_leads as $lead): ?>
                            <option value="<?php echo $lead['id']; ?>" <?php echo $lead_id == $lead['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lead['name'] . ' (' . $lead['email'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Activity Type</label>
                    <select name="activity_type">
                        <option value="">All Types</option>
                        <option value="call" <?php echo $activity_type === 'call' ? 'selected' : ''; ?>>Phone Call</option>
                        <option value="email" <?php echo $activity_type === 'email' ? 'selected' : ''; ?>>Email</option>
                        <option value="meeting" <?php echo $activity_type === 'meeting' ? 'selected' : ''; ?>>Meeting</option>
                        <option value="note" <?php echo $activity_type === 'note' ? 'selected' : ''; ?>>Note</option>
                        <option value="status_change" <?php echo $activity_type === 'status_change' ? 'selected' : ''; ?>>Status Change</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Team Member</label>
                    <select name="user_id">
                        <option value="">All Members</option>
                        <?php foreach ($all_users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $user_id == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Date From</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="filter-group">
                    <label>Date To</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Activities List -->
        <div class="section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h2 class="section-title">
                    <i class="fas fa-list"></i>
                    Activities
                </h2>
                <button onclick="openAddActivityModal()" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add Activity
                </button>
            </div>
            
            <?php if (empty($activities)): ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>No Activities Found</h3>
                    <p>No activities match your current filters</p>
                </div>
            <?php else: ?>
                <?php foreach ($activities as $activity): ?>
                <div class="activity-item <?php echo $activity['activity_type']; ?>">
                    <div class="activity-header">
                        <div class="activity-type">
                            <?php
                            $icons = [
                                'call' => 'fas fa-phone',
                                'email' => 'fas fa-envelope',
                                'meeting' => 'fas fa-handshake',
                                'note' => 'fas fa-sticky-note',
                                'status_change' => 'fas fa-exchange-alt'
                            ];
                            $icon = $icons[$activity['activity_type']] ?? 'fas fa-circle';
                            ?>
                            <i class="<?php echo $icon; ?>"></i>
                            <?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'])); ?>
                        </div>
                        <div class="activity-date">
                            <?php echo date('d M Y, H:i', strtotime($activity['created_at'])); ?>
                        </div>
                    </div>
                    
                    <div class="activity-content">
                        <div class="activity-description">
                            <?php echo nl2br(htmlspecialchars($activity['description'])); ?>
                        </div>
                    </div>
                    
                    <div class="activity-meta">
                        <div>
                            <i class="fas fa-user"></i>
                            <strong>Lead:</strong> <?php echo htmlspecialchars($activity['lead_name']); ?>
                        </div>
                        <div>
                            <i class="fas fa-envelope"></i>
                            <strong>Email:</strong> <?php echo htmlspecialchars($activity['lead_email']); ?>
                        </div>
                        <?php if ($activity['lead_phone']): ?>
                        <div>
                            <i class="fas fa-phone"></i>
                            <strong>Phone:</strong> <?php echo htmlspecialchars($activity['lead_phone']); ?>
                        </div>
                        <?php endif; ?>
                        <div>
                            <i class="fas fa-user-tie"></i>
                            <strong>By:</strong> <?php echo htmlspecialchars($activity['user_name'] ?: $activity['user_username']); ?>
                        </div>
                    </div>
                    
                    <div class="activity-actions">
                        <button onclick="deleteActivity(<?php echo $activity['id']; ?>)" class="btn btn-danger btn-small">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Activity Modal -->
    <div id="addActivityModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Activity</h3>
                <span class="close" onclick="closeAddActivityModal()">&times;</span>
            </div>
            
            <form method="POST" id="addActivityForm">
                <input type="hidden" name="action" value="add_activity">
                
                <div class="form-group">
                    <label>Lead</label>
                    <select name="lead_id" class="form-control" required>
                        <option value="">Select Lead</option>
                        <?php foreach ($all_leads as $lead): ?>
                            <option value="<?php echo $lead['id']; ?>">
                                <?php echo htmlspecialchars($lead['name'] . ' (' . $lead['email'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Activity Type</label>
                    <select name="activity_type" class="form-control" required>
                        <option value="">Select Type</option>
                        <option value="call">Phone Call</option>
                        <option value="email">Email</option>
                        <option value="meeting">Meeting</option>
                        <option value="note">Note</option>
                        <option value="status_change">Status Change</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="4" required placeholder="Describe the activity..."></textarea>
                </div>
                
                <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="closeAddActivityModal()" class="btn" style="background: #6c757d; color: white;">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Add Activity
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddActivityModal() {
            document.getElementById('addActivityModal').style.display = 'block';
        }

        function closeAddActivityModal() {
            document.getElementById('addActivityModal').style.display = 'none';
        }

        // Delete activity
        function deleteActivity(activityId) {
            if (confirm('Are you sure you want to delete this activity?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_activity">
                    <input type="hidden" name="activity_id" value="${activityId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addActivityModal');
            if (event.target === modal) {
                closeAddActivityModal();
            }
        }
    </script>
</body>
</html>
