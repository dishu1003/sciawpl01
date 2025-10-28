<?php
/**
 * Follow-up Reminders - Admin Interface
 * Manage and track follow-up reminders for leads
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
                case 'set_reminder':
                    $lead_id = $_POST['lead_id'];
                    $reminder_date = $_POST['reminder_date'];
                    $reminder_notes = trim($_POST['reminder_notes']);
                    
                    $stmt = $pdo->prepare("UPDATE leads SET follow_up_date = ?, notes = CONCAT(IFNULL(notes, ''), '\nFollow-up: ', ?) WHERE id = ?");
                    $stmt->execute([$reminder_date, $reminder_notes, $lead_id]);
                    
                    $_SESSION['success_message'] = "Follow-up reminder set successfully!";
                    break;
                    
                case 'mark_completed':
                    $lead_id = $_POST['lead_id'];
                    $follow_up_notes = trim($_POST['follow_up_notes']);
                    
                    $stmt = $pdo->prepare("UPDATE leads SET follow_up_date = NULL, notes = CONCAT(IFNULL(notes, ''), '\nFollow-up completed: ', ?) WHERE id = ?");
                    $stmt->execute([$follow_up_notes, $lead_id]);
                    
                    $_SESSION['success_message'] = "Follow-up marked as completed!";
                    break;
            }
        }
    }
    
    // Get overdue follow-ups
    $overdue_stmt = $pdo->prepare("
        SELECT l.*, u.full_name as assigned_to_name 
        FROM leads l 
        LEFT JOIN users u ON l.assigned_to = u.id 
        WHERE l.follow_up_date IS NOT NULL AND l.follow_up_date < CURDATE() AND l.status != 'converted'
        ORDER BY l.follow_up_date ASC
    ");
    $overdue_stmt->execute();
    $overdue_followups = $overdue_stmt->fetchAll();
    
    // Get today's follow-ups
    $today_stmt = $pdo->prepare("
        SELECT l.*, u.full_name as assigned_to_name 
        FROM leads l 
        LEFT JOIN users u ON l.assigned_to = u.id 
        WHERE l.follow_up_date = CURDATE() AND l.status != 'converted'
        ORDER BY l.created_at DESC
    ");
    $today_stmt->execute();
    $today_followups = $today_stmt->fetchAll();
    
    // Get upcoming follow-ups (next 7 days)
    $upcoming_stmt = $pdo->prepare("
        SELECT l.*, u.full_name as assigned_to_name 
        FROM leads l 
        LEFT JOIN users u ON l.assigned_to = u.id 
        WHERE l.follow_up_date IS NOT NULL AND l.follow_up_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND l.status != 'converted'
        ORDER BY l.follow_up_date ASC
    ");
    $upcoming_stmt->execute();
    $upcoming_followups = $upcoming_stmt->fetchAll();
    
    // Get all leads for setting reminders
    $all_leads_stmt = $pdo->prepare("
        SELECT l.*, u.full_name as assigned_to_name 
        FROM leads l 
        LEFT JOIN users u ON l.assigned_to = u.id 
        WHERE l.status != 'converted'
        ORDER BY l.created_at DESC
        LIMIT 100
    ");
    $all_leads_stmt->execute();
    $all_leads = $all_leads_stmt->fetchAll();
    
    // Statistics
    $stats = [];
    $stats['overdue_count'] = count($overdue_followups);
    $stats['today_count'] = count($today_followups);
    $stats['upcoming_count'] = count($upcoming_followups);
    
    Logger::info('Follow-up reminders accessed', [
        'user_id' => $_SESSION['user_id']
    ]);

} catch (PDOException $e) {
    Logger::error('Database error in follow-up reminders', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id']
    ]);
    $overdue_followups = $today_followups = $upcoming_followups = $all_leads = [];
    $stats = ['overdue_count' => 0, 'today_count' => 0, 'upcoming_count' => 0];
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⏰ Follow-up Reminders - Admin Dashboard</title>
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
        
        .stat-card.overdue::before { background: linear-gradient(90deg, #ff6b6b, #ee5a24); }
        .stat-card.today::before { background: linear-gradient(90deg, #feca57, #ff9ff3); }
        .stat-card.upcoming::before { background: linear-gradient(90deg, #54a0ff, #5f27cd); }
        
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
        .stat-icon.overdue { background: linear-gradient(135deg, #ff6b6b, #ee5a24); }
        .stat-icon.today { background: linear-gradient(135deg, #feca57, #ff9ff3); }
        .stat-icon.upcoming { background: linear-gradient(135deg, #54a0ff, #5f27cd); }
        
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
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #e9ecef;
        }
        
        .table td {
            padding: 15px;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-overdue {
            background: #fee;
            color: #e74c3c;
        }
        
        .badge-today {
            background: #fef3e0;
            color: #f39c12;
        }
        
        .badge-upcoming {
            background: #e8f4f8;
            color: #3498db;
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
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
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
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
            }
            
            .stats-grid {
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
        <h1>⏰ Follow-up Reminders</h1>
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
            <div class="stat-card overdue">
                <div class="stat-icon overdue">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['overdue_count']; ?></div>
                <div class="stat-label">Overdue Follow-ups</div>
            </div>

            <div class="stat-card today">
                <div class="stat-icon today">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?php echo $stats['today_count']; ?></div>
                <div class="stat-label">Today's Follow-ups</div>
            </div>

            <div class="stat-card upcoming">
                <div class="stat-icon upcoming">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-value"><?php echo $stats['upcoming_count']; ?></div>
                <div class="stat-label">Upcoming (7 days)</div>
            </div>
        </div>

        <!-- Overdue Follow-ups -->
        <?php if (!empty($overdue_followups)): ?>
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-exclamation-triangle" style="color: #e74c3c;"></i>
                Overdue Follow-ups
            </h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Assigned To</th>
                        <th>Due Date</th>
                        <th>Days Overdue</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overdue_followups as $lead): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($lead['name']); ?></strong></td>
                        <td>
                            <div><?php echo htmlspecialchars($lead['email']); ?></div>
                            <?php if ($lead['phone']): ?>
                                <div><small><?php echo htmlspecialchars($lead['phone']); ?></small></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($lead['assigned_to_name'] ?: 'Unassigned'); ?></td>
                        <td><?php echo date('d M Y', strtotime($lead['follow_up_date'])); ?></td>
                        <td>
                            <span class="badge badge-overdue">
                                <?php echo (strtotime('now') - strtotime($lead['follow_up_date'])) / (60 * 60 * 24); ?> days
                            </span>
                        </td>
                        <td>
                            <button onclick="openCompleteModal(<?php echo $lead['id']; ?>, '<?php echo htmlspecialchars($lead['name'], ENT_QUOTES); ?>')" class="btn btn-success">
                                <i class="fas fa-check"></i> Complete
                            </button>
                            <button onclick="openReminderModal(<?php echo $lead['id']; ?>, '<?php echo htmlspecialchars($lead['name'], ENT_QUOTES); ?>')" class="btn btn-warning">
                                <i class="fas fa-clock"></i> Reschedule
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Today's Follow-ups -->
        <?php if (!empty($today_followups)): ?>
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-clock" style="color: #f39c12;"></i>
                Today's Follow-ups
            </h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Assigned To</th>
                        <th>Lead Score</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($today_followups as $lead): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($lead['name']); ?></strong></td>
                        <td>
                            <div><?php echo htmlspecialchars($lead['email']); ?></div>
                            <?php if ($lead['phone']): ?>
                                <div><small><?php echo htmlspecialchars($lead['phone']); ?></small></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($lead['assigned_to_name'] ?: 'Unassigned'); ?></td>
                        <td>
                            <span class="badge badge-<?php echo strtolower($lead['lead_score']); ?>">
                                <?php echo $lead['lead_score']; ?>
                            </span>
                        </td>
                        <td>
                            <button onclick="openCompleteModal(<?php echo $lead['id']; ?>, '<?php echo htmlspecialchars($lead['name'], ENT_QUOTES); ?>')" class="btn btn-success">
                                <i class="fas fa-check"></i> Complete
                            </button>
                            <button onclick="openReminderModal(<?php echo $lead['id']; ?>, '<?php echo htmlspecialchars($lead['name'], ENT_QUOTES); ?>')" class="btn btn-warning">
                                <i class="fas fa-clock"></i> Reschedule
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Upcoming Follow-ups -->
        <?php if (!empty($upcoming_followups)): ?>
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-calendar-alt" style="color: #3498db;"></i>
                Upcoming Follow-ups (Next 7 Days)
            </h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Assigned To</th>
                        <th>Follow-up Date</th>
                        <th>Lead Score</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming_followups as $lead): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($lead['name']); ?></strong></td>
                        <td>
                            <div><?php echo htmlspecialchars($lead['email']); ?></div>
                            <?php if ($lead['phone']): ?>
                                <div><small><?php echo htmlspecialchars($lead['phone']); ?></small></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($lead['assigned_to_name'] ?: 'Unassigned'); ?></td>
                        <td><?php echo date('d M Y', strtotime($lead['follow_up_date'])); ?></td>
                        <td>
                            <span class="badge badge-<?php echo strtolower($lead['lead_score']); ?>">
                                <?php echo $lead['lead_score']; ?>
                            </span>
                        </td>
                        <td>
                            <button onclick="openCompleteModal(<?php echo $lead['id']; ?>, '<?php echo htmlspecialchars($lead['name'], ENT_QUOTES); ?>')" class="btn btn-success">
                                <i class="fas fa-check"></i> Complete
                            </button>
                            <button onclick="openReminderModal(<?php echo $lead['id']; ?>, '<?php echo htmlspecialchars($lead['name'], ENT_QUOTES); ?>')" class="btn btn-warning">
                                <i class="fas fa-clock"></i> Reschedule
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- All Leads for Setting Reminders -->
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-plus"></i>
                Set Follow-up Reminders
            </h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Assigned To</th>
                        <th>Lead Score</th>
                        <th>Current Follow-up</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_leads as $lead): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($lead['name']); ?></strong></td>
                        <td>
                            <div><?php echo htmlspecialchars($lead['email']); ?></div>
                            <?php if ($lead['phone']): ?>
                                <div><small><?php echo htmlspecialchars($lead['phone']); ?></small></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($lead['assigned_to_name'] ?: 'Unassigned'); ?></td>
                        <td>
                            <span class="badge badge-<?php echo strtolower($lead['lead_score']); ?>">
                                <?php echo $lead['lead_score']; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($lead['follow_up_date']): ?>
                                <?php echo date('d M Y', strtotime($lead['follow_up_date'])); ?>
                            <?php else: ?>
                                <span style="color: #999;">No follow-up set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button onclick="openReminderModal(<?php echo $lead['id']; ?>, '<?php echo htmlspecialchars($lead['name'], ENT_QUOTES); ?>')" class="btn btn-primary">
                                <i class="fas fa-clock"></i> Set Reminder
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Set Reminder Modal -->
    <div id="reminderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Set Follow-up Reminder</h3>
                <span class="close" onclick="closeReminderModal()">&times;</span>
            </div>
            
            <form method="POST" id="reminderForm">
                <input type="hidden" name="action" value="set_reminder">
                <input type="hidden" name="lead_id" id="reminderLeadId">
                
                <div class="form-group">
                    <label>Lead Name</label>
                    <input type="text" id="reminderLeadName" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label>Follow-up Date</label>
                    <input type="date" name="reminder_date" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Reminder Notes</label>
                    <textarea name="reminder_notes" class="form-control" rows="3" placeholder="Add notes for this follow-up..."></textarea>
                </div>
                
                <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="closeReminderModal()" class="btn" style="background: #6c757d; color: white;">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Set Reminder
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Complete Follow-up Modal -->
    <div id="completeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Complete Follow-up</h3>
                <span class="close" onclick="closeCompleteModal()">&times;</span>
            </div>
            
            <form method="POST" id="completeForm">
                <input type="hidden" name="action" value="mark_completed">
                <input type="hidden" name="lead_id" id="completeLeadId">
                
                <div class="form-group">
                    <label>Lead Name</label>
                    <input type="text" id="completeLeadName" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label>Follow-up Notes</label>
                    <textarea name="follow_up_notes" class="form-control" rows="4" placeholder="What happened during the follow-up? Any outcomes or next steps?"></textarea>
                </div>
                
                <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="closeCompleteModal()" class="btn" style="background: #6c757d; color: white;">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Mark Complete
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openReminderModal(leadId, leadName) {
            document.getElementById('reminderLeadId').value = leadId;
            document.getElementById('reminderLeadName').value = leadName;
            document.getElementById('reminderModal').style.display = 'block';
            
            // Set default date to tomorrow
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            document.querySelector('input[name="reminder_date"]').value = tomorrow.toISOString().split('T')[0];
        }

        function closeReminderModal() {
            document.getElementById('reminderModal').style.display = 'none';
        }

        function openCompleteModal(leadId, leadName) {
            document.getElementById('completeLeadId').value = leadId;
            document.getElementById('completeLeadName').value = leadName;
            document.getElementById('completeModal').style.display = 'block';
        }

        function closeCompleteModal() {
            document.getElementById('completeModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const reminderModal = document.getElementById('reminderModal');
            const completeModal = document.getElementById('completeModal');
            
            if (event.target === reminderModal) {
                closeReminderModal();
            }
            if (event.target === completeModal) {
                closeCompleteModal();
            }
        }

        // Auto-refresh page every 5 minutes
        setInterval(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
