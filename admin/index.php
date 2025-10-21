<?php
/**
 * Admin Dashboard
 * Main admin panel with statistics and recent leads
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/security.php';

// Set security headers
SecurityHeaders::setAll();

// Require admin access
require_admin();

// Check session timeout
check_session_timeout();

try {
    $pdo = get_pdo_connection();
fix/login-error-handling
    // CRM Stats
=======
    // Stats with error handling
 main
    $total_leads = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
    $organic_leads = $pdo->query("SELECT COUNT(*) FROM leads WHERE source = 'organic'")->fetchColumn();
    $ad_leads = $pdo->query("SELECT COUNT(*) FROM leads WHERE source != 'organic'")->fetchColumn(); // Simplified for now
    $joined_leads = $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'Joined'")->fetchColumn();

    // Recent leads
    $stmt = $pdo->query("SELECT * FROM leads ORDER BY created_at DESC LIMIT 10");
    $recent_leads = $stmt->fetchAll();

    Logger::info('Admin dashboard accessed', [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username']
    ]);

} catch (PDOException $e) {
    Logger::error('Database error in admin dashboard', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id']
    ]);
    $total_leads = $hot_leads = $converted = $total_team = 0;
    $recent_leads = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .dashboard-nav {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .dashboard-nav h1 {
            margin: 0;
            font-size: 24px;
        }
        .dashboard-nav a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .dashboard-nav a:hover {
            background: rgba(255,255,255,0.2);
        }
        .admin-dashboard {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }
        .stat-card.hot {
            border-left-color: #e74c3c;
        }
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
        }
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        .leads-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .leads-table thead {
            background: #f8f9fa;
        }
        .leads-table th, .leads-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .leads-table th {
            font-weight: 600;
            color: #555;
        }
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
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
        .btn-small {
            padding: 6px 12px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }
        .btn-small:hover {
            background: #5568d3;
        }
        h2 {
            margin: 0 0 20px 0;
            color: #333;
        }
    </style>
</head>
<body>
    <nav class="dashboard-nav">
        <h1>🎯 <?php echo SITE_NAME; ?> - Admin</h1>
        <div>
            <a href="/admin/">Dashboard</a>
            <a href="/admin/leads.php">Leads</a>
            <a href="/admin/team.php">Team</a>
            <a href="/admin/scripts.php">Scripts</a>
            <a href="/admin/analytics.php">Analytics</a>
            <a href="/logout.php">Logout</a>
        </div>
    </nav>

    <div class="admin-dashboard">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Leads</h3>
                <p class="stat-number"><?php echo number_format($total_leads); ?></p>
            </div>
            <div class="stat-card">
                <h3>Organic Leads</h3>
                <p class="stat-number"><?php echo number_format($organic_leads); ?></p>
            </div>
            <div class="stat-card">
                <h3>Ad Leads</h3>
                <p class="stat-number"><?php echo number_format($ad_leads); ?></p>
            </div>
            <div class="stat-card hot">
                <h3>✅ New Joinees</h3>
                <p class="stat-number"><?php echo number_format($joined_leads); ?></p>
            </div>
        </div>

        <h2>Recent Leads (Last 10)</h2>
        <?php if (empty($recent_leads)): ?>
            <p style="text-align:center; color:#999; padding:40px;">No leads found.</p>
        <?php else: ?>
        <table class="leads-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Step</th>
                    <th>Score</th>
                    <th>Assigned To</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_leads as $lead): ?>
                <tr>
                    <td><?php echo htmlspecialchars($lead['name']); ?></td>
                    <td><?php echo htmlspecialchars($lead['phone']); ?></td>
                    <td><?php echo htmlspecialchars($lead['email']); ?></td>
                    <td><?php echo $lead['current_step']; ?>/5</td>
                    <td><span class="badge badge-<?php echo strtolower($lead['lead_score']); ?>"><?php echo $lead['lead_score']; ?></span></td>
                    <td><?php echo $lead['assigned_to'] ? 'User #' . $lead['assigned_to'] : 'Unassigned'; ?></td>
                    <td><?php echo date('d M Y H:i', strtotime($lead['created_at'])); ?></td>
                    <td>
                        <a href="/admin/leads.php?id=<?php echo $lead['id']; ?>" class="btn-small">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</body>
</html>