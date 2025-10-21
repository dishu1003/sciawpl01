<?php
/**
 * Login Page
 * Handles user authentication with security features
 */

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/security.php';

// Set security headers
SecurityHeaders::setAll();

// Redirect if already logged in
if (is_logged_in()) {
    error_log("LOGIN: already logged in, redirecting...");
    header('Location: ' . (is_admin() ? '/admin/' : '/team/'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("LOGIN: POST request received");

    // CSRF Protection
    try {
        CSRF::validateRequest();
        error_log("LOGIN: CSRF validation passed");
    } catch (Exception $e) {
        error_log("LOGIN: CSRF validation failed: " . $e->getMessage());
        Logger::security('CSRF validation failed on login', [
            'ip' => $_SERVER['REMOTE_ADDR'],
            'error' => $e->getMessage()
        ]);
        $error = 'Security validation failed. Please try again.';
    }

    if (!$error) {
        $username = Sanitizer::clean($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        error_log("LOGIN: attempting login for user=$username");

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
            error_log("LOGIN: empty username or password");
        } else {
            try {
                if (login_user($username, $password)) {
                    error_log("LOGIN: login_user returned TRUE, redirecting...");
                    header('Location: ' . (is_admin() ? '/admin/' : '/team/'));
                    exit;
                } else {
                    $error = 'Invalid credentials. Please try again.';
                    error_log("LOGIN: login_user returned FALSE");
                }
            } catch (Exception $e) {
                error_log("LOGIN: Database error during login process: " . $e->getMessage());
                $error = "Could not connect to the service. Please try again later.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .login-page {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        .login-container h2 {
            margin-bottom: 30px;
            text-align: center;
            color: #333;
        }
        .error {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #fcc;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .submit-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .submit-btn:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <h2>🔐 Team Login</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <?php echo CSRF::inputField(); ?>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autocomplete="username" autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>

            <button type="submit" class="submit-btn">Login</button>
        </form>
    </div>
</body>
</html>