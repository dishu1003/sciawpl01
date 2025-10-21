<?php
require_once '../includes/auth.php';
require_admin();

// Database credentials
$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$dbname = DB_NAME;

// Backup file name
$backup_file = '../backups/backup_' . date('Y-m-d_H-i-s') . '.sql';

// Create backups directory if not exists
if (!file_exists('../backups')) {
    mkdir('../backups', 0755, true);
}

// Execute mysqldump
$command = "mysqldump --host=$host --user=$user --password=$pass $dbname > $backup_file";
exec($command, $output, $return_var);

if ($return_var === 0) {
    // Compress the backup
    $zip = new ZipArchive();
    $zip_file = $backup_file . '.zip';
    
    if ($zip->open($zip_file, ZipArchive::CREATE) === TRUE) {
        $zip->addFile($backup_file, basename($backup_file));
        $zip->close();
        
        // Delete uncompressed file
        unlink($backup_file);
        
        // Download the backup
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zip_file) . '"');
        header('Content-Length: ' . filesize($zip_file));
        readfile($zip_file);
        exit;
    }
} else {
    die('Backup failed!');
}
?>