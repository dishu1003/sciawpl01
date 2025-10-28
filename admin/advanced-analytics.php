<?php
/**
 * Advanced Analytics - Admin Dashboard
 * Comprehensive analytics for direct selling business support system
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
    
    // Advanced analytics for direct selling business
    $analytics = [];
    
    // Lead trends over time (last 30 days)
    $stmt = $pdo->query("
        SELECT DATE(created_at) as date, COUNT(*) as count,
               COUNT(CASE WHEN lead_score = 'HOT' THEN 1 END) as hot_leads,
               COUNT(CASE WHEN status = 'converted' THEN 1 END) as conversions
        FROM leads 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $analytics['lead_trends'] = $stmt->fetchAll();
    
    // Lead score distribution
    $stmt = $pdo->query("
        SELECT lead_score, COUNT(*) as count,
               ROUND((COUNT(*) / (SELECT COUNT(*) FROM leads)) * 100, 2) as percentage
        FROM leads
        GROUP BY lead_score
        ORDER BY 
            CASE lead_score 
                WHEN 'HOT' THEN 1 
                WHEN 'WARM' THEN 2 
                WHEN 'COLD' THEN 3 
            END
    ");
    $analytics['score_distribution'] = $stmt->fetchAll();
    
    // Team performance analytics
    $stmt = $pdo->query("
        SELECT u.full_name, u.username, u.referral_code,
               COUNT(l.id) as total_leads,
               COUNT(CASE WHEN l.lead_score = 'HOT' THEN 1 END) as hot_leads,
               COUNT(CASE WHEN l.status = 'converted' THEN 1 END) as conversions,
               COUNT(CASE WHEN l.status = 'active' THEN 1 END) as active_leads,
               ROUND((COUNT(CASE WHEN l.status = 'converted' THEN 1 END) / COUNT(l.id)) * 100, 2) as conversion_rate,
               MAX(l.created_at) as last_lead_date
        FROM users u
        LEFT JOIN leads l ON u.id = l.assigned_to
        WHERE u.role = 'team'
        GROUP BY u.id, u.full_name, u.username, u.referral_code
        ORDER BY conversions DESC, total_leads DESC
    ");
    $analytics['team_performance'] = $stmt->fetchAll();
    
    // Lead sources analysis
    $stmt = $pdo->query("
        SELECT source, COUNT(*) as count,
               COUNT(CASE WHEN status = 'converted' THEN 1 END) as conversions,
               COUNT(CASE WHEN lead_score = 'HOT' THEN 1 END) as hot_leads,
               ROUND((COUNT(CASE WHEN status = 'converted' THEN 1 END) / COUNT(*)) * 100, 2) as conversion_rate
        FROM leads
        GROUP BY source
        ORDER BY count DESC
    ");
    $analytics['lead_sources'] = $stmt->fetchAll();
    
    // Referral performance
    $stmt = $pdo->query("
        SELECT u.referral_code, u.full_name,
               COUNT(l.id) as referral_leads,
               COUNT(CASE WHEN l.status = 'converted' THEN 1 END) as conversions,
               ROUND((COUNT(CASE WHEN l.status = 'converted' THEN 1 END) / COUNT(l.id)) * 100, 2) as conversion_rate
        FROM users u
        LEFT JOIN leads l ON u.referral_code = l.referral_code
        WHERE u.role = 'team' AND u.referral_code IS NOT NULL
        GROUP BY u.referral_code, u.full_name
        HAVING referral_leads > 0
        ORDER BY referral_leads DESC
    ");
    $analytics['referral_performance'] = $stmt->fetchAll();
    
    // Follow-up analytics
    $stmt = $pdo->query("
        SELECT 
            COUNT(CASE WHEN follow_up_date IS NULL THEN 1 END) as no_followup,
            COUNT(CASE WHEN follow_up_date IS NOT NULL AND follow_up_date > CURDATE() THEN 1 END) as scheduled_followup,
            COUNT(CASE WHEN follow_up_date IS NOT NULL AND follow_up_date <= CURDATE() THEN 1 END) as overdue_followup,
            COUNT(CASE WHEN follow_up_date = CURDATE() THEN 1 END) as today_followup
        FROM leads
        WHERE status != 'converted'
    ");
    $analytics['followup_stats'] = $stmt->fetch();
    
    // Activity analytics
    $stmt = $pdo->query("
        SELECT 
            activity_type,
            COUNT(*) as count,
            COUNT(DISTINCT lead_id) as unique_leads,
            COUNT(DISTINCT user_id) as active_users
        FROM lead_activities
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY activity_type
        ORDER BY count DESC
    ");
    $analytics['activity_stats'] = $stmt->fetchAll();
    
    // Monthly performance comparison
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total_leads,
            COUNT(CASE WHEN lead_score = 'HOT' THEN 1 END) as hot_leads,
            COUNT(CASE WHEN status = 'converted' THEN 1 END) as conversions,
            ROUND((COUNT(CASE WHEN status = 'converted' THEN 1 END) / COUNT(*)) * 100, 2) as conversion_rate
        FROM leads
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ");
    $analytics['monthly_performance'] = $stmt->fetchAll();
    
    // Category performance (if categories exist)
    $stmt = $pdo->query("
        SELECT lc.name as category_name, lc.color,
               COUNT(lca.lead_id) as lead_count,
               COUNT(CASE WHEN l.status = 'converted' THEN 1 END) as conversions,
               ROUND((COUNT(CASE WHEN l.status = 'converted' THEN 1 END) / COUNT(lca.lead_id)) * 100, 2) as conversion_rate
        FROM lead_categories lc
        LEFT JOIN lead_category_assignments lca ON lc.id = lca.category_id
        LEFT JOIN leads l ON lca.lead_id = l.id
        GROUP BY lc.id, lc.name, lc.color
        HAVING lead_count > 0
        ORDER BY lead_count DESC
    ");
    $analytics['category_performance'] = $stmt->fetchAll();
    
    // Overall statistics
    $stats = [];
    $stats['total_leads'] = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
    $stats['total_conversions'] = $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'converted'")->fetchColumn();
    $stats['total_hot_leads'] = $pdo->query("SELECT COUNT(*) FROM leads WHERE lead_score = 'HOT'")->fetchColumn();
    $stats['total_team_members'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'team'")->fetchColumn();
    $stats['overall_conversion_rate'] = $stats['total_leads'] > 0 ? round(($stats['total_conversions'] / $stats['total_leads']) * 100, 2) : 0;
    
    Logger::info('Advanced analytics accessed', [
        'user_id' => $_SESSION['user_id']
    ]);

} catch (PDOException $e) {
    Logger::error('Database error in analytics', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id']
    ]);
    $analytics = [
        'lead_trends' => [],
        'score_distribution' => [],
        'team_performance' => [],
        'lead_sources' => [],
        'referral_performance' => [],
        'followup_stats' => [],
        'activity_stats' => [],
        'monthly_performance' => [],
        'category_performance' => []
    ];
    $stats = ['total_leads' => 0, 'total_conversions' => 0, 'total_hot_leads' => 0, 'total_team_members' => 0, 'overall_conversion_rate' => 0];
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📈 Advanced Analytics - Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2/?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .stat-card.conversions::before { background: linear-gradient(90deg, #00d2d3, #54a0ff); }
        .stat-card.hot::before { background: linear-gradient(90deg, #ff6b6b, #ee5a24); }
        .stat-card.team::before { background: linear-gradient(90deg, #feca57, #ff9ff3); }
        
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
        .stat-icon.conversions { background: linear-gradient(135deg, #00d2d3, #54a0ff); }
        .stat-icon.hot { background: linear-gradient(135deg, #ff6b6b, #ee5a24); }
        .stat-icon.team { background: linear-gradient(135deg, #feca57, #ff9ff3); }
        
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
        
        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 30px;
        }
        
        .analytics-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .performance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .performance-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #e9ecef;
        }
        
        .performance-table td {
            padding: 15px;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .performance-table tr:hover {
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
        
        .metric-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
            transition: transform 0.3s ease;
        }
        
        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .metric-name {
            font-weight: 700;
            color: #333;
        }
        
        .metric-value {
            font-size: 24px;
            font-weight: 800;
            color: #667eea;
        }
        
        .metric-description {
            color: #666;
            font-size: 14px;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .analytics-grid {
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
        <h1>📈 Advanced Analytics</h1>
        <div class="nav-links">
            <a href="/admin/index.php">Dashboard</a>
            <a href="/admin/leads.php">Leads</a>
            <a href="/admin/team.php">Team</a>
            <a href="/logout.php">Logout</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <!-- Overall Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_leads']; ?></div>
                <div class="stat-label">Total Leads</div>
            </div>

            <div class="stat-card conversions">
                <div class="stat-icon conversions">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_conversions']; ?></div>
                <div class="stat-label">Conversions</div>
            </div>

            <div class="stat-card hot">
                <div class="stat-icon hot">
                    <i class="fas fa-fire"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_hot_leads']; ?></div>
                <div class="stat-label">Hot Leads</div>
            </div>

            <div class="stat-card team">
                <div class="stat-icon team">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_team_members']; ?></div>
                <div class="stat-label">Team Members</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-value"><?php echo $stats['overall_conversion_rate']; ?>%</div>
                <div class="stat-label">Conversion Rate</div>
            </div>
        </div>

        <!-- Lead Trends Chart -->
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-chart-line"></i>
                Lead Trends (Last 30 Days)
            </h2>
            <div class="chart-container">
                <canvas id="leadTrendsChart"></canvas>
            </div>
        </div>

        <div class="analytics-grid">
            <!-- Lead Score Distribution -->
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-pie-chart"></i>
                    Lead Score Distribution
                </h2>
                <div class="chart-container">
                    <canvas id="scoreDistributionChart"></canvas>
                </div>
            </div>

            <!-- Lead Sources Performance -->
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-chart-bar"></i>
                    Lead Sources Performance
                </h2>
                <div class="chart-container">
                    <canvas id="leadSourcesChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Team Performance -->
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-trophy"></i>
                Team Performance
            </h2>
            <table class="performance-table">
                <thead>
                    <tr>
                        <th>Team Member</th>
                        <th>Total Leads</th>
                        <th>Hot Leads</th>
                        <th>Conversions</th>
                        <th>Conversion Rate</th>
                        <th>Active Leads</th>
                        <th>Last Lead</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analytics['team_performance'] as $member): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($member['full_name'] ?: $member['username']); ?></strong>
                            <br><small><?php echo htmlspecialchars($member['referral_code']); ?></small>
                        </td>
                        <td><?php echo $member['total_leads']; ?></td>
                        <td>
                            <span class="badge badge-hot"><?php echo $member['hot_leads']; ?></span>
                        </td>
                        <td>
                            <span class="badge badge-converted"><?php echo $member['conversions']; ?></span>
                        </td>
                        <td><?php echo $member['conversion_rate']; ?>%</td>
                        <td>
                            <span class="badge badge-active"><?php echo $member['active_leads']; ?></span>
                        </td>
                        <td><?php echo $member['last_lead_date'] ? date('d M Y', strtotime($member['last_lead_date'])) : 'N/A'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Follow-up Analytics -->
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-clock"></i>
                Follow-up Analytics
            </h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div class="metric-card">
                    <div class="metric-header">
                        <div class="metric-name">No Follow-up</div>
                        <div class="metric-value"><?php echo $analytics['followup_stats']['no_followup']; ?></div>
                    </div>
                    <div class="metric-description">Leads without follow-up dates</div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-header">
                        <div class="metric-name">Scheduled</div>
                        <div class="metric-value"><?php echo $analytics['followup_stats']['scheduled_followup']; ?></div>
                    </div>
                    <div class="metric-description">Future follow-ups scheduled</div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-header">
                        <div class="metric-name">Overdue</div>
                        <div class="metric-value"><?php echo $analytics['followup_stats']['overdue_followup']; ?></div>
                    </div>
                    <div class="metric-description">Follow-ups past due date</div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-header">
                        <div class="metric-name">Today</div>
                        <div class="metric-value"><?php echo $analytics['followup_stats']['today_followup']; ?></div>
                    </div>
                    <div class="metric-description">Follow-ups scheduled for today</div>
                </div>
            </div>
        </div>

        <!-- Monthly Performance -->
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-calendar-alt"></i>
                Monthly Performance (Last 6 Months)
            </h2>
            <div class="chart-container">
                <canvas id="monthlyPerformanceChart"></canvas>
            </div>
        </div>

        <!-- Activity Analytics -->
        <?php if (!empty($analytics['activity_stats'])): ?>
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-chart-area"></i>
                Activity Analytics (Last 30 Days)
            </h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <?php foreach ($analytics['activity_stats'] as $activity): ?>
                <div class="metric-card">
                    <div class="metric-header">
                        <div class="metric-name"><?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'])); ?></div>
                        <div class="metric-value"><?php echo $activity['count']; ?></div>
                    </div>
                    <div class="metric-description">
                        <?php echo $activity['unique_leads']; ?> unique leads, <?php echo $activity['active_users']; ?> active users
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Lead Trends Chart
        const leadTrendsCtx = document.getElementById('leadTrendsChart').getContext('2d');
        new Chart(leadTrendsCtx, {
            type: 'line',
            data: {
                labels: [<?php echo "'" . implode("','", array_column($analytics['lead_trends'], 'date')) . "'"; ?>],
                datasets: [{
                    label: 'Total Leads',
                    data: [<?php echo implode(',', array_column($analytics['lead_trends'], 'count')); ?>],
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Hot Leads',
                    data: [<?php echo implode(',', array_column($analytics['lead_trends'], 'hot_leads')); ?>],
                    borderColor: '#ff6b6b',
                    backgroundColor: 'rgba(255, 107, 107, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Conversions',
                    data: [<?php echo implode(',', array_column($analytics['lead_trends'], 'conversions')); ?>],
                    borderColor: '#00d2d3',
                    backgroundColor: 'rgba(0, 210, 211, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Score Distribution Chart
        const scoreDistributionCtx = document.getElementById('scoreDistributionChart').getContext('2d');
        new Chart(scoreDistributionCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php echo "'" . implode("','", array_column($analytics['score_distribution'], 'lead_score')) . "'"; ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_column($analytics['score_distribution'], 'count')); ?>],
                    backgroundColor: ['#ff6b6b', '#feca57', '#54a0ff'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });

        // Lead Sources Chart
        const leadSourcesCtx = document.getElementById('leadSourcesChart').getContext('2d');
        new Chart(leadSourcesCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo "'" . implode("','", array_column($analytics['lead_sources'], 'source')) . "'"; ?>],
                datasets: [{
                    label: 'Total Leads',
                    data: [<?php echo implode(',', array_column($analytics['lead_sources'], 'count')); ?>],
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: '#667eea',
                    borderWidth: 1
                }, {
                    label: 'Conversions',
                    data: [<?php echo implode(',', array_column($analytics['lead_sources'], 'conversions')); ?>],
                    backgroundColor: 'rgba(0, 210, 211, 0.8)',
                    borderColor: '#00d2d3',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Monthly Performance Chart
        const monthlyPerformanceCtx = document.getElementById('monthlyPerformanceChart').getContext('2d');
        new Chart(monthlyPerformanceCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo "'" . implode("','", array_column($analytics['monthly_performance'], 'month')) . "'"; ?>],
                datasets: [{
                    label: 'Total Leads',
                    data: [<?php echo implode(',', array_column($analytics['monthly_performance'], 'total_leads')); ?>],
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: '#667eea',
                    borderWidth: 1
                }, {
                    label: 'Hot Leads',
                    data: [<?php echo implode(',', array_column($analytics['monthly_performance'], 'hot_leads')); ?>],
                    backgroundColor: 'rgba(255, 107, 107, 0.8)',
                    borderColor: '#ff6b6b',
                    borderWidth: 1
                }, {
                    label: 'Conversions',
                    data: [<?php echo implode(',', array_column($analytics['monthly_performance'], 'conversions')); ?>],
                    backgroundColor: 'rgba(0, 210, 211, 0.8)',
                    borderColor: '#00d2d3',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
