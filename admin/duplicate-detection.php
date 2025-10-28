<?php
/**
 * Duplicate Lead Detection - Admin Interface
 * Find and manage duplicate leads
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
                case 'merge_leads':
                    $primary_id = $_POST['primary_id'];
                    $duplicate_id = $_POST['duplicate_id'];
                    
                    // Get duplicate lead data
                    $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
                    $stmt->execute([$duplicate_id]);
                    $duplicate_lead = $stmt->fetch();
                    
                    if ($duplicate_lead) {
                        // Merge data into primary lead
                        $merged_notes = "MERGED DATA:\n";
                        if ($duplicate_lead['notes']) {
                            $merged_notes .= "From Lead #{$duplicate_id}: " . $duplicate_lead['notes'] . "\n";
                        }
                        
                        $stmt = $pdo->prepare("UPDATE leads SET notes = CONCAT(IFNULL(notes, ''), '\n', ?) WHERE id = ?");
                        $stmt->execute([$merged_notes, $primary_id]);
                        
                        // Delete duplicate lead
                        $stmt = $pdo->prepare("DELETE FROM leads WHERE id = ?");
                        $stmt->execute([$duplicate_id]);
                        
                        $_SESSION['success_message'] = "Leads merged successfully!";
                    }
                    break;
                    
                case 'delete_duplicate':
                    $duplicate_id = $_POST['duplicate_id'];
                    $stmt = $pdo->prepare("DELETE FROM leads WHERE id = ?");
                    $stmt->execute([$duplicate_id]);
                    $_SESSION['success_message'] = "Duplicate lead deleted successfully!";
                    break;
                    
                case 'mark_not_duplicate':
                    $lead_id = $_POST['lead_id'];
                    $stmt = $pdo->prepare("UPDATE leads SET notes = CONCAT(IFNULL(notes, ''), '\nMarked as NOT duplicate on ', NOW()) WHERE id = ?");
                    $stmt->execute([$lead_id]);
                    $_SESSION['success_message'] = "Lead marked as not duplicate!";
                    break;
            }
        }
    }
    
    // Find duplicate leads by email
    $email_duplicates = [];
    $email_stmt = $pdo->query("
        SELECT email, COUNT(*) as count, GROUP_CONCAT(id) as lead_ids
        FROM leads 
        WHERE email IS NOT NULL AND email != ''
        GROUP BY email 
        HAVING COUNT(*) > 1
        ORDER BY count DESC
    ");
    $email_duplicates = $email_stmt->fetchAll();
    
    // Find duplicate leads by phone
    $phone_duplicates = [];
    $phone_stmt = $pdo->query("
        SELECT phone, COUNT(*) as count, GROUP_CONCAT(id) as lead_ids
        FROM leads 
        WHERE phone IS NOT NULL AND phone != ''
        GROUP BY phone 
        HAVING COUNT(*) > 1
        ORDER BY count DESC
    ");
    $phone_duplicates = $phone_stmt->fetchAll();
    
    // Find potential duplicates by name similarity
    $name_duplicates = [];
    $name_stmt = $pdo->query("
        SELECT l1.id as lead1_id, l1.name as lead1_name, l1.email as lead1_email,
               l2.id as lead2_id, l2.name as lead2_name, l2.email as lead2_email
        FROM leads l1
        JOIN leads l2 ON l1.id < l2.id
        WHERE l1.name IS NOT NULL AND l2.name IS NOT NULL
        AND (
            SOUNDEX(l1.name) = SOUNDEX(l2.name) OR
            LEVENSHTEIN(l1.name, l2.name) <= 2
        )
        LIMIT 50
    ");
    $name_duplicates = $name_stmt->fetchAll();
    
    // Get detailed lead information for duplicates
    function getLeadDetails($pdo, $lead_ids) {
        $ids = explode(',', $lead_ids);
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT l.*, u.full_name as assigned_to_name
            FROM leads l
            LEFT JOIN users u ON l.assigned_to = u.id
            WHERE l.id IN ($placeholders)
            ORDER BY l.created_at ASC
        ");
        $stmt->execute($ids);
        return $stmt->fetchAll();
    }
    
    // Statistics
    $stats = [];
    $stats['email_duplicates'] = count($email_duplicates);
    $stats['phone_duplicates'] = count($phone_duplicates);
    $stats['name_duplicates'] = count($name_duplicates);
    $stats['total_duplicate_groups'] = $stats['email_duplicates'] + $stats['phone_duplicates'] + $stats['name_duplicates'];
    
    Logger::info('Duplicate detection accessed', [
        'user_id' => $_SESSION['user_id']
    ]);

} catch (PDOException $e) {
    Logger::error('Database error in duplicate detection', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id']
    ]);
    $email_duplicates = $phone_duplicates = $name_duplicates = [];
    $stats = ['email_duplicates' => 0, 'phone_duplicates' => 0, 'name_duplicates' => 0, 'total_duplicate_groups' => 0];
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 Duplicate Detection - Admin Dashboard</title>
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
        
        .stat-card.email::before { background: linear-gradient(90deg, #ff6b6b, #ee5a24); }
        .stat-card.phone::before { background: linear-gradient(90deg, #feca57, #ff9ff3); }
        .stat-card.name::before { background: linear-gradient(90deg, #54a0ff, #5f27cd); }
        .stat-card.total::before { background: linear-gradient(90deg, #00d2d3, #54a0ff); }
        
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
        .stat-icon.email { background: linear-gradient(135deg, #ff6b6b, #ee5a24); }
        .stat-icon.phone { background: linear-gradient(135deg, #feca57, #ff9ff3); }
        .stat-icon.name { background: linear-gradient(135deg, #54a0ff, #5f27cd); }
        .stat-icon.total { background: linear-gradient(135deg, #00d2d3, #54a0ff); }
        
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
        
        .duplicate-group {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #ff6b6b;
        }
        
        .duplicate-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .duplicate-type {
            font-weight: 700;
            color: #e74c3c;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .duplicate-count {
            background: #e74c3c;
            color: white;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .duplicate-leads {
            display: grid;
            gap: 15px;
        }
        
        .duplicate-lead {
            background: white;
            border-radius: 10px;
            padding: 15px;
            border: 2px solid #e9ecef;
            transition: border-color 0.3s ease;
        }
        
        .duplicate-lead:hover {
            border-color: #667eea;
        }
        
        .duplicate-lead.primary {
            border-color: #00d2d3;
            background: #f0ffff;
        }
        
        .lead-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .lead-name {
            font-weight: 700;
            color: #333;
            font-size: 16px;
        }
        
        .lead-id {
            background: #e9ecef;
            color: #666;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
        }
        
        .lead-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .lead-detail {
            font-size: 14px;
        }
        
        .lead-detail strong {
            color: #555;
        }
        
        .lead-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-hot {
            background: #fee;
            color: #e74c3c;
        }
        
        .badge-warm {
            background: #fef3e0;
            color: #f39c12;
        }
        
        .badge-cold {
            background: #e8f4f8;
            color: #3498db;
        }
        
        .badge-converted {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-active {
            background: #cce5ff;
            color: #0066cc;
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
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .lead-details {
                grid-template-columns: 1fr;
            }
            
            .lead-actions {
                flex-direction: column;
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
        <h1>🔍 Duplicate Detection</h1>
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
            <div class="stat-card email">
                <div class="stat-icon email">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-value"><?php echo $stats['email_duplicates']; ?></div>
                <div class="stat-label">Email Duplicates</div>
            </div>

            <div class="stat-card phone">
                <div class="stat-icon phone">
                    <i class="fas fa-phone"></i>
                </div>
                <div class="stat-value"><?php echo $stats['phone_duplicates']; ?></div>
                <div class="stat-label">Phone Duplicates</div>
            </div>

            <div class="stat-card name">
                <div class="stat-icon name">
                    <i class="fas fa-user"></i>
                </div>
                <div class="stat-value"><?php echo $stats['name_duplicates']; ?></div>
                <div class="stat-label">Name Similarities</div>
            </div>

            <div class="stat-card total">
                <div class="stat-icon total">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_duplicate_groups']; ?></div>
                <div class="stat-label">Total Groups</div>
            </div>
        </div>

        <!-- Email Duplicates -->
        <?php if (!empty($email_duplicates)): ?>
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-envelope" style="color: #e74c3c;"></i>
                Email Duplicates
            </h2>
            
            <?php foreach ($email_duplicates as $duplicate): ?>
                <?php $leads = getLeadDetails($pdo, $duplicate['lead_ids']); ?>
                <div class="duplicate-group">
                    <div class="duplicate-header">
                        <div class="duplicate-type">
                            <i class="fas fa-envelope"></i>
                            Email: <?php echo htmlspecialchars($duplicate['email']); ?>
                        </div>
                        <div class="duplicate-count"><?php echo $duplicate['count']; ?> duplicates</div>
                    </div>
                    
                    <div class="duplicate-leads">
                        <?php foreach ($leads as $index => $lead): ?>
                        <div class="duplicate-lead <?php echo $index === 0 ? 'primary' : ''; ?>">
                            <div class="lead-header">
                                <div class="lead-name"><?php echo htmlspecialchars($lead['name']); ?></div>
                                <div class="lead-id">#<?php echo $lead['id']; ?></div>
                            </div>
                            
                            <div class="lead-details">
                                <div class="lead-detail"><strong>Email:</strong> <?php echo htmlspecialchars($lead['email']); ?></div>
                                <div class="lead-detail"><strong>Phone:</strong> <?php echo htmlspecialchars($lead['phone'] ?: 'N/A'); ?></div>
                                <div class="lead-detail"><strong>Assigned:</strong> <?php echo htmlspecialchars($lead['assigned_to_name'] ?: 'Unassigned'); ?></div>
                                <div class="lead-detail"><strong>Score:</strong> 
                                    <span class="badge badge-<?php echo strtolower($lead['lead_score']); ?>">
                                        <?php echo $lead['lead_score']; ?>
                                    </span>
                                </div>
                                <div class="lead-detail"><strong>Status:</strong> 
                                    <span class="badge badge-<?php echo $lead['status'] === 'converted' ? 'converted' : 'active'; ?>">
                                        <?php echo ucfirst($lead['status']); ?>
                                    </span>
                                </div>
                                <div class="lead-detail"><strong>Created:</strong> <?php echo date('d M Y', strtotime($lead['created_at'])); ?></div>
                            </div>
                            
                            <div class="lead-actions">
                                <?php if ($index === 0): ?>
                                    <span class="btn btn-success">
                                        <i class="fas fa-star"></i> Primary Lead
                                    </span>
                                <?php else: ?>
                                    <button onclick="mergeLeads(<?php echo $leads[0]['id']; ?>, <?php echo $lead['id']; ?>)" class="btn btn-primary">
                                        <i class="fas fa-compress-alt"></i> Merge with Primary
                                    </button>
                                    <button onclick="deleteDuplicate(<?php echo $lead['id']; ?>)" class="btn btn-danger">
                                        <i class="fas fa-trash"></i> Delete Duplicate
                                    </button>
                                    <button onclick="markNotDuplicate(<?php echo $lead['id']; ?>)" class="btn btn-warning">
                                        <i class="fas fa-times"></i> Not Duplicate
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Phone Duplicates -->
        <?php if (!empty($phone_duplicates)): ?>
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-phone" style="color: #f39c12;"></i>
                Phone Duplicates
            </h2>
            
            <?php foreach ($phone_duplicates as $duplicate): ?>
                <?php $leads = getLeadDetails($pdo, $duplicate['lead_ids']); ?>
                <div class="duplicate-group">
                    <div class="duplicate-header">
                        <div class="duplicate-type">
                            <i class="fas fa-phone"></i>
                            Phone: <?php echo htmlspecialchars($duplicate['phone']); ?>
                        </div>
                        <div class="duplicate-count"><?php echo $duplicate['count']; ?> duplicates</div>
                    </div>
                    
                    <div class="duplicate-leads">
                        <?php foreach ($leads as $index => $lead): ?>
                        <div class="duplicate-lead <?php echo $index === 0 ? 'primary' : ''; ?>">
                            <div class="lead-header">
                                <div class="lead-name"><?php echo htmlspecialchars($lead['name']); ?></div>
                                <div class="lead-id">#<?php echo $lead['id']; ?></div>
                            </div>
                            
                            <div class="lead-details">
                                <div class="lead-detail"><strong>Email:</strong> <?php echo htmlspecialchars($lead['email']); ?></div>
                                <div class="lead-detail"><strong>Phone:</strong> <?php echo htmlspecialchars($lead['phone']); ?></div>
                                <div class="lead-detail"><strong>Assigned:</strong> <?php echo htmlspecialchars($lead['assigned_to_name'] ?: 'Unassigned'); ?></div>
                                <div class="lead-detail"><strong>Score:</strong> 
                                    <span class="badge badge-<?php echo strtolower($lead['lead_score']); ?>">
                                        <?php echo $lead['lead_score']; ?>
                                    </span>
                                </div>
                                <div class="lead-detail"><strong>Status:</strong> 
                                    <span class="badge badge-<?php echo $lead['status'] === 'converted' ? 'converted' : 'active'; ?>">
                                        <?php echo ucfirst($lead['status']); ?>
                                    </span>
                                </div>
                                <div class="lead-detail"><strong>Created:</strong> <?php echo date('d M Y', strtotime($lead['created_at'])); ?></div>
                            </div>
                            
                            <div class="lead-actions">
                                <?php if ($index === 0): ?>
                                    <span class="btn btn-success">
                                        <i class="fas fa-star"></i> Primary Lead
                                    </span>
                                <?php else: ?>
                                    <button onclick="mergeLeads(<?php echo $leads[0]['id']; ?>, <?php echo $lead['id']; ?>)" class="btn btn-primary">
                                        <i class="fas fa-compress-alt"></i> Merge with Primary
                                    </button>
                                    <button onclick="deleteDuplicate(<?php echo $lead['id']; ?>)" class="btn btn-danger">
                                        <i class="fas fa-trash"></i> Delete Duplicate
                                    </button>
                                    <button onclick="markNotDuplicate(<?php echo $lead['id']; ?>)" class="btn btn-warning">
                                        <i class="fas fa-times"></i> Not Duplicate
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Name Similarities -->
        <?php if (!empty($name_duplicates)): ?>
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-user" style="color: #3498db;"></i>
                Name Similarities
            </h2>
            
            <?php foreach ($name_duplicates as $duplicate): ?>
                <div class="duplicate-group">
                    <div class="duplicate-header">
                        <div class="duplicate-type">
                            <i class="fas fa-user"></i>
                            Similar Names
                        </div>
                        <div class="duplicate-count">2 similar</div>
                    </div>
                    
                    <div class="duplicate-leads">
                        <div class="duplicate-lead primary">
                            <div class="lead-header">
                                <div class="lead-name"><?php echo htmlspecialchars($duplicate['lead1_name']); ?></div>
                                <div class="lead-id">#<?php echo $duplicate['lead1_id']; ?></div>
                            </div>
                            <div class="lead-details">
                                <div class="lead-detail"><strong>Email:</strong> <?php echo htmlspecialchars($duplicate['lead1_email']); ?></div>
                            </div>
                            <div class="lead-actions">
                                <span class="btn btn-success">
                                    <i class="fas fa-star"></i> Primary Lead
                                </span>
                            </div>
                        </div>
                        
                        <div class="duplicate-lead">
                            <div class="lead-header">
                                <div class="lead-name"><?php echo htmlspecialchars($duplicate['lead2_name']); ?></div>
                                <div class="lead-id">#<?php echo $duplicate['lead2_id']; ?></div>
                            </div>
                            <div class="lead-details">
                                <div class="lead-detail"><strong>Email:</strong> <?php echo htmlspecialchars($duplicate['lead2_email']); ?></div>
                            </div>
                            <div class="lead-actions">
                                <button onclick="mergeLeads(<?php echo $duplicate['lead1_id']; ?>, <?php echo $duplicate['lead2_id']; ?>)" class="btn btn-primary">
                                    <i class="fas fa-compress-alt"></i> Merge with Primary
                                </button>
                                <button onclick="deleteDuplicate(<?php echo $duplicate['lead2_id']; ?>)" class="btn btn-danger">
                                    <i class="fas fa-trash"></i> Delete Duplicate
                                </button>
                                <button onclick="markNotDuplicate(<?php echo $duplicate['lead2_id']; ?>)" class="btn btn-warning">
                                    <i class="fas fa-times"></i> Not Duplicate
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($email_duplicates) && empty($phone_duplicates) && empty($name_duplicates)): ?>
        <div class="section">
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>No Duplicates Found!</h3>
                <p>Your lead database is clean and well-organized.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Action</h3>
                <span class="close" onclick="closeConfirmModal()">&times;</span>
            </div>
            
            <div style="margin-bottom: 20px;">
                <p id="confirmMessage"></p>
            </div>
            
            <div style="display: flex; gap: 15px; justify-content: flex-end;">
                <button onclick="closeConfirmModal()" class="btn" style="background: #6c757d; color: white;">
                    Cancel
                </button>
                <button onclick="confirmAction()" class="btn btn-danger" id="confirmBtn">
                    <i class="fas fa-check"></i> Confirm
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentAction = null;
        let currentData = null;

        function mergeLeads(primaryId, duplicateId) {
            showConfirmModal(
                `Are you sure you want to merge Lead #${duplicateId} with Lead #${primaryId}? This will combine all data and delete the duplicate.`,
                'Merge Leads',
                () => {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="merge_leads">
                        <input type="hidden" name="primary_id" value="${primaryId}">
                        <input type="hidden" name="duplicate_id" value="${duplicateId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            );
        }

        function deleteDuplicate(leadId) {
            showConfirmModal(
                `Are you sure you want to delete Lead #${leadId}? This action cannot be undone.`,
                'Delete Duplicate',
                () => {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="delete_duplicate">
                        <input type="hidden" name="duplicate_id" value="${leadId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            );
        }

        function markNotDuplicate(leadId) {
            showConfirmModal(
                `Are you sure Lead #${leadId} is not a duplicate?`,
                'Mark as Not Duplicate',
                () => {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="mark_not_duplicate">
                        <input type="hidden" name="lead_id" value="${leadId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            );
        }

        function showConfirmModal(message, title, callback) {
            document.getElementById('confirmMessage').textContent = message;
            document.getElementById('modal-title').textContent = title;
            currentAction = callback;
            document.getElementById('confirmModal').style.display = 'block';
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').style.display = 'none';
            currentAction = null;
        }

        function confirmAction() {
            if (currentAction) {
                currentAction();
            }
            closeConfirmModal();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('confirmModal');
            if (event.target === modal) {
                closeConfirmModal();
            }
        }
    </script>
</body>
</html>
