<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_team_access();

$pdo = get_pdo_connection();

$type_filter = $_GET['type'] ?? 'all';
$search = $_GET['search'] ?? '';

$where_clauses = ["visibility = 'all'"];
$params = [];

if ($type_filter !== 'all') {
    $where_clauses[] = 'type = ?';
    $params[] = $type_filter;
}

if (!empty($search)) {
    $where_clauses[] = '(title LIKE ? OR content LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$stmt = $pdo->prepare("SELECT * FROM scripts $where_sql ORDER BY type, title");
$stmt->execute($params);
$scripts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sales Scripts</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/team_sidebar.php'; ?>
    <h1>Sales Scripts</h1>

    <form method="GET">
        <input type="text" name="search" placeholder="Search scripts..." value="<?php echo htmlspecialchars($search); ?>">
        <select name="type">
            <option value="all">All Types</option>
            <option value="followup" <?php if ($type_filter === 'followup') echo 'selected'; ?>>Follow-up</option>
            <option value="sales" <?php if ($type_filter === 'sales') echo 'selected'; ?>>Sales</option>
            <option value="closing" <?php if ($type_filter === 'closing') echo 'selected'; ?>>Closing</option>
            <option value="objection" <?php if ($type_filter === 'objection') echo 'selected'; ?>>Objection Handling</option>
        </select>
        <button type="submit">Filter</button>
    </form>

    <?php foreach ($scripts as $script): ?>
        <div>
            <h2><?php echo htmlspecialchars($script['title']); ?></h2>
            <p><?php echo nl2br(htmlspecialchars($script['content'])); ?></p>
            <button onclick="copyToClipboard(<?php echo json_encode($script['content']); ?>)">Copy to Clipboard</button>
        </div>
        <hr>
    <?php endforeach; ?>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Script copied to clipboard!');
            }, function(err) {
                alert('Could not copy script.');
            });
        }
    </script>
</body>
</html>
