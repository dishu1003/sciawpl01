<?php
/**
 * Network Marketing CRM - Team Management (ENHANCED)
 * FINAL SECURED VERSION - uplid_id REMOVED
 * Features:
 *  - Role based access (admin/team)
 *  - Performance insights: leads, conversions, recent activity
 *  - Referral/signup token support (signup handled via token)
 *  - Secure POST handlers (prepared statements, password_hash)
 *  - Improved UI strings and removed all upline_id references
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/security.php';

// Security headers
function set_default_security_headers() {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: frame-ancestors 'self'");
}
set_default_security_headers();

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

    if ($user_role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Add member (upline removed) -------------------------------------------------
        if (isset($_POST['add_member'])) {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $full_name = trim($_POST['full_name']);
            $phone = trim($_POST['phone']);
            $password = $_POST['password'];
            $level = filter_var($_POST['level'], FILTER_VALIDATE_INT) ?: 1;

            $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check_stmt->execute([$username, $email]);

            if ($check_stmt->fetch()) {
                $_SESSION['error_message'] = "Username or email already exists!";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, phone, password_hash, role, status, level, referral_token, created_at) VALUES (?, ?, ?, ?, ?, 'team', 'active', ?, ?, NOW())");
                // Generate a referral token for this user (unique)
                $ref_token = bin2hex(random_bytes(8));

                $stmt->execute([
                    htmlspecialchars($username),
                    htmlspecialchars($email),
                    htmlspecialchars($full_name),
                    htmlspecialchars($phone),
                    $password_hash,
                    $level,
                    $ref_token
                ]);

                $_SESSION['success_message'] = "Team member added successfully!";
            }

            header('Location: /admin/team.php');
            exit;
        }

        // Update status ----------------------------------------------------------------
        if (isset($_POST['update_status'])) {
            $user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
            $status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';
            $stmt = $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $user_id]);
            $_SESSION['success_message'] = "Status updated successfully!";
            header('Location: /admin/team.php');
            exit;
        }

        // Update level (no upline) -----------------------------------------------------
        if (isset($_POST['update_level'])) {
            $user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
            $level = filter_var($_POST['level'], FILTER_VALIDATE_INT) ?: 1;

            $stmt = $pdo->prepare("UPDATE users SET level = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$level, $user_id]);

            $_SESSION['success_message'] = "Level updated successfully!";
            header('Location: /admin/team.php');
            exit;
        }

        // Reset password ----------------------------------------------------------------
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

        // Delete member -----------------------------------------------------------------
        if (isset($_POST['delete_member'])) {
            $user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);

            // Unassign all leads first
            $stmt = $pdo->prepare("UPDATE leads SET assigned_to = NULL WHERE assigned_to = ?");
            $stmt->execute([$user_id]);

            // Remove referrals (if any) - if your schema stores referred_by, update accordingly
            // We'll avoid assumptions and only delete the user if role is 'team'
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'team'");
            $stmt->execute([$user_id]);

            $_SESSION['success_message'] = "Team member deleted successfully!";
            header('Location: /admin/team.php');
            exit;
        }
    }

    // Safe count helper
    $safe_count = function($sql, $params = []) use ($pdo) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    };

    // Member filter for team role (team sees only self)
    $member_filter = $user_role === 'team' ? "AND u.id = {$current_user_id}" : "";

    // Main query - removed upline and downlines references
    $stmt = $pdo->prepare(
        "SELECT u.*, 
               COUNT(l.id) as total_leads,
               COUNT(CASE WHEN l.status = 'converted' THEN 1 END) as conversions,
               COUNT(CASE WHEN l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as leads_last_7_days,
               COUNT(CASE WHEN l.status = 'active' AND l.updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as stagnant_leads
        FROM users u
            LEFT JOIN leads l ON u.id = l.assigned_to
        WHERE u.role = 'team' {$member_filter}
        GROUP BY u.id
        ORDER BY u.level DESC, conversions DESC, total_leads DESC"
    );
    $stmt->execute();
    $team_members = $stmt->fetchAll();

    // Upline options removed - keep basic active user list for potential assignment features
    $upline_options = [];

    // Team stats (admin)
    $team_stats = [];
    if ($user_role === 'admin') {
        $team_stats['total_members'] = count($team_members);
        $team_stats['active_members'] = $safe_count("SELECT COUNT(*) FROM users WHERE role = 'team' AND status = 'active'");
        $team_stats['total_downlines'] = $safe_count("SELECT COUNT(*) FROM users WHERE role = 'team' AND referral_token IS NOT NULL");
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* (styles same as before - kept for brevity) */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: #333; }
        .dashboard-container { display: grid; grid-template-columns: 280px 1fr; min-height: 100vh; }
        .sidebar { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); padding: 30px 20px; box-shadow: 2px 0 20px rgba(0,0,0,0.1); }
        .main-content { padding: 30px; overflow-y: auto; }
        .stat-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); padding: 30px; border-radius: 20px; }
        .table-container { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-radius: 20px; padding: 30px; }
        .table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        .table th, .table td { padding: 12px; border-bottom: 1px solid #f1f3f4; text-align: left; }
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .btn { padding: 8px 14px; border-radius: 8px; font-weight: 600; cursor: pointer; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="header">
                <h1 data-en="Team Management" data-hi="टीम प्रबंधन">टीम प्रबंधन</h1>
                <p data-en="Manage your network marketing team members and performance insights." data-hi="अपने नेटवर्क मार्केटिंग टीम सदस्यों और प्रदर्शन अंतर्दृष्टि को प्रबंधित करें।">अपने नेटवर्क मार्केटिंग टीम सदस्यों और प्रदर्शन अंतर्दृष्टि को प्रबंधित करें।</p>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">❌ <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
            <?php endif; ?>
            <?php if ($db_status === 'error'): ?>
                <div class="alert alert-error">❌ डाटाबेस त्रुटि: टीम डेटा लोड नहीं हो सका।</div>
            <?php endif; ?>

            <?php if ($user_role === 'admin'): ?>
            <div class="stats-grid" style="display:flex;gap:20px;margin-bottom:20px;">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($team_stats['total_members']); ?></div>
                    <div class="stat-label">कुल सदस्य</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($team_stats['active_members']); ?></div>
                    <div class="stat-label">सक्रिय सदस्य</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($team_stats['total_downlines']); ?></div>
                    <div class="stat-label">Referral Tokens</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo htmlspecialchars($team_stats['top_level']); ?></div>
                    <div class="stat-label">सर्वोच्च स्तर</div>
                </div>
            </div>
            <?php endif; ?>

            <div class="table-container">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <h3>टीम सदस्य</h3>
                    <?php if ($user_role === 'admin'): ?>
                    <div>
                        <button onclick="generateSignupLink()" class="btn btn-primary"><i class="fas fa-link"></i> साइनअप लिंक बनाएं</button>
                        <button onclick="openModal('addModal')" class="btn btn-success"><i class="fas fa-plus"></i> नया सदस्य जोड़ें</button>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (empty($team_members)): ?>
                    <p style="text-align:center;color:#999;padding:40px;">कोई टीम सदस्य नहीं मिला</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Level</th>
                                <th>Last 7 Days</th>
                                <th>Performance</th>
                                <th>Challenge</th>
                                <th>Status</th>
                                <?php if ($user_role === 'admin'): ?><th>Actions</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($team_members as $member):
                                $is_stagnant = $member['stagnant_leads'] > 0;
                                $is_low_activity = $member['leads_last_7_days'] < 5 && $member['status'] === 'active';
                                $growth_badge_class = '';
                                if ($member['leads_last_7_days'] > 15) { $growth_badge_class = 'badge-high-growth'; }
                                elseif ($is_low_activity) { $growth_badge_class = 'badge-low-activity'; }
                            ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;color:white;font-weight:800;">
                                            <?php echo strtoupper(substr(htmlspecialchars($member['username']), 0, 1)); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($member['full_name'] ?: $member['username']); ?></strong>
                                            <br><small style="color:#666;">@<?php echo htmlspecialchars($member['username']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge">Level <?php echo htmlspecialchars($member['level']); ?></span></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($member['leads_last_7_days']); ?></strong> leads
                                    <?php if ($growth_badge_class): ?>
                                        <br><span class="badge" style="margin-top:5px;"><?php echo $growth_badge_class === 'badge-high-growth' ? 'High Growth' : 'Low Activity'; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($member['conversions']); ?></strong> conversions
                                    <br><small style="color:#666;"><?php echo htmlspecialchars($member['total_leads']); ?> leads</small>
                                </td>
                                <td>
                                    <?php if ($is_stagnant): ?>
                                        <span class="badge" style="background:#ffcccc;color:#cc0000;">⚠️ <?php echo htmlspecialchars($member['stagnant_leads']); ?> Stagnant</span>
                                        <br><small style="color:#666;">Needs Follow-up</small>
                                    <?php else: ?>
                                        <span style="color:#008000;">✅ OK</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge"><?php echo htmlspecialchars(ucfirst($member['status'])); ?></span>
                                </td>
                                <?php if ($user_role === 'admin'): ?>
                                <td>
                                    <button onclick="openEditModal(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars($member['full_name'] ?: $member['username'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($member['status'], ENT_QUOTES); ?>', <?php echo htmlspecialchars($member['level']); ?>)" class="btn btn-primary"><i class="fas fa-edit"></i> संपादित</button>
                                    <button onclick="openPasswordModal(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars($member['full_name'] ?: $member['username'], ENT_QUOTES); ?>')" class="btn btn-warning"><i class="fas fa-key"></i> पासवर्ड रीसेट</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this member?');">
                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($member['id']); ?>">
                                        <button type="submit" name="delete_member" class="btn btn-danger"><i class="fas fa-trash"></i> हटाएं</button>
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
    <!-- Add Modal (upline removed) -->
    <div id="addModal" class="modal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>नया टीम सदस्य जोड़ें</h3>
                <span class="close" onclick="closeModal('addModal')">&times;</span>
            </div>
            <form method="POST">
                <label>Username *</label><input type="text" name="username" required>
                <label>Email *</label><input type="email" name="email" required>
                <label>Full Name *</label><input type="text" name="full_name" required>
                <label>Phone</label><input type="tel" name="phone">
                <label>Password *</label><input type="password" name="password" required minlength="6">
                <label>Level *</label>
                <select name="level" required><?php for($i=1;$i<=5;$i++) echo "<option value=\"$i\">Level $i</option>"; ?></select>
                <button type="submit" name="add_member">सदस्य जोड़ें</button>
            </form>
        </div>
    </div>

    <!-- Edit Modal (upline removed) -->
    <div id="editModal" class="modal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>टीम सदस्य संपादित करें</h3>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="user_id" id="editUserId">
                <label>Member</label><input type="text" id="editMemberName" readonly>
                <label>Status *</label>
                <select name="status" id="editStatus">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <label>Level *</label>
                <select name="level" id="editLevel"><?php for($i=1;$i<=5;$i++) echo "<option value=\"$i\">Level $i</option>"; ?></select>
                <button type="submit" name="update_level">सदस्य अपडेट करें</button>
            </form>
        </div>
    </div>

    <!-- Password Modal -->
    <div id="passwordModal" class="modal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>पासवर्ड रीसेट करें</h3>
                <span class="close" onclick="closeModal('passwordModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="user_id" id="passUserId">
                <label>Member</label><input type="text" id="passMemberName" readonly>
                <label>New Password *</label><input type="password" name="new_password" required minlength="6">
                <button type="submit" name="reset_password">पासवर्ड रीसेट करें</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        function openEditModal(userId, name, status, level) {
            document.getElementById('editUserId').value = userId;
            document.getElementById('editMemberName').value = name;
            document.getElementById('editStatus').value = status;
            document.getElementById('editLevel').value = level;
            openModal('editModal');
        }
        function openPasswordModal(userId, name) {
            document.getElementById('passUserId').value = userId;
            document.getElementById('passMemberName').value = name;
            openModal('passwordModal');
        }

        function generateSignupLink() {
            fetch('/api/generate_signup_token.php', { method: 'POST' })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const signupLink = window.location.origin + '/team/register.php?token=' + data.token;
                    prompt("Copy this signup link and share it with your new team member:", signupLink);
                } else {
                    alert('Could not generate signup link.');
                }
            }).catch(err => { console.error(err); alert('Error generating signup link.'); });
        }

        window.onclick = function(event) {
            if (event.target.className === 'modal') event.target.style.display = 'none';
        }

    </script>
</body>
</html>
