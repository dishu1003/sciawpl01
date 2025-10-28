<?php
/**
 * Enhanced Admin CRM Dashboard for Network Marketing
 * High-end dashboard with advanced analytics and team management
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
    
    // Enhanced statistics for network marketing
    $stats = [];
    
    // Basic lead stats
    $stats['total_leads'] = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
    $stats['hot_leads'] = $pdo->query("SELECT COUNT(*) FROM leads WHERE lead_score = 'HOT'")->fetchColumn();
    $stats['converted_leads'] = $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'converted'")->fetchColumn();
    $stats['active_team'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'team' AND status = 'active'")->fetchColumn();
    
    // Network marketing specific stats
    $stats['total_downlines'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'team' AND upline_id IS NOT NULL")->fetchColumn();
    $stats['new_recruits_today'] = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $stats['total_commissions'] = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM commissions")->fetchColumn();
    $stats['active_recruiters'] = $pdo->query("SELECT COUNT(DISTINCT upline_id) FROM users WHERE upline_id IS NOT NULL")->fetchColumn();
    
    // Performance metrics
    $stats['conversion_rate'] = $stats['total_leads'] > 0 ? round(($stats['converted_leads'] / $stats['total_leads']) * 100, 2) : 0;
    $stats['avg_team_size'] = $stats['active_recruiters'] > 0 ? round($stats['total_downlines'] / $stats['active_recruiters'], 1) : 0;
    
    // Recent activities
    $recent_leads = $pdo->query("SELECT * FROM leads ORDER BY created_at DESC LIMIT 10")->fetchAll();
    $top_performers = $pdo->query("
        SELECT u.username, u.full_name, 
               COUNT(l.id) as leads_generated,
               COUNT(CASE WHEN l.status = 'converted' THEN 1 END) as conversions,
               COUNT(d.id) as downlines
        FROM users u 
        LEFT JOIN leads l ON u.id = l.assigned_to 
        LEFT JOIN users d ON d.upline_id = u.id
        WHERE u.role = 'team'
        GROUP BY u.id
        ORDER BY conversions DESC, leads_generated DESC
        LIMIT 5
    ")->fetchAll();
    
    // Monthly performance
    $monthly_stats = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as leads,
            COUNT(CASE WHEN status = 'converted' THEN 1 END) as conversions,
            COUNT(CASE WHEN lead_score = 'HOT' THEN 1 END) as hot_leads
        FROM leads 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ")->fetchAll();
    
    Logger::info('Enhanced admin dashboard accessed', [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username']
    ]);

} catch (PDOException $e) {
    Logger::error('Database error in enhanced admin dashboard', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id']
    ]);
    $stats = array_fill_keys(['total_leads', 'hot_leads', 'converted_leads', 'active_team', 'total_downlines', 'new_recruits_today', 'total_commissions', 'active_recruiters', 'conversion_rate', 'avg_team_size'], 0);
    $recent_leads = $top_performers = $monthly_stats = [];
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎯 Network Marketing CRM - Admin Dashboard</title>
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
        
        .stat-change.negative {
            background: #f8d7da;
            color: #721c24;
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
                <h1>🎯 Network CRM</h1>
                <p>Admin Dashboard</p>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="/admin/crm-dashboard.php" class="nav-link active">
                        <i class="fas fa-tachometer-alt"></i>
                        <span data-en="Dashboard" data-hi="डैशबोर्ड">डैशबोर्ड</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/leads.php" class="nav-link">
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
                    <a href="/admin/advanced-analytics.php" class="nav-link">
                        <i class="fas fa-chart-line"></i>
                        <span data-en="Analytics" data-hi="विश्लेषण">विश्लेषण</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/training-support.php" class="nav-link">
                        <i class="fas fa-graduation-cap"></i>
                        <span data-en="Training Materials" data-hi="प्रशिक्षण सामग्री">प्रशिक्षण सामग्री</span>
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
                <h1 data-en="Network Marketing CRM Dashboard" data-hi="नेटवर्क मार्केटिंग सीआरएम डैशबोर्ड">नेटवर्क मार्केटिंग सीआरएम डैशबोर्ड</h1>
                <p data-en="Complete overview of your network marketing business" data-hi="आपके नेटवर्क मार्केटिंग व्यवसाय का पूर्ण अवलोकन">आपके नेटवर्क मार्केटिंग व्यवसाय का पूर्ण अवलोकन</p>
            </div>

            <!-- Key Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-change positive">+12%</div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_leads']); ?></div>
                    <div class="stat-label" data-en="Total Leads" data-hi="कुल लीड्स">कुल लीड्स</div>
                </div>

                <div class="stat-card hot">
                    <div class="stat-header">
                        <div class="stat-icon hot">
                            <i class="fas fa-fire"></i>
                        </div>
                        <div class="stat-change positive">+8%</div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['hot_leads']); ?></div>
                    <div class="stat-label" data-en="🔥 Hot Leads" data-hi="🔥 गर्म लीड्स">🔥 गर्म लीड्स</div>
                </div>

                <div class="stat-card success">
                    <div class="stat-header">
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-change positive">+15%</div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['converted_leads']); ?></div>
                    <div class="stat-label" data-en="✅ Conversions" data-hi="✅ रूपांतरण">✅ रूपांतरण</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon primary">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <div class="stat-change positive">+5%</div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['active_team']); ?></div>
                    <div class="stat-label" data-en="Active Team" data-hi="सक्रिय टीम">सक्रिय टीम</div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-header">
                        <div class="stat-icon warning">
                            <i class="fas fa-sitemap"></i>
                        </div>
                        <div class="stat-change positive">+20%</div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_downlines']); ?></div>
                    <div class="stat-label" data-en="Total Downlines" data-hi="कुल डाउनलाइन्स">कुल डाउनलाइन्स</div>
                </div>

                <div class="stat-card success">
                    <div class="stat-header">
                        <div class="stat-icon success">
                            <i class="fas fa-rupee-sign"></i>
                        </div>
                        <div class="stat-change positive">+25%</div>
                    </div>
                    <div class="stat-value">₹<?php echo number_format($stats['total_commissions']); ?></div>
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

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon primary">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $stats['avg_team_size']; ?></div>
                    <div class="stat-label" data-en="Avg Team Size" data-hi="औसत टीम साइज">औसत टीम साइज</div>
                </div>

                <div class="stat-card hot">
                    <div class="stat-header">
                        <div class="stat-icon hot">
                            <i class="fas fa-user-plus"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $stats['new_recruits_today']; ?></div>
                    <div class="stat-label" data-en="New Recruits Today" data-hi="आज के नए भर्ती">आज के नए भर्ती</div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-header">
                        <div class="stat-icon warning">
                            <i class="fas fa-trophy"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $stats['active_recruiters']; ?></div>
                    <div class="stat-label" data-en="Active Recruiters" data-hi="सक्रिय भर्तीकर्ता">सक्रिय भर्तीकर्ता</div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Recent Leads -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title" data-en="Recent Leads" data-hi="हाल के लीड्स">हाल के लीड्स</h3>
                        <a href="/admin/leads.php" class="btn btn-primary">
                            <i class="fas fa-eye"></i>
                            <span data-en="View All" data-hi="सभी देखें">सभी देखें</span>
                        </a>
                    </div>
                    
                    <?php if (empty($recent_leads)): ?>
                        <p style="text-align: center; color: #999; padding: 40px;" data-en="No leads found" data-hi="कोई लीड नहीं मिला">कोई लीड नहीं मिला</p>
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

                <!-- Top Performers -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title" data-en="Top Performers" data-hi="टॉप परफॉर्मर्स">टॉप परफॉर्मर्स</h3>
                    </div>
                    
                    <?php if (empty($top_performers)): ?>
                        <p style="text-align: center; color: #999; padding: 40px;" data-en="No performance data" data-hi="कोई प्रदर्शन डेटा नहीं">कोई प्रदर्शन डेटा नहीं</p>
                    <?php else: ?>
                        <?php foreach ($top_performers as $index => $performer): ?>
                        <div class="performance-card">
                            <h4>#<?php echo $index + 1; ?> <?php echo htmlspecialchars($performer['full_name'] ?: $performer['username']); ?></h4>
                            <div class="value"><?php echo $performer['conversions']; ?></div>
                            <div class="label" data-en="Conversions" data-hi="रूपांतरण">रूपांतरण</div>
                            <div style="margin-top: 10px;">
                                <small><?php echo $performer['leads_generated']; ?> <span data-en="leads" data-hi="लीड्स">लीड्स</span> | <?php echo $performer['downlines']; ?> <span data-en="downlines" data-hi="डाउनलाइन्स">डाउनलाइन्स</span></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" data-en="Quick Actions" data-hi="त्वरित कार्य">त्वरित कार्य</h3>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <a href="/admin/leads.php?action=add" class="btn btn-primary" style="justify-content: center; padding: 20px;">
                        <i class="fas fa-user-plus"></i>
                        <span data-en="Add New Lead" data-hi="नया लीड जोड़ें">नया लीड जोड़ें</span>
                    </a>
                    
                    <a href="/admin/team.php?action=add" class="btn btn-primary" style="justify-content: center; padding: 20px;">
                        <i class="fas fa-user-friends"></i>
                        <span data-en="Add Team Member" data-hi="टीम सदस्य जोड़ें">टीम सदस्य जोड़ें</span>
                    </a>
                    
                    <a href="/admin/analytics.php" class="btn btn-primary" style="justify-content: center; padding: 20px;">
                        <i class="fas fa-chart-bar"></i>
                        <span data-en="View Reports" data-hi="रिपोर्ट देखें">रिपोर्ट देखें</span>
                    </a>
                    
                    <a href="/admin/commissions.php" class="btn btn-primary" style="justify-content: center; padding: 20px;">
                        <i class="fas fa-calculator"></i>
                        <span data-en="Calculate Commissions" data-hi="कमीशन गणना">कमीशन गणना</span>
                    </a>
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
            console.log('Updating stats...');
        }

        // Update stats every 30 seconds
        setInterval(updateStats, 30000);

        // Initialize tooltips and interactions
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
