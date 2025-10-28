<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = get_pdo_connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_goal'])) {
        $name = $_POST['name'];
        $description = $_POST['description'];
        $target_value = $_POST['target_value'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $stmt = $pdo->prepare("INSERT INTO goals (name, description, target_value, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $description, $target_value, $start_date, $end_date]);
    } elseif (isset($_POST['delete_goal'])) {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM goals WHERE id = ?");
        $stmt->execute([$id]);
    }
}

$stmt = $pdo->prepare("SELECT * FROM goals ORDER BY end_date DESC");
$stmt->execute();
$goals = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Goals</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <h1>Manage Goals</h1>

    <h2>Add New Goal</h2>
    <form method="POST">
        <input type="text" name="name" placeholder="Goal Name" required>
        <textarea name="description" placeholder="Description"></textarea>
        <input type="number" name="target_value" placeholder="Target Value" required>
        <input type="date" name="start_date" required>
        <input type="date" name="end_date" required>
        <button type="submit" name="add_goal">Add Goal</button>
    </form>

    <h2>Existing Goals</h2>
    <table border="1">
        <thead>
            <tr>
                <th>Name</th>
                <th>Target</th>
                <th>Current</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($goals as $goal): ?>
                <tr>
                    <td><?php echo htmlspecialchars($goal['name']); ?></td>
                    <td><?php echo htmlspecialchars($goal['target_value']); ?></td>
                    <td><?php echo htmlspecialchars($goal['current_value']); ?></td>
                    <td><?php echo htmlspecialchars($goal['start_date']); ?></td>
                    <td><?php echo htmlspecialchars($goal['end_date']); ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $goal['id']; ?>">
                            <button type="submit" name="delete_goal">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
