#!/usr/bin/env php
<?php
/**
 * System Health Check Script
 * Tests all security features and configurations
 */

echo "\n";
echo "==============================================\n";
echo "  🏥 System Health Check\n";
echo "==============================================\n\n";

$errors = [];
$warnings = [];
$passed = [];

// Test 1: Check if .env file exists
echo "1. Checking .env file... ";
if (file_exists(__DIR__ . '/../.env')) {
    echo "✅ PASS\n";
    $passed[] = ".env file exists";
} else {
    echo "❌ FAIL\n";
    $errors[] = ".env file not found";
}

// Test 2: Check if logs directory exists and is writable
echo "2. Checking logs directory... ";
if (is_dir(__DIR__ . '/../logs') && is_writable(__DIR__ . '/../logs')) {
    echo "✅ PASS\n";
    $passed[] = "Logs directory is writable";
} else {
    echo "❌ FAIL\n";
    $errors[] = "Logs directory not writable";
}

// Test 3: Load environment variables
echo "3. Loading environment variables... ";
try {
    require_once __DIR__ . '/../includes/env.php';
    echo "✅ PASS\n";
    $passed[] = "Environment loader working";
} catch (Exception $e) {
    echo "❌ FAIL\n";
    $errors[] = "Environment loader failed: " . $e->getMessage();
}

// Test 4: Check required environment variables
echo "4. Checking required environment variables... ";
$requiredVars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'ENCRYPTION_KEY', 'WEBHOOK_SECRET'];
$missingVars = [];
foreach ($requiredVars as $var) {
    if (empty(env($var))) {
        $missingVars[] = $var;
    }
}
if (empty($missingVars)) {
    echo "✅ PASS\n";
    $passed[] = "All required environment variables set";
} else {
    echo "❌ FAIL\n";
    $errors[] = "Missing environment variables: " . implode(', ', $missingVars);
}

// Test 5: Check database connection
echo "5. Testing database connection... ";
try {
    require_once __DIR__ . '/../config/database.php';
    $pdo->query("SELECT 1");
    echo "✅ PASS\n";
    $passed[] = "Database connection successful";
} catch (Exception $e) {
    echo "❌ FAIL\n";
    $errors[] = "Database connection failed: " . $e->getMessage();
}

// Test 6: Check if security classes exist
echo "6. Checking security classes... ";
try {
    require_once __DIR__ . '/../includes/csrf.php';
    require_once __DIR__ . '/../includes/validator.php';
    require_once __DIR__ . '/../includes/security.php';
    require_once __DIR__ . '/../includes/logger.php';
    echo "✅ PASS\n";
    $passed[] = "All security classes loaded";
} catch (Exception $e) {
    echo "❌ FAIL\n";
    $errors[] = "Security classes failed: " . $e->getMessage();
}

// Test 7: Test CSRF token generation
echo "7. Testing CSRF token generation... ";
try {
    session_start();
    $token = CSRF::generateToken();
    if (!empty($token) && strlen($token) === 64) {
        echo "✅ PASS\n";
        $passed[] = "CSRF token generation working";
    } else {
        echo "❌ FAIL\n";
        $errors[] = "CSRF token generation failed";
    }
} catch (Exception $e) {
    echo "❌ FAIL\n";
    $errors[] = "CSRF test failed: " . $e->getMessage();
}

// Test 8: Test validator
echo "8. Testing validator... ";
try {
    $validator = new Validator();
    $validator->email('test', 'test@example.com');
    if ($validator->passes()) {
        echo "✅ PASS\n";
        $passed[] = "Validator working correctly";
    } else {
        echo "❌ FAIL\n";
        $errors[] = "Validator failed validation test";
    }
} catch (Exception $e) {
    echo "❌ FAIL\n";
    $errors[] = "Validator test failed: " . $e->getMessage();
}

// Test 9: Test logger
echo "9. Testing logger... ";
try {
    Logger::info('Health check test');
    if (file_exists(__DIR__ . '/../logs/app.log')) {
        echo "✅ PASS\n";
        $passed[] = "Logger working correctly";
    } else {
        echo "⚠️  WARNING\n";
        $warnings[] = "Logger may not be writing to file";
    }
} catch (Exception $e) {
    echo "❌ FAIL\n";
    $errors[] = "Logger test failed: " . $e->getMessage();
}

// Test 10: Check database indexes
echo "10. Checking database indexes... ";
try {
    $stmt = $pdo->query("SHOW INDEX FROM leads WHERE Key_name = 'idx_leads_email'");
    if ($stmt->rowCount() > 0) {
        echo "✅ PASS\n";
        $passed[] = "Database indexes installed";
    } else {
        echo "⚠️  WARNING\n";
        $warnings[] = "Database indexes not found - run migrations/add_indexes.sql";
    }
} catch (Exception $e) {
    echo "⚠️  WARNING\n";
    $warnings[] = "Could not check indexes: " . $e->getMessage();
}

// Test 11: Check .gitignore
echo "11. Checking .gitignore... ";
if (file_exists(__DIR__ . '/../.gitignore')) {
    $gitignore = file_get_contents(__DIR__ . '/../.gitignore');
    if (strpos($gitignore, '.env') !== false) {
        echo "✅ PASS\n";
        $passed[] = ".gitignore configured correctly";
    } else {
        echo "⚠️  WARNING\n";
        $warnings[] = ".env not in .gitignore";
    }
} else {
    echo "⚠️  WARNING\n";
    $warnings[] = ".gitignore file not found";
}

// Test 12: Check encryption
echo "12. Testing encryption... ";
try {
    require_once __DIR__ . '/../config/config.php';
    $testData = "Test encryption data";
    $encrypted = encrypt_data($testData);
    $decrypted = decrypt_data($encrypted);
    if ($decrypted === $testData) {
        echo "✅ PASS\n";
        $passed[] = "Encryption/decryption working";
    } else {
        echo "❌ FAIL\n";
        $errors[] = "Encryption/decryption failed";
    }
} catch (Exception $e) {
    echo "❌ FAIL\n";
    $errors[] = "Encryption test failed: " . $e->getMessage();
}

// Summary
echo "\n";
echo "==============================================\n";
echo "  📊 Summary\n";
echo "==============================================\n\n";

echo "✅ Passed: " . count($passed) . "\n";
echo "⚠️  Warnings: " . count($warnings) . "\n";
echo "❌ Errors: " . count($errors) . "\n\n";

if (!empty($errors)) {
    echo "==============================================\n";
    echo "  ❌ ERRORS (Must Fix):\n";
    echo "==============================================\n";
    foreach ($errors as $i => $error) {
        echo ($i + 1) . ". " . $error . "\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "==============================================\n";
    echo "  ⚠️  WARNINGS (Should Fix):\n";
    echo "==============================================\n";
    foreach ($warnings as $i => $warning) {
        echo ($i + 1) . ". " . $warning . "\n";
    }
    echo "\n";
}

if (empty($errors) && empty($warnings)) {
    echo "🎉 All tests passed! Your system is healthy!\n\n";
} elseif (empty($errors)) {
    echo "✅ System is functional but has some warnings.\n\n";
} else {
    echo "❌ System has critical errors that need to be fixed.\n\n";
}

// Exit code
exit(empty($errors) ? 0 : 1);
?>
