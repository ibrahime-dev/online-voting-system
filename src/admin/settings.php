<?php
/**
 * System Settings - Simple Reset & Clear
 * Online Voting System - Wollo University
 */
require_once '../config/config.php';
requireLogin();

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'super_admin') {
    header('Location: ../dashboard.php');
    exit();
}

$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = getDBConnection();
        
        if ($action === 'create_sub_admin') {
            // Create new sub-admin account
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            // Validation
            if (empty($first_name) || empty($last_name) || empty($email) || empty($username) || empty($password)) {
                throw new Exception('All fields are required for sub-admin creation.');
            }
            
            if (strlen($password) < 6) {
                throw new Exception('Password must be at least 6 characters long.');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Please enter a valid email address.');
            }
            
            if (strlen($username) < 3) {
                throw new Exception('Username must be at least 3 characters long.');
            }
            
            // Check if username or email already exists
            $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                throw new Exception('Username or email already exists.');
            }
            
            // Create sub-admin account
            $db->beginTransaction();
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, date_of_birth, user_type, is_verified, is_active, created_at) VALUES (?, ?, ?, ?, ?, '1990-01-01', 'sub_admin', 1, 1, NOW())");
            $stmt->execute([$username, $email, $hashed_password, $first_name, $last_name]);
            
            $user_id = $db->lastInsertId();
            
            // Check if sub_admins table exists, if not create it
            try {
                $stmt = $db->prepare("INSERT INTO sub_admins (user_id, created_by, created_at, is_active) VALUES (?, ?, NOW(), 1)");
                $stmt->execute([$user_id, $_SESSION['user_id']]);
            } catch (Exception $e) {
                // If sub_admins table doesn't exist, just continue (user is still created as sub_admin type)
                error_log("Sub_admins table insert failed: " . $e->getMessage());
            }
            
            $db->commit();
            
            logActivity($_SESSION['user_id'], 'SUB_ADMIN_CREATED', "Created sub-admin: $username");
            $message = "Sub-admin account created successfully! Username: $username, Email: $email";
            
        } elseif ($action === 'restore_database' && $_SESSION['user_type'] === 'super_admin') {
            // Database recovery from uploaded file
            if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Please select a valid backup file to restore.');
            }
            
            $uploaded_file = $_FILES['backup_file'];
            $file_extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
            
            if ($file_extension !== 'sql') {
                throw new Exception('Only SQL backup files are allowed.');
            }
            
            if ($uploaded_file['size'] > 50 * 1024 * 1024) { // 50MB limit
                throw new Exception('Backup file is too large. Maximum size is 50MB.');
            }
            
            $backup_content = file_get_contents($uploaded_file['tmp_name']);
            
            if (empty($backup_content)) {
                throw new Exception('Backup file is empty or corrupted.');
            }
            
            // Disable foreign key checks for restoration
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // Split SQL content into individual statements
            $statements = array_filter(array_map('trim', explode(';', $backup_content)));
            
            $db->beginTransaction();
            
            foreach ($statements as $statement) {
                if (!empty($statement) && !preg_match('/^--/', $statement)) {
                    try {
                        $db->exec($statement);
                    } catch (Exception $e) {
                        // Log the error but continue with other statements
                        error_log("SQL Error during restore: " . $e->getMessage() . " - Statement: " . substr($statement, 0, 100));
                    }
                }
            }
            
            $db->commit();
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            logActivity($_SESSION['user_id'], 'DATABASE_RESTORE', "Database restored from file: " . $uploaded_file['name']);
            $message = 'Database successfully restored from backup file: ' . htmlspecialchars($uploaded_file['name']);
            
            
        } elseif ($action === 'reset_system' && $_SESSION['user_type'] === 'super_admin') {
            // Clear all election data but keep admin users
            $db->beginTransaction();
            
            // Delete in correct order to avoid foreign key constraints
            $db->query("DELETE FROM votes");
            $db->query("DELETE FROM candidates");
            $db->query("DELETE FROM positions");
            $db->query("DELETE FROM elections");
            $db->query("DELETE FROM voter_profiles WHERE user_id NOT IN (SELECT user_id FROM users WHERE user_type IN ('admin', 'super_admin'))");
            $db->query("DELETE FROM users WHERE user_type = 'voter'");
            $db->query("DELETE FROM audit_log");
            $db->query("DELETE FROM login_attempts");
            
            $db->commit();
            
            logActivity($_SESSION['user_id'], 'SYSTEM_RESET');
            $message = 'System reset successfully! All elections, votes, and voter data have been cleared.';
            
        } elseif ($action === 'clear_elections') {
            // Clear only election data, keep users
            $db->beginTransaction();
            
            $db->query("DELETE FROM votes");
            $db->query("DELETE FROM candidates");
            $db->query("DELETE FROM positions");
            $db->query("DELETE FROM elections");
            
            $db->commit();
            
            logActivity($_SESSION['user_id'], 'ELECTIONS_CLEARED');
            $message = 'All elections and voting data have been cleared successfully.';
            
        } elseif ($action === 'clear_history') {
            // Clear audit logs and login attempts
            $db->query("DELETE FROM audit_log");
            $db->query("DELETE FROM login_attempts");
            
            logActivity($_SESSION['user_id'], 'HISTORY_CLEARED');
            $message = 'System history and logs have been cleared successfully.';
        }
        
    } catch (Exception $e) {
        if (isset($db)) {
            $db->rollBack();
        }
        $error = 'Action failed: ' . $e->getMessage();
    }
}

try {
    $db = getDBConnection();
    
    // Get system statistics
    $stats = [];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM elections");
    $stats['elections'] = $stmt->fetch()['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM votes");
    $stats['votes'] = $stmt->fetch()['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'voter'");
    $stats['voters'] = $stmt->fetch()['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE user_type IN ('admin', 'super_admin')");
    $stats['admins'] = $stmt->fetch()['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'sub_admin'");
    $stats['sub_admins'] = $stmt->fetch()['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM audit_log");
    $stats['audit_logs'] = $stmt->fetch()['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM login_attempts");
    $stats['login_attempts'] = $stmt->fetch()['count'];
    
} catch (Exception $e) {
    $error = 'Error loading system data: ' . $e->getMessage();
    $stats = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Admin Panel</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
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
        
        /* Ensure admin header is black */
        .admin-header {
            background: #000000 !important;
            color: white !important;
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="container">
            <h1>⚙️ System Settings</h1>
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
                <li><a href="settings.php" class="active">Settings</a></li>
                <li><a href="backup.php">Backup</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <main>
        <div class="container">
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- System Overview -->
            <div class="settings-section">
                <div class="settings-header">📊 System Overview</div>
                <div class="settings-content">
                    <div class="system-info">
                        <div class="info-card">
                            <div class="info-number"><?php echo number_format($stats['elections'] ?? 0); ?></div>
                            <div class="info-label">Elections</div>
                        </div>
                        <div class="info-card">
                            <div class="info-number"><?php echo number_format($stats['votes'] ?? 0); ?></div>
                            <div class="info-label">Total Votes</div>
                        </div>
                        <div class="info-card">
                            <div class="info-number"><?php echo number_format($stats['voters'] ?? 0); ?></div>
                            <div class="info-label">Voters</div>
                        </div>
                        <div class="info-card">
                            <div class="info-number"><?php echo number_format($stats['admins'] ?? 0); ?></div>
                            <div class="info-label">Admins</div>
                        </div>
                        <div class="info-card">
                            <div class="info-number"><?php echo number_format($stats['sub_admins'] ?? 0); ?></div>
                            <div class="info-label">Sub-Admins</div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 2rem; padding: 1rem; background: #e3f2fd; border-radius: 8px;">
                        <h4>📈 System Activity</h4>
                        <p><strong>Audit Log Entries:</strong> <?php echo number_format($stats['audit_logs'] ?? 0); ?></p>
                        <p><strong>Login Attempts:</strong> <?php echo number_format($stats['login_attempts'] ?? 0); ?></p>
                        <p><strong>System Status:</strong> <span style="color: #28a745; font-weight: bold;">Online</span></p>
                    </div>
                </div>
            </div>
            
            <!-- System Actions -->
            <div class="settings-section">
                <div class="settings-header">🔧 System Actions</div>
                <div class="settings-content">
                    <div class="maintenance-actions">
                        
                        <!-- Create Sub-Admin -->
                        <div class="maintenance-card">
                            <h4>👤 Create Sub-Admin</h4>
                            <p>Create a new sub-admin account. Sub-admins have most admin privileges except settings access.</p>
                            <form method="POST" class="sub-admin-form">
                                <input type="hidden" name="action" value="create_sub_admin">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="first_name">First Name *</label>
                                        <input type="text" id="first_name" name="first_name" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="last_name">Last Name *</label>
                                        <input type="text" id="last_name" name="last_name" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="email">Email *</label>
                                        <input type="email" id="email" name="email" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="username">Username *</label>
                                        <input type="text" id="username" name="username" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="password">Password *</label>
                                    <input type="password" id="password" name="password" required minlength="6">
                                    <small>Minimum 6 characters</small>
                                </div>
                                <button type="submit" class="btn btn-primary">Create Sub-Admin</button>
                            </form>
                        </div>
                        
                        <!-- Database Recovery (Super Admin Only) -->
                        <?php if ($_SESSION['user_type'] === 'super_admin'): ?>
                        <div class="maintenance-card">
                            <h4>🔄 Database Recovery</h4>
                            <p><strong>WARNING:</strong> This will restore the database from a backup file. All current data will be replaced.</p>
                            <form method="POST" enctype="multipart/form-data" onsubmit="return confirmRestore()">
                                <input type="hidden" name="action" value="restore_database">
                                <div class="form-group">
                                    <label for="backup_file">Select Backup File (.sql):</label>
                                    <input type="file" id="backup_file" name="backup_file" accept=".sql" required>
                                    <small>Maximum file size: 50MB</small>
                                </div>
                                <button type="submit" class="btn btn-warning">Restore Database</button>
                            </form>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Clear Elections -->
                        <div class="maintenance-card">
                            <h4>🗳️ Clear Elections</h4>
                            <p>Remove all elections, candidates, positions, and votes. User accounts will be preserved.</p>
                            <form method="POST" onsubmit="return confirmClearElections()">
                                <input type="hidden" name="action" value="clear_elections">
                                <button type="submit" class="btn btn-warning">Clear All Elections</button>
                            </form>
                        </div>
                        
                        <!-- Clear History -->
                        <div class="maintenance-card">
                            <h4>📋 Clear History</h4>
                            <p>Remove audit logs and login attempt records. This will clear system activity history.</p>
                            <form method="POST" onsubmit="return confirmClearHistory()">
                                <input type="hidden" name="action" value="clear_history">
                                <button type="submit" class="btn btn-secondary">Clear System History</button>
                            </form>
                        </div>
                        
                        <!-- Reset System (Super Admin Only) -->
                        <?php if ($_SESSION['user_type'] === 'super_admin'): ?>
                        <div class="maintenance-card">
                            <h4>🔄 Reset System</h4>
                            <p><strong>WARNING:</strong> This will delete ALL data including elections, votes, and voter accounts. Only admin accounts will be preserved.</p>
                            <form method="POST" onsubmit="return confirmSystemReset()">
                                <input type="hidden" name="action" value="reset_system">
                                <button type="submit" class="btn btn-danger">Complete System Reset</button>
                            </form>
                        </div>
                        <?php endif; ?>
                        
                    </div>
                </div>
            </div>
            
            <!-- Quick Info -->
            <div class="settings-section">
                <div class="settings-header">ℹ️ Information</div>
                <div class="settings-content">
                    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px;">
                        <h4>System Information</h4>
                        <p><strong>Institution:</strong> Wollo University</p>
                        <p><strong>Current User:</strong> <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?> (<?php echo ucfirst($_SESSION['user_type']); ?>)</p>
                    </div>
                </div>
            </div>
            
        </div>
    </main>

    <script>
        function confirmBackup() {
            return confirm('Create database backup?\n\nThis will generate a complete backup file of the voting system database and download it to your computer.\n\nProceed with backup?');
        }
        
        function confirmRestore() {
            return confirm('⚠️ DANGER: Database Recovery ⚠️\n\nThis will REPLACE ALL current data with the backup file contents.\n\nAll current elections, votes, and user data will be LOST!\n\nAre you absolutely sure you want to proceed?');
        }
        
        function confirmClearElections() {
            return confirm('Are you sure you want to clear all elections?\n\nThis will delete:\n- All elections\n- All candidates\n- All votes\n- All positions\n\nUser accounts will be preserved.\n\nThis action cannot be undone!');
        }
        
        function confirmClearHistory() {
            return confirm('Are you sure you want to clear system history?\n\nThis will delete:\n- All audit logs\n- All login attempt records\n\nThis action cannot be undone!');
        }
        
        function confirmSystemReset() {
            if (confirm('⚠️ DANGER: Complete System Reset ⚠️\n\nThis will DELETE ALL DATA including:\n- All elections and votes\n- All voter accounts\n- All system history\n\nOnly admin accounts will be preserved.\n\nAre you absolutely sure?')) {
                if (confirm('This action is IRREVERSIBLE!\n\nType "RESET" in the next prompt to confirm.')) {
                    const confirmation = prompt('Type "RESET" to confirm complete system reset:');
                    if (confirmation === 'RESET') {
                        return true;
                    } else {
                        alert('System reset cancelled - confirmation text did not match.');
                        return false;
                    }
                }
            }
            return false;
        }
    </script>
</body>
</html>