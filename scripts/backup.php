<?php
/**
 * Automated Backup Script
 * Creates database and file backups
 * 
 * Usage:
 * - Manual: php scripts/backup.php
 * - Cron: 0 2 * * * /usr/bin/php /path/to/scripts/backup.php
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/logger.php';

// Configuration
$backup_dir = __DIR__ . '/../backups';
$max_backups = 7; // Keep last 7 backups
$timestamp = date('Y-m-d_H-i-s');

// Create backup directory if it doesn't exist
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
    file_put_contents($backup_dir . '/.gitignore', "*\n!.gitignore\n");
}

echo "🔄 Starting backup process...\n";
Logger::info('Backup process started');

try {
    // 1. Database Backup
    echo "📦 Creating database backup...\n";
    
    $db_backup_file = $backup_dir . '/database_' . $timestamp . '.sql';
    
    $command = sprintf(
        'mysqldump --user=%s --password=%s --host=%s %s > %s 2>&1',
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASS),
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_NAME),
        escapeshellarg($db_backup_file)
    );
    
    exec($command, $output, $return_code);
    
    if ($return_code === 0 && file_exists($db_backup_file)) {
        $db_size = filesize($db_backup_file);
        echo "✅ Database backup created: " . basename($db_backup_file) . " (" . format_bytes($db_size) . ")\n";
        Logger::info('Database backup created', [
            'file' => basename($db_backup_file),
            'size' => $db_size
        ]);
        
        // Compress database backup
        echo "🗜️  Compressing database backup...\n";
        $compressed_file = $db_backup_file . '.gz';
        
        if (function_exists('gzencode')) {
            $sql_content = file_get_contents($db_backup_file);
            file_put_contents($compressed_file, gzencode($sql_content, 9));
            unlink($db_backup_file); // Remove uncompressed file
            
            $compressed_size = filesize($compressed_file);
            echo "✅ Database backup compressed: " . basename($compressed_file) . " (" . format_bytes($compressed_size) . ")\n";
            Logger::info('Database backup compressed', [
                'file' => basename($compressed_file),
                'size' => $compressed_size,
                'compression_ratio' => round((1 - $compressed_size / $db_size) * 100, 2) . '%'
            ]);
        }
    } else {
        throw new Exception('Database backup failed: ' . implode("\n", $output));
    }
    
    // 2. Files Backup (logs, uploads, etc.)
    echo "📁 Creating files backup...\n";
    
    $files_backup_file = $backup_dir . '/files_' . $timestamp . '.tar.gz';
    
    // Directories to backup
    $dirs_to_backup = [
        __DIR__ . '/../logs',
        __DIR__ . '/../uploads'
    ];
    
    $existing_dirs = array_filter($dirs_to_backup, 'is_dir');
    
    if (!empty($existing_dirs)) {
        $tar_command = sprintf(
            'tar -czf %s -C %s %s 2>&1',
            escapeshellarg($files_backup_file),
            escapeshellarg(__DIR__ . '/..'),
            implode(' ', array_map(function($dir) {
                return basename($dir);
            }, $existing_dirs))
        );
        
        exec($tar_command, $tar_output, $tar_return);
        
        if ($tar_return === 0 && file_exists($files_backup_file)) {
            $files_size = filesize($files_backup_file);
            echo "✅ Files backup created: " . basename($files_backup_file) . " (" . format_bytes($files_size) . ")\n";
            Logger::info('Files backup created', [
                'file' => basename($files_backup_file),
                'size' => $files_size,
                'directories' => array_map('basename', $existing_dirs)
            ]);
        } else {
            echo "⚠️  Files backup skipped or failed\n";
        }
    } else {
        echo "ℹ️  No files to backup\n";
    }
    
    // 3. Cleanup old backups
    echo "🧹 Cleaning up old backups...\n";
    
    $backup_files = glob($backup_dir . '/*');
    usort($backup_files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    $deleted_count = 0;
    foreach (array_slice($backup_files, $max_backups * 2) as $old_backup) {
        if (is_file($old_backup) && basename($old_backup) !== '.gitignore') {
            unlink($old_backup);
            $deleted_count++;
            echo "🗑️  Deleted old backup: " . basename($old_backup) . "\n";
        }
    }
    
    if ($deleted_count > 0) {
        Logger::info('Old backups cleaned up', ['count' => $deleted_count]);
    }
    
    // 4. Summary
    echo "\n✨ Backup completed successfully!\n";
    echo "📊 Summary:\n";
    echo "   - Database backup: ✅\n";
    echo "   - Files backup: " . (isset($files_size) ? '✅' : 'ℹ️') . "\n";
    echo "   - Old backups cleaned: " . $deleted_count . "\n";
    echo "   - Backup location: " . realpath($backup_dir) . "\n";
    
    Logger::info('Backup process completed successfully', [
        'database_backup' => isset($compressed_size),
        'files_backup' => isset($files_size),
        'old_backups_deleted' => $deleted_count
    ]);
    
} catch (Exception $e) {
    echo "❌ Backup failed: " . $e->getMessage() . "\n";
    Logger::error('Backup process failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
}

/**
 * Format bytes to human readable format
 */
function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
