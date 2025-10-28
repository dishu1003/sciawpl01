<?php
/**
 * My Referrals - Team Member Interface
 * Track leads generated through personal referral links
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

$user_id = $_SESSION['user_id'];

try {
    $pdo = get_pdo_connection();
    
    // Get user information
    $user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();
    
    // Generate personal referral link
    $referral_code = 'REF' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
    $referral_link = "https://" . $_SERVER['HTTP_HOST'] . "/?ref=" . $referral_code;
    
    // Optimized referral statistics query
    $stats_query = "
        SELECT
            COUNT(*) as total_referral_leads,
            SUM(CASE WHEN lead_score = 'HOT' THEN 1 ELSE 0 END) as hot_referral_leads,
            SUM(CASE WHEN lead_score = 'WARM' THEN 1 ELSE 0 END) as warm_referral_leads,
            SUM(CASE WHEN lead_score = 'COLD' THEN 1 ELSE 0 END) as cold_referral_leads,
            SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as referral_conversions,
            SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as this_month_referrals
        FROM leads
        WHERE referral_code = ?
    ";
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute([$referral_code]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Performance metrics
    $stats['referral_conversion_rate'] = $stats['total_referral_leads'] > 0 ? round(($stats['referral_conversions'] / $stats['total_referral_leads']) * 100, 2) : 0;
    
    // Get all referral leads
    $referral_leads_stmt = $pdo->prepare("
        SELECT * FROM leads 
        WHERE referral_code = ? 
        ORDER BY created_at DESC
    ");
    $referral_leads_stmt->execute([$referral_code]);
    $referral_leads = $referral_leads_stmt->fetchAll();
    
    // Monthly referral trend (last 6 months)
    $monthly_referral_trend = [];
    for ($i = 5; $i >= 0; $i--) {
        $month_start = date('Y-m-01', strtotime("-$i months"));
        $month_end = date('Y-m-t', strtotime("-$i months"));
        $month_name = date('M Y', strtotime("-$i months"));
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE referral_code = ? AND created_at >= ? AND created_at <= ?");
        $stmt->execute([$referral_code, $month_start, $month_end]);
        $count = $stmt->fetchColumn();
        
        $monthly_referral_trend[] = [
            'month' => $month_name,
            'count' => $count
        ];
    }
    
    Logger::info('Referral tracking page accessed', [
        'user_id' => $user_id,
        'referral_code' => $referral_code
    ]);

} catch (PDOException $e) {
    Logger::error('Database error in referral tracking', [
        'error' => $e->getMessage(),
        'user_id' => $user_id
    ]);
    $stats = array_fill_keys(['total_referral_leads', 'hot_referral_leads', 'warm_referral_leads', 'cold_referral_leads', 'referral_conversions', 'this_month_referrals', 'referral_conversion_rate'], 0);
    $referral_leads = $monthly_referral_trend = [];
    $user = ['username' => 'User', 'full_name' => 'User'];
    $referral_code = 'REF000000';
    $referral_link = '#';
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔗 My Referrals - My Dashboard</title>
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
        
        .stat-card.hot::before { background: linear-gradient(90deg, #ff6b6b, #ee5a24); }
        .stat-card.warm::before { background: linear-gradient(90deg, #feca57, #ff9ff3); }
        .stat-card.cold::before { background: linear-gradient(90deg, #54a0ff, #5f27cd); }
        .stat-card.success::before { background: linear-gradient(90deg, #00d2d3, #54a0ff); }
        .stat-card.referral::before { background: linear-gradient(90deg, #ff9ff3, #f368e0); }
        
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
        .stat-icon.hot { background: linear-gradient(135deg, #ff6b6b, #ee5a24); }
        .stat-icon.warm { background: linear-gradient(135deg, #feca57, #ff9ff3); }
        .stat-icon.cold { background: linear-gradient(135deg, #54a0ff, #5f27cd); }
        .stat-icon.success { background: linear-gradient(135deg, #00d2d3, #54a0ff); }
        .stat-icon.referral { background: linear-gradient(135deg, #ff9ff3, #f368e0); }
        
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
        
        /* Referral Link Section */
        .referral-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }
        
        .referral-title {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .referral-link-container {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .referral-link {
            font-family: 'Courier New', monospace;
            font-size: 16px;
            color: #667eea;
            word-break: break-all;
            margin-bottom: 15px;
            padding: 10px;
            background: white;
            border-radius: 8px;
            border: 2px solid #e9ecef;
        }
        
        .referral-code {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
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
        
        .btn-success {
            background: linear-gradient(135deg, #00d2d3, #54a0ff);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 210, 211, 0.3);
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        /* Referral Leads Table */
        .referral-leads-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .referral-leads-title {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
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
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                <div class="user-name"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></div>
                <div class="user-role" data-en="Direct Seller" data-hi="डायरेक्ट सेलर">डायरेक्ट सेलर</div>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="/team/index.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span data-en="Dashboard" data-hi="डैशबोर्ड">डैशबोर्ड</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/team/lead-management.php" class="nav-link">
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
                    <a href="/team/my-referrals.php" class="nav-link active">
                        <i class="fas fa-link"></i>
                        <span data-en="My Referrals" data-hi="मेरे रेफरल">मेरे रेफरल</span>
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
                <h1 data-en="My Referrals" data-hi="मेरे रेफरल">मेरे रेफरल</h1>
                <p data-en="Track leads generated through your personal referral link" data-hi="अपने व्यक्तिगत रेफरल लिंक के माध्यम से उत्पन्न लीड्स को ट्रैक करें">अपने व्यक्तिगत रेफरल लिंक के माध्यम से उत्पन्न लीड्स को ट्रैक करें</p>
            </div>

            <!-- Personal Referral Link -->
            <div class="referral-section">
                <h3 class="referral-title">
                    <i class="fas fa-link"></i>
                    <span data-en="Your Personal Referral Link" data-hi="आपका व्यक्तिगत रेफरल लिंक">आपका व्यक्तिगत रेफरल लिंक</span>
                </h3>
                <div class="referral-link-container">
                    <div class="referral-code" data-en="Referral Code: <?php echo $referral_code; ?>" data-hi="रेफरल कोड: <?php echo $referral_code; ?>">रेफरल कोड: <?php echo $referral_code; ?></div>
                    <div class="referral-link" id="referralLink"><?php echo $referral_link; ?></div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button onclick="copyReferralLink()" class="btn btn-primary">
                            <i class="fas fa-copy"></i>
                            <span data-en="Copy Link" data-hi="लिंक कॉपी करें">लिंक कॉपी करें</span>
                        </button>
                        <button onclick="shareReferralLink()" class="btn btn-success">
                            <i class="fas fa-share"></i>
                            <span data-en="Share" data-hi="शेयर करें">शेयर करें</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Referral Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-link"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_referral_leads']); ?></div>
                    <div class="stat-label" data-en="Total Referral Leads" data-hi="कुल रेफरल लीड्स">कुल रेफरल लीड्स</div>
                </div>

                <div class="stat-card hot">
                    <div class="stat-icon hot">
                        <i class="fas fa-fire"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['hot_referral_leads']); ?></div>
                    <div class="stat-label" data-en="🔥 Hot Referrals" data-hi="🔥 गर्म रेफरल">🔥 गर्म रेफरल</div>
                </div>

                <div class="stat-card warm">
                    <div class="stat-icon warm">
                        <i class="fas fa-thermometer-half"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['warm_referral_leads']); ?></div>
                    <div class="stat-label" data-en="🌡️ Warm Referrals" data-hi="🌡️ गुनगुने रेफरल">🌡️ गुनगुने रेफरल</div>
                </div>

                <div class="stat-card cold">
                    <div class="stat-icon cold">
                        <i class="fas fa-snowflake"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['cold_referral_leads']); ?></div>
                    <div class="stat-label" data-en="❄️ Cold Referrals" data-hi="❄️ ठंडे रेफरल">❄️ ठंडे रेफरल</div>
                </div>

                <div class="stat-card success">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['referral_conversions']); ?></div>
                    <div class="stat-label" data-en="✅ Referral Conversions" data-hi="✅ रेफरल रूपांतरण">✅ रेफरल रूपांतरण</div>
                </div>

                <div class="stat-card referral">
                    <div class="stat-icon referral">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['this_month_referrals']); ?></div>
                    <div class="stat-label" data-en="This Month" data-hi="इस महीने">इस महीने</div>
                </div>
            </div>

            <!-- Performance Metrics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['referral_conversion_rate']; ?>%</div>
                    <div class="stat-label" data-en="Referral Conversion Rate" data-hi="रेफरल रूपांतरण दर">रेफरल रूपांतरण दर</div>
                </div>
            </div>

            <!-- Referral Leads List -->
            <div class="referral-leads-container">
                <h3 class="referral-leads-title" data-en="All Referral Leads" data-hi="सभी रेफरल लीड्स">सभी रेफरल लीड्स</h3>
                
                <?php if (empty($referral_leads)): ?>
                    <div class="empty-state">
                        <i class="fas fa-link"></i>
                        <h3 data-en="No Referral Leads Yet" data-hi="अभी तक कोई रेफरल लीड नहीं">अभी तक कोई रेफरल लीड नहीं</h3>
                        <p data-en="Share your referral link to start getting leads" data-hi="लीड्स पाना शुरू करने के लिए अपना रेफरल लिंक साझा करें">लीड्स पाना शुरू करने के लिए अपना रेफरल लिंक साझा करें</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th data-en="Name" data-hi="नाम">नाम</th>
                                <th data-en="Contact" data-hi="संपर्क">संपर्क</th>
                                <th data-en="Score" data-hi="स्कोर">स्कोर</th>
                                <th data-en="Status" data-hi="स्थिति">स्थिति</th>
                                <th data-en="Date" data-hi="तारीख">तारीख</th>
                                <th data-en="Notes" data-hi="नोट्स">नोट्स</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($referral_leads as $lead): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($lead['name']); ?></strong>
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
                                    if ($lead['status'] === 'lost') $status_badge_class = 'badge-hot';
                                    ?>
                                    <span class="badge <?php echo $status_badge_class; ?>">
                                        <?php echo ucfirst($lead['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y', strtotime($lead['created_at'])); ?></td>
                                <td>
                                    <?php if ($lead['notes']): ?>
                                        <small style="color: #666;"><?php echo htmlspecialchars(substr($lead['notes'], 0, 50)); ?><?php echo strlen($lead['notes']) > 50 ? '...' : ''; ?></small>
                                    <?php else: ?>
                                        <small style="color: #999;">No notes</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
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
        }

        // Copy referral link to clipboard
        function copyReferralLink() {
            const referralLink = document.getElementById('referralLink').textContent;
            navigator.clipboard.writeText(referralLink).then(function() {
                // Show success message
                const button = event.target.closest('button');
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i> Copied!';
                button.style.background = '#28a745';
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.style.background = '';
                }, 2000);
            }).catch(function(err) {
                console.error('Could not copy text: ', err);
                alert('Could not copy link. Please copy manually.');
            });
        }

        // Share referral link
        function shareReferralLink() {
            const referralLink = document.getElementById('referralLink').textContent;
            const referralCode = '<?php echo $referral_code; ?>';
            
            if (navigator.share) {
                navigator.share({
                    title: 'Join My Direct Selling Business',
                    text: `Check out this opportunity! Use my referral code: ${referralCode}`,
                    url: referralLink
                });
            } else {
                // Fallback for browsers that don't support Web Share API
                const shareText = `Check out this opportunity! Use my referral code: ${referralCode}\n\n${referralLink}`;
                navigator.clipboard.writeText(shareText).then(function() {
                    alert('Share text copied to clipboard!');
                });
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
