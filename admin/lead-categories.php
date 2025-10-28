<?php
/**
 * Lead Categories - Admin Interface
 * Manage lead categories for better organization
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
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_category':
                    $name = trim($_POST['name']);
                    $description = trim($_POST['description']);
                    $color = $_POST['color'];
                    
                    if ($name) {
                        $stmt = $pdo->prepare("INSERT INTO lead_categories (name, description, color) VALUES (?, ?, ?)");
                        $stmt->execute([$name, $description, $color]);
                        $_SESSION['success_message'] = "Category created successfully!";
                    }
                    break;
                    
                case 'update_category':
                    $id = $_POST['category_id'];
                    $name = trim($_POST['name']);
                    $description = trim($_POST['description']);
                    $color = $_POST['color'];
                    
                    if ($name) {
                        $stmt = $pdo->prepare("UPDATE lead_categories SET name = ?, description = ?, color = ? WHERE id = ?");
                        $stmt->execute([$name, $description, $color, $id]);
                        $_SESSION['success_message'] = "Category updated successfully!";
                    }
                    break;
                    
                case 'delete_category':
                    $id = $_POST['category_id'];
                    $stmt = $pdo->prepare("/DELETE FROM lead_categories WHERE id = ?");
                    $stmt->execute([$id]);
                    $_SESSION['success_message'] = "Category deleted successfully!";
                    break;
                    
                case 'assign_category':
                    $lead_id = $_POST['lead_id'];
                    $category_id = $_POST['category_id'];
                    
                    // Remove existing assignments first
                    $stmt = $pdo->prepare("DELETE FROM lead_category_assignments WHERE lead_id = ?");
                    $stmt->execute([$lead_id]);
                    
                    // Add new assignment
                    if ($category_id) {
                        $stmt = $pdo->prepare("INSERT INTO lead_category_assignments (lead_id, category_id) VALUES (?, ?)");
                        $stmt->execute([$lead_id, $category_id]);
                    }
                    
                    $_SESSION['success_message'] = "Category assigned successfully!";
                    break;
            }
        }
    }
    
    // Get all categories
    $categories_stmt = $pdo->query("SELECT * FROM lead_categories ORDER BY name");
    $categories = $categories_stmt->fetchAll();
    
    // Get leads with their categories
    $leads_stmt = $pdo->query("
        SELECT l.*, u.full_name as assigned_to_name,
               GROUP_CONCAT(lc.name) as category_names,
               GROUP_CONCAT(lc.color) as category_colors
        FROM leads l
        LEFT JOIN users u ON l.assigned_to = u.id
        LEFT JOIN lead_category_assignments lca ON l.id = lca.lead_id
        LEFT JOIN lead_categories lc ON lca.category_id = lc.id
        GROUP BY l.id
        ORDER BY l.created_at DESC
        LIMIT 100
    ");
    $leads = $leads_stmt->fetchAll();
    
    // Category statistics
    $category_stats = [];
    foreach ($categories as $category) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lead_category_assignments WHERE category_id = ?");
        $stmt->execute([$category['id']]);
        $category_stats[$category['id']] = $stmt->fetchColumn();
    }
    
    Logger::info('Lead categories accessed', [
        'user_id' => $_SESSION['user_id']
    ]);

} catch (PDOException $e) {
    Logger::error('Database error in lead categories', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id']
    ]);
    $categories = $leads = [];
    $category_stats = [];
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📋 Lead Categories - Admin Dashboard</title>
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
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
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
        
        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .category-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            border-left: 4px solid #667eea;
            transition: transform 0.3s ease;
        }
        
        .category-card:hover {
            transform: translateY(-3px);
        }
        
        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .category-name {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .category-color {
            width: 20px;
            height: 20px;
            border-radius: 50%;
        }
        
        .category-count {
            background: #e9ecef;
            color: #666;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .category-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .category-actions {
            display: flex;
            gap: 10px;
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
        
        .category-tags {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .category-tag {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            color: white;
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
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            font-size: 16px;
        }
        
        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 多px;
            }
            
            .category-grid {
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
        <h1>📋 Lead Categories</h1>
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

        <div class="content-grid">
            <!-- Categories Management -->
            <div class="section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                    <h2 class="section-title">
                        <i class="fas fa-tags"></i>
                        Categories
                    </h2>
                    <button onclick="openCategoryModal()" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Category
                    </button>
                </div>
                
                <?php if (empty($categories)): ?>
                    <div class="empty-state">
                        <i class="fas fa-tags"></i>
                        <h3>No Categories Yet</h3>
                        <p>Create categories to better organize your leads</p>
                    </div>
                <?php else: ?>
                    <div class="category-grid">
                        <?php foreach ($categories as $category): ?>
                        <div class="category-card" style="border-left-color: <?php echo $category['color']; ?>">
                            <div class="category-header">
                                <div class="category-name">
                                    <div class="category-color" style="background-color: <?php echo $category['color']; ?>"></div>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </div>
                                <div class="category-count"><?php echo $category_stats[$category['id']] ?? 0; ?> leads</div>
                            </div>
                            <div class="category-description"><?php echo htmlspecialchars($category['description']); ?></div>
                            <div class="category-actions">
                                <button onclick="openEditCategoryModal(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($category['description'], ENT_QUOTES); ?>', '<?php echo $category['color']; ?>')" class="btn btn-success btn-small">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button onclick="deleteCategory(<?php echo $category['id']; ?>)" class="btn btn-danger btn-small">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Leads with Categories -->
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-users"></i>
                    Leads with Categories
                </h2>
                
                <?php if (empty($leads)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>No Leads Yet</h3>
                        <p>Leads will appear here once they are created</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Categories</th>
                                <th>Score</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leads as $lead): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($lead['name']); ?></strong></td>
                                <td>
                                    <div><?php echo htmlspecialchars($lead['email']); ?></div>
                                    <?php if ($lead['phone']): ?>
                                        <div><small><?php echo htmlspecialchars($lead['phone']); ?></small></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="category-tags">
                                        <?php if ($lead['category_names']): ?>
                                            <?php 
                                            $categoryNames = explode(',', $lead['category_names']);
                                            $categoryColors = explode(',', $lead['category_colors']);
                                            foreach ($categoryNames as $index => $categoryName): ?>
                                                <span class="category-tag" style="background-color: <?php echo $categoryColors[$index] ?? '#667eea'; ?>">
                                                    <?php echo htmlspecialchars(trim($categoryName)); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span style="color: #999; font-size: 12px;">No categories</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
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
                                    <button onclick="openAssignCategoryModal(<?php echo $lead['id']; ?>, '<?php echo htmlspecialchars($lead['name'], ENT_QUOTES); ?>')" class="btn btn-primary btn-small">
                                        <i class="fas fa-tag"></i> Assign
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Category Modal -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="categoryModalTitle">Create New Category</h3>
                <span class="close" onclick="closeCategoryModal()">&times;</span>
            </div>
            
            <form method="POST" id="categoryForm">
                <input type="hidden" name="action" value="create_category" id="categoryAction">
                <input type="hidden" name="category_id" id="categoryId">
                
                <div class="form-group">
                    <label>Category Name</label>
                    <input type="text" name="name" id="categoryName" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="categoryDescription" class="form-control" rows="3" placeholder="Describe this category..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Color</label>
                    <input type="color" name="color" id="categoryColor" class="form-control" value="#667eea" style="height: 50px;">
                </div>
                
                <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="closeCategoryModal()" class="btn" style="background: #6c757d; color: white;">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assign Category Modal -->
    <div id="assignCategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Assign Category</h3>
                <span class="close" onclick="closeAssignCategoryModal()">&times;</span>
            </div>
            
            <form method="POST" id="assignCategoryForm">
                <input type="hidden" name="action" value="assign_category">
                <input type="hidden" name="lead_id" id="assignLeadId">
                
                <div class="form-group">
                    <label>Lead Name</label>
                    <input type="text" id="assignLeadName" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label>Select Category</label>
                    <select name="category_id" id="assignCategorySelect" class="form-control">
                        <option value="">No Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" style="background-color: <?php echo $category['color']; ?>; color: white;">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="closeAssignCategoryModal()" class="btn" style="background: #6c757d; color: white;">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-tag"></i> Assign Category
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Category Modal functions
        function openCategoryModal() {
            document.getElementById('categoryModalTitle').textContent = 'Create New Category';
            document.getElementById('categoryAction').value = 'create_category';
            document.getElementById('categoryForm').reset();
            document.getElementById('categoryId').value = '';
            document.getElementById('categoryModal').style.display = 'block';
        }

        function openEditCategoryModal(id, name, description, color) {
            document.getElementById('categoryModalTitle').textContent = 'Edit Category';
            document.getElementById('categoryAction').value = 'update_category';
            document.getElementById('categoryId').value = id;
            document.getElementById('categoryName').value = name;
            document.getElementById('categoryDescription').value = description;
            document.getElementById('categoryColor').value = color;
            document.getElementById('categoryModal').style.display = 'block';
        }

        function closeCategoryModal() {
            document.getElementById('categoryModal').style.display = 'none';
        }

        // Assign Category Modal functions
        function openAssignCategoryModal(leadId, leadName) {
            document.getElementById('assignLeadId').value = leadId;
            document.getElementById('assignLeadName').value = leadName;
            document.getElementById('assignCategoryModal').style.display = 'block';
        }

        function closeAssignCategoryModal() {
            document.getElementById('assignCategoryModal').style.display = 'none';
        }

        // Delete category
        function deleteCategory(categoryId) {
            if (confirm('Are you sure you want to delete this category?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_category">
                    <input type="hidden" name="category_id" value="${categoryId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const categoryModal = document.getElementById('categoryModal');
            const assignModal = document.getElementById('assignCategoryModal');
            
            if (event.target === categoryModal) {
                closeCategoryModal();
            }
            if (event.target === assignModal) {
                closeAssignCategoryModal();
            }
        }
    </script>
</body>
</html>
