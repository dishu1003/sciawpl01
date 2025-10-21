<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_admin();

$pdo = get_pdo_connection();

// Handle script actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_script'])) {
        $type = $_POST['type'];
        $title = $_POST['title'];
        $content = $_POST['content'];
        $visibility = $_POST['visibility'];
        
        $stmt = $pdo->prepare("INSERT INTO scripts (type, title, content, created_by, visibility) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$type, $title, $content, $_SESSION['user_id'], $visibility]);
        $success = "Script added successfully!";
    }
    
    if (isset($_POST['update_script'])) {
        $script_id = $_POST['script_id'];
        $title = $_POST['title'];
        $content = $_POST['content'];
        $visibility = $_POST['visibility'];
        
        $stmt = $pdo->prepare("UPDATE scripts SET title = ?, content = ?, visibility = ? WHERE id = ?");
        $stmt->execute([$title, $content, $visibility, $script_id]);
        $success = "Script updated successfully!";
    }
    
    if (isset($_POST['delete_script'])) {
        $script_id = $_POST['script_id'];
        $stmt = $pdo->prepare("DELETE FROM scripts WHERE id = ?");
        $stmt->execute([$script_id]);
        $success = "Script deleted successfully!";
    }
}

// Fetch all scripts
$stmt = $pdo->query("SELECT * FROM scripts ORDER BY type, title");
$scripts = $stmt->fetchAll();

$grouped_scripts = [];
foreach ($scripts as $script) {
    $grouped_scripts[$script['type']][] = $script;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Scripts Management</title>
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
            margin: 3% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 700px;
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
        <h1><?php echo SITE_NAME; ?> - Scripts Management</h1>
        <a href="/admin/">← Back to Dashboard</a>
    </nav>
    
    <div class="main-content">
        <?php if (isset($success)): ?>
            <div style="background:#27ae60; color:white; padding:15px; border-radius:8px; margin-bottom:20px;">
                ✅ <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
            <h2>Scripts Library</h2>
            <button onclick="openModal('addModal')" class="cta-btn" style="padding:10px 20px; font-size:1rem;">
                ➕ Add New Script
            </button>
        </div>
        
        <?php foreach ($grouped_scripts as $type => $scripts_list): ?>
            <div class="script-category">
                <h3><?php echo ucfirst($type); ?> Scripts (<?php echo count($scripts_list); ?>)</h3>
                
                <?php foreach ($scripts_list as $script): ?>
                    <div class="script-card">
                        <div style="display:flex; justify-content:space-between; align-items:start;">
                            <div style="flex:1;">
                                <h4><?php echo htmlspecialchars($script['title']); ?></h4>
                                <p style="white-space:pre-wrap; margin:15px 0;"><?php echo nl2br(htmlspecialchars($script['content'])); ?></p>
                                <p style="color:#7f8c8d; font-size:0.9rem;">
                                    Visibility: <strong><?php echo ucfirst(str_replace('_', ' ', $script['visibility'])); ?></strong> | 
                                    Created: <?php echo date('d M Y', strtotime($script['created_at'])); ?>
                                </p>
                            </div>
                            <div style="margin-left:20px;">
                                <button onclick="copyScript(this)" class="btn-small">📋 Copy</button>
                                <button onclick="openEditModal(<?php echo $script['id']; ?>, '<?php echo htmlspecialchars($script['title']); ?>', '<?php echo htmlspecialchars(addslashes($script['content'])); ?>', '<?php echo $script['visibility']; ?>')" 
                                        class="btn-small" style="background:#f39c12;">✏️ Edit</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this script?');">
                                    <input type="hidden" name="script_id" value="<?php echo $script['id']; ?>">
                                    <button type="submit" name="delete_script" class="btn-small" style="background:#e74c3c;">🗑️ Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Add Script Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addModal')">&times;</span>
            <h2>Add New Script</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Script Type *</label>
                    <select name="type" required>
                        <option value="followup">Follow-up</option>
                        <option value="sales">Sales</option>
                        <option value="closing">Closing</option>
                        <option value="objection">Objection Handling</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" required>
                </div>
                <div class="form-group">
                    <label>Content *</label>
                    <textarea name="content" required rows="8"></textarea>
                </div>
                <div class="form-group">
                    <label>Visibility *</label>
                    <select name="visibility" required>
                        <option value="all">All Team Members</option>
                        <option value="admin_only">Admin Only</option>
                    </select>
                </div>
                <button type="submit" name="add_script" class="submit-btn">Add Script</button>
            </form>
        </div>
    </div>
    
    <!-- Edit Script Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editModal')">&times;</span>
            <h2>Edit Script</h2>
            <form method="POST">
                <input type="hidden" name="script_id" id="edit_script_id">
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" id="edit_title" required>
                </div>
                <div class="form-group">
                    <label>Content *</label>
                    <textarea name="content" id="edit_content" required rows="8"></textarea>
                </div>
                <div class="form-group">
                    <label>Visibility *</label>
                    <select name="visibility" id="edit_visibility" required>
                        <option value="all">All Team Members</option>
                        <option value="admin_only">Admin Only</option>
                    </select>
                </div>
                <button type="submit" name="update_script" class="submit-btn">Update Script</button>
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
        
        function openEditModal(scriptId, title, content, visibility) {
            document.getElementById('edit_script_id').value = scriptId;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_content').value = content;
            document.getElementById('edit_visibility').value = visibility;
            openModal('editModal');
        }
        
        function copyScript(btn) {
            const text = btn.closest('.script-card').querySelector('p').textContent;
            navigator.clipboard.writeText(text).then(function() {
                btn.textContent = '✅ Copied!';
                setTimeout(() => btn.textContent = '📋 Copy', 2000);
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