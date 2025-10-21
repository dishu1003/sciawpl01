<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_login();

$user = get_current_user();
$pdo = get_pdo_connection();
$lead_id = $_GET['id'] ?? 0;

// Fetch lead (ensure it's assigned to this user)
$stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ? AND assigned_to = ?");
$stmt->execute([$lead_id, $user['id']]);
$lead = $stmt->fetch();

if (!$lead) {
    die('Lead not found or access denied');
}

// Fetch activity logs
$stmt = $pdo->prepare("SELECT * FROM logs WHERE lead_id = ? ORDER BY timestamp DESC");
$stmt->execute([$lead_id]);
$logs = $stmt->fetchAll();

// Handle quick actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        update_lead_status($lead_id, $_POST['status']);
        header('Location: /team/lead-detail.php?id=' . $lead_id);
        exit;
    }
    
    if (isset($_POST['add_note'])) {
        add_lead_note($lead_id, $_POST['note']);
        header('Location: /team/lead-detail.php?id=' . $lead_id);
        exit;
    }
    
    if (isset($_POST['update_score'])) {
        update_lead_score($lead_id, $_POST['score']);
        header('Location: /team/lead-detail.php?id=' . $lead_id);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lead Details - <?php echo htmlspecialchars($lead['name']); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <nav class="dashboard-nav">
        <h1><?php echo SITE_NAME; ?></h1>
        <a href="/team/">← Back to Dashboard</a>
    </nav>
    
    <div class="lead-detail">
        <div class="lead-header">
            <h2><?php echo htmlspecialchars($lead['name']); ?></h2>
            <span class="badge badge-<?php echo strtolower($lead['lead_score']); ?>"><?php echo $lead['lead_score']; ?></span>
        </div>
        
        <div class="lead-info">
            <p><strong>Email:</strong> <?php echo htmlspecialchars($lead['email']); ?></p>
            <p><strong>Phone:</strong> <a href="tel:<?php echo $lead['phone']; ?>"><?php echo htmlspecialchars($lead['phone']); ?></a></p>
            <p><strong>Current Step:</strong> <?php echo $lead['current_step']; ?>/4</p>
            <p><strong>Status:</strong> <?php echo ucfirst($lead['status']); ?></p>
            <p><strong>Created:</strong> <?php echo date('d M Y, h:i A', strtotime($lead['created_at'])); ?></p>
        </div>
        
        <div class="quick-actions">
            <h3>Quick Actions</h3>
            
            <form method="POST" style="display:inline;">
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
            
            <form method="POST" style="display:inline;">
                <select name="score" required>
                    <option value="">Update Score...</option>
                    <option value="HOT">HOT</option>
                    <option value="WARM">WARM</option>
                    <option value="COLD">COLD</option>
                </select>
                <button type="submit" name="update_score">Update</button>
            </form>
        </div>
        
        <div class="form-responses">
            <h3>Form Responses</h3>
            
            <?php if ($lead['form_a_data']): ?>
                <div class="form-data">
                    <h4>Form A (Contact Info)</h4>
                    <pre><?php echo json_encode(json_decode($lead['form_a_data']), JSON_PRETTY_PRINT); ?></pre>
                </div>
            <?php endif; ?>
            
            <?php if ($lead['form_b_data']): ?>
                <div class="form-data">
                    <h4>Form B (Engagement)</h4>
                    <pre><?php echo json_encode(json_decode($lead['form_b_data']), JSON_PRETTY_PRINT); ?></pre>
                </div>
            <?php endif; ?>
            
            <?php if ($lead['form_c_data']): ?>
                <div class="form-data">
                    <h4>Form C (Qualification)</h4>
                    <pre><?php echo json_encode(json_decode($lead['form_c_data']), JSON_PRETTY_PRINT); ?></pre>
                </div>
            <?php endif; ?>
            
            <?php if ($lead['form_d_data']): ?>
                <div class="form-data">
                    <h4>Form D (Call Booking)</h4>
                    <pre><?php echo json_encode(json_decode($lead['form_d_data']), JSON_PRETTY_PRINT); ?></pre>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="notes-section">
            <h3>Notes</h3>
            <pre><?php echo htmlspecialchars($lead['notes'] ?? 'No notes yet'); ?></pre>
            
            <form method="POST">
                <textarea name="note" placeholder="Add a note..." required></textarea>
                <button type="submit" name="add_note">Add Note</button>
            </form>
        </div>
        
        <div class="activity-log">
            <h3>Activity Log</h3>
            <?php foreach ($logs as $log): ?>
                <div class="log-entry">
                    <strong><?php echo $log['action']; ?></strong>
                    <span><?php echo date('d M Y, h:i A', strtotime($log['timestamp'])); ?></span>
                    <?php if ($log['details']): ?>
                        <p><?php echo htmlspecialchars($log['details']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>