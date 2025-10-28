<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_team_access();

$pdo = get_pdo_connection();

$type_filter = $_GET['type'] ?? 'all';
$search = $_GET['search'] ?? '';

$where_clauses = [];
$params = [];

if ($type_filter !== 'all') {
    $where_clauses[] = 'type = ?';
    $params[] = $type_filter;
}

if (!empty($search)) {
    $where_clauses[] = '(title LIKE ? OR description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$stmt = $pdo->prepare("SELECT * FROM training_materials $where_sql ORDER BY created_at DESC");
$stmt->execute($params);
$materials = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Training & Support</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/team_sidebar.php'; ?>
    <h1>Training & Support</h1>

    <form method="GET">
        <input type="text" name="search" placeholder="Search materials..." value="<?php echo htmlspecialchars($search); ?>">
        <select name="type">
            <option value="all">All Types</option>
            <option value="video" <?php if ($type_filter === 'video') echo 'selected'; ?>>Videos</option>
            <option value="document" <?php if ($type_filter === 'document') echo 'selected'; ?>>Documents</option>
        </select>
        <button type="submit">Filter</button>
    </form>

    <?php foreach ($materials as $material): ?>
        <div>
            <h2><?php echo htmlspecialchars($material['title']); ?></h2>
            <p><?php echo nl2br(htmlspecialchars($material['description'])); ?></p>
            <a href="<?php echo htmlspecialchars($material['url']); ?>" target="_blank">View Material</a>
        </div>
        <hr>
    <?php endforeach; ?>
</body>
</html>
