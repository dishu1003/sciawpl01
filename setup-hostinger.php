<?php
/**
 * Hostinger Database Setup Script
 * Specifically designed for Hostinger hosting
 */

echo "=== SpartanCommunityIndia Hostinger Setup ===\n\n";

// Hostinger database configuration
$host = 'localhost';  // Hostinger uses localhost for database
$dbname = 'u782093275_awpl';  // Your Hostinger database name
$username = 'u782093275_awpl';  // Your Hostinger username
$password = 'Vktmdp@2025';  // Your Hostinger database password

try {
    echo "Connecting to Hostinger database...\n";
    
    // Connect to MySQL server
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connected to Hostinger MySQL successfully!\n";
    
    // Use the existing database
    $pdo->exec("USE `$dbname`");
    echo "✅ Using database: $dbname\n";
    
    // Create tables
    echo "Creating tables...\n";
    $sql = file_get_contents(__DIR__ . '/database.sql');
    $pdo->exec($sql);
    echo "✅ Database tables created successfully.\n";
    
    // Create default admin user
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO users (name, email, username, password, role, unique_ref, status)
        VALUES ('Admin User', 'admin@spartancommunity.com', 'admin', ?, 'admin', 'admin123', 'active')
    ");
    $stmt->execute([$adminPassword]);
    
    echo "✅ Admin user created:\n";
    echo "   Username: admin\n";
    echo "   Password: admin123\n\n";
    
    // Create sample team user
    $teamPassword = password_hash('team123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO users (name, email, username, password, role, unique_ref, status)
        VALUES ('Team Member', 'team@spartancommunity.com', 'team', ?, 'team', 'team123', 'active')
    ");
    $stmt->execute([$teamPassword]);
    
    echo "✅ Team user created:\n";
    echo "   Username: team\n";
    echo "   Password: team123\n\n";
    
    echo "🎉 Hostinger database setup completed successfully!\n\n";
    echo "Your website is now ready at:\n";
    echo "🌐 Main Site: https://your-domain.com\n";
    echo "🔐 Admin Panel: https://your-domain.com/admin/\n";
    echo "👥 Team Panel: https://your-domain.com/team/\n";
    echo "📝 Form A: https://your-domain.com/index.html\n\n";
    
    echo "Login Credentials:\n";
    echo "Admin: admin / admin123\n";
    echo "Team: team / team123\n";
    
} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
    
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "Database connection failed. Please check:\n";
        echo "1. Database name: $dbname\n";
        echo "2. Username: $username\n";
        echo "3. Password: $password\n";
        echo "4. Make sure database exists in Hostinger control panel\n\n";
        
        echo "To fix this:\n";
        echo "1. Go to Hostinger Control Panel\n";
        echo "2. Go to Databases section\n";
        echo "3. Create database if not exists\n";
        echo "4. Update credentials in this script\n";
    }
}
?>
