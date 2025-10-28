<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = get_pdo_connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_script'])) {
        $title = $_POST['title'];
        $content = $_POST['content'];
        $type = $_POST['type'];
        $visibility = $_POST['visibility'];
        $stmt = $pdo->prepare("INSERT INTO scripts (title, content, type, visibility, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $content, $type, $visibility, $_SESSION['user_id']]);
    } elseif (isset($_POST['delete_script'])) {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM scripts WHERE id = ?");
        $stmt->execute([$id]);
    }
}

$stmt = $pdo->prepare("SELECT * FROM scripts ORDER BY type, title");
$stmt->execute();
$scripts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Sales Scripts</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <h1>Manage Sales Scripts</h1>

    <h2>Add New Script</h2>
    <form method="POST">
        <input type="text" name="title" placeholder="Script Title" required>
        <textarea name="content" placeholder="Script Content" required></textarea>
        <select name="type" required>
            <option value="followup">Follow-up</option>
            <option value="sales">Sales</option>
            <option value="closing">Closing</option>
            <option value="objection">Objection Handling</option>
        </select>
        <select name="visibility" required>
            <option value="all">All</option>
            <option value="admin_only">Admin Only</option>
        </select>
        <button type="submit" name="add_script">Add Script</button>
    </form>

    <h2>Existing Scripts</h2>
    <table border="1">
        <thead>
            <tr>
                <th>Title</th>
                <th>Type</th>
                <th>Visibility</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($scripts as $script): ?>
                <tr>
                    <td><?php echo htmlspecialchars($script['title']); ?></td>
                    <td><?php echo htmlspecialchars($script['type']); ?></td>
                    <td><?php echo htmlspecialchars($script['visibility']); ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $script['id']; ?>">
                            <button type="submit" name="delete_script">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
