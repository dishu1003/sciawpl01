<?php
require_once __DIR__ . '/../includes/init.php';

$token = $_GET['token'] ?? '';
$error_message = '';
$is_token_valid = false;

if (empty($token)) {
    $error_message = 'Invalid signup link.';
} else {
    try {
        $pdo = get_pdo_connection();
        $stmt = $pdo->prepare("SELECT * FROM signup_tokens WHERE token = ? AND is_used = 0 AND expires_at > NOW()");
        $stmt->execute([$token]);
        $token_data = $stmt->fetch();

        if ($token_data) {
            $is_token_valid = true;
        } else {
            $error_message = 'This signup link is invalid or has expired.';
        }
    } catch (PDOException $e) {
        $error_message = 'Database error. Please try again later.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $token = $_POST['token'] ?? '';
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];

    try {
        $pdo = get_pdo_connection();
        $stmt = $pdo->prepare("SELECT * FROM signup_tokens WHERE token = ? AND is_used = 0 AND expires_at > NOW()");
        $stmt->execute([$token]);
        $token_data = $stmt->fetch();

        if ($token_data) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error_message = "Username or email already exists!";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, phone, password, role, status) VALUES (?, ?, ?, ?, ?, 'team', 'active')");
                $stmt->execute([$username, $email, $full_name, $phone, $password_hash]);

                $stmt = $pdo->prepare("UPDATE signup_tokens SET is_used = 1 WHERE id = ?");
                $stmt->execute([$token_data['id']]);

                header('Location: /login.php?registration=success');
                exit;
            }
        } else {
            $error_message = 'This signup link is invalid or has expired.';
        }
    } catch (PDOException $e) {
        $error_message = 'Database error. Please try again later.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Member Registration</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .registration-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
        }
        .registration-container h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 20px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
        }
        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 8px;
            background: #667eea;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #5568d3;
        }
        .error-message {
            background: #fee2e2;
            color: #ef4444;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="registration-container">
        <h1>Create Your Team Member Account</h1>
        <?php if (!$is_token_valid): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php else: ?>
            <form action="/team/register.php" method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="6">
                </div>
                <button type="submit" name="register" class="btn">Register</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
