<?php
/**
 * Logout Page
 * Handles user logout and session destruction
 */

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';

// CSRF Protection for logout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        CSRF::validateRequest();
        logout_user();
    } catch (Exception $e) {
        Logger::security('CSRF validation failed on logout', [
            'ip' => $_SERVER['REMOTE_ADDR'],
            'error' => $e->getMessage()
        ]);
        header('Location: /login.php');
        exit;
    }
} else {
    // For GET requests, show confirmation page
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .logout-page {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .logout-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .logout-container h2 {
            margin-bottom: 20px;
            color: #333;
        }
        .logout-container p {
            margin-bottom: 30px;
            color: #666;
        }
        .btn-group {
            display: flex;
            gap: 15px;
        }
        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn-logout {
            background: #e74c3c;
            color: white;
        }
        .btn-cancel {
            background: #95a5a6;
            color: white;
        }
    </style>
</head>
<body class="logout-page">
    <div class="logout-container">
        <h2>🚪 Logout Confirmation</h2>
        <p>Are you sure you want to logout?</p>

        <form method="POST" class="btn-group">
            <?php echo CSRF::inputField(); ?>
            <button type="submit" class="btn btn-logout">Yes, Logout</button>
            <a href="<?php echo is_admin() ? '/admin/' : '/team/'; ?>" class="btn btn-cancel" style="text-decoration:none; display:flex; align-items:center; justify-content:center;">Cancel</a>
        </form>
    </div>
</body>
</html>