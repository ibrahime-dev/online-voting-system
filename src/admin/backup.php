<?php
/**
 * Database Backup Management - Online Voting System
 * Wollo University - Professional Backup System
 */
require_once '../config/config.php';
requireLogin();

// Check if user is admin or super admin
if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'super_admin') {
    header('Location: ../dashboard.php');
    exit();
}

$message = '';
$error = '';

// Create backups directory if it doesn't exist
$backup_dir = '../backups/';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = getDBConnection();
        
        if ($action === 'create_backup') {
            // Generate database backup
            $backup_filename = 'voting_system_backup_' . date('Y-m-d_H-i-s') . '.sql';
            
            // Start building the SQL backup
            $backup_content = "-- Online Voting System Database Backup\n";
            $backup_content .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
            $backup_content .= "-- Database: online_voting_system\n";
            $backup_content .= "-- Created by: " . $_SESSION['first_name'] . " " . $_SESSION['last_name'] . "\n\n";
            
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
            
            // Backup each table
            foreach ($tables as $table) {
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
                
                if ($result->rowCount() > 0) {
                    $backup_content .= "-- --------------------------------------------------------\n";
                    $backup_content .= "-- Dumping data for table `$table`\n";
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
            $backup_content .= "-- Backup completed successfully\n";
            
            // Save backup to file
            $backup_path = $backup_dir . $backup_filename;
            file_put_contents($backup_path, $backup_content);
            
            // Log activity
            logActivity($_SESSION['user_id'], 'DATABASE_BACKUP', "Backup created: $backup_filename");
            
            // Send file for download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $backup_filename . '"');
            header('Content-Length: ' . strlen($backup_content));
            echo $backup_content;
            exit();
            
        } elseif ($action === 'delete_backup') {
            $filename = $_POST['filename'] ?? '';
            $backup_path = $backup_dir . $filename;
            
            if (file_exists($backup_path) && strpos($filename, '..') === false) {
                unlink($backup_path);
                logActivity($_SESSION['user_id'], 'BACKUP_DELETE', "Deleted backup: $filename");
                $message = "Backup file deleted successfully.";
            } else {
                throw new Exception('Backup file not found.');
            }
            
        } elseif ($action === 'download_backup') {
            $filename = $_POST['filename'] ?? '';
            $backup_path = $backup_dir . $filename;
            
            if (file_exists($backup_path) && strpos($filename, '..') === false) {
                logActivity($_SESSION['user_id'], 'BACKUP_DOWNLOAD', "Downloaded backup: $filename");
                
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . filesize($backup_path));
                readfile($backup_path);
                exit();
            } else {
                throw new Exception('Backup file not found.');
            }
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Backup error: " . $e->getMessage());
    }
}

// Get existing backups
$existing_backups = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $filepath = $backup_dir . $file;
            $existing_backups[] = [
                'filename' => $file,
                'size' => filesize($filepath),
                'date' => filemtime($filepath)
            ];
        }
    }
    // Sort by date (newest first)
    usort($existing_backups, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

// Get database statistics
try {
    $db = getDBConnection();
    
    // Get database size
    $stmt = $db->query("
        SELECT 
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS db_size_mb
        FROM information_schema.tables 
        WHERE table_schema = 'online_voting_system'
    ");
    $db_size = $stmt->fetch()['db_size_mb'] ?? 0;
    
    // Get table count
    $stmt = $db->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = 'online_voting_system'");
    $table_count = $stmt->fetch()['table_count'];
    
    // Get record counts
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $user_count = $stmt->fetch()['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM elections");
    $election_count = $stmt->fetch()['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM votes");
    $vote_count = $stmt->fetch()['count'];
    
} catch (Exception $e) {
    $db_size = 0;
    $table_count = 0;
    $user_count = 0;
    $election_count = 0;
    $vote_count = 0;
}

// Check for automatic backup schedule
$last_backup_date = '';
if (!empty($existing_backups)) {
    $last_backup_date = date('Y-m-d H:i:s', $existing_backups[0]['date']);
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup Management - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        /* Clean white background for admin */
        body {
            background-color: #ffffff !important;
        }
        
        .hero {
            background: #f7fafc !important;
            color: #1a202c !important;
            border: 1px solid #e2e8f0 !important;
        }
        
        /* Backup specific styles */
        .backup-dashboard {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .backup-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .backup-controls {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 15px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 15px var(--shadow-light);
        }
        
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .backup-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        
        .backup-list {
            margin-top: 2rem;
        }
        
        .backup-item {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .backup-item:hover {
            box-shadow: 0 4px 15px var(--shadow-light);
            border-color: #ff7200;
        }
        
        .backup-info h4 {
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        
        .backup-meta {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .backup-actions-item {
            display: flex;
            gap: 0.5rem;
        }
        
        .schedule-info {
            background: #e8f5e8;
            border: 1px solid #4caf50;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            color: #2e7d32;
        }
        
        .schedule-info.warning {
            background: #fff3e0;
            border-color: #ff9800;
            color: #f57c00;
        }
        
        .schedule-info.error {
            background: #ffebee;
            border-color: #f44336;
            color: #c62828;
        }
        
        .progress-bar {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 1rem;
        }
        
        .progress-fill {
            height: 100%;
            background: #4caf50;
            border-radius: 3px;
            transition: width 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .backup-dashboard {
                grid-template-columns: 1fr;
            }
            
            .stat-grid {
                grid-template-columns: 1fr;
            }
            
            .backup-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .backup-actions-item {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="admin-header">
            <div class="container">
                <h1>🛡️ Admin Panel - Database Backup Management</h1>
                <p>Secure and automated database backup system</p>
            </div>
        </div>
    </header>
    
    <nav class="admin-nav">
        <div class="container">
            <ul>
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="users.php">User Management</a></li>
                <li><a href="elections.php">Elections</a></li>
                <li><a href="candidates.php">Candidates</a></li>
                <li><a href="reports.php">Reports</a></li>
                <?php if ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'super_admin'): ?>
                <li><a href="settings.php">Settings</a></li>
                <li><a href="backup.php" class="active">Backup</a></li>
                <?php endif; ?>
                <li><a href="../logout.php">Logout</a></li>
                <li>
                    <div class="theme-toggle" onclick="toggleTheme()">
                        <span class="icon">🌙</span>
                        <span class="text">Dark</span>
                    </div>
                </li>
            </ul>
        </div>
    </nav>

    <main>
        <div class="container">
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <strong>✅ Success!</strong> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <strong>❌ Error!</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Backup Dashboard -->
            <div class="backup-dashboard">
                <!-- Database Statistics -->
                <div class="backup-stats">
                    <h2>📊 Database Overview</h2>
                    <div class="stat-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $db_size; ?> MB</div>
                            <div class="stat-label">Database Size</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $table_count; ?></div>
                            <div class="stat-label">Tables</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo number_format($user_count); ?></div>
                            <div class="stat-label">Users</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo number_format($vote_count); ?></div>
                            <div class="stat-label">Votes Cast</div>
                        </div>
                    </div>
                    
                    <?php if ($last_backup_date): ?>
                        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.2);">
                            <strong>Last Backup:</strong> <?php echo $last_backup_date; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Backup Controls -->
                <div class="backup-controls">
                    <h2>🔧 Backup Controls</h2>
                    
                    <div class="backup-actions">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="create_backup">
                            <button type="submit" class="btn btn-primary" onclick="return confirmBackup()">
                                💾 Create Backup Now
                            </button>
                        </form>
                    </div>
                    
                    <!-- Automatic Backup Schedule Info -->
                    <?php
                    $days_since_backup = 0;
                    if ($last_backup_date) {
                        $days_since_backup = floor((time() - strtotime($last_backup_date)) / (60 * 60 * 24));
                    }
                    ?>
                    
                    <div class="schedule-info <?php echo $days_since_backup >= 5 ? 'error' : ($days_since_backup >= 3 ? 'warning' : ''); ?>">
                        <h4>📅 Backup Schedule Status</h4>
                        <?php if ($days_since_backup == 0): ?>
                            <p><strong>✅ Up to date:</strong> Backup created today</p>
                        <?php elseif ($days_since_backup < 3): ?>
                            <p><strong>✅ Recent:</strong> Last backup was <?php echo $days_since_backup; ?> day(s) ago</p>
                        <?php elseif ($days_since_backup < 5): ?>
                            <p><strong>⚠️ Due soon:</strong> Last backup was <?php echo $days_since_backup; ?> day(s) ago</p>
                        <?php else: ?>
                            <p><strong>🚨 Overdue:</strong> Last backup was <?php echo $days_since_backup; ?> day(s) ago</p>
                            <p><em>Recommended: Create a backup every 5 days</em></p>
                        <?php endif; ?>
                        
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo min(100, ($days_since_backup / 5) * 100); ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Existing Backups -->
            <div class="admin-section">
                <div class="section-header">
                    📁 Backup History (<?php echo count($existing_backups); ?> files)
                </div>
                <div class="section-content">
                    <?php if (empty($existing_backups)): ?>
                        <div class="no-data">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">📦</div>
                            <p>No backup files found.</p>
                            <p>Create your first backup using the button above.</p>
                        </div>
                    <?php else: ?>
                        <div class="backup-list">
                            <?php foreach ($existing_backups as $backup): ?>
                                <div class="backup-item">
                                    <div class="backup-info">
                                        <h4>📄 <?php echo htmlspecialchars($backup['filename']); ?></h4>
                                        <div class="backup-meta">
                                            <span>📅 <?php echo date('M j, Y g:i A', $backup['date']); ?></span> • 
                                            <span>📊 <?php echo formatFileSize($backup['size']); ?></span> • 
                                            <span>⏰ <?php echo floor((time() - $backup['date']) / (60 * 60 * 24)); ?> days ago</span>
                                        </div>
                                    </div>
                                    <div class="backup-actions-item">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="download_backup">
                                            <input type="hidden" name="filename" value="<?php echo htmlspecialchars($backup['filename']); ?>">
                                            <button type="submit" class="btn btn-secondary btn-xs">
                                                ⬇️ Download
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;" onsubmit="return confirmDelete('<?php echo htmlspecialchars($backup['filename']); ?>')">
                                            <input type="hidden" name="action" value="delete_backup">
                                            <input type="hidden" name="filename" value="<?php echo htmlspecialchars($backup['filename']); ?>">
                                            <button type="submit" class="btn btn-danger btn-xs">
                                                🗑️ Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Backup Information -->
            <div class="admin-section">
                <div class="section-header">
                    ℹ️ Backup Information
                </div>
                <div class="section-content">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                        <div class="maintenance-card">
                            <h4>🔒 Security Features</h4>
                            <ul style="margin: 1rem 0; padding-left: 1.5rem;">
                                <li>Complete database structure and data</li>
                                <li>Compressed SQL format</li>
                                <li>Automatic file naming with timestamps</li>
                                <li>Secure download with activity logging</li>
                            </ul>
                        </div>
                        
                        <div class="maintenance-card">
                            <h4>📋 Best Practices</h4>
                            <ul style="margin: 1rem 0; padding-left: 1.5rem;">
                                <li>Create backups every 5 days</li>
                                <li>Store backups in multiple locations</li>
                                <li>Test backup restoration regularly</li>
                                <li>Keep at least 3 recent backups</li>
                            </ul>
                        </div>
                        
                        <div class="maintenance-card">
                            <h4>⚠️ Important Notes</h4>
                            <ul style="margin: 1rem 0; padding-left: 1.5rem;">
                                <li>Backups include all sensitive data</li>
                                <li>Store backup files securely</li>
                                <li>Regular cleanup prevents storage issues</li>
                                <li>Monitor backup schedule status</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2018 Wollo University - Informatics College. All rights reserved.</p>
        </div>
    </footer>

    <script src="../../assets/js/main.js"></script>
    <script>
        function confirmBackup() {
            return confirm('Create a new database backup? This will include all current data and may take a few moments to generate.');
        }
        
        function confirmDelete(filename) {
            return confirm(`Are you sure you want to delete the backup file "${filename}"? This action cannot be undone.`);
        }
        
        // Auto-refresh backup status every 5 minutes
        setInterval(function() {
            // You could implement AJAX refresh here if needed
        }, 300000);
        
        // Show backup creation progress (if needed)
        function showBackupProgress() {
            const button = document.querySelector('button[type="submit"]');
            if (button) {
                button.innerHTML = '⏳ Creating Backup...';
                button.disabled = true;
            }
        }
        
        // Add event listener to backup form
        document.querySelector('form[method="POST"]').addEventListener('submit', function() {
            showBackupProgress();
        });
    </script>
</body>
</html>