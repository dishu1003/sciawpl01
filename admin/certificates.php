<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$pdo = get_pdo_connection();

if (isset($_POST['revoke'])) {
    $id = (int)$_POST['id'];
    $pdo->prepare("UPDATE certificates SET status = 'revoked' WHERE id = ?")->execute([$id]);
}

// fetch
$q = $pdo->query("SELECT c.*, u.username, u.email, co.title AS course_title FROM certificates c JOIN users u ON c.user_id = u.id JOIN courses co ON c.course_id = co.id ORDER BY c.issued_at DESC");
$rows = $q->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head><title>Certificates</title></head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<main>
<h1>Certificates Issued</h1>
<table border="1" cellpadding="8">
<tr><th>ID</th><th>User</th><th>Course</th><th>Issued At</th><th>File</th><th>Status</th><th>Action</th></tr>
<?php foreach($rows as $r): ?>
<tr>
<td><?= $r['id'] ?></td>
<td><?= htmlspecialchars($r['username']).' ('.htmlspecialchars($r['email']).')' ?></td>
<td><?= htmlspecialchars($r['course_title']) ?></td>
<td><?= $r['issued_at'] ?></td>
<td><a href="<?= htmlspecialchars($r['file_path']) ?>" target="_blank">Download</a></td>
<td><?= $r['status'] ?></td>
<td>
<form method="POST" style="display:inline">
<input type="hidden" name="id" value="<?= $r['id'] ?>">
<button name="revoke" onclick="return confirm('Revoke?')">Revoke</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
</main>
</body>
</html>
