<?php
/**
 * Manual Database Setup Script
 * Run this if you know your MySQL password
 */

echo "=== SpartanCommunityIndia Manual Database Setup ===\n\n";

// Get database credentials from user
echo "Enter your MySQL database credentials:\n";

// For web interface, we'll use a simple form
if (isset($_POST['submit'])) {
    $host = $_POST['host'] ?? 'localhost';
    $username = $_POST['username'] ?? 'root';
    $password = $_POST['password'] ?? '';
    $dbname = $_POST['dbname'] ?? 'lead_management';
    
    try {
        // Connect to MySQL server
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "✅ Connected to MySQL server successfully!<br>";
        
        // Create database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "Database '$dbname' created/verified successfully.<br>";
        
        // Use the database
        $pdo->exec("USE `$dbname`");
        
        // Read and execute SQL file
        $sql = file_get_contents(__DIR__ . '/database.sql');
        $pdo->exec($sql);
        echo "Database tables created successfully.<br>";
        
        // Create default admin user
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO users (name, email, username, password, role, unique_ref, status)
            VALUES ('Admin User', 'admin@spartancommunity.com', 'admin', ?, 'admin', 'admin123', 'active')
        ");
        $stmt->execute([$adminPassword]);
        
        echo "Default admin user created:<br>";
        echo "Username: admin<br>";
        echo "Password: admin123<br><br>";
        
        // Create sample team user
        $teamPassword = password_hash('team123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO users (name, email, username, password, role, unique_ref, status)
            VALUES ('Team Member', 'team@spartancommunity.com', 'team', ?, 'team', 'team123', 'active')
        ");
        $stmt->execute([$teamPassword]);
        
        echo "Sample team user created:<br>";
        echo "Username: team<br>";
        echo "Password: team123<br><br>";
        
        echo "🎉 Database setup completed successfully!<br>";
        echo "You can now access the admin panel at: <a href='admin/'>Admin Panel</a><br>";
        echo "You can now access the team panel at: <a href='team/'>Team Panel</a><br>";
        echo "Test the forms at: <a href='index.html'>Form A</a><br>";
        
    } catch(PDOException $e) {
        echo "❌ Error: " . $e->getMessage() . "<br>";
        echo "Please check your database credentials and try again.<br>";
    }
} else {
    // Show form
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Setup - SpartanCommunityIndia</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
            .form-group { margin: 15px 0; }
            label { display: block; margin-bottom: 5px; font-weight: bold; }
            input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
            button { background: #007cba; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; }
            button:hover { background: #005a87; }
            .help { background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <h2>🔧 Database Setup</h2>
        
        <div class="help">
            <h3>Common MySQL Credentials:</h3>
            <ul>
                <li><strong>XAMPP:</strong> Username: root, Password: (empty)</li>
                <li><strong>WAMP:</strong> Username: root, Password: (empty)</li>
                <li><strong>MAMP:</strong> Username: root, Password: root</li>
                <li><strong>Custom:</strong> Use your own credentials</li>
            </ul>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label for="host">Host:</label>
                <input type="text" id="host" name="host" value="localhost" required>
            </div>
            
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="root" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" placeholder="Leave empty if no password">
            </div>
            
            <div class="form-group">
                <label for="dbname">Database Name:</label>
                <input type="text" id="dbname" name="dbname" value="lead_management" required>
            </div>
            
            <button type="submit" name="submit">Setup Database</button>
        </form>
        
        <div class="help">
            <h3>If you don't know your MySQL password:</h3>
            <ol>
                <li>Try <a href="setup-database.php">Automatic Setup</a> first</li>
                <li>Check your XAMPP/WAMP control panel</li>
                <li>Look for MySQL configuration files</li>
                <li>Reset MySQL password if needed</li>
            </ol>
        </div>
    </body>
    </html>
    <?php
}
?>
