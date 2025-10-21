<?php
/**
 * Database Credentials Checker
 * This will help you find the correct database credentials
 */

echo "<h2>🔍 Database Credentials Checker</h2>";

// Step 1: Ask user for their credentials
if (!isset($_POST['check'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Checker - SpartanCommunityIndia</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 700px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
            .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h2 { color: #333; border-bottom: 3px solid #007cba; padding-bottom: 10px; }
            .form-group { margin: 20px 0; }
            label { display: block; margin-bottom: 8px; font-weight: bold; color: #555; }
            input { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px; box-sizing: border-box; }
            input:focus { border-color: #007cba; outline: none; }
            button { background: #007cba; color: white; padding: 14px 30px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; width: 100%; }
            button:hover { background: #005a87; }
            .help { background: #e8f4f8; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #007cba; }
            .help h3 { margin-top: 0; color: #007cba; }
            .help ol { margin: 10px 0; padding-left: 20px; }
            .help li { margin: 8px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <h2>Enter Your Hostinger Database Credentials</h2>
            
            <div class="help">
                <h3>📋 Where to find these in Hostinger:</h3>
                <ol>
                    <li>Login to <strong>Hostinger Control Panel</strong></li>
                    <li>Go to <strong>"Databases"</strong> section</li>
                    <li>Click on your database (u782093275_awpl)</li>
                    <li>You'll see all the credentials there</li>
                </ol>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="host">Database Host:</label>
                    <input type="text" id="host" name="host" value="localhost" required>
                    <small>Usually: localhost</small>
                </div>
                
                <div class="form-group">
                    <label for="dbname">Database Name:</label>
                    <input type="text" id="dbname" name="dbname" value="u782093275_awpl" required>
                    <small>Your database name from Hostinger</small>
                </div>
                
                <div class="form-group">
                    <label for="username">Database Username:</label>
                    <input type="text" id="username" name="username" value="u782093275_awpl" required>
                    <small>Your database username from Hostinger</small>
                </div>
                
                <div class="form-group">
                    <label for="password">Database Password:</label>
                    <input type="password" id="password" name="password" placeholder="Enter your database password" required>
                    <small>Your database password from Hostinger</small>
                </div>
                
                <button type="submit" name="check">🔍 Check Connection</button>
            </form>
            
            <div class="help">
                <h3>💡 Common Issues:</h3>
                <ul>
                    <li>Make sure you're using the <strong>database password</strong>, not your Hostinger account password</li>
                    <li>Copy-paste the credentials exactly as shown in Hostinger</li>
                    <li>Check for any extra spaces before or after the credentials</li>
                </ul>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Step 2: Test the connection
$host = $_POST['host'];
$dbname = $_POST['dbname'];
$username = $_POST['username'];
$password = $_POST['password'];

echo "<h2>🔍 Testing Connection...</h2>";

try {
    echo "<p>Attempting to connect with:</p>";
    echo "<ul>";
    echo "<li>Host: <strong>$host</strong></li>";
    echo "<li>Database: <strong>$dbname</strong></li>";
    echo "<li>Username: <strong>$username</strong></li>";
    echo "<li>Password: <strong>" . str_repeat('*', strlen($password)) . "</strong></li>";
    echo "</ul>";
    
    // Try to connect
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div style='background: #d4edda; color: #155724; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>✅ SUCCESS! Connection Working!</h3>";
    echo "<p>Your database credentials are correct!</p>";
    echo "</div>";
    
    // Check if tables exist
    echo "<h3>📊 Checking Tables...</h3>";
    $tables = ['users', 'leads', 'scripts', 'logs', 'templates'];
    $existingTables = [];
    
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->fetch()) {
            echo "✅ Table '$table' exists<br>";
            $existingTables[] = $table;
        } else {
            echo "❌ Table '$table' missing<br>";
        }
    }
    
    if (count($existingTables) == 0) {
        echo "<div style='background: #fff3cd; color: #856404; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>⚠️ No Tables Found</h3>";
        echo "<p>You need to run the setup script to create tables.</p>";
        echo "<a href='setup-hostinger.php' style='display: inline-block; background: #007cba; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin-top: 10px;'>Run Setup Now</a>";
        echo "</div>";
    } else {
        echo "<div style='background: #d4edda; color: #155724; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>✅ Database is Ready!</h3>";
        echo "<p>Your database is fully set up and ready to use.</p>";
        echo "<a href='admin/' style='display: inline-block; background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin-top: 10px;'>Go to Admin Panel</a>";
        echo "</div>";
    }
    
    // Save working credentials
    $config = "<?php\n";
    $config .= "// Database Configuration\n";
    $config .= "define('DB_HOST', '$host');\n";
    $config .= "define('DB_NAME', '$dbname');\n";
    $config .= "define('DB_USER', '$username');\n";
    $config .= "define('DB_PASS', '$password');\n";
    $config .= "?>";
    
    file_put_contents('working-credentials.php', $config);
    
    echo "<div style='background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>💾 Credentials Saved</h4>";
    echo "<p>Your working credentials have been saved to <code>working-credentials.php</code></p>";
    echo "</div>";
    
} catch(PDOException $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>❌ Connection Failed</h3>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    
    echo "<h3>🔧 Troubleshooting Steps:</h3>";
    echo "<ol>";
    echo "<li><strong>Double-check your credentials</strong> in Hostinger Control Panel</li>";
    echo "<li>Make sure you're using the <strong>database password</strong>, not your Hostinger account password</li>";
    echo "<li>Copy-paste the credentials exactly (no extra spaces)</li>";
    echo "<li>Check if the database exists in Hostinger</li>";
    echo "<li>Try resetting the database password in Hostinger</li>";
    echo "</ol>";
    
    echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>📞 Need Help?</h4>";
    echo "<p>If you're still having issues, contact Hostinger support or check the <a href='DATABASE_FIX_GUIDE.md'>Database Fix Guide</a></p>";
    echo "</div>";
    
    echo "<a href='check-database.php' style='display: inline-block; background: #007cba; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin-top: 10px;'>Try Again</a>";
}
?>

