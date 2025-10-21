#!/usr/bin/env php
<?php
/**
 * Security Key Generator
 * Generates secure random keys for .env file
 */

echo "\n";
echo "==============================================\n";
echo "  🔐 Security Key Generator\n";
echo "==============================================\n\n";

echo "Copy these values to your .env file:\n\n";

// Generate Encryption Key (32 characters)
$encryptionKey = bin2hex(random_bytes(16));
echo "ENCRYPTION_KEY=" . $encryptionKey . "\n";

// Generate Webhook Secret (64 characters)
$webhookSecret = bin2hex(random_bytes(32));
echo "WEBHOOK_SECRET=" . $webhookSecret . "\n";

// Generate Session Secret (64 characters)
$sessionSecret = bin2hex(random_bytes(32));
echo "SESSION_SECRET=" . $sessionSecret . "\n";

echo "\n";
echo "==============================================\n";
echo "  ⚠️  IMPORTANT SECURITY NOTES:\n";
echo "==============================================\n";
echo "1. Never share these keys with anyone\n";
echo "2. Never commit .env file to Git\n";
echo "3. Rotate keys every 90 days\n";
echo "4. Store backup keys securely\n";
echo "5. Use different keys for dev/staging/prod\n";
echo "\n";

// Save to file option
echo "Do you want to save these to a file? (y/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
$answer = trim(strtolower($line));

if ($answer === 'y' || $answer === 'yes') {
    $filename = 'generated_keys_' . date('Y-m-d_His') . '.txt';
    $content = "# Generated Security Keys - " . date('Y-m-d H:i:s') . "\n\n";
    $content .= "ENCRYPTION_KEY=" . $encryptionKey . "\n";
    $content .= "WEBHOOK_SECRET=" . $webhookSecret . "\n";
    $content .= "SESSION_SECRET=" . $sessionSecret . "\n";
    $content .= "\n# IMPORTANT: Delete this file after copying to .env\n";
    
    file_put_contents($filename, $content);
    echo "\n✅ Keys saved to: " . $filename . "\n";
    echo "⚠️  Remember to delete this file after copying to .env!\n\n";
} else {
    echo "\n✅ Keys generated successfully!\n\n";
}

fclose($handle);
?>
