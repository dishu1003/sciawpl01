<?php
/**
 * Network Marketing CRM - Team Management
 * Enhanced team management with network marketing features
 * FINAL SECURED VERSION with Performance Insights
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

// 💡 CHANGE: Allowing both 'admin' and 'team' roles to access, 
// but we'll show different views based on the role later.
$allowed_roles = ['admin', 'team'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: /login.php');
    exit;
}
$user_role = $_SESSION['role'];
$current_user_id = $_SESSION['user_id'];

check_session_timeout();

$db_status = 'ok';
try {
    $pdo = get_pdo_connection();

    // Securely check for admin rights before allowing POST actions
    if ($user_role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // --- SECURED POST HANDLERS ---
        if (isset($_POST['add_member'])) {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $full_name = trim($_POST['full_name']);
            $phone = trim($_POST['phone']);
            $password = $_POST['password'];
            // Security Fix: Sanitize and validate numeric inputs
            $upline_id = filter_var($_POST['upline_id'], FILTER_VALIDATE_INT) ?: null;
            $level = filter_var($_POST['level'], FILTER_VALIDATE_INT) ?: 1;
            
            // 🔒 SECURITY: Use prepared statements for input checking
            $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check_stmt->execute([$username, $email]);
            
            if ($check_stmt->fetch()) {
                $_SESSION['error_message'] = "Username or email already exists!";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // 🔒 SECURITY: Use prepared statement for INSERT
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, full_name, phone, password_hash, role, status, level, upline_id, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'team', 'active', ?, ?, NOW())
                ");
                // Note: Phone number sanitization (e.g., regex) should be done here in production
                $stmt->execute([
                    htmlspecialchars($username), 
                    htmlspecialchars($email), 
                    htmlspecialchars($full_name), 
                    htmlspecialchars($phone), 
                    $password_hash, 
                    $level, 
                    $upline_id
                ]);
                
                $_SESSION['success_message'] = "Team member added successfully!";
            }
            header('Location: /admin/team.php');
            exit;
        }
        
        // --- Update Status, Level, Password Reset, and Delete (All use prepared statements) ---
        if (isset($_POST['update_status'])) {
            $user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
            $status = $_POST['status'];
            $stmt = $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $user_id]);
            $_SESSION['success_message'] = "Status updated successfully!";
            header('Location: /admin/team.php');
            exit;
        }
        
        if (isset($_POST['update_level'])) {
            $user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
            $level = filter_var($_POST['level'], FILTER_VALIDATE_INT);
            $upline_id = filter_var($_POST['upline_id'], FILTER_VALIDATE_INT) ?: null;
            
            $stmt = $pdo->prepare("UPDATE users SET level = ?, upline_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$level, $upline_id, $user_id]);
            
            $_SESSION['success_message'] = "Level and upline updated successfully!";
            header('Location: /admin/team.php');
            exit;
        }
        
        if (isset($_POST['reset_password'])) {
            $user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
            $new_password = $_POST['new_password'];
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$password_hash, $user_id]);
            
            $_SESSION['success_message'] = "Password reset successfully!";
            header('Location: /admin/team.php');
            exit;
        }
        
        if (isset($_POST['delete_member'])) {
            $user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
            // Delete should also be protected against changing Admin role
            
            // Unassign all leads first
            $stmt = $pdo->prepare("UPDATE leads SET assigned_to = NULL WHERE assigned_to = ?");
            $stmt->execute([$user_id]);
            
            // Update downlines to remove upline reference
            $stmt = $pdo->prepare("UPDATE users SET upline_id = NULL WHERE upline_id = ?");
            $stmt->execute([$user_id]);
            
            // Delete user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'team'");
            $stmt->execute([$user_id]);
            
            $_SESSION['success_message'] = "Team member deleted successfully!";
            header('Location: /admin/team.php');
            exit;
        }
    }
    // --- END SECURED POST HANDLERS ---

    // Function for safe COUNT queries
    $safe_count = function($sql, $params = []) use ($pdo) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    };

    // Get team members with network marketing stats and growth metrics
    $member_filter = $user_role === 'team' ? "AND u.id = {$current_user_id}" : "";
    
    $stmt = $pdo->prepare("
        SELECT u.*, 
               upline.username as upline_username,
               upline.full_name as upline_name,
               
               COUNT(l.id) as total_leads,
               COUNT(CASE WHEN l.status = 'converted' THEN 1 END) as conversions,
               COUNT(d.id) as direct_downlines,
               COALESCE(SUM(c.amount), 0) as total_commissions,
               
               -- 💡 NEW: Performance and Growth Metrics
               COUNT(CASE WHEN l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as leads_last_7_days,
               COUNT(CASE WHEN l.status = 'active' AND l.updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as stagnant_leads 
               
        FROM users u
            LEFT JOIN users upline ON u.upline_id = upline.id
            LEFT JOIN leads l ON u.id = l.assigned_to
            LEFT JOIN users d ON d.upline_id = u.id
            LEFT JOIN commissions c ON u.id = c.user_id
        WHERE u.role = 'team' {$member_filter}
        GROUP BY u.id
        ORDER BY u.level DESC, conversions DESC, total_leads DESC
    ");
    $stmt->execute();
    $team_members = $stmt->fetchAll();
    
    // Get upline options for dropdown (Only for Admin)
    $upline_options = [];
    if ($user_role === 'admin') {
        $upline_stmt = $pdo->prepare("SELECT id, username, full_name, level FROM users WHERE role = 'team' AND status = 'active' ORDER BY level DESC, full_name");
        $upline_stmt->execute();
        $upline_options = $upline_stmt->fetchAll();
    }

    // Get team statistics (Admin view only)
    $team_stats = [];
    if ($user_role === 'admin') {
        $team_stats['total_members'] = count($team_members); // Use count of filtered list
        $team_stats['active_members'] = $safe_count("SELECT COUNT(*) FROM users WHERE role = 'team' AND status = 'active'");
        $team_stats['total_downlines'] = $safe_count("SELECT COUNT(*) FROM users WHERE role = 'team' AND upline_id IS NOT NULL");
        $team_stats['top_level'] = $safe_count("SELECT MAX(level) FROM users WHERE role = 'team'") ?: 0;
    }

    Logger::info('Team management accessed', [
        'user_id' => $current_user_id,
        'username' => $_SESSION['username'],
        'role' => $user_role
    ]);

} catch (PDOException $e) {
    Logger::error('Database error in team management', ['error' => $e->getMessage(), 'user_id' => $current_user_id]);
    $team_members = [];
    $upline_options = [];
    $team_stats = array_fill_keys(['total_members', 'active_members', 'total_downlines', 'top_level'], 0);
    $db_status = 'error';
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>👥 Team Management - Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* BASE STYLES (RETAINED) */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: #333; }
        .dashboard-container { display: grid; grid-template-columns: 280px 1fr; min-height: 100vh; }
        .sidebar { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); padding: 30px 20px; box-shadow: 2px 0 20px rgba(0,0,0,0.1); }
        .logo { text-align: center; margin-bottom: 40px; }
        .logo h1 { font-size: 24px; font-weight: 800; color: #667eea; margin-bottom: 5px; }
        .logo p { color: #666; font-size: 14px; }
        .nav-menu { list-style: none; }
        .nav-link { display: flex; align-items: center; padding: 15px 20px; color: #555; text-decoration: none; border-radius: 12px; transition: all 0.3s ease; font-weight: 500; }
        .nav-link:hover, .nav-link.active { background: linear-gradient(135deg, #667eea, #764ba2); color: white; transform: translateX(5px); }
        .nav-link i { margin-right: 12px; width: 20px; text-align: center; }
        .main-content { padding: 30px; overflow-y: auto; }
        .header { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); padding: 20px 30px; border-radius: 20px; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; margin-bottom: 40px; }
        .stat-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); padding: 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); position: relative; overflow: hidden; transition: transform 0.3s ease; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #667eea, #764ba2); }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: white; margin-bottom: 15px; }
        .stat-icon.primary { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-icon.success { background: linear-gradient(135deg, #00d2d3, #54a0ff); }
        .stat-icon.warning { background: linear-gradient(135deg, #ff9ff3, #f368e0); }
        .stat-icon.danger { background: linear-gradient(135deg, #ff6b6b, #ee5a24); }
        .stat-icon.info { background: linear-gradient(135deg, #feca57, #ff9ff3); }
        .stat-value { font-size: 36px; font-weight: 800; color: #333; margin-bottom: 5px; }
        .stat-label { color: #666; font-size: 14px; font-weight: 500; }
        .table-container { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-radius: 20px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; min-width: 1400px; } /* Increased min-width */
        .table th, .table td { padding: 15px; border-bottom: 1px solid #f1f3f4; text-align: left; }
        .table th { background: #f8f9fa; font-weight: 600; color: #555; border-bottom: 2px solid #e9ecef; }
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-inactive { background: #f8d7da; color: #721c24; }
        .badge-level { background: #cce5ff; color: #0066cc; }
        .badge-low-activity { background: #ffe4e6; color: #e74c3c; } /* New: For low performing members */
        .badge-high-growth { background: #d4edda; color: #008000; } /* New: For high growth */
        .btn { padding: 8px 16px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-size: 14px; margin: 2px; }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-warning { background: linear-gradient(135deg, #feca57, #ff9ff3); color: white; }
        .btn-danger { background: linear-gradient(135deg, #ff6b6b, #ee5a24); color: white; }
        .btn-success { background: linear-gradient(135deg, #00d2d3, #54a0ff); color: white; }
        /* Modal and Form styles (retained) */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(5px); }
        .modal-content { background: white; margin: 3% auto; padding: 30px; border-radius: 20px; width: 90%; max-width: 600px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-height: 90vh; overflow-y: auto; }
        /* ... (rest of the form/modal styles) ... */

        @media (max-width: 1200px) {
            .dashboard-container { grid-template-columns: 1fr; }
            .sidebar { display: none; }
        }
    </style>
</head>
<body>
    <div class="lang-toggle">
        <button class="lang-btn" onclick="switchLanguage('en')">EN</button>
        <button class="lang-btn active" onclick="switchLanguage('hi')">हिं</button>
    </div>

    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
        <main class="main-content">
            <div class="header">
                <h1 data-en="Team Management" data-hi="टीम प्रबंधन">टीम प्रबंधन</h1>
                <p data-en="Manage your network marketing team members and performance insights." data-hi="अपने नेटवर्क मार्केटिंग टीम सदस्यों और प्रदर्शन अंतर्दृष्टि को प्रबंधित करें।">अपने नेटवर्क मार्केटिंग टीम सदस्यों और प्रदर्शन अंतर्दृष्टि को प्रबंधित करें।</p>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    ✅ <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
        
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    ❌ <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($db_status === 'error'): ?>
                <div class="alert alert-error">
                    ❌ **डेटाबेस त्रुटि:** टीम डेटा लोड नहीं हो सका। कृपया लॉग्स की जाँच करें।
                </div>
            <?php endif; ?>
        
            <?php if ($user_role === 'admin'): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary"><i class="fas fa-users"></i></div>
                    <div class="stat-value"><?php echo number_format($team_stats['total_members']); ?></div>
                    <div class="stat-label" data-en="Total Members" data-hi="कुल सदस्य">कुल सदस्य</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-icon success"><i class="fas fa-user-check"></i></div>
                    <div class="stat-value"><?php echo number_format($team_stats['active_members']); ?></div>
                    <div class="stat-label" data-en="Active Members" data-hi="सक्रिय सदस्य">सक्रिय सदस्य</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon warning"><i class="fas fa-sitemap"></i></div>
                    <div class="stat-value"><?php echo number_format($team_stats['total_downlines']); ?></div>
                    <div class="stat-label" data-en="Total Downlines" data-hi="कुल डाउनलाइन्स">कुल डाउनलाइन्स</div>
                </div>
                <div class="stat-card info">
                    <div class="stat-icon info"><i class="fas fa-layer-group"></i></div>
                    <div class="stat-value"><?php echo htmlspecialchars($team_stats['top_level']); ?></div>
                    <div class="stat-label" data-en="Highest Level" data-hi="सर्वोच्च स्तर">सर्वोच्च स्तर</div>
                </div>
            </div>
            <?php endif; ?>

            <div class="table-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0; color: #333;" data-en="Team Members" data-hi="टीम सदस्य">टीम सदस्य</h3>
                    <?php if ($user_role === 'admin'): ?>
                    <div>
                        <button onclick="generateSignupLink()" class="btn btn-primary">
                            <i class="fas fa-link"></i>
                            <span data-en="Generate Signup Link" data-hi="साइनअप लिंक बनाएं">साइनअप लिंक बनाएं</span>
                        </button>
                        <button onclick="openModal('addModal')" class="btn btn-success">
                            <i class="fas fa-plus"></i>
                            <span data-en="Add New Member" data-hi="नया सदस्य जोड़ें">नया सदस्य जोड़ें</span>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
        
                <?php if (empty($team_members)): ?>
                    <p style="text-align: center; color: #999; padding: 40px;" data-en="No team members found" data-hi="कोई टीम सदस्य नहीं मिला">कोई टीम सदस्य नहीं मिला</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th data-en="Member" data-hi="सदस्य">सदस्य</th>
                                <th data-en="Level" data-hi="स्तर">स्तर</th>
                                <th data-en="Upline" data-hi="अपलाइन">अपलाइन</th>
                                <th data-en="Last 7 Days" data-hi="पिछले 7 दिन">पिछले 7 दिन</th>
                                <th data-en="Performance" data-hi="प्रदर्शन">प्रदर्शन</th>
                                <th data-en="Challenge" data-hi="चुनौती">चुनौती</th>
                                <th data-en="Network" data-hi="नेटवर्क">नेटवर्क</th>
                                <th data-en="Commissions" data-hi="कमीशन">कमीशन</th>
                                <th data-en="Status" data-hi="स्थिति">स्थिति</th>
                                <?php if ($user_role === 'admin'): ?>
                                    <th data-en="Actions" data-hi="कार्य">कार्य</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($team_members as $member): 
                                // 💡 PHP Logic for Insights
                                $is_stagnant = $member['stagnant_leads'] > 0;
                                $is_low_activity = $member['leads_last_7_days'] < 5 && $member['status'] === 'active';
                                $growth_badge_class = '';
                                if ($member['leads_last_7_days'] > 15) {
                                    $growth_badge_class = 'badge-high-growth';
                                } elseif ($is_low_activity) {
                                    $growth_badge_class = 'badge-low-activity';
                                }
                            ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-weight: 800;">
                                            <?php echo strtoupper(substr(htmlspecialchars($member['username']), 0, 1)); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($member['full_name'] ?: $member['username']); ?></strong>
                                            <br><small style="color: #666;">@<?php echo htmlspecialchars($member['username']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-level">
                                        Level <?php echo htmlspecialchars($member['level']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($member['upline_name']): ?>
                                        <?php echo htmlspecialchars($member['upline_name']); ?>
                                        <br><small style="color: #666;">@<?php echo htmlspecialchars($member['upline_username']); ?></small>
                                    <?php else: ?>
                                        <span style="color: #999;" data-en="Top Level" data-hi="शीर्ष स्तर">शीर्ष स्तर</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($member['leads_last_7_days']); ?></strong> <span data-en="leads" data-hi="लीड्स">लीड्स</span>
                                    <?php if ($growth_badge_class): ?>
                                        <br><span class="badge <?php echo $growth_badge_class; ?>" style="margin-top: 5px;">
                                            <?php echo $growth_badge_class === 'badge-high-growth' ? 'High Growth' : 'Low Activity'; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($member['conversions']); ?></strong> <span data-en="conversions" data-hi="रूपांतरण">रूपांतरण</span>
                                    </div>
                                    <small style="color: #666;"><?php echo htmlspecialchars($member['total_leads']); ?> <span data-en="leads" data-hi="लीड्स">लीड्स</span></small>
                                </td>
                                <td>
                                    <?php if ($is_stagnant): ?>
                                        <span class="badge badge-danger" style="background: #ffcccc; color: #cc0000;" data-en="Stagnant Leads" data-hi="स्थिर लीड्स">
                                            ⚠️ <?php echo htmlspecialchars($member['stagnant_leads']); ?> Stagnant
                                        </span>
                                        <br><small style="color: #666;" data-en="Needs Follow-up" data-hi="फॉलो-अप चाहिए">फॉलो-अप चाहिए</small>
                                    <?php else: ?>
                                        <span style="color: #008000;" data-en="OK" data-hi="ठीक है">✅ OK</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($member['direct_downlines']); ?></strong> <span data-en="downlines" data-hi="डाउनलाइन्स">डाउनलाइन्स</span>
                                </td>
                                <td>
                                    ₹<?php echo number_format($member['total_commissions']); ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $member['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?php echo htmlspecialchars(ucfirst($member['status'])); ?>
                                    </span>
                                </td>
                                <?php if ($user_role === 'admin'): ?>
                                <td>
                                    <button onclick="openEditModal(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars($member['full_name'] ?: $member['username'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($member['status'], ENT_QUOTES); ?>', <?php echo htmlspecialchars($member['level']); ?>, <?php echo $member['upline_id'] ?: 'null'; ?>)" class="btn btn-primary">
                                        <i class="fas fa-edit"></i>
                                        <span data-en="Edit" data-hi="संपादित">संपादित</span>
                                    </button>
                                    
                                    <button onclick="openPasswordModal(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars($member['full_name'] ?: $member['username'], ENT_QUOTES); ?>')" class="btn btn-warning">
                                        <i class="fas fa-key"></i>
                                        <span data-en="Reset Pass" data-hi="पासवर्ड रीसेट">पासवर्ड रीसेट</span>
                                    </button>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this member?');">
                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($member['id']); ?>">
                                        <button type="submit" name="delete_member" class="btn btn-danger">
                                            <i class="fas fa-trash"></i>
                                            <span data-en="Delete" data-hi="हटाएं">हटाएं</span>
                                        </button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <?php if ($user_role === 'admin'): ?>
    
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" data-en="Add New Team Member" data-hi="नया टीम सदस्य जोड़ें">नया टीम सदस्य जोड़ें</h3>
            <span class="close" onclick="closeModal('addModal')">&times;</span>
            </div>
            
            <form method="POST">
                <div class="form-group"><label data-en="Username *" data-hi="उपयोगकर्ता नाम *">उपयोगकर्ता नाम *</label><input type="text" name="username" class="form-control" required></div>
                <div class="form-group"><label data-en="Email *" data-hi="ईमेल *">ईमेल *</label><input type="email" name="email" class="form-control" required></div>
                <div class="form-group"><label data-en="Full Name *" data-hi="पूरा नाम *">पूरा नाम *</label><input type="text" name="full_name" class="form-control" required></div>
                <div class="form-group"><label data-en="Phone" data-hi="फ़ोन">फ़ोन</label><input type="tel" name="phone" class="form-control"></div>
                <div class="form-group"><label data-en="Password *" data-hi="पासवर्ड *">पासवर्ड *</label><input type="password" name="password" class="form-control" required minlength="6"></div>
                <div class="form-group">
                    <label data-en="Level *" data-hi="स्तर *">स्तर *</label>
                    <select name="level" class="form-control" required><?php for($i=1; $i<=5; $i++) echo "<option value=\"$i\">Level $i</option>"; ?></select>
                </div>
                <div class="form-group">
                    <label data-en="Upline (Optional)" data-hi="अपलाइन (वैकल्पिक)">अपलाइन (वैकल्पिक)</label>
                    <select name="upline_id" class="form-control">
                        <option value="">No Upline</option>
                        <?php foreach ($upline_options as $upline): ?>
                            <option value="<?php echo htmlspecialchars($upline['id']); ?>">
                                <?php echo htmlspecialchars($upline['full_name'] ?: $upline['username']); ?> (Level <?php echo htmlspecialchars($upline['level']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" name="add_member" class="submit-btn">
                    <i class="fas fa-user-plus"></i>
                    <span data-en="Add Member" data-hi="सदस्य जोड़ें">सदस्य जोड़ें</span>
                </button>
            </form>
        </div>
    </div>
    
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" data-en="Edit Team Member" data-hi="टीम सदस्य संपादित करें">टीम सदस्य संपादित करें</h3>
            <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            
            <form method="POST">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="form-group"><label data-en="Member" data-hi="सदस्य">सदस्य</label><input type="text" id="editMemberName" class="form-control" readonly style="background: #f8f9fa;"></div>
                <div class="form-group">
                    <label data-en="Status *" data-hi="स्थिति *">स्थिति *</label>
                    <select name="status" id="editStatus" class="form-control" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label data-en="Level *" data-hi="स्तर *">स्तर *</label>
                    <select name="level" id="editLevel" class="form-control" required>
                        <?php for($i=1; $i<=5; $i++) echo "<option value=\"$i\">Level $i</option>"; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label data-en="Upline" data-hi="अपलाइन">अपलाइन</label>
                    <select name="upline_id" id="editUplineId" class="form-control">
                        <option value="">No Upline</option>
                        <?php foreach ($upline_options as $upline): ?>
                            <option value="<?php echo htmlspecialchars($upline['id']); ?>">
                                <?php echo htmlspecialchars($upline['full_name'] ?: $upline['username']); ?> (Level <?php echo htmlspecialchars($upline['level']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="update_level" class="submit-btn"><i class="fas fa-save"></i><span data-en="Update Member" data-hi="सदस्य अपडेट करें">सदस्य अपडेट करें</span></button>
            </form>
        </div>
    </div>
    
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" data-en="Reset Password" data-hi="पासवर्ड रीसेट करें">पासवर्ड रीसेट करें</h3>
            <span class="close" onclick="closeModal('passwordModal')">&times;</span>
            </div>
            
            <form method="POST">
                <input type="hidden" name="user_id" id="passUserId">
                <div class="form-group"><label data-en="Member" data-hi="सदस्य">सदस्य</label><input type="text" id="passMemberName" class="form-control" readonly style="background: #f8f9fa;"></div>
                <div class="form-group"><label data-en="New Password *" data-hi="नया पासवर्ड *">नया पासवर्ड *</label><input type="password" name="new_password" class="form-control" required minlength="6"></div>
                <button type="submit" name="reset_password" class="submit-btn"><i class="fas fa-key"></i><span data-en="Reset Password" data-hi="पासवर्ड रीसेट करें">पासवर्ड रीसेट करें</span></button>
            </form>
        </div>
    </div>
    <?php endif; /* End of Admin-only Modals */ ?>
    
    <script>
        // Language switching functionality (Retained)
        function switchLanguage(lang) {
            document.querySelectorAll('[data-en][data-hi]').forEach(element => {
                element.textContent = element.getAttribute('data-' + lang);
            });
            document.querySelectorAll('.lang-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
        }

        // Modal functions (Retained)
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function openEditModal(userId, name, status, level, uplineId) {
            document.getElementById('editUserId').value = userId;
            document.getElementById('editMemberName').value = name;
            document.getElementById('editStatus').value = status;
            document.getElementById('editLevel').value = level;
            document.getElementById('editUplineId').value = uplineId || '';
            openModal('editModal');
        }
        
        function openPasswordModal(userId, name) {
            document.getElementById('passUserId').value = userId;
            document.getElementById('passMemberName').value = name;
            openModal('passwordModal');
        }
        
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }

        function generateSignupLink() {
            fetch('/api/generate_signup_token.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const signupLink = window.location.origin + '/team/register.php?token=' + data.token;
                    prompt("Copy this signup link and share it with your new team member:", signupLink);
                } else {
                    alert('Could not generate signup link. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while generating the signup link.');
            });
        }

        // Initialize interactions
        document.addEventListener('DOMContentLoaded', function() {
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