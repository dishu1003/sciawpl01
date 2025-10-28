<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = get_pdo_connection();

// Handle Add / Delete / Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_material'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $type = $_POST['type'];
        $url = trim($_POST['url']);

        $stmt = $pdo->prepare("INSERT INTO training_materials (title, description, type, url, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$title, $description, $type, $url]);

    } elseif (isset($_POST['delete_material'])) {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM training_materials WHERE id = ?");
        $stmt->execute([$id]);

    } elseif (isset($_POST['edit_material'])) {
        $id = (int)$_POST['id'];
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $type = $_POST['type'];
        $url = trim($_POST['url']);
        $stmt = $pdo->prepare("UPDATE training_materials SET title=?, description=?, type=?, url=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$title, $description, $type, $url, $id]);
    }
}

// Filter/Search
$filter_type = $_GET['type'] ?? '';
$search = $_GET['q'] ?? '';

$query = "SELECT * FROM training_materials WHERE 1";
$params = [];

if ($filter_type) {
    $query .= " AND type = ?";
    $params[] = $filter_type;
}
if ($search) {
    $query .= " AND (title LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Training & Support</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body{font-family:Arial;background:#f8f9fa;margin:0;padding:20px;}
        h1{color:#333;}
        form{margin-bottom:20px;}
        input, textarea, select{padding:8px;margin:4px 0;width:100%;max-width:400px;}
        table{border-collapse:collapse;width:100%;background:#fff;}
        th, td{border:1px solid #ddd;padding:10px;text-align:left;}
        th{background:#f1f1f1;}
        button{background:#007bff;color:#fff;border:none;padding:8px 12px;border-radius:4px;cursor:pointer;}
        button:hover{background:#0056b3;}
        .filter-bar{margin-bottom:20px;background:#fff;padding:10px;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.1);}
        .actions form{display:inline;}
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main style="max-width:1000px;margin:auto;">
    <h1>🎓 Manage Training & Support</h1>

    <!-- 🔍 Filter/Search -->
    <div class="filter-bar">
        <form method="GET">
            <input type="text" name="q" placeholder="Search title or description..." value="<?= htmlspecialchars($search) ?>">
            <select name="type">
                <option value="">All Types</option>
                <option value="video" <?= $filter_type=='video'?'selected':'' ?>>Videos</option>
                <option value="document" <?= $filter_type=='document'?'selected':'' ?>>Documents</option>
            </select>
            <button type="submit">Filter</button>
            <a href="training-support.php" style="margin-left:10px;color:#007bff;">Reset</a>
        </form>
    </div>

    <!-- ➕ Add New Material -->
    <div style="background:#fff;padding:15px;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.1);margin-bottom:20px;">
        <h2>Add New Material</h2>
        <form method="POST">
            <input type="text" name="title" placeholder="Title" required>
            <textarea name="description" placeholder="Description"></textarea>
            <select name="type" required>
                <option value="video">Video</option>
                <option value="document">Document</option>
            </select>
            <input type="url" name="url" placeholder="Material URL" required>
            <button type="submit" name="add_material">Add Material</button>
        </form>
    </div>

    <!-- 📚 Materials List -->
    <h2>Existing Materials</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Title</th>
                <th>Type</th>
                <th>URL</th>
                <th>Created</th>
                <th>Updated</th>
                <th>Views</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if(!$materials): ?>
                <tr><td colspan="8" style="text-align:center;">No materials found.</td></tr>
            <?php else: ?>
                <?php foreach ($materials as $i=>$m): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><?= htmlspecialchars($m['title']) ?></td>
                        <td><?= htmlspecialchars($m['type']) ?></td>
                        <td><a href="<?= htmlspecialchars($m['url']) ?>" target="_blank">Open</a></td>
                        <td><?= htmlspecialchars($m['created_at']) ?></td>
                        <td><?= htmlspecialchars($m['updated_at']) ?></td>
                        <td><?= (int)$m['view_count'] ?></td>
                        <td class="actions">
                            <!-- Delete -->
                            <form method="POST" onsubmit="return confirm('Delete this material?');">
                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                <button name="delete_material">🗑 Delete</button>
                            </form>
                            <!-- Edit -->
                            <form method="POST" style="margin-top:4px;">
                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                <input type="hidden" name="title" value="<?= htmlspecialchars($m['title']) ?>">
                                <input type="hidden" name="description" value="<?= htmlspecialchars($m['description']) ?>">
                                <input type="hidden" name="type" value="<?= htmlspecialchars($m['type']) ?>">
                                <input type="hidden" name="url" value="<?= htmlspecialchars($m['url']) ?>">
                                <button name="edit_material">✏️ Edit</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</main>
</body>
</html>
