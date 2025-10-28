<?php
/**
 * Direct Selling Business Support System - Enhanced Admin Dashboard
 * Version 4.0 - Modern UI/UX with Advanced Features
 * @author Enhanced by AI Assistant
 * @version 4.0 - Professional Upgrade
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/security.php'; // Defines EnhancedSecurity

// 🚨 FIX: The original code called SecurityHeaders::setAll().
// Since 'SecurityHeaders' is now 'EnhancedSecurity' and lacks 'setAll()', 
// we will manually set common security headers first to resolve the Class Not Found error.

// Function to set basic security headers (replaces the missing SecurityHeaders::setAll())
function set_default_security_headers() {
    // Prevent Clickjacking
    header('X-Frame-Options: SAMEORIGIN'); 
    // Enable browser XSS filtering
    header('X-XSS-Protection: 1; mode=block');
    // Prevent MIME-sniffing
    header('X-Content-Type-Options: nosniff');
    // Enforce HTTPS communication
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    // Controls where the referrer header is sent
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Restrict embedding content
    header("Content-Security-Policy: frame-ancestors 'self'");
}

set_default_security_headers();

// Access the singleton instance of the enhanced security features
// We don't call anything here, but the class is loaded for other parts of the system.
// $security = EnhancedSecurity::getInstance(); // Uncomment this line if you use instance methods later

require_admin();
check_session_timeout();

// --- PHP INITIALIZATION & SECURE DATA FETCHING ---
$stats = array_fill_keys([
    'total_leads', 'hot_leads', 'warm_leads', 'cold_leads', 'converted_leads', 
    'active_leads', 'total_team_members', 'active_team_members', 
    'new_members_this_month', 'conversion_rate', 'hot_lead_percentage',
    'today_leads', 'weekly_leads', 'monthly_revenue'
], 0);
$recent_leads = $top_team_members = $recent_activities = [];
$db_status = 'ok'; 

try {
    $pdo = get_pdo_connection();
    
    $safe_count = function($sql) use ($pdo) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchColumn();
    };

    // Enhanced Stats
    $stats['total_leads'] = $safe_count("SELECT COUNT(*) FROM leads");
    $stats['hot_leads'] = $safe_count("SELECT COUNT(*) FROM leads WHERE lead_score = 'HOT'");
    $stats['warm_leads'] = $safe_count("SELECT COUNT(*) FROM leads WHERE lead_score = 'WARM'");
    $stats['cold_leads'] = $safe_count("SELECT COUNT(*) FROM leads WHERE lead_score = 'COLD'");
    $stats['converted_leads'] = $safe_count("SELECT COUNT(*) FROM leads WHERE status = 'converted'");
    $stats['active_leads'] = $safe_count("SELECT COUNT(*) FROM leads WHERE status = 'active'");
    $stats['today_leads'] = $safe_count("SELECT COUNT(*) FROM leads WHERE DATE(created_at) = CURDATE()");
    $stats['weekly_leads'] = $safe_count("SELECT COUNT(*) FROM leads WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    
    $stats['total_team_members'] = $safe_count("SELECT COUNT(*) FROM users WHERE role = 'team'");
    $stats['active_team_members'] = $safe_count("SELECT COUNT(*) FROM users WHERE role = 'team' AND status = 'active'");
    $stats['new_members_this_month'] = $safe_count("
        SELECT COUNT(*) FROM users 
        WHERE role = 'team' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
    ");
    
    $stats['conversion_rate'] = $stats['total_leads'] > 0 ? round(($stats['converted_leads'] / $stats['total_leads']) * 100, 2) : 0;
    $stats['hot_lead_percentage'] = $stats['total_leads'] > 0 ? round(($stats['hot_leads'] / $stats['total_leads']) * 100, 2) : 0;

    // Recent leads
    $stmt = $pdo->prepare("SELECT * FROM leads ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    $recent_leads = $stmt->fetchAll();

    // Top performers
    $stmt = $pdo->prepare("
        SELECT u.username, u.full_name, u.email,
              COUNT(l.id) as total_leads,
              COUNT(CASE WHEN l.status = 'converted' THEN 1 END) as conversions,
              COUNT(CASE WHEN l.lead_score = 'HOT' THEN 1 END) as hot_leads
        FROM users u 
        LEFT JOIN leads l ON u.id = l.assigned_to 
        WHERE u.role = 'team'
        GROUP BY u.id
        ORDER BY total_leads DESC, conversions DESC
        LIMIT 5
    ");
    $stmt->execute();
    $top_team_members = $stmt->fetchAll();

    Logger::info('Enhanced dashboard accessed', [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username']
    ]);

} catch (PDOException $e) {
    Logger::error('Database error in dashboard', ['error' => $e->getMessage()]);
    $db_status = 'error'; 
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎯 Direct Selling Business - Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --primary: #667eea;
            --primary-dark: #5568d3;
            --secondary: #764ba2;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --hot: #ff6b6b;
            --warm: #ffd93d;
            --cold: #6bcbef;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 25px -3px rgba(0,0,0,0.1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ============= ENHANCED SIDEBAR ============= */
        .dashboard-nav {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            z-index: 1000;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .dashboard-nav::-webkit-scrollbar { width: 6px; }
        .dashboard-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 3px; }

        .nav-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .nav-header h1 {
            font-size: 20px;
            font-weight: 800;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-header .emoji { font-size: 28px; }

        .user-info {
            padding: 15px 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            margin: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: var(--primary);
            font-size: 18px;
        }

        .user-details {
            flex: 1;
            overflow: hidden;
        }

        .user-details .name {
            color: white;
            font-weight: 600;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-details .role {
            color: rgba(255,255,255,0.7);
            font-size: 12px;
        }

        .nav-links {
            display: flex;
            flex-direction: column;
            padding: 10px;
            gap: 4px;
        }

        .nav-links a {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            font-weight: 500;
            padding: 12px 15px;
            border-radius: 10px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
        }

        .nav-links a i {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        .nav-links a:hover {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateX(5px);
        }

        .nav-links a.active {
            background: rgba(255,255,255,0.25);
            color: white;
            font-weight: 600;
        }

        .nav-links a.logout {
            margin-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 20px;
            color: rgba(255,255,255,0.8);
        }

        .nav-links a.logout:hover {
            background: rgba(239,68,68,0.2);
            color: white;
        }

        .nav-toggle-btn {
            margin: 15px;
            padding: 12px;
            background: rgba(255,255,255,0.1);
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .nav-toggle-btn:hover {
            background: rgba(255,255,255,0.2);
        }

        /* Collapsed State */
        .dashboard-nav.collapsed {
            width: 80px;
        }

        .dashboard-nav.collapsed .nav-header h1 span,
        .dashboard-nav.collapsed .user-info,
        .dashboard-nav.collapsed .nav-links a span,
        .dashboard-nav.collapsed .nav-toggle-btn span {
            display: none;
        }

        .dashboard-nav.collapsed .nav-header h1 {
            justify-content: center;
        }

        .dashboard-nav.collapsed .nav-links a {
            justify-content: center;
            padding: 12px;
        }

        .dashboard-nav.collapsed .nav-toggle-btn {
            padding: 12px;
        }

        .dashboard-nav.collapsed .nav-toggle-btn i {
            transform: rotate(180deg);
        }

        /* ============= MAIN CONTAINER ============= */
        .dashboard-container {
            margin-left: 280px;
            padding: 30px;
            width: 100%;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .dashboard-container.sidebar-collapsed {
            margin-left: 80px;
        }

        /* Header Section */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header h2 {
            font-size: 28px;
            font-weight: 800;
            color: var(--text);
        }

        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        /* Language Toggle */
        .lang-toggle {
            display: flex;
            background: var(--card-bg);
            border-radius: 10px;
            padding: 4px;
            box-shadow: var(--shadow);
        }

        .lang-btn {
            padding: 8px 16px;
            border: none;
            background: transparent;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--text-muted);
        }

        .lang-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        /* Mobile Menu */
        .menu-icon {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            padding: 12px 16px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: var(--shadow-lg);
        }

        #menu-toggle {
            display: none;
        }

        /* ============= STATS GRID ============= */
        .stats-overview {
            margin-bottom: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--shadow);
            transition: all 0.3s;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .stat-icon.primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .stat-icon.success { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .stat-icon.hot { background: linear-gradient(135deg, #ff6b6b, #ee5a6f); color: white; }
        .stat-icon.warm { background: linear-gradient(135deg, #ffd93d, #fcbf49); color: white; }
        .stat-icon.cold { background: linear-gradient(135deg, #6bcbef, #4facfe); color: white; }
        .stat-icon.team { background: linear-gradient(135deg, #a78bfa, #8b5cf6); color: white; }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
        }

        .stat-trend.up {
            background: rgba(16,185,129,0.1);
            color: var(--success);
        }

        .stat-trend.down {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
        }

        .stat-value {
            font-size: 32px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 8px;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
        }

        /* ============= CONTENT GRID ============= */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }

        .card-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
        }

        /* ============= TABLE STYLES ============= */
        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table thead th {
            text-align: left;
            padding: 12px 8px;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border);
        }

        .table tbody td {
            padding: 15px 8px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }

        .table tbody tr:hover {
            background: rgba(102,126,234,0.05);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .badge-hot { background: rgba(255,107,107,0.15); color: var(--hot); }
        .badge-warm { background: rgba(255,217,61,0.15); color: #d4a106; }
        .badge-cold { background: rgba(107,203,239,0.15); color: #0891b2; }
        .badge-active { background: rgba(16,185,129,0.15); color: var(--success); }
        .badge-converted { background: rgba(139,92,246,0.15); color: #8b5cf6; }

        /* Performance Cards */
        .performance-card {
            padding: 20px;
            background: linear-gradient(135deg, rgba(102,126,234,0.05), rgba(118,75,162,0.05));
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 4px solid var(--primary);
        }

        .performance-card h4 {
            font-size: 16px;
            margin-bottom: 10px;
            color: var(--text);
        }

        .performance-card .value {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .performance-card .label {
            color: var(--text-muted);
            font-size: 13px;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .action-card {
            padding: 25px;
            background: linear-gradient(135deg, rgba(102,126,234,0.05), rgba(118,75,162,0.05));
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .action-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }

        .action-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .action-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text);
        }

        .action-desc {
            font-size: 13px;
            color: var(--text-muted);
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 13px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
            font-size: 14px;
        }

        /* Alert Box */
        .alert {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .alert-error {
            background: rgba(239,68,68,0.1);
            border-color: var(--danger);
            color: var(--danger);
        }

        /* ============= RESPONSIVE ============= */
        @media (max-width: 768px) {
            body { display: block; }
            
            .dashboard-nav {
                transform: translateX(-100%);
            }
            
            #menu-toggle:checked ~ .dashboard-nav {
                transform: translateX(0);
            }
            
            .dashboard-container {
                margin-left: 0;
                padding: 15px;
                padding-top: 70px;
            }
            
            .menu-icon {
                display: block;
            }
            
            .nav-toggle-btn {
                display: none !important;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 480px) {
            .stat-value {
                font-size: 24px;
            }
            
            .card {
                padding: 15px;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <label for="menu-toggle" class="menu-icon" aria-label="Toggle Navigation">
        <i class="fas fa-bars"></i>
    </label>
    <input type="checkbox" id="menu-toggle" aria-controls="sidebar-nav">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="dashboard-container" id="main-container">
        <div class="page-header">
            <h2 data-en="Dashboard Overview" data-hi="डैशबोर्ड अवलोकन">डैशबोर्ड अवलोकन</h2>
            <div class="header-actions">
                <div class="lang-toggle">
                    <button class="lang-btn active" onclick="switchLanguage('hi', this)">हिं</button>
                    <button class="lang-btn" onclick="switchLanguage('en', this)">EN</button>
                </div>
            </div>
        </div>

        <?php if ($db_status === 'error'): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle" style="font-size: 24px;"></i>
                <div>
                    <strong data-en="Database Error" data-hi="डेटाबेस त्रुटि">डेटाबेस त्रुटि</strong><br>
                    <span data-en="Unable to load statistics. Please check system logs." 
                            data-hi="सांख्यिकी लोड नहीं हो सकी। कृपया सिस्टम लॉग की जाँच करें।">
                        सांख्यिकी लोड नहीं हो सकी। कृपया सिस्टम लॉग की जाँच करें।
                    </span>
                </div>
            </div>
        <?php endif; ?>

        <div class="stats-overview">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-trend up">
                            <i class="fas fa-arrow-up"></i> +<?php echo $stats['today_leads']; ?>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_leads']); ?></div>
                    <div class="stat-label" data-en="Total Leads" data-hi="कुल लीड्स">कुल लीड्स</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon hot">
                            <i class="fas fa-fire"></i>
                        </div>
                        <div class="stat-trend up">
                            <?php echo $stats['hot_lead_percentage']; ?>%
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['hot_leads']); ?></div>
                    <div class="stat-label" data-en="🔥 Hot Leads" data-hi="🔥 गर्म लीड्स">🔥 गर्म लीड्स</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-trend up">
                            <?php echo $stats['conversion_rate']; ?>%
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['converted_leads']); ?></div>
                    <div class="stat-label" data-en="Conversions" data-hi="रूपांतरण">रूपांतरण</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon team">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <div class="stat-trend up">
                            +<?php echo $stats['new_members_this_month']; ?>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['active_team_members']); ?></div>
                    <div class="stat-label" data-en="Active Team" data-hi="सक्रिय टीम">सक्रिय टीम</div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon warm">
                            <i class="fas fa-thermometer-half"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['warm_leads']); ?></div>
                    <div class="stat-label" data-en="🌡️ Warm Leads" data-hi="🌡️ गुनगुने लीड्स">🌡️ गुनगुने लीड्स</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon cold">
                            <i class="fas fa-snowflake"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['cold_leads']); ?></div>
                    <div class="stat-label" data-en="❄️ Cold Leads" data-hi="❄️ ठंडे लीड्स">❄️ ठंडे लीड्स</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon primary">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['weekly_leads']); ?></div>
                    <div class="stat-label" data-en="This Week" data-hi="इस सप्ताह">इस सप्ताह</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon success">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['active_leads']); ?></div>
                    <div class="stat-label" data-en="Active Leads" data-hi="सक्रिय लीड्स">सक्रिय लीड्स</div>
                </div>
            </div>
        </div>

        <div class="content-grid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" data-en="Recent Leads" data-hi="हाल के लीड्स">हाल के लीड्स</h3>
                    <a href="/admin/leads.php" class="btn btn-primary btn-small">
                        <i class="fas fa-eye"></i>
                        <span data-en="View All" data-hi="सभी देखें">सभी देखें</span>
                    </a>
                </div>
                
                <?php if (empty($recent_leads)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                        <p data-en="No leads found" data-hi="कोई लीड नहीं मिला">कोई लीड नहीं मिला</p>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_leads as $lead): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($lead['name']); ?></strong>
                                    <?php if (!empty($lead['notes'])): ?>
                                        <br><small style="color: var(--text-muted);">
                                            <?php echo htmlspecialchars(substr($lead['notes'], 0, 30)); ?>
                                            <?php echo strlen($lead['notes']) > 30 ? '...' : ''; ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($lead['email']); ?></div>
                                    <?php if (!empty($lead['phone'])): ?>
                                        <div><small style="color: var(--text-muted);">
                                            <?php echo htmlspecialchars($lead['phone']); ?>
                                        </small></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo htmlspecialchars(strtolower($lead['lead_score'])); ?>">
                                        <?php echo htmlspecialchars($lead['lead_score']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $status_class = 'badge-active';
                                    if ($lead['status'] === 'converted') $status_class = 'badge-converted';
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars(ucfirst($lead['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars(date('d M Y', strtotime($lead['created_at']))); ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" data-en="Top Performers" data-hi="टॉप परफॉर्मर्स">टॉप परफॉर्मर्स</h3>
                </div>
                
                <?php if (empty($top_team_members)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users-slash" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                        <p data-en="No team members yet" data-hi="अभी तक कोई टीम सदस्य नहीं">अभी तक कोई टीम सदस्य नहीं</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($top_team_members as $index => $member): ?>
                    <div class="performance-card">
                        <h4>
                            <i class="fas fa-trophy" style="color: <?php 
                                echo $index === 0 ? '#FFD700' : ($index === 1 ? '#C0C0C0' : ($index === 2 ? '#CD7F32' : 'var(--primary)')); 
                            ?>; margin-right: 8px;"></i>
                            <?php echo htmlspecialchars($member['full_name'] ?: $member['username']); ?>
                        </h4>
                        <div class="value"><?php echo number_format($member['total_leads']); ?></div>
                        <div class="label" data-en="Total Leads" data-hi="कुल लीड्स">कुल लीड्स</div>
                        <div style="margin-top: 12px; display: flex; gap: 15px; font-size: 13px;">
                            <div>
                                <i class="fas fa-check-circle" style="color: var(--success);"></i>
                                <?php echo number_format($member['conversions']); ?>
                                <span data-en="converted" data-hi="रूपांतरित">रूपांतरित</span>
                            </div>
                            <div>
                                <i class="fas fa-fire" style="color: var(--hot);"></i>
                                <?php echo number_format($member['hot_leads']); ?>
                                <span data-en="hot" data-hi="गर्म">गर्म</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title" data-en="Quick Actions" data-hi="त्वरित कार्य">त्वरित कार्य</h3>
            </div>
            
            <div class="quick-actions">
                <div class="action-card" onclick="window.location.href='/admin/leads.php'">
                    <div class="action-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="action-title" data-en="Manage Leads" data-hi="लीड्स प्रबंधित करें">लीड्स प्रबंधित करें</div>
                    <div class="action-desc" data-en="View and manage all leads" data-hi="सभी लीड्स देखें और प्रबंधित करें">
                        सभी लीड्स देखें और प्रबंधित करें
                    </div>
                </div>
                
                <div class="action-card" onclick="window.location.href='/admin/team.php'">
                    <div class="action-icon">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="action-title" data-en="Team Management" data-hi="टीम प्रबंधन">टीम प्रबंधन</div>
                    <div class="action-desc" data-en="Manage team members" data-hi="टीम सदस्यों को प्रबंधित करें">
                        टीम सदस्यों को प्रबंधित करें
                    </div>
                </div>
                
                <div class="action-card" onclick="window.location.href='/admin/advanced-analytics.php'">
                    <div class="action-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="action-title" data-en="Analytics" data-hi="एनालिटिक्स">एनालिटिक्स</div>
                    <div class="action-desc" data-en="View detailed analytics" data-hi="विस्तृत विश्लेषण देखें">
                        विस्तृत विश्लेषण देखें
                    </div>
                </div>
                
                <div class="action-card" onclick="window.location.href='/admin/goal-tracking.php'">
                    <div class="action-icon">
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <div class="action-title" data-en="Goal Tracking" data-hi="लक्ष्य ट्रैकिंग">लक्ष्य ट्रैकिंग</div>
                    <div class="action-desc" data-en="Track your goals" data-hi="अपने लक्ष्यों को ट्रैक करें">
                        अपने लक्ष्यों को ट्रैक करें
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Language switching functionality
        function switchLanguage(lang, targetButton) {
            document.querySelectorAll('[data-en][data-hi]').forEach(element => {
                element.textContent = element.getAttribute('data-' + lang);
            });
            
            document.querySelectorAll('.lang-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            if (targetButton) {
                targetButton.classList.add('active');
            }
            
            // Save language preference
            localStorage.setItem('preferredLanguage', lang);
        }

        // DOM Initialization
        document.addEventListener('DOMContentLoaded', function() {
            // Load saved language preference
            const savedLang = localStorage.getItem('preferredLanguage') || 'hi';
            const langButton = document.querySelector(`.lang-btn[onclick*="${savedLang}"]`);
            if (langButton && savedLang !== 'hi') {
                switchLanguage(savedLang, langButton);
            }

            // Sidebar persistence
            const sidebar = document.getElementById('sidebar-nav');
            const container = document.getElementById('main-container');
            const toggleButton = document.getElementById('nav-toggle-btn');
            const isDesktop = window.innerWidth > 768;

            // Load saved sidebar state (desktop only)
            if (isDesktop) {
                const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                if (isCollapsed) {
                    sidebar.classList.add('collapsed');
                    container.classList.add('sidebar-collapsed');
                }
            }
            
            // Toggle functionality
            if (toggleButton) {
                toggleButton.addEventListener('click', function() {
                    const isNowCollapsed = !sidebar.classList.contains('collapsed');
                    
                    sidebar.classList.toggle('collapsed');
                    container.classList.toggle('sidebar-collapsed');
                    
                    localStorage.setItem('sidebarCollapsed', isNowCollapsed);
                });
            }
            
            // Auto-close mobile menu on link click
            if (!isDesktop) {
                const menuToggle = document.getElementById('menu-toggle');
                const sidebarLinks = sidebar.querySelectorAll('a');
                
                sidebarLinks.forEach(link => {
                    link.addEventListener('click', () => {
                        setTimeout(() => {
                            menuToggle.checked = false;
                        }, 100);
                    });
                });
            }

            // Smooth animations for cards
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '0';
                        entry.target.style.transform = 'translateY(20px)';
                        
                        setTimeout(() => {
                            entry.target.style.transition = 'all 0.5s ease';
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }, 100);
                        
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.stat-card, .card, .action-card').forEach(el => {
                observer.observe(el);
            });

            // Update time-based greetings
            updateGreeting();
            setInterval(updateGreeting, 60000); // Update every minute
        });

        function updateGreeting() {
            const hour = new Date().getHours();
            let greeting = 'नमस्ते';
            
            if (hour < 12) greeting = 'सुप्रभात';
            else if (hour < 17) greeting = 'शुभ दोपहर';
            else if (hour < 20) greeting = 'शुभ संध्या';
            else greeting = 'शुभ रात्रि';
            
            // You can use this greeting in the UI if needed
        }

        // Prevent accidental logout
        document.querySelector('a[href="/logout.php"]')?.addEventListener('click', function(e) {
            const currentLang = localStorage.getItem('preferredLanguage') || 'hi';
            const message = currentLang === 'hi' 
                ? 'क्या आप वाकई लॉग आउट करना चाहते हैं?' 
                : 'Are you sure you want to logout?';
            
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>