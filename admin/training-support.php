<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = get_pdo_connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_material'])) {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $type = $_POST['type'];
        $url = $_POST['url'];
        $stmt = $pdo->prepare("INSERT INTO training_materials (title, description, type, url) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $description, $type, $url]);
    } elseif (isset($_POST['delete_material'])) {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM training_materials WHERE id = ?");
        $stmt->execute([$id]);
    }
}

$stmt = $pdo->prepare("SELECT * FROM training_materials ORDER BY created_at DESC");
$stmt->execute();
$materials = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Training & Support</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <h1>Manage Training & Support</h1>

    <h2>Add New Material</h2>
    <form method="POST">
        <input type="text" name="title" placeholder="Title" required>
        <textarea name="description" placeholder="Description"></textarea>
        <select name="type" required>
            <option value="video">Video</option>
            <option value="document">Document</option>
        </select>
        <input type="url" name="url" placeholder="URL" required>
        <button type="submit" name="add_material">Add Material</button>
    </form>

    <h2>Existing Materials</h2>
    <table border="1">
        <thead>
            <tr>
                <th>Title</th>
                <th>Type</th>
                <th>URL</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($materials as $material): ?>
                <tr>
                    <td><?php echo htmlspecialchars($material['title']); ?></td>
                    <td><?php echo htmlspecialchars($material['type']); ?></td>
                    <td><a href="<?php echo htmlspecialchars($material['url']); ?>" target="_blank">View</a></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $material['id']; ?>">
                            <button type="submit" name="delete_material">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
