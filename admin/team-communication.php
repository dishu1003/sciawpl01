<?php
/**
 * Team Communication - Admin Interface
 * Internal communication system for team members
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
    
    // Create communication tables if they don't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS team_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            recipient_id INT NULL,
            message_type ENUM('direct', 'announcement', 'group') NOT NULL DEFAULT 'direct',
            subject VARCHAR(200),
            message TEXT NOT NULL,
            is_urgent TINYINT(1) DEFAULT 0,
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS team_announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            content TEXT NOT NULL,
            created_by INT NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'send_message':
                    $recipient_id = $_POST['recipient_id'];
                    $subject = trim($_POST['subject']);
                    $message = trim($_POST['message']);
                    $is_urgent = isset($_POST['is_urgent']) ? 1 : 0;
                    $sender_id = $_SESSION['user_id'];
                    
                    if ($recipient_id && $message) {
                        $stmt = $pdo->prepare("
                            INSERT INTO team_messages (sender_id, recipient_id, subject, message, is_urgent)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$sender_id, $recipient_id, $subject, $message, $is_urgent]);
                        $_SESSION['success_message'] = "Message sent successfully!";
                    }
                    break;
                    
                case 'send_announcement':
                    $title = trim($_POST['title']);
                    $content = trim($_POST['content']);
                    $priority = $_POST['priority'];
                    $created_by = $_SESSION['user_id'];
                    
                    if ($title && $content) {
                        $stmt = $pdo->prepare("
                            INSERT INTO team_announcements (title, content, created_by, priority)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$title, $content, $created_by, $priority]);
                        $_SESSION['success_message'] = "Announcement posted successfully!";
                    }
                    break;
                    
                case 'delete_message':
                    $message_id = $_POST['message_id'];
                    $stmt = $pdo->prepare("DELETE FROM team_messages WHERE id = ?");
                    $stmt->execute([$message_id]);
                    $_SESSION['success_message'] = "Message deleted successfully!";
                    break;
                    
                case 'delete_announcement':
                    $announcement_id = $_POST['announcement_id'];
                    $stmt = $pdo->prepare("DELETE FROM team_announcements WHERE id = ?");
                    $stmt->execute([$announcement_id]);
                    $_SESSION['success_message'] = "Announcement deleted successfully!";
                    break;

                case 'send_whatsapp_team':
                    $recipient_id = $_POST['recipient_id'];
                    $message = trim($_POST['message']);

                    if ($recipient_id === 'all') {
                        $stmt = $pdo->query("SELECT phone FROM users WHERE role = 'team' AND status = 'active'");
                        $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    } else {
                        $stmt = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
                        $stmt->execute([$recipient_id]);
                        $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    }

                    foreach ($recipients as $recipient) {
                        send_whatsapp_message($recipient, $message);
                    }
                    $_SESSION['success_message'] = "WhatsApp message sent successfully!";
                    break;

                case 'send_whatsapp_lead':
                    $recipient_id = $_POST['recipient_id'];
                    $message = trim($_POST['message']);

                    if ($recipient_id === 'all') {
                        $stmt = $pdo->query("SELECT phone FROM leads");
                        $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    } else {
                        $stmt = $pdo->prepare("SELECT phone FROM leads WHERE id = ?");
                        $stmt->execute([$recipient_id]);
                        $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    }

                    foreach ($recipients as $recipient) {
                        send_whatsapp_message($recipient, $message);
                    }
                    $_SESSION['success_message'] = "WhatsApp message sent successfully!";
                    break;
            }
        }
    }
    
    // Get team members
    $team_stmt = $pdo->query("SELECT id, full_name, username FROM users WHERE role = 'team' AND status = 'active' ORDER BY full_name");
    $team_members = $team_stmt->fetchAll();
    
    // Get recent messages
    $messages_stmt = $pdo->query("
        SELECT tm.*, 
               s.full_name as sender_name, s.username as sender_username,
               r.full_name as recipient_name, r.username as recipient_username
        FROM team_messages tm
        JOIN users s ON tm.sender_id = s.id
        LEFT JOIN users r ON tm.recipient_id = r.id
        ORDER BY tm.created_at DESC
        LIMIT 20
    ");
    $recent_messages = $messages_stmt->fetchAll();
    
    // Get announcements
    $announcements_stmt = $pdo->query("
        SELECT ta.*, u.full_name as creator_name
        FROM team_announcements ta
        JOIN users u ON ta.created_by = u.id
        WHERE ta.is_active = 1
        ORDER BY ta.created_at DESC
        LIMIT 10
    ");
    $announcements = $announcements_stmt->fetchAll();
    
    // Communication statistics
    $stats = [];
    $stats['total_messages'] = $pdo->query("SELECT COUNT(*) FROM team_messages")->fetchColumn();
    $stats['unread_messages'] = $pdo->query("SELECT COUNT(*) FROM team_messages WHERE is_read = 0")->fetchColumn();
    $stats['urgent_messages'] = $pdo->query("SELECT COUNT(*) FROM team_messages WHERE is_urgent = 1 AND is_read = 0")->fetchColumn();
    $stats['active_announcements'] = $pdo->query("SELECT COUNT(*) FROM team_announcements WHERE is_active = 1")->fetchColumn();
    
    Logger::info('Team communication accessed', [
        'user_id' => $_SESSION['user_id']
    ]);

} catch (PDOException $e) {
    Logger::error('Database error in team communication', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id']
    ]);
    $team_members = $recent_messages = $announcements = [];
    $stats = ['total_messages' => 0, 'unread_messages' => 0, 'urgent_messages' => 0, 'active_announcements' => 0];
}

function send_whatsapp_message($to, $message) {
    $config = require __DIR__ . '/../config/whatsapp.php';
    $url = "{$config['whatsapp_api_url']}/{$config['whatsapp_api_version']}/{$config['whatsapp_api_phone_number_id']}/messages";

    $data = [
        'messaging_product' => 'whatsapp',
        'to' => $to,
        'text' => ['body' => $message],
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $config['whatsapp_api_token'],
        'Content-Type: application/json',
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        // Log the error
        error_log("Error sending WhatsApp message: " . $error);
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💬 Team Communication - Admin Dashboard</title>
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
        
        .stat-card.messages::before { background: linear-gradient(90deg, #00d2d3, #54a0ff); }
        .stat-card.unread::before { background: linear-gradient(90deg, #feca57, #ff9ff3); }
        .stat-card.urgent::before { background: linear-gradient(90deg, #ff6b6b, #ee5a24); }
        
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
        .stat-icon.messages { background: linear-gradient(135deg, #00d2d3, #54a0ff); }
        .stat-icon.unread { background: linear-gradient(135deg, #feca57, #ff9ff3); }
        .stat-icon.urgent { background: linear-gradient(135deg, #ff6b6b, #ee5a24); }
        
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
        
        .communication-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .message-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
            transition: transform 0.3s ease;
        }
        
        .message-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .message-card.urgent {
            border-left-color: #ff6b6b;
            background: #fff5f5;
        }
        
        .message-card.unread {
            background: #f8f9ff;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .message-sender {
            font-weight: 700;
            color: #333;
        }
        
        .message-time {
            color: #666;
            font-size: 14px;
        }
        
        .message-subject {
            font-weight: 600;
            color: #555;
            margin-bottom: 10px;
        }
        
        .message-content {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        .message-actions {
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
        
        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .announcement-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
            transition: transform 0.3s ease;
        }
        
        .announcement-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .announcement-card.high {
            border-left-color: #ff6b6b;
            background: #fff5f5;
        }
        
        .announcement-card.medium {
            border-left-color: #feca57;
            background: #fffaf0;
        }
        
        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .announcement-title {
            font-size: 18px;
            font-weight: 700;
            color: #333;
        }
        
        .announcement-priority {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .announcement-priority.high {
            background: #fee;
            color: #e74c3c;
        }
        
        .announcement-priority.medium {
            background: #fef3e0;
            color: #f39c12;
        }
        
        .announcement-priority.low {
            background: #e8f4f8;
            color: #3498db;
        }
        
        .announcement-content {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        .announcement-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: #666;
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
            backdrop-filter: blur(ไฟpx);
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

        .tab-nav {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 20px;
        }
        .tab-link {
            background: none;
            border: none;
            padding: 15px 20px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            color: #666;
            transition: all 0.2s ease;
        }
        .tab-link.active {
            color: #667eea;
            border-bottom: 2px solid #667eea;
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .communication-grid {
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
        <h1><i class="fab fa-whatsapp"></i> WhatsApp Messaging</h1>
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
                    <i class="fas fa-comments"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_messages']; ?></div>
                <div class="stat-label">Total Messages</div>
            </div>

            <div class="stat-card unread">
                <div class="stat-icon unread">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-value"><?php echo $stats['unread_messages']; ?></div>
                <div class="stat-label">Unread Messages</div>
            </div>

            <div class="stat-card urgent">
                <div class="stat-icon urgent">
                    <i class="fas fa-exclamation"></i>
                </div>
                <div class="stat-value"><?php echo $stats['urgent_messages']; ?></div>
                <div class="stat-label">Urgent Messages</div>
            </div>

            <div class="stat-card messages">
                <div class="stat-icon messages">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <div class="stat-value"><?php echo $stats['active_announcements']; ?></div>
                <div class="stat-label">Active Announcements</div>
            </div>
        </div>

        <div class="section">
            <h2 class="section-title">Sent Messages</h2>
            <div style="height: 300px; overflow-y: scroll; border: 1px solid #ccc; padding: 10px;">
                <?php
                $log_file = __DIR__ . '/../logs/whatsapp_messages.log';
                if (file_exists($log_file)) {
                    $log_contents = file_get_contents($log_file);
                    echo nl2br(htmlspecialchars($log_contents));
                } else {
                    echo "No messages sent yet.";
                }
                ?>
            </div>
        </div>

        <div class="section">
            <div class="tab-nav">
                <button class="tab-link active" onclick="openTab('team')">Team Members</button>
                <button class="tab-link" onclick="openTab('leads')">Leads</button>
            </div>

            <div id="team" class="tab-content">
                <form method="POST" id="teamMessageForm">
                    <input type="hidden" name="action" value="send_whatsapp_team">
                    <div class="form-group">
                        <label>Recipient</label>
                        <select name="recipient_id" class="form-control">
                            <option value="all">All Team Members</option>
                            <?php foreach ($team_members as $member): ?>
                                <option value="<?php echo $member['id']; ?>">
                                    <?php echo htmlspecialchars($member['full_name'] ?: $member['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message" class="form-control" rows="4" required placeholder="Type your message..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fab fa-whatsapp"></i> Send WhatsApp Message
                    </button>
                </form>
            </div>

            <div id="leads" class="tab-content" style="display:none;">
                <form method="POST" id="leadMessageForm">
                    <input type="hidden" name="action" value="send_whatsapp_lead">
                    <div class="form-group">
                        <label>Recipient</label>
                        <select name="recipient_id" class="form-control">
                            <option value="all">All Leads</option>
                            <?php
                            $leads_stmt = $pdo->query("SELECT id, name FROM leads");
                            $leads = $leads_stmt->fetchAll();
                            foreach ($leads as $lead) {
                                echo "<option value=\"{$lead['id']}\">{$lead['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message" class="form-control" rows="4" required placeholder="Type your message..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fab fa-whatsapp"></i> Send WhatsApp Message
                    </button>
                </form>
            </div>
        </div>

            <!-- Recent Messages -->
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-inbox"></i>
                    Recent Messages
                </h2>
                
                <?php if (empty($recent_messages)): ?>
                    <p style="text-align: center; color: #666; padding: 40px;">No messages yet</p>
                <?php else: ?>
                    <?php foreach ($recent_messages as $message): ?>
                    <div class="message-card <?php echo $message['is_urgent'] ? 'urgent' : ''; ?> <?php echo !$message['is_read'] ? 'unread' : ''; ?>">
                        <div class="message-header">
                            <div class="message-sender">
                                <?php echo htmlspecialchars($message['sender_name'] ?: $message['sender_username']); ?>
                                <?php if ($message['recipient_name']): ?>
                                    → <?php echo htmlspecialchars($message['recipient_name'] ?: $message['recipient_username']); ?>
                                <?php endif; ?>
                                <?php if ($message['is_urgent']): ?>
                                    <span style="color: #ff6b6b; margin-left: 10px;">🚨 URGENT</span>
                                <?php endif; ?>
                            </div>
                            <div class="message-time">
                                <?php echo date('d M Y, H:i', strtotime($message['created_at'])); ?>
                            </div>
                        </div>
                        
                        <?php if ($message['subject']): ?>
                        <div class="message-subject">
                            <?php echo htmlspecialchars($message['subject']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                        </div>
                        
                        <div class="message-actions">
                            <button onclick="deleteMessage(<?php echo $message['id']; ?>)" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Announcements -->
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-bullhorn"></i>
                Team Announcements
            </h2>
            
            <?php if (empty($announcements)): ?>
                <p style="text-align: center; color: #666; padding: 40px;">No announcements yet</p>
            <?php else: ?>
                <?php foreach ($announcements as $announcement): ?>
                <div class="announcement-card <?php echo $announcement['priority']; ?>">
                    <div class="announcement-header">
                        <div class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></div>
                        <div class="announcement-priority <?php echo $announcement['priority']; ?>">
                            <?php echo strtoupper($announcement['priority']); ?>
                        </div>
                    </div>
                    
                    <div class="announcement-content">
                        <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                    </div>
                    
                    <div class="announcement-meta">
                        <span>By: <?php echo htmlspecialchars($announcement['creator_name']); ?></span>
                        <span><?php echo date('d M Y, H:i', strtotime($announcement['created_at'])); ?></span>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <button onclick="deleteAnnouncement(<?php echo $announcement['id']; ?>)" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Announcement Modal -->
    <div id="announcementModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Post Team Announcement</h3>
                <span class="close" onclick="closeAnnouncementModal()">&times;</span>
            </div>
            
            <form method="POST" id="announcementForm">
                <input type="hidden" name="action" value="send_announcement">
                
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" class="form-control" required placeholder="Announcement title...">
                </div>
                
                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority" class="form-control" required>
                        <option value="low">Low Priority</option>
                        <option value="medium" selected>Medium Priority</option>
                        <option value="high">High Priority</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Content</label>
                    <textarea name="content" class="form-control" rows="6" required placeholder="Announcement content..."></textarea>
                </div>
                
                <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="closeAnnouncementModal()" class="btn" style="background: #6c757d; color: white;">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-bullhorn"></i> Post Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openAnnouncementModal() {
            document.getElementById('announcementModal').style.display = 'block';
        }

        function closeAnnouncementModal() {
            document.getElementById('announcementModal').style.display = 'none';
        }

        // Delete functions
        function deleteMessage(messageId) {
            if (confirm('Are you sure you want to delete this message?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_message">
                    <input type="hidden" name="message_id" value="${messageId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteAnnouncement(announcementId) {
            if (confirm('Are you sure you want to delete this announcement?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_announcement">
                    <input type="hidden" name="announcement_id" value="${announcementId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('announcementModal');
            if (event.target === modal) {
                closeAnnouncementModal();
            }
        }

        // Auto-refresh messages every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);

        function openTab(tabName) {
            var i;
            var x = document.getElementsByClassName("tab-content");
            for (i = 0; i < x.length; i++) {
                x[i].style.display = "none";
            }
            document.getElementById(tabName).style.display = "block";

            var tabLinks = document.getElementsByClassName("tab-link");
            for (i = 0; i < tabLinks.length; i++) {
                tabLinks[i].className = tabLinks[i].className.replace(" active", "");
            }
            event.currentTarget.className += " active";
        }
    </script>
</body>
</html>
