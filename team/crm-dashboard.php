<?php
/**
 * Enhanced Team Member CRM Dashboard for Network Marketing
 * User-friendly dashboard for direct sellers with lead management and network tracking
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
require_team_access();
check_session_timeout();

try {
    $pdo = get_pdo_connection();
    $user_id = $_SESSION['user_id'];
    
    // Get user information
    $user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();
    
    // Team member statistics
    $stats = [];
    
    // Personal lead stats
    $stats['my_leads'] = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE assigned_to = ?");
    $stats['my_leads']->execute([$user_id]);
    $stats['my_leads'] = $stats['my_leads']->fetchColumn();
    
    $stats['my_hot_leads'] = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE assigned_to = ? AND lead_score = 'HOT'");
    $stats['my_hot_leads']->execute([$user_id]);
    $stats['my_hot_leads'] = $stats['my_hot_leads']->fetchColumn();
    
    $stats['my_conversions'] = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE assigned_to = ? AND status = 'converted'");
    $stats['my_conversions']->execute([$user_id]);
    $stats['my_conversions'] = $stats['my_conversions']->fetchColumn();
    
    // Network stats
    $stats['my_downlines'] = $pdo->prepare("SELECT COUNT(*) FROM users WHERE upline_id = ?");
    $stats['my_downlines']->execute([$user_id]);
    $stats['my_downlines'] = $stats['my_downlines']->fetchColumn();
    
    $stats['total_network'] = $pdo->prepare("
        SELECT COUNT(*) FROM users 
        WHERE upline_id = ? OR upline_id IN (
            SELECT id FROM users WHERE upline_id = ?
        )
    ");
    $stats['total_network']->execute([$user_id, $user_id]);
    $stats['total_network'] = $stats['total_network']->fetchColumn();
    
    // Commission stats
    $stats['my_commissions'] = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM commissions WHERE user_id = ?");
    $stats['my_commissions']->execute([$user_id]);
    $stats['my_commissions'] = $stats['my_commissions']->fetchColumn();
    
    $stats['this_month_commission'] = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) FROM commissions 
        WHERE user_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
    ");
    $stats['this_month_commission']->execute([$user_id]);
    $stats['this_month_commission'] = $stats['this_month_commission']->fetchColumn();
    
    // Performance metrics
    $stats['conversion_rate'] = $stats['my_leads'] > 0 ? round(($stats['my_conversions'] / $stats['my_leads']) * 100, 2) : 0;
    
    // Recent leads assigned to this user
    $recent_leads_stmt = $pdo->prepare("
        SELECT * FROM leads 
        WHERE assigned_to = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $recent_leads_stmt->execute([$user_id]);
    $recent_leads = $recent_leads_stmt->fetchAll();
    
    // My downlines
    $downlines_stmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(l.id) as leads_count,
               COUNT(CASE WHEN l.status = 'converted' THEN 1 END) as conversions
        FROM users u
        LEFT JOIN leads l ON u.id = l.assigned_to
        WHERE u.upline_id = ?
        GROUP BY u.id
        ORDER BY conversions DESC, leads_count DESC
        LIMIT 5
    ");
    $downlines_stmt->execute([$user_id]);
    $top_downlines = $downlines_stmt->fetchAll();
    
    // Monthly performance
    $monthly_performance = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as leads,
            COUNT(CASE WHEN status = 'converted' THEN 1 END) as conversions,
            COUNT(CASE WHEN lead_score = 'HOT' THEN 1 END) as hot_leads
        FROM leads 
        WHERE assigned_to = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ");
    $monthly_performance->execute([$user_id]);
    $monthly_stats = $monthly_performance->fetchAll();
    
    // Goals and targets
    $goals_stmt = $pdo->prepare("SELECT * FROM user_goals WHERE user_id = ? AND status = 'active'");
    $goals_stmt->execute([$user_id]);
    $goals = $goals_stmt->fetchAll();
    
    Logger::info('Team member dashboard accessed', [
        'user_id' => $user_id,
        'username' => $user['username']
    ]);

} catch (PDOException $e) {
    Logger::error('Database error in team dashboard', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id']
    ]);
    $stats = array_fill_keys(['my_leads', 'my_hot_leads', 'my_conversions', 'my_downlines', 'total_network', 'my_commissions', 'this_month_commission', 'conversion_rate'], 0);
    $recent_leads = $top_downlines = $monthly_stats = $goals = [];
    $user = ['username' => 'User', 'full_name' => 'User'];
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎯 My Network Marketing Dashboard</title>
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
        
        .user-profile {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 15px;
            color: white;
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
            font-weight: 800;
        }
        
        .user-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .user-role {
            font-size: 14px;
            opacity: 0.8;
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
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
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
        
        .stat-card.hot::before {
            background: linear-gradient(90deg, #ff6b6b, #ee5a24);
        }
        
        .stat-card.success::before {
            background: linear-gradient(90deg, #00d2d3, #54a0ff);
        }
        
        .stat-card.warning::before {
            background: linear-gradient(90deg, #ff9ff3, #f368e0);
        }
        
        .stat-card.money::before {
            background: linear-gradient(90deg, #feca57, #ff9ff3);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .stat-icon.primary { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-icon.hot { background: linear-gradient(135deg, #ff6b6b, #ee5a24); }
        .stat-icon.success { background: linear-gradient(135deg, #00d2d3, #54a0ff); }
        .stat-icon.warning { background: linear-gradient(135deg, #ff9ff3, #f368e0); }
        .stat-icon.money { background: linear-gradient(135deg, #feca57, #ff9ff3); }
        
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
        
        .stat-change {
            font-size: 12px;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 20px;
            margin-top: 10px;
            display: inline-block;
        }
        
        .stat-change.positive {
            background: #d4edda;
            color: #155724;
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f3f4;
        }
        
        .card-title {
            font-size: 20px;
            font-weight: 700;
            color: #333;
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
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 14px;
        }
        
        /* Tables */
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
        
        /* Performance Cards */
        .performance-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        
        .performance-card h4 {
            font-size: 16px;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        
        .performance-card .value {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 5px;
        }
        
        .performance-card .label {
            font-size: 14px;
            opacity: 0.8;
        }
        
        /* Goals Section */
        .goals-section {
            margin-bottom: 30px;
        }
        
        .goal-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
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
        
        .goal-progress {
            background: #f1f3f4;
            height: 8px;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .goal-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        
        .goal-stats {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #666;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .action-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            cursor: pointer;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
        }
        
        .action-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
            color: white;
        }
        
        .action-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .action-desc {
            font-size: 14px;
            color: #666;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
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
        
        /* Motivational Quote */
        .motivation-quote {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .quote-text {
            font-size: 18px;
            font-style: italic;
            margin-bottom: 10px;
        }
        
        .quote-author {
            font-size: 14px;
            opacity: 0.8;
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
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                <div class="user-name"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></div>
                <div class="user-role" data-en="Direct Seller" data-hi="डायरेक्ट सेलर">डायरेक्ट सेलर</div>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="/team/crm-dashboard.php" class="nav-link active">
                        <i class="fas fa-tachometer-alt"></i>
                        <span data-en="Dashboard" data-hi="डैशबोर्ड">डैशबोर्ड</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/team/my-leads.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span data-en="My Leads" data-hi="मेरे लीड्स">मेरे लीड्स</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/team/add-lead.php" class="nav-link">
                        <i class="fas fa-user-plus"></i>
                        <span data-en="Add Lead" data-hi="लीड जोड़ें">लीड जोड़ें</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/team/training.php" class="nav-link">
                        <i class="fas fa-graduation-cap"></i>
                        <span data-en="Training" data-hi="प्रशिक्षण">प्रशिक्षण</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/team/goal-tracking.php" class="nav-link">
                        <i class="fas fa-target"></i>
                        <span data-en="Goals" data-hi="लक्ष्य">लक्ष्य</span>
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
                <h1 data-en="Welcome Back, <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>!" data-hi="वापसी में स्वागत है, <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>!">वापसी में स्वागत है, <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>!</h1>
                <p data-en="Track your progress and grow your network" data-hi="अपनी प्रगति को ट्रैक करें और अपना नेटवर्क बढ़ाएं">अपनी प्रगति को ट्रैक करें और अपना नेटवर्क बढ़ाएं</p>
            </div>

            <!-- Motivational Quote -->
            <div class="motivation-quote">
                <div class="quote-text" data-en="Success is not final, failure is not fatal: it is the courage to continue that counts." data-hi="सफलता अंतिम नहीं है, असफलता घातक नहीं है: जारी रखने का साहस ही मायने रखता है।">सफलता अंतिम नहीं है, असफलता घातक नहीं है: जारी रखने का साहस ही मायने रखता है।</div>
                <div class="quote-author" data-en="- Winston Churchill" data-hi="- विंस्टन चर्चिल">- विंस्टन चर्चिल</div>
            </div>

            <!-- Personal Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-change positive">+8%</div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['my_leads']); ?></div>
                    <div class="stat-label" data-en="My Total Leads" data-hi="मेरे कुल लीड्स">मेरे कुल लीड्स</div>
                </div>

                <div class="stat-card hot">
                    <div class="stat-header">
                        <div class="stat-icon hot">
                            <i class="fas fa-fire"></i>
                        </div>
                        <div class="stat-change positive">+12%</div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['my_hot_leads']); ?></div>
                    <div class="stat-label" data-en="🔥 Hot Leads" data-hi="🔥 गर्म लीड्स">🔥 गर्म लीड्स</div>
                </div>

                <div class="stat-card success">
                    <div class="stat-header">
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-change positive">+18%</div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['my_conversions']); ?></div>
                    <div class="stat-label" data-en="✅ My Conversions" data-hi="✅ मेरे रूपांतरण">✅ मेरे रूपांतरण</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon primary">
                            <i class="fas fa-sitemap"></i>
                        </div>
                        <div class="stat-change positive">+5%</div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['my_downlines']); ?></div>
                    <div class="stat-label" data-en="Direct Downlines" data-hi="प्रत्यक्ष डाउनलाइन्स">प्रत्यक्ष डाउनलाइन्स</div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-header">
                        <div class="stat-icon warning">
                            <i class="fas fa-network-wired"></i>
                        </div>
                        <div class="stat-change positive">+15%</div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_network']); ?></div>
                    <div class="stat-label" data-en="Total Network" data-hi="कुल नेटवर्क">कुल नेटवर्क</div>
                </div>

                <div class="stat-card money">
                    <div class="stat-header">
                        <div class="stat-icon money">
                            <i class="fas fa-rupee-sign"></i>
                        </div>
                        <div class="stat-change positive">+22%</div>
                    </div>
                    <div class="stat-value">₹<?php echo number_format($stats['my_commissions']); ?></div>
                    <div class="stat-label" data-en="Total Commissions" data-hi="कुल कमीशन">कुल कमीशन</div>
                </div>
            </div>

            <!-- Performance Metrics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon primary">
                            <i class="fas fa-percentage"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $stats['conversion_rate']; ?>%</div>
                    <div class="stat-label" data-en="Conversion Rate" data-hi="रूपांतरण दर">रूपांतरण दर</div>
                </div>

                <div class="stat-card money">
                    <div class="stat-header">
                        <div class="stat-icon money">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                    <div class="stat-value">₹<?php echo number_format($stats['this_month_commission']); ?></div>
                    <div class="stat-label" data-en="This Month" data-hi="इस महीने">इस महीने</div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- My Recent Leads -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title" data-en="My Recent Leads" data-hi="मेरे हाल के लीड्स">मेरे हाल के लीड्स</h3>
                        <a href="/team/my-leads.php" class="btn btn-primary btn-small">
                            <i class="fas fa-eye"></i>
                            <span data-en="View All" data-hi="सभी देखें">सभी देखें</span>
                        </a>
                    </div>
                    
                    <?php if (empty($recent_leads)): ?>
                        <p style="text-align: center; color: #999; padding: 40px;" data-en="No leads assigned to you yet" data-hi="अभी तक आपको कोई लीड नहीं दिया गया">अभी तक आपको कोई लीड नहीं दिया गया</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th data-en="Name" data-hi="नाम">नाम</th>
                                    <th data-en="Contact" data-hi="संपर्क">संपर्क</th>
                                    <th data-en="Score" data-hi="स्कोर">स्कोर</th>
                                    <th data-en="Status" data-hi="स्थिति">स्थिति</th>
                                    <th data-en="Date" data-hi="तारीख">तारीख</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_leads as $lead): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($lead['name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($lead['email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($lead['phone']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($lead['lead_score']); ?>">
                                            <?php echo $lead['lead_score']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($lead['status'] == 'converted'): ?>
                                            <span class="badge badge-converted">Converted</span>
                                        <?php else: ?>
                                            <span class="badge badge-warm">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($lead['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- My Top Downlines -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title" data-en="My Top Downlines" data-hi="मेरे टॉप डाउनलाइन्स">मेरे टॉप डाउनलाइन्स</h3>
                    </div>
                    
                    <?php if (empty($top_downlines)): ?>
                        <p style="text-align: center; color: #999; padding: 40px;" data-en="No downlines yet. Start recruiting!" data-hi="अभी तक कोई डाउनलाइन नहीं। भर्ती शुरू करें!">अभी तक कोई डाउनलाइन नहीं। भर्ती शुरू करें!</p>
                    <?php else: ?>
                        <?php foreach ($top_downlines as $index => $downline): ?>
                        <div class="performance-card">
                            <h4>#<?php echo $index + 1; ?> <?php echo htmlspecialchars($downline['full_name'] ?: $downline['username']); ?></h4>
                            <div class="value"><?php echo $downline['conversions']; ?></div>
                            <div class="label" data-en="Conversions" data-hi="रूपांतरण">रूपांतरण</div>
                            <div style="margin-top: 10px;">
                                <small><?php echo $downline['leads_count']; ?> <span data-en="leads" data-hi="लीड्स">लीड्स</span></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Goals Section -->
            <?php if (!empty($goals)): ?>
            <div class="goals-section">
                <h2 style="margin-bottom: 20px; color: #333;" data-en="My Goals" data-hi="मेरे लक्ष्य">मेरे लक्ष्य</h2>
                <?php foreach ($goals as $goal): ?>
                <div class="goal-card">
                    <div class="goal-header">
                        <div class="goal-title"><?php echo htmlspecialchars($goal['title']); ?></div>
                        <div class="stat-change positive"><?php echo $goal['target_value']; ?> <?php echo $goal['unit']; ?></div>
                    </div>
                    <div class="goal-progress">
                        <div class="goal-progress-bar" style="width: <?php echo min(100, ($goal['current_value'] / $goal['target_value']) * 100); ?>%"></div>
                    </div>
                    <div class="goal-stats">
                        <span data-en="Progress" data-hi="प्रगति">प्रगति: <?php echo $goal['current_value']; ?>/<?php echo $goal['target_value']; ?></span>
                        <span><?php echo round(($goal['current_value'] / $goal['target_value']) * 100, 1); ?>%</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" data-en="Quick Actions" data-hi="त्वरित कार्य">त्वरित कार्य</h3>
                </div>
                
                <div class="quick-actions">
                    <div class="action-card" onclick="window.location.href='/team/add-lead.php'">
                        <div class="action-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="action-title" data-en="Add New Lead" data-hi="नया लीड जोड़ें">नया लीड जोड़ें</div>
                        <div class="action-desc" data-en="Capture a new prospect" data-hi="एक नया प्रॉस्पेक्ट कैप्चर करें">एक नया प्रॉस्पेक्ट कैप्चर करें</div>
                    </div>
                    
                    <div class="action-card" onclick="window.location.href='/team/recruit.php'">
                        <div class="action-icon">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <div class="action-title" data-en="Recruit Team Member" data-hi="टीम सदस्य भर्ती करें">टीम सदस्य भर्ती करें</div>
                        <div class="action-desc" data-en="Expand your network" data-hi="अपना नेटवर्क बढ़ाएं">अपना नेटवर्क बढ़ाएं</div>
                    </div>
                    
                    <div class="action-card" onclick="window.location.href='/team/training.php'">
                        <div class="action-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="action-title" data-en="Training Materials" data-hi="प्रशिक्षण सामग्री">प्रशिक्षण सामग्री</div>
                        <div class="action-desc" data-en="Improve your skills" data-hi="अपने कौशल में सुधार करें">अपने कौशल में सुधार करें</div>
                    </div>
                    
                    <div class="action-card" onclick="window.location.href='/team/commissions.php'">
                        <div class="action-icon">
                            <i class="fas fa-rupee-sign"></i>
                        </div>
                        <div class="action-title" data-en="View Commissions" data-hi="कमीशन देखें">कमीशन देखें</div>
                        <div class="action-desc" data-en="Track your earnings" data-hi="अपनी कमाई ट्रैक करें">अपनी कमाई ट्रैक करें</div>
                    </div>
                </div>
            </div>
        </main>
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
            
            // Update document direction for Hindi
            if (lang === 'hi') {
                document.documentElement.setAttribute('dir', 'ltr');
            } else {
                document.documentElement.removeAttribute('dir');
            }
        }

        // Real-time updates (simulate)
        function updateStats() {
            // This would typically make AJAX calls to update stats
            console.log('Updating personal stats...');
        }

        // Update stats every 30 seconds
        setInterval(updateStats, 30000);

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

            // Add click effects to action cards
            document.querySelectorAll('.action-card').forEach(card => {
                card.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 150);
                });
            });
        });

        // Motivational quotes rotation
        const quotes = [
            {
                text: "सफलता अंतिम नहीं है, असफलता घातक नहीं है: जारी रखने का साहस ही मायने रखता है।",
                author: "विंस्टन चर्चिल"
            },
            {
                text: "महान चीजें कभी भी आराम से नहीं आतीं। आपको हमेशा कड़ी मेहनत करनी होती है।",
                author: "अज्ञात"
            },
            {
                text: "आपका भविष्य आपके आज के निर्णयों पर निर्भर करता है।",
                author: "अज्ञात"
            }
        ];

        function rotateQuote() {
            const randomQuote = quotes[Math.floor(Math.random() * quotes.length)];
            document.querySelector('.quote-text').textContent = randomQuote.text;
            document.querySelector('.quote-author').textContent = "- " + randomQuote.author;
        }

        // Rotate quote every 30 seconds
        setInterval(rotateQuote, 30000);
    </script>
</body>
</html>
