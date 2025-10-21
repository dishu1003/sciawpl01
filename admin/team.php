<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_admin();

$pdo = get_pdo_connection();

// Handle team member actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_member'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $unique_ref = strtolower(str_replace(' ', '', $username)) . rand(100, 999);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, username, password, role, unique_ref) VALUES (?, ?, ?, ?, 'team', ?)");
            $stmt->execute([$name, $email, $username, $password, $unique_ref]);
            $success = "Team member added successfully!";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['update_status'])) {
        $user_id = $_POST['user_id'];
        $status = $_POST['status'];
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$status, $user_id]);
        $success = "Status updated successfully!";
    }
    
    if (isset($_POST['delete_member'])) {
        $user_id = $_POST['user_id'];
        // Unassign all leads first
        $stmt = $pdo->prepare("UPDATE leads SET assigned_to = NULL WHERE assigned_to = ?");
        $stmt->execute([$user_id]);
        // Delete user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'team'");
        $stmt->execute([$user_id]);
        $success = "Team member deleted successfully!";
    }
    
    if (isset($_POST['reset_password'])) {
        $user_id = $_POST['user_id'];
        $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$new_password, $user_id]);
        $success = "Password reset successfully!";
    }
}

// Fetch all team members
$stmt = $pdo->query("
    SELECT u.*, 
           COUNT(l.id) as total_leads,
           SUM(CASE WHEN l.status = 'converted' THEN 1 ELSE 0 END) as converted_leads
    FROM users u
    LEFT JOIN leads l ON u.id = l.assigned_to
    WHERE u.role = 'team'
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$team_members = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Team Management</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: #000;
        }
    </style>
</head>
<body>
    <nav class="dashboard-nav">
        <h1><?php echo SITE_NAME; ?> - Team Management</h1>
        <a href="/admin/">← Back to Dashboard</a>
    </nav>
    
    <div class="main-content">
        <?php if (isset($success)): ?>
            <div style="background:#27ae60; color:white; padding:15px; border-radius:8px; margin-bottom:20px;">
                ✅ <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div style="background:#e74c3c; color:white; padding:15px; border-radius:8px; margin-bottom:20px;">
                ❌ <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
            <h2>Team Members (<?php echo count($team_members); ?>)</h2>
            <button onclick="openModal('addModal')" class="cta-btn" style="padding:10px 20px; font-size:1rem;">
                ➕ Add New Member
            </button>
        </div>
        
        <table class="leads-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Unique Ref</th>
                    <th>Total Leads</th>
                    <th>Converted</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($team_members as $member): ?>
                <tr>
                    <td><?php echo htmlspecialchars($member['name']); ?></td>
                    <td><?php echo htmlspecialchars($member['username']); ?></td>
                    <td><?php echo htmlspecialchars($member['email']); ?></td>
                    <td>
                        <code><?php echo htmlspecialchars($member['unique_ref']); ?></code>
                        <button onclick="copyToClipboard('<?php echo SITE_URL; ?>/?ref=<?php echo $member['unique_ref']; ?>')" 
                                style="padding:3px 8px; font-size:0.8rem; margin-left:5px;">📋</button>
                    </td>
                    <td><?php echo $member['total_leads']; ?></td>
                    <td><?php echo $member['converted_leads']; ?></td>
                    <td>
                        <span class="badge" style="background:<?php echo $member['status'] == 'active' ? '#27ae60' : '#95a5a6'; ?>">
                            <?php echo ucfirst($member['status']); ?>
                        </span>
                    </td>
                    <td>
                        <button onclick="openEditModal(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars($member['name']); ?>', '<?php echo $member['status']; ?>')" 
                                class="btn-small">Edit</button>
                        <button onclick="openPasswordModal(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars($member['name']); ?>')" 
                                class="btn-small" style="background:#f39c12;">Reset Pass</button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this member?');">
                            <input type="hidden" name="user_id" value="<?php echo $member['id']; ?>">
                            <button type="submit" name="delete_member" class="btn-small" style="background:#e74c3c;">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Add Member Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addModal')">&times;</span>
            <h2>Add New Team Member</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" required minlength="6">
                </div>
                <button type="submit" name="add_member" class="submit-btn">Add Member</button>
            </form>
        </div>
    </div>
    
    <!-- Edit Status Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editModal')">&times;</span>
            <h2>Update Status</h2>
            <form method="POST">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="form-group">
                    <label>Member: <strong id="edit_member_name"></strong></label>
                </div>
                <div class="form-group">
                    <label>Status *</label>
                    <select name="status" id="edit_status" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <button type="submit" name="update_status" class="submit-btn">Update Status</button>
            </form>
        </div>
    </div>
    
    <!-- Reset Password Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('passwordModal')">&times;</span>
            <h2>Reset Password</h2>
            <form method="POST">
                <input type="hidden" name="user_id" id="pass_user_id">
                <div class="form-group">
                    <label>Member: <strong id="pass_member_name"></strong></label>
                </div>
                <div class="form-group">
                    <label>New Password *</label>
                    <input type="password" name="new_password" required minlength="6">
                </div>
                <button type="submit" name="reset_password" class="submit-btn">Reset Password</button>
            </form>
        </div>
    </div>
    
    <script>
        function openModal(id) {
            document.getElementById(id).style.display = 'block';
        }
        
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        
        function openEditModal(userId, name, status) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_member_name').textContent = name;
            document.getElementById('edit_status').value = status;
            openModal('editModal');
        }
        
        function openPasswordModal(userId, name) {
            document.getElementById('pass_user_id').value = userId;
            document.getElementById('pass_member_name').textContent = name;
            openModal('passwordModal');
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Link copied to clipboard!');
            });
        }
        
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>