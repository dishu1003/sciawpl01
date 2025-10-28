<?php
/**
 * Bulk Actions - Admin Interface
 * Perform bulk operations on leads
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
    
    // Handle bulk actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['bulk_action']) && isset($_POST['selected_leads'])) {
            $bulk_action = $_POST['bulk_action'];
            $selected_leads = $_POST['selected_leads'];
            $lead_ids = array_filter($selected_leads, 'is_numeric');
            
            if (!empty($lead_ids)) {
                $placeholders = str_repeat('?,', count($lead_ids) - 1) . '?';
                
                switch ($bulk_action) {
                    case 'assign_team':
                        $assigned_to = $_POST['assigned_to'];
                        $stmt = $pdo->prepare("UPDATE leads SET assigned_to = ? WHERE id IN ($placeholders)");
                        $stmt->execute(array_merge([$assigned_to], $lead_ids));
                        $_SESSION['success_message'] = count($lead_ids) . " leads assigned successfully!";
                        break;
                        
                    case 'update_status':
                        $status = $_POST['status'];
                        $stmt = $pdo->prepare("UPDATE leads SET status = ? WHERE id IN ($placeholders)");
                        $stmt->execute(array_merge([$status], $lead_ids));
                        $_SESSION['success_message'] = count($lead_ids) . " leads status updated successfully!";
                        break;
                        
                    case 'update_score':
                        $lead_score = $_POST['lead_score'];
                        $stmt = $pdo->prepare("UPDATE leads SET lead_score = ? WHERE id IN ($placeholders)");
                        $stmt->execute(array_merge([$lead_score], $lead_ids));
                        $_SESSION['success_message'] = count($lead_ids) . " leads score updated successfully!";
                        break;
                        
                    case 'assign_category':
                        $category_id = $_POST['category_id'];
                        if ($category_id) {
                            // Remove existing assignments first
                            $stmt = $pdo->prepare("DELETE FROM lead_category_assignments WHERE lead_id IN ($placeholders)");
                            $stmt->execute($lead_ids);
                            
                            // Add new assignments
                            foreach ($lead_ids as $lead_id) {
                                $stmt = $pdo->prepare("INSERT INTO lead_category_assignments (lead_id, category_id) VALUES (?, ?)");
                                $stmt->execute([$lead_id, $category_id]);
                            }
                            $_SESSION['success_message'] = count($lead_ids) . " leads categorized successfully!";
                        }
                        break;
                        
                    case 'set_follow_up':
                        $follow_up_date = $_POST['follow_up_date'];
                        $follow_up_notes = $_POST['follow_up_notes'];
                        $stmt = $pdo->prepare("UPDATE leads SET follow_up_date = ?, notes = CONCAT(IFNULL(notes, ''), '\nBulk follow-up: ', ?) WHERE id IN ($placeholders)");
                        $stmt->execute(array_merge([$follow_up_date, $follow_up_notes], $lead_ids));
                        $_SESSION['success_message'] = count($lead_ids) . " leads follow-up set successfully!";
                        break;
                        
                    case 'delete_leads':
                        // First delete category assignments
                        $stmt = $pdo->prepare("DELETE FROM lead_category_assignments WHERE lead_id IN ($placeholders)");
                        $stmt->execute($lead_ids);
                        
                        // Then delete leads
                        $stmt = $pdo->prepare("DELETE FROM leads WHERE id IN ($placeholders)");
                        $stmt->execute($lead_ids);
                        $_SESSION['success_message'] = count($lead_ids) . " leads deleted successfully!";
                        break;
                }
            }
        }
    }
    
    // Get all leads
    $leads_stmt = $pdo->query("
        SELECT l.*, u.full_name as assigned_to_name,
               GROUP_CONCAT(lc.name) as category_names
        FROM leads l
        LEFT JOIN users u ON l.assigned_to = u.id
        LEFT JOIN lead_category_assignments lca ON l.id = lca.lead_id
        LEFT JOIN lead_categories lc ON lca.category_id = lc.id
        GROUP BY l.id
        ORDER BY l.created_at DESC
    ");
    $leads = $leads_stmt->fetchAll();
    
    // Get team members
    $team_stmt = $pdo->query("SELECT id, full_name, username FROM users WHERE role = 'team' AND status = 'active' ORDER BY full_name");
    $team_members = $team_stmt->fetchAll();
    
    // Get categories
    $categories_stmt = $pdo->query("SELECT id, name FROM lead_categories ORDER BY name");
    $categories = $categories_stmt->fetchAll();
    
    // Statistics
    $stats = [];
    $stats['total_leads'] = count($leads);
    $stats['active_leads'] = count(array_filter($leads, function($lead) { return $lead['status'] === 'active'; }));
    $stats['hot_leads'] = count(array_filter($leads, function($lead) { return $lead['lead_score'] === 'HOT'; }));
    $stats['unassigned_leads'] = count(array_filter($leads, function($lead) { return !$lead['assigned_to']; }));
    
    Logger::info('Bulk actions accessed', [
        'user_id' => $_SESSION['user_id']
    ]);

} catch (PDOException $e) {
    Logger::error('Database error in bulk actions', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id']
    ]);
    $leads = $team_members = $categories = [];
    $stats = ['total_leads' => 0, 'active_leads' => 0, 'hot_leads' => 0, 'unassigned_leads' => 0];
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔄 Bulk Actions - Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2/?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
        
        .stat-card.active::before { background: linear-gradient(90deg, #00d2d3, #54a0ff); }
        .stat-card.hot::before { background: linear-gradient(90deg, #ff6b6b, #ee5a24); }
        .stat-card.unassigned::before { background: linear-gradient(90deg, #feca57, #ff9ff3); }
        
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
        .stat-icon.active { background: linear-gradient(135deg, #00d2d3, #54a0ff); }
        .stat-icon.hot { background: linear-gradient(135deg, #ff6b6b, #ee5a24); }
        .stat-icon.unassigned { background: linear-gradient(135deg, #feca57, #ff9ff3); }
        
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
        
        .bulk-actions-bar {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .bulk-actions-bar select,
        .bulk-actions-bar input {
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .bulk-actions-bar select:focus,
        .bulk-actions-bar input:focus {
            outline: none;
            border-color: #667eea;
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
            font-size: 14px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #00d2d3, #54a0ff);
            color: white;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #feca57, #ff9ff3);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
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
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .selection-info {
            background: #e3f2fd;
            color: #1976d2;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f3f4;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 700;
            color: #333;
        }
        
        .close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #999;
        }
        
        .close:hover {
            color: #333;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .bulk-actions-bar {
                flex-direction: column;
                align-items: stretch;
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
        <h1>🔄 Bulk Actions</h1>
        <div class="nav-links">
            <a href="/admin/index.php">Dashboard</a>
            <a href="/admin/leads.php">Leads</a>
            <a href="/admin/team.php">Team</a>
            <a href="/logout.php">Logout</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <!-- Success Message -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                ✅ <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_leads']; ?></div>
                <div class="stat-label">Total Leads</div>
            </div>

            <div class="stat-card active">
                <div class="stat-icon active">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['active_leads']; ?></div>
                <div class="stat-label">Active Leads</div>
            </div>

            <div class="stat-card hot">
                <div class="stat-icon hot">
                    <i class="fas fa-fire"></i>
                </div>
                <div class="stat-value"><?php echo $stats['hot_leads']; ?></div>
                <div class="stat-label">Hot Leads</div>
            </div>

            <div class="stat-card unassigned">
                <div class="stat-icon unassigned">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stat-value"><?php echo $stats['unassigned_leads']; ?></div>
                <div class="stat-label">Unassigned</div>
            </div>
        </div>

        <!-- Bulk Actions Section -->
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-tasks"></i>
                Bulk Actions
            </h2>
            
            <div id="selectionInfo" class="selection-info" style="display: none;">
                <i class="fas fa-info-circle"></i>
                <span id="selectedCount">0</span> leads selected
            </div>
            
            <div class="bulk-actions-bar">
                <select id="bulkAction" onchange="showBulkOptions()">
                    <option value="">Select Action</option>
                    <option value="assign_team">Assign to Team Member</option>
                    <option value="update_status">Update Status</option>
                    <option value="update_score">Update Lead Score</option>
                    <option value="assign_category">Assign Category</option>
                    <option value="set_follow_up">Set Follow-up Date</option>
                    <option value="delete_leads">Delete Selected</option>
                </select>
                
                <div id="bulkOptions" style="display: none;">
                    <!-- Dynamic options will be inserted here -->
                </div>
                
                <button onclick="executeBulkAction()" class="btn btn-primary" id="executeBtn" disabled>
                    <i class="fas fa-play"></i> Execute
                </button>
                
                <button onclick="selectAllLeads()" class="btn btn-warning btn-small">
                    <i class="fas fa-check-square"></i> Select All
                </button>
                
                <button onclick="clearSelection()" class="btn btn-danger btn-small">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 50px;">
                            <input type="checkbox" id="selectAll" onchange="toggleAllLeads()">
                        </th>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Assigned To</th>
                        <th>Score</th>
                        <th>Status</th>
                        <th>Categories</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leads as $lead): ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="lead-checkbox" value="<?php echo $lead['id']; ?>" onchange="updateSelection()">
                        </td>
                        <td><strong><?php echo htmlspecialchars($lead['name']); ?></strong></td>
                        <td>
                            <div><?php echo htmlspecialchars($lead['email']); ?></div>
                            <?php if ($lead['phone']): ?>
                                <div><small><?php echo htmlspecialchars($lead['phone']); ?></small></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($lead['assigned_to_name'] ?: 'Unassigned'); ?></td>
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
                        <td>
                            <?php if ($lead['category_names']): ?>
                                <?php echo htmlspecialchars($lead['category_names']); ?>
                            <?php else: ?>
                                <span style="color: #999;">No categories</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d M Y', strtotime($lead['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Action</h3>
                <span class="close" onclick="closeConfirmModal()">&times;</span>
            </div>
            
            <div style="margin-bottom: 20px;">
                <p id="confirmMessage"></p>
            </div>
            
            <div style="display: flex; gap: 15px; justify-content: flex-end;">
                <button onclick="closeConfirmModal()" class="btn" style="background: #6c757d; color: white;">
                    Cancel
                </button>
                <button onclick="confirmBulkAction()" class="btn btn-danger" id="confirmBtn">
                    <i class="fas fa-check"></i> Confirm
                </button>
            </div>
        </div>
    </div>

    <script>
        let selectedLeads = [];
        let currentBulkAction = '';
        let bulkFormData = {};

        function updateSelection() {
            const checkboxes = document.querySelectorAll('.lead-checkbox:checked');
            selectedLeads = Array.from(checkboxes).map(cb => cb.value);
            
            document.getElementById('selectedCount').textContent = selectedLeads.length;
            document.getElementById('selectionInfo').style.display = selectedLeads.length > 0 ? 'block' : 'none';
            document.getElementById('executeBtn').disabled = selectedLeads.length === 0;
            
            // Update select all checkbox
            const allCheckboxes = document.querySelectorAll('.lead-checkbox');
            document.getElementById('selectAll').checked = allCheckboxes.length === selectedLeads.length;
            document.getElementById('selectAll').indeterminate = selectedLeads.length > 0 && selectedLeads.length < allCheckboxes.length;
        }

        function toggleAllLeads() {
            const selectAll = document.getElementById('selectAll').checked;
            const checkboxes = document.querySelectorAll('.lead-checkbox');
            
            checkboxes.forEach(cb => cb.checked = selectAll);
            updateSelection();
        }

        function selectAllLeads() {
            document.getElementById('selectAll').checked = true;
            toggleAllLeads();
        }

        function clearSelection() {
            document.getElementById('selectAll').checked = false;
            document.querySelectorAll('.lead-checkbox').forEach(cb => cb.checked = false);
            updateSelection();
        }

        function showBulkOptions() {
            const action = document.getElementById('bulkAction').value;
            const optionsDiv = document.getElementById('bulkOptions');
            
            if (!action) {
                optionsDiv.style.display = 'none';
                return;
            }
            
            currentBulkAction = action;
            let optionsHTML = '';
            
            switch (action) {
                case 'assign_team':
                    optionsHTML = `
                        <select name="assigned_to" required>
                            <option value="">Select Team Member</option>
                            <?php foreach ($team_members as $member): ?>
                                <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['full_name'] ?: $member['username']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    `;
                    break;
                    
                case 'update_status':
                    optionsHTML = `
                        <select name="status" required>
                            <option value="">Select Status</option>
                            <option value="active">Active</option>
                            <option value="converted">Converted</option>
                            <option value="lost">Lost</option>
                            <option value="follow_up">Follow-up</option>
                        </select>
                    `;
                    break;
                    
                case 'update_score':
                    optionsHTML = `
                        <select name="lead_score" required>
                            <option value="">Select Score</option>
                            <option value="HOT">HOT</option>
                            <option value="WARM">WARM</option>
                            <option value="COLD">COLD</option>
                        </select>
                    `;
                    break;
                    
                case 'assign_category':
                    optionsHTML = `
                        <select name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    `;
                    break;
                    
                case 'set_follow_up':
                    optionsHTML = `
                        <input type="date" name="follow_up_date" required>
                        <input type="text" name="follow_up_notes" placeholder="Follow-up notes..." style="width: 200px;">
                    `;
                    break;
            }
            
            optionsDiv.innerHTML = optionsHTML;
            optionsDiv.style.display = 'flex';
            optionsDiv.style.gap = '10px';
            optionsDiv.style.alignItems = 'center';
        }

        function executeBulkAction() {
            if (selectedLeads.length === 0) {
                alert('Please select at least one lead.');
                return;
            }
            
            const action = document.getElementById('bulkAction').value;
            if (!action) {
                alert('Please select an action.');
                return;
            }
            
            // Collect form data
            const formData = new FormData();
            formData.append('bulk_action', action);
            selectedLeads.forEach(id => formData.append('selected_leads[]', id));
            
            // Add action-specific data
            const optionsDiv = document.getElementById('bulkOptions');
            const inputs = optionsDiv.querySelectorAll('select, input');
            inputs.forEach(input => {
                if (input.name && input.value) {
                    formData.append(input.name, input.value);
                }
            });
            
            // Show confirmation for destructive actions
            if (action === 'delete_leads') {
                showConfirmModal(
                    `Are you sure you want to delete ${selectedLeads.length} leads? This action cannot be undone.`,
                    'Delete Leads',
                    () => submitBulkAction(formData)
                );
            } else {
                submitBulkAction(formData);
            }
        }

        function showConfirmModal(message, title, callback) {
            document.getElementById('confirmMessage').textContent = message;
            document.getElementById('modal-title').textContent = title;
            document.getElementById('confirmBtn').onclick = callback;
            document.getElementById('confirmModal').style.display = 'block';
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').style.display = 'none';
        }

        function confirmBulkAction() {
            closeConfirmModal();
            submitBulkAction(bulkFormData);
        }

        function submitBulkAction(formData) {
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    location.reload();
                } else {
                    alert('Error executing bulk action. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error executing bulk action. Please try again.');
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('confirmModal');
            if (event.target === modal) {
                closeConfirmModal();
            }
        }
    </script>
</body>
</html>
