<?php
/**
 * Landing Pages Management - Admin Interface
 * Manage 5 different landing pages for different audiences
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
    
    // Create landing pages table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS landing_pages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page_name VARCHAR(100) NOT NULL,
            page_slug VARCHAR(100) NOT NULL UNIQUE,
            page_title VARCHAR(200) NOT NULL,
            page_description TEXT,
            target_audience VARCHAR(100) NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            unlock_requirements TEXT,
            page_content LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Create team landing access table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS team_landing_access (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            landing_page_id INT NOT NULL,
            unlocked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (landing_page_id) REFERENCES landing_pages(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_landing (user_id, landing_page_id)
        )
    ");
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_landing_page':
                    $page_name = trim($_POST['page_name']);
                    $page_slug = trim($_POST['page_slug']);
                    $page_title = trim($_POST['page_title']);
                    $page_description = trim($_POST['page_description']);
                    $target_audience = $_POST['target_audience'];
                    $unlock_requirements = json_encode($_POST['unlock_requirements']);
                    $page_content = $_POST['page_content'];
                    
                    if ($page_name && $page_slug && $page_title && $target_audience) {
                        $stmt = $pdo->prepare("
                            INSERT INTO landing_pages (page_name, page_slug, page_title, page_description, target_audience, unlock_requirements, page_content)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$page_name, $page_slug, $page_title, $page_description, $target_audience, $unlock_requirements, $page_content]);
                        $_SESSION['success_message'] = "Landing page created successfully!";
                    }
                    break;
                    
                case 'update_landing_page':
                    $page_id = $_POST['page_id'];
                    $page_name = trim($_POST['page_name']);
                    $page_slug = trim($_POST['page_slug']);
                    $page_title = trim($_POST['page_title']);
                    $page_description = trim($_POST['page_description']);
                    $target_audience = $_POST['target_audience'];
                    $unlock_requirements = json_encode($_POST['unlock_requirements']);
                    $page_content = $_POST['page_content'];
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    $stmt = $pdo->prepare("
                        UPDATE landing_pages 
                        SET page_name = ?, page_slug = ?, page_title = ?, page_description = ?, target_audience = ?, unlock_requirements = ?, page_content = ?, is_active = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$page_name, $page_slug, $page_title, $page_description, $target_audience, $unlock_requirements, $page_content, $is_active, $page_id]);
                    $_SESSION['success_message'] = "Landing page updated successfully!";
                    break;
                    
                case 'delete_landing_page':
                    $page_id = $_POST['page_id'];
                    $stmt = $pdo->prepare("DELETE FROM landing_pages WHERE id = ?");
                    $stmt->execute([$page_id]);
                    $_SESSION['success_message'] = "Landing page deleted successfully!";
                    break;
                    
                case 'grant_access':
                    $user_id = $_POST['user_id'];
                    $landing_page_id = $_POST['landing_page_id'];
                    
                    $stmt = $pdo->prepare("
                        INSERT IGNORE INTO team_landing_access (user_id, landing_page_id)
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$user_id, $landing_page_id]);
                    $_SESSION['success_message'] = "Landing page access granted successfully!";
                    break;
            }
        }
    }
    
    // Get all landing pages
    $landing_pages_stmt = $pdo->query("
        SELECT lp.*, 
               COUNT(tla.user_id) as access_count,
               COUNT(CASE WHEN l.assigned_to IS NOT NULL THEN 1 END) as total_leads
        FROM landing_pages lp
        LEFT JOIN team_landing_access tla ON lp.id = tla.landing_page_id
        LEFT JOIN leads l ON lp.page_slug = SUBSTRING_INDEX(l.source, '_', 1)
        GROUP BY lp.id
        ORDER BY lp.created_at DESC
    ");
    $landing_pages = $landing_pages_stmt->fetchAll();
    
    // Get team members
    $team_stmt = $pdo->query("SELECT id, full_name, username FROM users WHERE role = 'team' AND status = 'active' ORDER BY full_name");
    $team_members = $team_stmt->fetchAll();
    
    // Get landing page statistics
    $stats = [];
    $stats['total_pages'] = count($landing_pages);
    $stats['active_pages'] = count(array_filter($landing_pages, function($p) { return $p['is_active']; }));
    $stats['total_leads'] = $pdo->query("SELECT COUNT(*) FROM leads WHERE source LIKE '%landing_%'")->fetchColumn();
    
    Logger::info('Landing pages accessed', [
        'user_id' => $_SESSION['user_id']
    ]);

} catch (PDOException $e) {
    Logger::error('Database error in landing pages', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id']
    ]);
    $landing_pages = $team_members = [];
    $stats = ['total_pages' => 0, 'active_pages' => 0, 'total_leads' => 0];
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎯 Landing Pages - Admin Dashboard</title>
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
        
        .stat-card.pages::before { background: linear-gradient(90deg, #00d2d3, #54a0ff); }
        .stat-card.active::before { background: linear-gradient(90deg, #feca57, #ff9ff3); }
        .stat-card.leads::before { background: linear-gradient(90deg, #ff6b6b, #ee5a24); }
        
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
        .stat-icon.pages { background: linear-gradient(135deg, #00d2d3, #54a0ff); }
        .stat-icon.active { background: linear-gradient(135deg, #feca57, #ff9ff3); }
        .stat-icon.leads { background: linear-gradient(135deg, #ff6b6b, #ee5a24); }
        
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
        
        .landing-pages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .landing-page-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            border-left: 4px solid #667eea;
            transition: transform 0.3s ease;
        }
        
        .landing-page-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .landing-page-card.students { border-left-color: #00d2d3; }
        .landing-page-card.housewives { border-left-color: #feca57; }
        .landing-page-card.retirees { border-left-color: #ff6b6b; }
        .landing-page-card.jobless { border-left-color: #54a0ff; }
        .landing-page-card.side-business { border-left-color: #5f27cd; }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .page-title {
            font-size: 18px;
            font-weight: 700;
            color: #333;
        }
        
        .page-status {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .page-status.active {
            background: #d4edda;
            color: #155724;
        }
        
        .page-status.inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .page-details {
            margin-bottom: 20px;
        }
        
        .page-detail {
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .page-detail strong {
            color: #555;
        }
        
        .page-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
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
            margin: 2% auto;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-height: 90vh;
            overflow-y: auto;
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
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .unlock-requirements {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .unlock-requirements h4 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .requirement-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .requirement-item input {
            flex: 1;
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .landing-pages-grid {
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
        <h1>🎯 Landing Pages</h1>
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
                    <i class="fas fa-globe"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_pages']; ?></div>
                <div class="stat-label">Total Pages</div>
            </div>

            <div class="stat-card active">
                <div class="stat-icon active">
                    <i class="fas fa-play"></i>
                </div>
                <div class="stat-value"><?php echo $stats['active_pages']; ?></div>
                <div class="stat-label">Active Pages</div>
            </div>

            <div class="stat-card leads">
                <div class="stat-icon leads">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_leads']; ?></div>
                <div class="stat-label">Landing Page Leads</div>
            </div>
        </div>

        <!-- Landing Pages -->
        <div class="section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h2 class="section-title">
                    <i class="fas fa-rocket"></i>
                    Landing Pages
                </h2>
                <button onclick="openLandingPageModal()" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Landing Page
                </button>
            </div>
            
            <?php if (empty($landing_pages)): ?>
                <p style="text-align: center; color: #666; padding: 40px;">No landing pages created yet</p>
            <?php else: ?>
                <div class="landing-pages-grid">
                    <?php foreach ($landing_pages as $page): ?>
                    <div class="landing-page-card <?php echo strtolower(str_replace(' ', '', $page['target_audience'])); ?>">
                        <div class="page-header">
                            <div class="page-title"><?php echo htmlspecialchars($page['page_name']); ?></div>
                            <div class="page-status <?php echo $page['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $page['is_active'] ? 'Active' : 'Inactive'; ?>
                            </div>
                        </div>
                        
                        <div class="page-details">
                            <div class="page-detail">
                                <strong>Audience:</strong> <?php echo htmlspecialchars($page['target_audience']); ?>
                            </div>
                            <div class="page-detail">
                                <strong>URL:</strong> /<?php echo htmlspecialchars($page['page_slug']); ?>
                            </div>
                            <div class="page-detail">
                                <strong>Access Count:</strong> <?php echo $page['access_count']; ?> team members
                            </div>
                            <div class="page-detail">
                                <strong>Total Leads:</strong> <?php echo $page['total_leads']; ?>
                            </div>
                            <?php if ($page['page_description']): ?>
                            <div class="page-detail">
                                <strong>Description:</strong> <?php echo htmlspecialchars(substr($page['page_description'], 0, 100)); ?>...
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="page-actions">
                            <a href="/<?php echo $page['page_slug']; ?>" target="_blank" class="btn btn-success btn-small">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <button onclick="editLandingPage(<?php echo $page['id']; ?>)" class="btn btn-primary btn-small">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button onclick="manageAccess(<?php echo $page['id']; ?>)" class="btn btn-warning btn-small">
                                <i class="fas fa-users"></i> Access
                            </button>
                            <button onclick="deleteLandingPage(<?php echo $page['id']; ?>)" class="btn btn-danger btn-small">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Landing Page Modal -->
    <div id="landingPageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="landingPageModalTitle">Create Landing Page</h3>
                <span class="close" onclick="closeLandingPageModal()">&times;</span>
            </div>
            
            <form method="POST" id="landingPageForm">
                <input type="hidden" name="action" value="create_landing_page" id="landingPageAction">
                <input type="hidden" name="page_id" id="pageId">
                
                <div class="form-group">
                    <label>Page Name</label>
                    <input type="text" name="page_name" id="pageName" class="form-control" required placeholder="e.g., Student Success">
                </div>
                
                <div class="form-group">
                    <label>Page Slug (URL)</label>
                    <input type="text" name="page_slug" id="pageSlug" class="form-control" required placeholder="e.g., student-success">
                </div>
                
                <div class="form-group">
                    <label>Page Title</label>
                    <input type="text" name="page_title" id="pageTitle" class="form-control" required placeholder="e.g., Build Your Future with Direct Selling">
                </div>
                
                <div class="form-group">
                    <label>Target Audience</label>
                    <select name="target_audience" id="targetAudience" class="form-control" required>
                        <option value="">Select Audience</option>
                        <option value="Students">Students</option>
                        <option value="Housewives">Housewives</option>
                        <option value="Retirees">Retirees</option>
                        <option value="Jobless">Jobless</option>
                        <option value="Side Business">Side Business</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Page Description</label>
                    <textarea name="page_description" id="pageDescription" class="form-control" rows="3" placeholder="Brief description of the landing page..."></textarea>
                </div>
                
                <div class="unlock-requirements">
                    <h4>Unlock Requirements</h4>
                    <div class="requirement-item">
                        <input type="number" name="unlock_requirements[leads_required]" class="form-control" placeholder="Leads Required" value="50">
                        <span>Leads Required</span>
                    </div>
                    <div class="requirement-item">
                        <input type="number" name="unlock_requirements[active_members_required]" class="form-control" placeholder="Active Members Required" value="5">
                        <span>Active Members Required</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Page Content (HTML)</label>
                    <textarea name="page_content" id="pageContent" class="form-control" rows="10" placeholder="HTML content for the landing page..."></textarea>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="is_active" id="isActive" checked>
                    <label for="isActive">Active Page</label>
                </div>
                
                <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="closeLandingPageModal()" class="btn" style="background: #6c757d; color: white;">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Landing Page
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Access Management Modal -->
    <div id="accessModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Manage Landing Page Access</h3>
                <span class="close" onclick="closeAccessModal()">&times;</span>
            </div>
            
            <form method="POST" id="accessForm">
                <input type="hidden" name="action" value="grant_access">
                <input type="hidden" name="landing_page_id" id="accessLandingPageId">
                
                <div class="form-group">
                    <label>Grant Access to Team Member</label>
                    <select name="user_id" class="form-control" required>
                        <option value="">Select Team Member</option>
                        <?php foreach ($team_members as $member): ?>
                            <option value="<?php echo $member['id']; ?>">
                                <?php echo htmlspecialchars($member['full_name'] ?: $member['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="closeAccessModal()" class="btn" style="background: #6c757d; color: white;">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Grant Access
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openLandingPageModal() {
            document.getElementById('landingPageModalTitle').textContent = 'Create Landing Page';
            document.getElementById('landingPageAction').value = 'create_landing_page';
            document.getElementById('landingPageForm').reset();
            document.getElementById('pageId').value = '';
            document.getElementById('isActive').checked = true;
            document.getElementById('landingPageModal').style.display = 'block';
        }

        function closeLandingPageModal() {
            document.getElementById('landingPageModal').style.display = 'none';
        }

        function closeAccessModal() {
            document.getElementById('accessModal').style.display = 'none';
        }

        function editLandingPage(pageId) {
            // This would populate the form with existing page data
            document.getElementById('landingPageModalTitle').textContent = 'Edit Landing Page';
            document.getElementById('landingPageAction').value = 'update_landing_page';
            document.getElementById('pageId').value = pageId;
            document.getElementById('landingPageModal').style.display = 'block';
        }

        function manageAccess(landingPageId) {
            document.getElementById('accessLandingPageId').value = landingPageId;
            document.getElementById('accessModal').style.display = 'block';
        }

        function deleteLandingPage(pageId) {
            if (confirm('Are you sure you want to delete this landing page?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_landing_page">
                    <input type="hidden" name="page_id" value="${pageId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Auto-generate slug from page name
        document.getElementById('pageName').addEventListener('input', function() {
            const slug = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
            document.getElementById('pageSlug').value = slug;
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const landingModal = document.getElementById('landingPageModal');
            const accessModal = document.getElementById('accessModal');
            
            if (event.target === landingModal) {
                closeLandingPageModal();
            }
            if (event.target === accessModal) {
                closeAccessModal();
            }
        }
    </script>
</body>
</html>
