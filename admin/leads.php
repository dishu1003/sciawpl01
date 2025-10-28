<?php
/**
 * Network Marketing CRM - Leads Management
 * Enhanced leads management with network marketing features
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/security.php';

// Set security headers
function set_default_security_headers() {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: frame-ancestors 'self'");
}
set_default_security_headers();
require_admin();
check_session_timeout();

try {
$pdo = get_pdo_connection();
    
    // Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_lead'])) {
            $lead_id = $_POST['lead_id'];
            $assigned_to = $_POST['assigned_to'];
            
            $stmt = $pdo->prepare("UPDATE leads SET assigned_to = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$assigned_to, $lead_id]);
            
            $_SESSION['success_message'] = "Lead assigned successfully!";
            header('Location: /admin/leads.php');
        exit;
    }
    
    if (isset($_POST['update_status'])) {
            $lead_id = $_POST['lead_id'];
            $status = $_POST['status'];
            
            $stmt = $pdo->prepare("UPDATE leads SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $lead_id]);
            
            $_SESSION['success_message'] = "Lead status updated successfully!";
            header('Location: /admin/leads.php');
        exit;
    }
    
    if (isset($_POST['update_score'])) {
            $lead_id = $_POST['lead_id'];
            $score = $_POST['score'];
            
            $stmt = $pdo->prepare("UPDATE leads SET lead_score = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$score, $lead_id]);
            
            $_SESSION['success_message'] = "Lead score updated successfully!";
            header('Location: /admin/leads.php');
        exit;
        }
    }
    
    // Get filters
    $filter = $_GET['filter'] ?? 'all';
    $search = $_GET['search'] ?? '';
    $assigned_to = $_GET['assigned_to'] ?? '';
    
    // Build query conditions
    $where_conditions = [];
    $params = [];
    
    if ($filter !== 'all') {
        $where_conditions[] = "status = ?";
        $params[] = $filter;
    }
    
    if ($search) {
        $where_conditions[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    if ($assigned_to) {
        $where_conditions[] = "assigned_to = ?";
        $params[] = $assigned_to;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get leads
    $stmt = $pdo->prepare("
        SELECT l.*, u.username as assigned_username, u.full_name as assigned_name
        FROM leads l
        LEFT JOIN users u ON l.assigned_to = u.id
        $where_clause
        ORDER BY 
            CASE l.lead_score 
                WHEN 'HOT' THEN 1 
                WHEN 'WARM' THEN 2 
                WHEN 'COLD' THEN 3 
            END, 
            l.created_at DESC
    ");
    $stmt->execute($params);
    $leads = $stmt->fetchAll();
    
    // Get team members for filter
    $team_stmt = $pdo->query("SELECT id, username, full_name FROM users WHERE role = 'team' AND status = 'active' ORDER BY full_name");
    $team_members = $team_stmt->fetchAll();
    
    // Get lead statistics
    $stats = [];
    $stats['total'] = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
    $stats['hot'] = $pdo->query("SELECT COUNT(*) FROM leads WHERE lead_score = 'HOT'")->fetchColumn();
    $stats['warm'] = $pdo->query("SELECT COUNT(*) FROM leads WHERE lead_score = 'WARM'")->fetchColumn();
    $stats['cold'] = $pdo->query("SELECT COUNT(*) FROM leads WHERE lead_score = 'COLD'")->fetchColumn();
    $stats['converted'] = $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'converted'")->fetchColumn();
    $stats['active'] = $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'active'")->fetchColumn();
    
    Logger::info('Admin leads management accessed', [
        'user_id' => $_SESSION['user_id'],
        'filter' => $filter,
        'search' => $search
    ]);

} catch (PDOException $e) {
    Logger::error('Database error in admin leads management', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id']
    ]);
    $leads = [];
    $team_members = [];
    $stats = array_fill_keys(['total', 'hot', 'warm', 'cold', 'converted', 'active'], 0);
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>👥 Leads Management - Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
        
        .dashboard-container {
            display: grid;
            grid-template-columns: 280px 1fr;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 30px 20px;
            box-shadow: 2px 0 20px rgba(0,0,0,0.1);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo h1 {
            font-size: 24px;
            font-weight: 800;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .logo p {
            color: #666;
            font-size: 14px;
        }
        
        .nav-menu {
            list-style: none;
        }
        
        .nav-item {
            margin-bottom: 8px;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: #555;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .nav-link:hover, .nav-link.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            transform: translateX(5px);
        }
        
        .nav-link i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            padding: 30px;
            overflow-y: auto;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 20px 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 32px;
            font-weight: 800;
            color: #333;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 16px;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 25px;
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
        
        .stat-card.hot::before { background: linear-gradient(90deg, #ff6b6b, #ee5a24); }
        .stat-card.warm::before { background: linear-gradient(90deg, #feca57, #ff9ff3); }
        .stat-card.cold::before { background: linear-gradient(90deg, #54a0ff, #5f27cd); }
        .stat-card.success::before { background: linear-gradient(90deg, #00d2d3, #54a0ff); }
        
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            margin: 0 auto 15px;
        }
        
        .stat-icon.primary { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-icon.hot { background: linear-gradient(135deg, #ff6b6b, #ee5a24); }
        .stat-icon.warm { background: linear-gradient(135deg, #feca57, #ff9ff3); }
        .stat-icon.cold { background: linear-gradient(135deg, #54a0ff, #5f27cd); }
        .stat-icon.success { background: linear-gradient(135deg, #00d2d3, #54a0ff); }
        
        .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }
        
        /* Filters */
        .filters-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 25px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .filters-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 20px;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 0;
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
        
        .btn {
            padding: 12px 24px;
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
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        /* Table */
        .table-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
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
        
        .badge-active {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-converted {
            background: #cce5ff;
            color: #0066cc;
        }
        
        .badge-lost {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
            margin: 2px;
        }
        
        /* Modal */
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
            font-size: 24px;
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
        
        /* Success Message */
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
        
        /* Responsive */
        @media (max-width: 1200px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .header h1 {
                font-size: 24px;
            }
        }
        
        /* Language Toggle */
        .lang-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.9);
            padding: 10px 15px;
            border-radius: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .lang-toggle button {
            background: none;
            border: none;
            padding: 5px 10px;
            margin: 0 2px;
            border-radius: 15px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .lang-toggle button.active {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <div class="lang-toggle">
        <button class="lang-btn" onclick="switchLanguage('en')">EN</button>
        <button class="lang-btn active" onclick="switchLanguage('hi')">हिं</button>
    </div>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="logo">
                <h1>👥 Leads CRM</h1>
                <p>Management</p>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="/admin/crm-dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span data-en="Dashboard" data-hi="डैशबोर्ड">डैशबोर्ड</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/leads.php" class="nav-link active">
                        <i class="fas fa-users"></i>
                        <span data-en="Leads Management" data-hi="लीड्स प्रबंधन">लीड्स प्रबंधन</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/team.php" class="nav-link">
                        <i class="fas fa-user-friends"></i>
                        <span data-en="Team Management" data-hi="टीम प्रबंधन">टीम प्रबंधन</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/analytics.php" class="nav-link">
                        <i class="fas fa-chart-line"></i>
                        <span data-en="Analytics" data-hi="विश्लेषण">विश्लेषण</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span data-en="Logout" data-hi="लॉग आउट">लॉग आउट</span>
                    </a>
                </li>
            </ul>
    </nav>
    
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1 data-en="Leads Management" data-hi="लीड्स प्रबंधन">लीड्स प्रबंधन</h1>
                <p data-en="Manage all leads and prospects in your network marketing business" data-hi="अपने नेटवर्क मार्केटिंग व्यवसाय में सभी लीड्स और प्रॉस्पेक्ट्स को प्रबंधित करें">अपने नेटवर्क मार्केटिंग व्यवसाय में सभी लीड्स और प्रॉस्पेक्ट्स को प्रबंधित करें</p>
            </div>

            <!-- Success Message -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <!-- Lead Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                    <div class="stat-label" data-en="Total Leads" data-hi="कुल लीड्स">कुल लीड्स</div>
                </div>

                <div class="stat-card hot">
                    <div class="stat-icon hot">
                        <i class="fas fa-fire"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['hot']); ?></div>
                    <div class="stat-label" data-en="🔥 Hot Leads" data-hi="🔥 गर्म लीड्स">🔥 गर्म लीड्स</div>
                </div>

                <div class="stat-card warm">
                    <div class="stat-icon warm">
                        <i class="fas fa-thermometer-half"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['warm']); ?></div>
                    <div class="stat-label" data-en="🌡️ Warm Leads" data-hi="🌡️ गुनगुने लीड्स">🌡️ गुनगुने लीड्स</div>
        </div>
        
                <div class="stat-card cold">
                    <div class="stat-icon cold">
                        <i class="fas fa-snowflake"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['cold']); ?></div>
                    <div class="stat-label" data-en="❄️ Cold Leads" data-hi="❄️ ठंडे लीड्स">❄️ ठंडे लीड्स</div>
                </div>

                <div class="stat-card success">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['converted']); ?></div>
                    <div class="stat-label" data-en="✅ Converted" data-hi="✅ रूपांतरित">✅ रूपांतरित</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['active']); ?></div>
                    <div class="stat-label" data-en="🔄 Active" data-hi="🔄 सक्रिय">🔄 सक्रिय</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" class="filters-row">
                    <div class="form-group">
                        <label data-en="Filter by Status" data-hi="स्थिति के अनुसार फ़िल्टर करें">स्थिति के अनुसार फ़िल्टर करें</label>
                        <select name="filter" class="form-control">
                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?> data-en="All Leads" data-hi="सभी लीड्स">सभी लीड्स</option>
                            <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?> data-en="Active" data-hi="सक्रिय">सक्रिय</option>
                            <option value="converted" <?php echo $filter === 'converted' ? 'selected' : ''; ?> data-en="Converted" data-hi="रूपांतरित">रूपांतरित</option>
                            <option value="lost" <?php echo $filter === 'lost' ? 'selected' : ''; ?> data-en="Lost" data-hi="खोए हुए">खोए हुए</option>
                        </select>
        </div>
        
                    <div class="form-group">
                        <label data-en="Assigned To" data-hi="असाइन किया गया">असाइन किया गया</label>
                        <select name="assigned_to" class="form-control">
                            <option value="" data-en="All Members" data-hi="सभी सदस्य">सभी सदस्य</option>
                    <?php foreach ($team_members as $member): ?>
                                <option value="<?php echo $member['id']; ?>" <?php echo $assigned_to == $member['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($member['full_name'] ?: $member['username']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                    </div>
                    
                    <div class="form-group">
                        <label data-en="Search Leads" data-hi="लीड्स खोजें">लीड्स खोजें</label>
                        <input type="text" name="search" class="form-control" placeholder="Name, email, or phone..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            <span data-en="Search" data-hi="खोजें">खोजें</span>
                        </button>
                    </div>
            </form>
        </div>
        
            <!-- Leads Table -->
            <div class="table-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0; color: #333;" data-en="All Leads" data-hi="सभी लीड्स">सभी लीड्स</h3>
                    <span style="color: #666;" data-en="<?php echo count($leads); ?> leads found" data-hi="<?php echo count($leads); ?> लीड्स मिले"><?php echo count($leads); ?> लीड्स मिले</span>
                </div>
                
                <?php if (empty($leads)): ?>
                    <p style="text-align: center; color: #999; padding: 40px;" data-en="No leads found" data-hi="कोई लीड नहीं मिला">कोई लीड नहीं मिला</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th data-en="Name" data-hi="नाम">नाम</th>
                                <th data-en="Contact" data-hi="संपर्क">संपर्क</th>
                                <th data-en="Score" data-hi="स्कोर">स्कोर</th>
                                <th data-en="Status" data-hi="स्थिति">स्थिति</th>
                                <th data-en="Assigned To" data-hi="असाइन किया गया">असाइन किया गया</th>
                                <th data-en="Source" data-hi="स्रोत">स्रोत</th>
                                <th data-en="Date Added" data-hi="तिथि जोड़ी गई">तिथि जोड़ी गई</th>
                                <th data-en="Actions" data-hi="कार्य">कार्य</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leads as $lead): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($lead['name']); ?></strong>
                                    <?php if ($lead['notes']): ?>
                                        <br><small style="color: #666;"><?php echo htmlspecialchars(substr($lead['notes'], 0, 50)); ?><?php echo strlen($lead['notes']) > 50 ? '...' : ''; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($lead['email']); ?></div>
                                    <?php if ($lead['phone']): ?>
                                        <div><small><?php echo htmlspecialchars($lead['phone']); ?></small></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($lead['lead_score']); ?>">
                                        <?php echo $lead['lead_score']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $status_badge_class = 'badge-active';
                                    if ($lead['status'] === 'converted') $status_badge_class = 'badge-converted';
                                    if ($lead['status'] === 'lost') $status_badge_class = 'badge-lost';
                                    ?>
                                    <span class="badge <?php echo $status_badge_class; ?>">
                                        <?php echo ucfirst($lead['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($lead['assigned_name']): ?>
                                        <?php echo htmlspecialchars($lead['assigned_name']); ?>
                                    <?php else: ?>
                                        <span style="color: #999;" data-en="Unassigned" data-hi="असाइन नहीं">असाइन नहीं</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($lead['source'] ?: 'Direct'); ?></td>
                                <td><?php echo date('d M Y', strtotime($lead['created_at'])); ?></td>
                                <td>
                                    <button onclick="openLeadModal(<?php echo $lead['id']; ?>)" class="btn btn-primary btn-small">
                                        <i class="fas fa-edit"></i>
                                        <span data-en="Manage" data-hi="प्रबंधित करें">प्रबंधित करें</span>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
            <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Lead Management Modal -->
    <div id="leadModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" data-en="Manage Lead" data-hi="लीड प्रबंधित करें">लीड प्रबंधित करें</h3>
                <span class="close" onclick="closeLeadModal()">&times;</span>
            </div>
            
            <form id="leadForm" method="POST">
                <input type="hidden" name="lead_id" id="leadId">
                
                <div class="form-group">
                    <label data-en="Assign to Team Member" data-hi="टीम सदस्य को असाइन करें">टीम सदस्य को असाइन करें</label>
                    <select name="assigned_to" id="assignedTo" class="form-control">
                        <option value="" data-en="Unassigned" data-hi="असाइन नहीं">असाइन नहीं</option>
                        <?php foreach ($team_members as $member): ?>
                            <option value="<?php echo $member['id']; ?>">
                                <?php echo htmlspecialchars($member['full_name'] ?: $member['username']); ?>
                            </option>
                    <?php endforeach; ?>
                    </select>
                    <button type="submit" name="assign_lead" class="btn btn-primary" style="margin-top: 10px;">
                        <i class="fas fa-user-plus"></i>
                        <span data-en="Assign Lead" data-hi="लीड असाइन करें">लीड असाइन करें</span>
                    </button>
                </div>
                
                <hr style="margin: 20px 0;">
                
                <div class="form-group">
                    <label data-en="Update Status" data-hi="स्थिति अपडेट करें">स्थिति अपडेट करें</label>
                    <select name="status" id="leadStatus" class="form-control">
                        <option value="active" data-en="Active" data-hi="सक्रिय">सक्रिय</option>
                        <option value="converted" data-en="Converted" data-hi="रूपांतरित">रूपांतरित</option>
                        <option value="lost" data-en="Lost" data-hi="खोया हुआ">खोया हुआ</option>
                    </select>
                    <button type="submit" name="update_status" class="btn btn-primary" style="margin-top: 10px;">
                        <i class="fas fa-check"></i>
                        <span data-en="Update Status" data-hi="स्थिति अपडेट करें">स्थिति अपडेट करें</span>
                    </button>
                </div>
                
                <hr style="margin: 20px 0;">
                
                <div class="form-group">
                    <label data-en="Update Lead Score" data-hi="लीड स्कोर अपडेट करें">लीड स्कोर अपडेट करें</label>
                    <select name="score" id="leadScore" class="form-control">
                        <option value="HOT" data-en="🔥 HOT" data-hi="🔥 HOT">🔥 HOT</option>
                        <option value="WARM" data-en="🌡️ WARM" data-hi="🌡️ WARM">🌡️ WARM</option>
                        <option value="COLD" data-en="❄️ COLD" data-hi="❄️ COLD">❄️ COLD</option>
                    </select>
                    <button type="submit" name="update_score" class="btn btn-primary" style="margin-top: 10px;">
                        <i class="fas fa-star"></i>
                        <span data-en="Update Score" data-hi="स्कोर अपडेट करें">स्कोर अपडेट करें</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Language switching functionality
        function switchLanguage(lang) {
            document.querySelectorAll('[data-en][data-hi]').forEach(element => {
                element.textContent = element.getAttribute('data-' + lang);
            });
            
            document.querySelectorAll('.lang-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            event.target.classList.add('active');
        }

        // Modal functions
        function openLeadModal(leadId) {
            document.getElementById('leadId').value = leadId;
            document.getElementById('leadModal').style.display = 'block';
        }

        function closeLeadModal() {
            document.getElementById('leadModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('leadModal');
            if (event.target === modal) {
                closeLeadModal();
            }
        }

        // Initialize interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to stat cards
            document.querySelectorAll('.stat-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });
        });
    </script>
</body>
</html>