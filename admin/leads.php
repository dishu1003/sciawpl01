<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_admin();

$pdo = get_pdo_connection();
$lead_id = $_GET['id'] ?? 0;

// Fetch lead
$stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
$stmt->execute([$lead_id]);
$lead = $stmt->fetch();

if (!$lead) {
    die('Lead not found');
}

// Fetch all team members
$stmt = $pdo->query("SELECT id, name, unique_ref FROM users WHERE role = 'team' AND status = 'active'");
$team_members = $stmt->fetchAll();

// Fetch activity logs
$stmt = $pdo->prepare("SELECT l.*, u.name as user_name FROM logs l LEFT JOIN users u ON l.user_id = u.id WHERE l.lead_id = ? ORDER BY l.timestamp DESC");
$stmt->execute([$lead_id]);
$logs = $stmt->fetchAll();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_lead'])) {
        $new_user_id = $_POST['assigned_to'];
        assign_lead($lead_id, $new_user_id);
        header('Location: /admin/leads.php?id=' . $lead_id);
        exit;
    }
    
    if (isset($_POST['update_status'])) {
        update_lead_status($lead_id, $_POST['status']);
        header('Location: /admin/leads.php?id=' . $lead_id);
        exit;
    }
    
    if (isset($_POST['update_score'])) {
        update_lead_score($lead_id, $_POST['score']);
        header('Location: /admin/leads.php?id=' . $lead_id);
        exit;
    }
    
    if (isset($_POST['add_note'])) {
        add_lead_note($lead_id, $_POST['note']);
        header('Location: /admin/leads.php?id=' . $lead_id);
        exit;
    }
    
    if (isset($_POST['delete_lead'])) {
        $stmt = $pdo->prepare("DELETE FROM leads WHERE id = ?");
        $stmt->execute([$lead_id]);
        header('Location: /admin/');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Lead - <?php echo htmlspecialchars($lead['name']); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <nav class="dashboard-nav">
        <h1><?php echo SITE_NAME; ?> - Admin</h1>
        <a href="/admin/">← Back to Dashboard</a>
    </nav>
    
    <div class="lead-detail" style="margin: 30px auto;">
        <div class="lead-header">
            <div>
                <h2><?php echo htmlspecialchars($lead['name']); ?></h2>
                <p style="color:#7f8c8d;">Lead ID: #<?php echo $lead['id']; ?></p>
            </div>
            <span class="badge badge-<?php echo strtolower($lead['lead_score']); ?>"><?php echo $lead['lead_score']; ?></span>
        </div>
        
        <div class="lead-info">
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:20px;">
                <div>
                    <p><strong>📧 Email:</strong> <?php echo htmlspecialchars($lead['email']); ?></p>
                    <p><strong>📱 Phone:</strong> <a href="tel:<?php echo $lead['phone']; ?>"><?php echo htmlspecialchars($lead['phone']); ?></a></p>
                    <p><strong>📊 Current Step:</strong> <?php echo $lead['current_step']; ?>/4</p>
                </div>
                <div>
                    <p><strong>🎯 Status:</strong> <?php echo ucfirst($lead['status']); ?></p>
                    <p><strong>🌱 Source:</strong> <?php echo ucfirst(str_replace('_', ' ', $lead['source'] ?? 'organic')); ?></p>
                    <p><strong>📅 Created:</strong> <?php echo date('d M Y, h:i A', strtotime($lead['created_at'])); ?></p>
                </div>
                <div>
                    <p><strong>👤 Assigned To:</strong> 
                        <?php 
                        if ($lead['assigned_to']) {
                            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                            $stmt->execute([$lead['assigned_to']]);
                            $assigned_user = $stmt->fetch();
                            echo htmlspecialchars($assigned_user['name'] ?? 'Unknown');
                        } else {
                            echo 'Unassigned';
                        }
                        ?>
                    </p>
                    <p><strong>🔄 Last Updated:</strong> <?php echo date('d M Y, h:i A', strtotime($lead['updated_at'])); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Admin Actions -->
        <div class="quick-actions">
            <h3>🛠️ Admin Actions</h3>
            
            <form method="POST" style="display:inline-block; margin-right:15px;">
                <select name="assigned_to" required>
                    <option value="">Assign to...</option>
                    <?php foreach ($team_members as $member): ?>
                        <option value="<?php echo $member['id']; ?>" <?php echo $lead['assigned_to'] == $member['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($member['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="assign_lead">Assign</button>
            </form>
            
            <form method="POST" style="display:inline-block; margin-right:15px;">
                <select name="status" required>
                    <option value="">Update Status...</option>
                    <option value="New" <?php echo $lead['status'] == 'New' ? 'selected' : ''; ?>>New</option>
                    <option value="Contacted" <?php echo $lead['status'] == 'Contacted' ? 'selected' : ''; ?>>Contacted</option>
                    <option value="Plan Shown" <?php echo $lead['status'] == 'Plan Shown' ? 'selected' : ''; ?>>Plan Shown</option>
                    <option value="Follow-up" <?php echo $lead['status'] == 'Follow-up' ? 'selected' : ''; ?>>Follow-up</option>
                    <option value="Joined" <?php echo $lead['status'] == 'Joined' ? 'selected' : ''; ?>>Joined</option>
                    <option value="Not Interested" <?php echo $lead['status'] == 'Not Interested' ? 'selected' : ''; ?>>Not Interested</option>
                </select>
                <button type="submit" name="update_status">Update</button>
            </form>
            
            <form method="POST" style="display:inline-block; margin-right:15px;">
                <select name="score" required>
                    <option value="">Update Score...</option>
                    <option value="HOT" <?php echo $lead['lead_score'] == 'HOT' ? 'selected' : ''; ?>>🔥 HOT</option>
                    <option value="WARM" <?php echo $lead['lead_score'] == 'WARM' ? 'selected' : ''; ?>>🌡️ WARM</option>
                    <option value="COLD" <?php echo $lead['lead_score'] == 'COLD' ? 'selected' : ''; ?>>❄️ COLD</option>
                </select>
                <button type="submit" name="update_score">Update</button>
            </form>
            
            <form method="POST" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this lead?');">
                <button type="submit" name="delete_lead" style="background:#e74c3c;">🗑️ Delete Lead</button>
            </form>
        </div>
        
        <!-- Form Responses -->
        <div class="form-responses">
            <h3>📋 Form Responses</h3>
            
            <?php if ($lead['form_a_data']): ?>
                <div class="form-data">
                    <h4>✅ Form A - Lead Capture & Intent</h4>
                    <p><small>Submitted: <?php echo date('d M Y, h:i A', strtotime($lead['form_a_submitted_at'])); ?></small></p>
                    <?php 
                    $form_a = json_decode($lead['form_a_data'], true);
                    foreach ($form_a as $key => $value): ?>
                        <p><strong><?php echo ucwords(str_replace('_', ' ', $key)); ?>:</strong> <?php echo htmlspecialchars($value); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($lead['form_b_data']): ?>
                <div class="form-data">
                    <h4>✅ Form B - Engagement & Value</h4>
                    <p><small>Submitted: <?php echo date('d M Y, h:i A', strtotime($lead['form_b_submitted_at'])); ?></small></p>
                    <?php 
                    $form_b = json_decode($lead['form_b_data'], true);
                    foreach ($form_b as $key => $value): ?>
                        <p><strong><?php echo ucwords(str_replace('_', ' ', $key)); ?>:</strong> <?php echo htmlspecialchars($value); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($lead['form_c_data']): ?>
                <div class="form-data">
                    <h4>✅ Form C - Commitment & Qualification</h4>
                    <p><small>Submitted: <?php echo date('d M Y, h:i A', strtotime($lead['form_c_submitted_at'])); ?></small></p>
                    <?php 
                    $form_c = json_decode($lead['form_c_data'], true);
                    foreach ($form_c as $key => $value): ?>
                        <p><strong><?php echo ucwords(str_replace('_', ' ', $key)); ?>:</strong> <?php echo htmlspecialchars($value); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($lead['form_d_data']): ?>
                <div class="form-data">
                    <h4>✅ Form D - Strategy Call Booking</h4>
                    <p><small>Submitted: <?php echo date('d M Y, h:i A', strtotime($lead['form_d_submitted_at'])); ?></small></p>
                    <?php 
                    $form_d = json_decode($lead['form_d_data'], true);
                    foreach ($form_d as $key => $value): ?>
                        <p><strong><?php echo ucwords(str_replace('_', ' ', $key)); ?>:</strong> <?php echo htmlspecialchars($value); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Notes Section -->
        <div class="notes-section">
            <h3>📝 Notes</h3>
            <div style="background:white; padding:15px; border-radius:8px; margin-bottom:15px; white-space:pre-wrap;">
                <?php echo htmlspecialchars($lead['notes'] ?: 'No notes yet'); ?>
            </div>
            
            <form method="POST">
                <textarea name="note" placeholder="Add a note..." required rows="4"></textarea>
                <button type="submit" name="add_note">Add Note</button>
            </form>
        </div>
        
        <!-- Activity Log -->
        <div class="activity-log">
            <h3>📊 Activity Log</h3>
            <?php if (count($logs) > 0): ?>
                <?php foreach ($logs as $log): ?>
                    <div class="log-entry">
                        <strong><?php echo htmlspecialchars($log['action']); ?></strong>
                        <span><?php echo date('d M Y, h:i A', strtotime($log['timestamp'])); ?></span>
                        <?php if ($log['user_name']): ?>
                            <p style="color:#7f8c8d; font-size:0.9rem;">By: <?php echo htmlspecialchars($log['user_name']); ?></p>
                        <?php endif; ?>
                        <?php if ($log['details']): ?>
                            <p><?php echo htmlspecialchars($log['details']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color:#7f8c8d;">No activity yet</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>