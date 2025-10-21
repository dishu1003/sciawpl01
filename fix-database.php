<?php
/**
 * Quick Database Fix Script
 * Tries to connect with common XAMPP/WAMP credentials
 */

echo "🔧 Trying to fix database connection...\n\n";

$host = 'localhost';
$dbname = 'lead_management';
$username = 'root';

// Common passwords for different setups
$commonPasswords = [
    '',           // XAMPP default
    'root',       // MAMP default  
    'password',   // Some setups
    '123456',     // Common password
    'admin',      // Common password
    'mysql',      // Some setups
    'toor',       // Root backwards
];

$success = false;

foreach ($commonPasswords as $password) {
    echo "Trying: " . ($password === '' ? '(no password)' : $password) . "... ";
    
    try {
        // Test connection
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "✅ SUCCESS!\n";
        
        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "✅ Database '$dbname' created\n";
        
        // Use database
        $pdo->exec("USE `$dbname`");
        
        // Create tables
        $sql = file_get_contents(__DIR__ . '/database.sql');
        $pdo->exec($sql);
        echo "✅ Tables created\n";
        
        // Create users
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO users (name, email, username, password, role, unique_ref, status)
            VALUES ('Admin User', 'admin@spartancommunity.com', 'admin', ?, 'admin', 'admin123', 'active')
        ");
        $stmt->execute([$adminPassword]);
        
        $teamPassword = password_hash('team123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO users (name, email, username, password, role, unique_ref, status)
            VALUES ('Team Member', 'team@spartancommunity.com', 'team', ?, 'team', 'team123', 'active')
        ");
        $stmt->execute([$teamPassword]);
        
        echo "✅ Admin and Team users created\n\n";
        echo "🎉 SUCCESS! Your database is now ready!\n\n";
        echo "Login Credentials:\n";
        echo "Admin: admin / admin123\n";
        echo "Team: team / team123\n\n";
        echo "Next Steps:\n";
        echo "1. Go to: http://localhost/admin/\n";
        echo "2. Login with: admin / admin123\n";
        echo "3. Test forms at: http://localhost/index.html\n";
        
        $success = true;
        break;
        
    } catch (Exception $e) {
        echo "❌ Failed\n";
        continue;
    }
}

if (!$success) {
    echo "\n❌ Could not connect with any common password.\n\n";
    echo "Solutions:\n";
    echo "1. Check if MySQL is running in XAMPP/WAMP\n";
    echo "2. Try manual setup: setup-database-manual.php\n";
    echo "3. Reset MySQL password\n";
    echo "4. Check MySQL configuration\n";
}
?>
