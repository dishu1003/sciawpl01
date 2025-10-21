<?php
/**
 * Database Connection Test Script
 * Run this to test if your database is working properly
 */

echo "<h2>Database Connection Test</h2>";

// Test 1: Basic database connection
echo "<h3>Test 1: Basic Database Connection</h3>";
try {
    $host = 'localhost';
    $dbname = 'lead_management';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connection successful!<br>";
    
    // Test 2: Check if tables exist
    echo "<h3>Test 2: Check Database Tables</h3>";
    $tables = ['users', 'leads', 'scripts', 'logs', 'templates'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->fetch()) {
            echo "✅ Table '$table' exists<br>";
        } else {
            echo "❌ Table '$table' missing<br>";
        }
    }
    
    // Test 3: Check admin user
    echo "<h3>Test 3: Check Admin User</h3>";
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "✅ Admin user found: " . htmlspecialchars($admin['username']) . "<br>";
        echo "Email: " . htmlspecialchars($admin['email']) . "<br>";
    } else {
        echo "❌ No admin user found<br>";
    }
    
    // Test 4: Check team user
    echo "<h3>Test 4: Check Team User</h3>";
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'team' LIMIT 1");
    $stmt->execute();
    $team = $stmt->fetch();
    
    if ($team) {
        echo "✅ Team user found: " . htmlspecialchars($team['username']) . "<br>";
        echo "Email: " . htmlspecialchars($team['email']) . "<br>";
    } else {
        echo "❌ No team user found<br>";
    }
    
    echo "<h3>Login Credentials:</h3>";
    echo "<strong>Admin Login:</strong><br>";
    echo "Username: admin<br>";
    echo "Password: admin123<br><br>";
    
    echo "<strong>Team Login:</strong><br>";
    echo "Username: team<br>";
    echo "Password: team123<br><br>";
    
    echo "<h3>Next Steps:</h3>";
    echo "1. <a href='admin/'>Go to Admin Panel</a><br>";
    echo "2. <a href='team/'>Go to Team Panel</a><br>";
    echo "3. <a href='index.html'>Test Form Flow</a><br>";
    
} catch(PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    echo "<h3>Setup Instructions:</h3>";
    echo "1. Make sure MySQL/MariaDB is running<br>";
    echo "2. Create database 'lead_management'<br>";
    echo "3. Run <a href='setup-database.php'>setup-database.php</a> to create tables and users<br>";
    echo "4. Update database credentials in config files if needed<br>";
}

echo "<hr>";
echo "<h3>Environment Check:</h3>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "PDO Available: " . (extension_loaded('pdo') ? '✅ Yes' : '❌ No') . "<br>";
echo "PDO MySQL Available: " . (extension_loaded('pdo_mysql') ? '✅ Yes' : '❌ No') . "<br>";
echo "Session Support: " . (function_exists('session_start') ? '✅ Yes' : '❌ No') . "<br>";
?>
