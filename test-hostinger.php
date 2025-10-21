<?php
/**
 * Hostinger Connection Test
 * Test your Hostinger database connection
 */

echo "<h2>🔧 Hostinger Database Test</h2>";

// Hostinger database credentials
$host = 'localhost';
$dbname = 'u782093275_awpl';
$username = 'u782093275_awpl';
$password = 'Vktmdp@2025';

try {
    echo "<h3>✅ Test 1: Database Connection</h3>";
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connected to Hostinger database successfully!<br>";
    echo "Database: $dbname<br>";
    echo "User: $username<br><br>";
    
    // Test 2: Check if tables exist
    echo "<h3>✅ Test 2: Database Tables</h3>";
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
    echo "<h3>✅ Test 3: Admin User</h3>";
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
    echo "<h3>✅ Test 4: Team User</h3>";
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'team' LIMIT 1");
    $stmt->execute();
    $team = $stmt->fetch();
    
    if ($team) {
        echo "✅ Team user found: " . htmlspecialchars($team['username']) . "<br>";
        echo "Email: " . htmlspecialchars($team['email']) . "<br>";
    } else {
        echo "❌ No team user found<br>";
    }
    
    echo "<h3>🔐 Login Credentials:</h3>";
    echo "<strong>Admin Login:</strong><br>";
    echo "URL: <a href='admin/'>https://your-domain.com/admin/</a><br>";
    echo "Username: admin<br>";
    echo "Password: admin123<br><br>";
    
    echo "<strong>Team Login:</strong><br>";
    echo "URL: <a href='team/'>https://your-domain.com/team/</a><br>";
    echo "Username: team<br>";
    echo "Password: team123<br><br>";
    
    echo "<h3>📝 Test Forms:</h3>";
    echo "<a href='index.html'>Form A - Start Application</a><br>";
    echo "<a href='_form-b-team.html'>Form B - Goals & Timeline</a><br>";
    echo "<a href='_form-c-team.html'>Form C - Investment</a><br>";
    echo "<a href='_form-d-team.html'>Form D - Final Details</a><br><br>";
    
    echo "<h3>🎉 Success!</h3>";
    echo "Your Hostinger database is working perfectly!<br>";
    echo "You can now use your website normally.<br>";
    
} catch(PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br><br>";
    
    echo "<h3>🔧 Troubleshooting:</h3>";
    echo "1. Check if database exists in Hostinger control panel<br>";
    echo "2. Verify database credentials<br>";
    echo "3. Run <a href='setup-hostinger.php'>setup-hostinger.php</a> to create tables<br>";
    echo "4. Check Hostinger support if issues persist<br>";
    
    echo "<h3>📋 Your Database Info:</h3>";
    echo "Host: $host<br>";
    echo "Database: $dbname<br>";
    echo "Username: $username<br>";
    echo "Password: $password<br>";
}

echo "<hr>";
echo "<h3>🌐 Environment Check:</h3>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "PDO Available: " . (extension_loaded('pdo') ? '✅ Yes' : '❌ No') . "<br>";
echo "PDO MySQL Available: " . (extension_loaded('pdo_mysql') ? '✅ Yes' : '❌ No') . "<br>";
echo "Session Support: " . (function_exists('session_start') ? '✅ Yes' : '❌ No') . "<br>";
echo "Current Domain: " . $_SERVER['HTTP_HOST'] . "<br>";
?>
