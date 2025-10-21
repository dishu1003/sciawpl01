<?php
/**
 * Database Setup Script
 * Run this once to set up your database and default admin user
 */

echo "=== SpartanCommunityIndia Database Setup ===\n\n";

// Database configuration - Try common passwords
$host = 'localhost';
$dbname = 'lead_management';
$username = 'root';

// Common MySQL passwords to try
$possiblePasswords = ['', 'root', 'password', '123456', 'admin'];

$connected = false;
$pdo = null;

echo "Attempting to connect to MySQL...\n";

foreach ($possiblePasswords as $password) {
    try {
        echo "Trying password: " . ($password === '' ? '(empty)' : $password) . "... ";
        
        // Connect to MySQL server (without database first)
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "✅ SUCCESS!\n";
        $connected = true;
        break;
        
    } catch(PDOException $e) {
        echo "❌ Failed\n";
        continue;
    }
}

if (!$connected) {
    echo "\n❌ Could not connect to MySQL with any common password.\n\n";
    echo "Please try one of these solutions:\n";
    echo "1. Reset MySQL root password:\n";
    echo "   - Stop MySQL service\n";
    echo "   - Start MySQL with: mysqld --skip-grant-tables\n";
    echo "   - Connect and reset password\n\n";
    echo "2. Create a new MySQL user:\n";
    echo "   CREATE USER 'spartan'@'localhost' IDENTIFIED BY 'spartan123';\n";
    echo "   GRANT ALL PRIVILEGES ON *.* TO 'spartan'@'localhost';\n";
    echo "   FLUSH PRIVILEGES;\n\n";
    echo "3. Use XAMPP/WAMP default credentials\n\n";
    echo "Then run this script again.\n";
    exit(1);
}

echo "\n✅ Connected to MySQL server successfully!\n";

try {
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database '$dbname' created/verified successfully.\n";
    
    // Use the database
    $pdo->exec("USE `$dbname`");
    
    // Read and execute SQL file
    $sql = file_get_contents(__DIR__ . '/database.sql');
    $pdo->exec($sql);
    echo "Database tables created successfully.\n";
    
    // Create default admin user
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO users (name, email, username, password, role, unique_ref, status)
        VALUES ('Admin User', 'admin@spartancommunity.com', 'admin', ?, 'admin', 'admin123', 'active')
    ");
    $stmt->execute([$adminPassword]);
    
    echo "Default admin user created:\n";
    echo "Username: admin\n";
    echo "Password: admin123\n\n";
    
    // Create sample team user
    $teamPassword = password_hash('team123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO users (name, email, username, password, role, unique_ref, status)
        VALUES ('Team Member', 'team@spartancommunity.com', 'team', ?, 'team', 'team123', 'active')
    ");
    $stmt->execute([$teamPassword]);
    
    echo "Sample team user created:\n";
    echo "Username: team\n";
    echo "Password: team123\n\n";
    
    echo "Database setup completed successfully!\n";
    echo "You can now access the admin panel at: http://localhost/admin/\n";
    echo "You can now access the team panel at: http://localhost/team/\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Please check your database credentials and try again.\n";
}
?>
