<?php
/**
 * Automatic Backup Scheduler - Online Voting System
 * This script should be run via cron job every day to check for backup needs
 * Recommended cron: 0 2 * * * (runs at 2 AM daily)
 */

// Prevent direct web access
if (isset($_SERVER['HTTP_HOST'])) {
    die('This script can only be run from command line.');
}

require_once '../config/config.php';

try {
    $db = getDBConnection();
    
    // Create backups directory if it doesn't exist
    $backup_dir = '../backups/';
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    // Check if backup is needed (every 5 days)
    $backup_needed = true;
    $files = scandir($backup_dir);
    $latest_backup_time = 0;
    
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $filepath = $backup_dir . $file;
            $file_time = filemtime($filepath);
            if ($file_time > $latest_backup_time) {
                $latest_backup_time = $file_time;
            }
        }
    }
    
    // Check if 5 days have passed since last backup
    $days_since_backup = floor((time() - $latest_backup_time) / (60 * 60 * 24));
    
    if ($days_since_backup < 5) {
        echo "Backup not needed. Last backup was $days_since_backup days ago.\n";
        exit(0);
    }
    
    echo "Creating automatic backup (last backup was $days_since_backup days ago)...\n";
    
    // Generate backup filename
    $backup_filename = 'auto_backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    // Start building the SQL backup
    $backup_content = "-- Online Voting System Automatic Database Backup\n";
    $backup_content .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
    $backup_content .= "-- Database: online_voting_system\n";
    $backup_content .= "-- Type: Automatic Scheduled Backup\n\n";
    
    $backup_content .= "SET FOREIGN_KEY_CHECKS = 0;\n";
    $backup_content .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
    $backup_content .= "SET AUTOCOMMIT = 0;\n";
    $backup_content .= "START TRANSACTION;\n\n";
    
    // Get all tables
    $tables = [];
    $result = $db->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    echo "Found " . count($tables) . " tables to backup...\n";
    
    // Backup each table
    foreach ($tables as $table) {
        echo "Backing up table: $table\n";
        
        // Get table structure
        $result = $db->query("SHOW CREATE TABLE `$table`");
        $row = $result->fetch(PDO::FETCH_NUM);
        
        $backup_content .= "-- --------------------------------------------------------\n";
        $backup_content .= "-- Table structure for table `$table`\n";
        $backup_content .= "-- --------------------------------------------------------\n\n";
        $backup_content .= "DROP TABLE IF EXISTS `$table`;\n";
        $backup_content .= $row[1] . ";\n\n";
        
        // Get table data
        $result = $db->query("SELECT * FROM `$table`");
        $num_fields = $result->columnCount();
        $row_count = $result->rowCount();
        
        if ($row_count > 0) {
            $backup_content .= "-- --------------------------------------------------------\n";
            $backup_content .= "-- Dumping data for table `$table` ($row_count rows)\n";
            $backup_content .= "-- --------------------------------------------------------\n\n";
            $backup_content .= "INSERT INTO `$table` VALUES ";
            
            $first_row = true;
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                if (!$first_row) {
                    $backup_content .= ",";
                }
                $backup_content .= "\n(";
                
                for ($i = 0; $i < $num_fields; $i++) {
                    if ($i > 0) {
                        $backup_content .= ", ";
                    }
                    
                    if ($row[$i] === null) {
                        $backup_content .= "NULL";
                    } else {
                        $backup_content .= "'" . addslashes($row[$i]) . "'";
                    }
                }
                $backup_content .= ")";
                $first_row = false;
            }
            $backup_content .= ";\n\n";
        }
    }
    
    $backup_content .= "COMMIT;\n";
    $backup_content .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    $backup_content .= "-- Automatic backup completed successfully\n";
    
    // Save backup to file
    $backup_path = $backup_dir . $backup_filename;
    $bytes_written = file_put_contents($backup_path, $backup_content);
    
    if ($bytes_written === false) {
        throw new Exception("Failed to write backup file");
    }
    
    echo "Backup created successfully: $backup_filename\n";
    echo "File size: " . formatBytes($bytes_written) . "\n";
    
    // Log the automatic backup (use system user ID = 1 for automatic backups)
    $stmt = $db->prepare("
        INSERT INTO audit_log (user_id, action, table_name, record_id, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        1, // System user
        'AUTO_DATABASE_BACKUP',
        'system',
        null,
        'localhost',
        'Automatic Backup Scheduler'
    ]);
    
    // Clean up old backups (keep only last 10 automatic backups)
    $auto_backups = [];
    foreach ($files as $file) {
        if (strpos($file, 'auto_backup_') === 0 && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $filepath = $backup_dir . $file;
            $auto_backups[] = [
                'file' => $file,
                'time' => filemtime($filepath)
            ];
        }
    }
    
    // Sort by time (newest first)
    usort($auto_backups, function($a, $b) {
        return $b['time'] - $a['time'];
    });
    
    // Remove old backups (keep only 10 most recent)
    if (count($auto_backups) > 10) {
        $to_delete = array_slice($auto_backups, 10);
        foreach ($to_delete as $old_backup) {
            $old_path = $backup_dir . $old_backup['file'];
            if (file_exists($old_path)) {
                unlink($old_path);
                echo "Cleaned up old backup: " . $old_backup['file'] . "\n";
            }
        }
    }
    
    echo "Automatic backup process completed successfully.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Automatic backup failed: " . $e->getMessage());
    exit(1);
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>