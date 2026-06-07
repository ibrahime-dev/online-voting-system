<?php
/**
 * User Management - Admin Panel
 * Online Voting System - Wollo University
 */
require_once '../config/config.php';
requireLogin();

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'super_admin' && $_SESSION['user_type'] !== 'sub_admin') {
    header('Location: ../dashboard.php');
    exit();
}

$message = '';
$error = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);
    
    try {
        $db = getDBConnection();
        
        if ($action === 'verify_voter' && $user_id) {
            $status = $_POST['status'] ?? 'verified';
            
            $stmt = $db->prepare("
                UPDATE voter_profiles 
                SET verification_status = ?, verification_date = NOW(), verified_by = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$status, $_SESSION['user_id'], $user_id]);
            
            $stmt = $db->prepare("UPDATE users SET is_verified = ? WHERE user_id = ?");
            $stmt->execute([$status === 'verified' ? 1 : 0, $user_id]);
            
            logActivity($_SESSION['user_id'], 'VOTER_' . strtoupper($status), 'voter_profiles', $user_id);
            $message = "Voter " . ($status === 'verified' ? 'approved' : 'rejected') . " successfully.";
            
        } elseif ($action === 'toggle_status' && $user_id) {
            $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            logActivity($_SESSION['user_id'], 'USER_STATUS_TOGGLE', 'users', $user_id);
            $message = "User status updated successfully.";
            
        } elseif ($action === 'delete_user' && $user_id) {
            // Prevent self-deletion
            if ($user_id == $_SESSION['user_id']) {
                throw new Exception("Cannot delete your own account.");
            }
            
            // Get user info to check permissions
            $stmt = $db->prepare("SELECT username, user_type, first_name, last_name FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $target_user = $stmt->fetch();
            
            if (!$target_user) {
                throw new Exception("User not found.");
            }
            
            // Check permissions: regular admin can only delete voters, super_admin can delete anyone
            if ($_SESSION['user_type'] === 'admin' && in_array($target_user['user_type'], ['admin', 'super_admin'])) {
                throw new Exception("Admins cannot delete other admins or super admins.");
            }
            
            if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'super_admin' && $_SESSION['user_type'] !== 'sub_admin') {
                throw new Exception("Insufficient permissions to delete users.");
            }
            
            // Additional restrictions for sub-admins
            if ($_SESSION['user_type'] === 'sub_admin' && $target_user['user_type'] !== 'voter') {
                throw new Exception("Sub-admins can only delete voter accounts.");
            }
            
            // Start transaction for safe deletion
            $db->beginTransaction();
            
            try {
                // Delete related records first (to handle foreign key constraints)
                $stmt = $db->prepare("DELETE FROM votes WHERE voter_id = ?");
                $stmt->execute([$user_id]);                                                                                                                                                                                                                                                                                                                                                                                                                         
                
                $stmt = $db->prepare("DELETE FROM audit_log WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                $stmt = $db->prepare("DELETE FROM voter_profiles WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                $stmt = $db->prepare("DELETE FROM login_attempts WHERE username = ?");
                $stmt->execute([$target_user['username']]);
                
                // Finally delete the user
                $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                if ($stmt->rowCount() > 0) {
                    $db->commit();
                    logActivity($_SESSION['user_id'], 'USER_DELETE', 'users', $user_id);
                    $message = "User '{$target_user['first_name']} {$target_user['last_name']}' and all associated data deleted successfully.";
                } else {
                    $db->rollback();
                    throw new Exception("User not found or could not be deleted.");
                }
                
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
        }
        
    } catch (Exception $e) {
        $error = 'Action failed: ' . $e->getMessage();
    }
}

try {
    $db = getDBConnection();
    
    // Get all users with voter profile info
    $stmt = $db->prepare("
        SELECT u.*, vp.national_id, vp.voter_id, vp.verification_status, vp.verification_date,
               (SELECT COUNT(*) FROM votes v WHERE v.voter_id = u.user_id) as vote_count
        FROM users u
        LEFT JOIN voter_profiles vp ON u.user_id = vp.user_id
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    // Get statistics
    $stats = [];
    $stmt = $db->query("SELECT COUNT(*) as total FROM users");
    $stats['total_users'] = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'sub_admin'");
    $stats['sub_admin_users'] = $stmt->fetch()['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM voter_profiles WHERE verification_status = 'pending'");
    $stats['pending_verifications'] = $stmt->fetch()['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
    $stats['active_users'] = $stmt->fetch()['count'];
    
} catch (Exception $e) {
    $error = 'Error loading user data: ' . $e->getMessage();
    $users = [];
    $stats = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
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
            <h1>👥 User Management</h1>
        </div>
    </header>
    
    <nav class="admin-nav">
        <div class="container">
            <ul>
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="users.php" class="active">User Management</a></li>
                <li><a href="elections.php">Elections</a></li>
                <li><a href="candidates.php">Candidates</a></li>
                <li><a href="reports.php">Reports</a></li>
                <?php if ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'super_admin'): ?>
                <li><a href="settings.php">Settings</a></li>
                <?php endif; ?>
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
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_users'] ?? 0; ?></div>
                    <div>Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['sub_admin_users'] ?? 0; ?></div>
                    <div>Sub Admin Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['pending_verifications'] ?? 0; ?></div>
                    <div>Pending Verifications</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['active_users'] ?? 0; ?></div>
                    <div>Active Users</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters">
                <label>Filter by:</label>
                <select id="userTypeFilter" onchange="filterUsers()">
                    <option value="">All User Types</option>
                    <option value="voter">Voters</option>
                    <option value="sub_admin">Sub Admins</option>
                    <option value="super_admin">Super Admins</option>
                </select>
                
                <select id="statusFilter" onchange="filterUsers()">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                
                <select id="verificationFilter" onchange="filterUsers()">
                    <option value="">All Verifications</option>
                    <option value="verified">Verified</option>
                    <option value="pending">Pending</option>
                    <option value="rejected">Rejected</option>
                </select>
                
                <input type="text" id="searchInput" placeholder="Search users..." onkeyup="filterUsers()" style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 3px;">
            </div>
            
            <!-- Users Table -->
            <div style="overflow-x: auto;">
                <table class="users-table" id="usersTable">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Contact</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Verification</th>
                            <th>Votes</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr data-user-id="<?php echo $user['user_id']; ?>"
                                data-user-type="<?php echo $user['user_type']; ?>" 
                                data-status="<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>"
                                data-verification="<?php echo $user['verification_status'] ?? 'none'; ?>">
                                <td>
                                    <div style="display: flex; align-items: center; gap: 1rem;">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: bold;">
                                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                            </div>
                                            <div style="font-size: 0.875rem; color: #666;">
                                                @<?php echo htmlspecialchars($user['username']); ?>
                                            </div>
                                            <?php if ($user['voter_id']): ?>
                                                <div style="font-size: 0.75rem; color: #999;">
                                                    ID: <?php echo htmlspecialchars($user['voter_id']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($user['email']); ?></div>
                                    <?php if ($user['phone_number']): ?>
                                        <div style="font-size: 0.875rem; color: #666;">
                                            <?php echo htmlspecialchars($user['phone_number']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="user-type-<?php echo $user['user_type']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $user['user_type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['verification_status']): ?>
                                        <span class="status-badge status-<?php echo $user['verification_status']; ?>">
                                            <?php echo ucfirst($user['verification_status']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #999;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-weight: bold; color: #28a745;">
                                        <?php echo $user['vote_count']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- View Details Button for all users -->
                                        <a href="user-details.php?id=<?php echo $user['user_id']; ?>" 
                                           class="btn btn-info btn-xs">👁️ View Details</a>
                                        
                                        <?php if ($user['verification_status'] === 'pending'): ?>
                                            <button onclick="verifyVoter(<?php echo $user['user_id']; ?>, 'verified')" 
                                                    class="btn btn-success btn-xs">✓ Approve</button>
                                            <button onclick="verifyVoter(<?php echo $user['user_id']; ?>, 'rejected')" 
                                                    class="btn btn-danger btn-xs">✗ Reject</button>
                                        <?php endif; ?>
                                        
                                        <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                            <button onclick="toggleUserStatus(<?php echo $user['user_id']; ?>, <?php echo $user['is_active'] ? 'true' : 'false'; ?>)" 
                                                    class="btn btn-secondary btn-xs" id="status-btn-<?php echo $user['user_id']; ?>">
                                                <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php 
                        // Show delete button based on permissions
                        $canDelete = false;
                        if ($user['user_id'] !== $_SESSION['user_id']) {
                            if ($_SESSION['user_type'] === 'super_admin') {
                                $canDelete = true; // Super admin can delete anyone
                            } elseif ($_SESSION['user_type'] === 'admin' && $user['user_type'] === 'voter') {
                                $canDelete = true; // Regular admin can only delete voters
                            } elseif ($_SESSION['user_type'] === 'sub_admin' && $user['user_type'] === 'voter') {
                                $canDelete = true; // Sub-admin can only delete voters
                            }
                        }
                        
                        if ($canDelete): ?>
                            <button onclick="deleteUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>')" 
                                    class="btn btn-danger btn-xs">Delete</button>
                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (empty($users)): ?>
                <div style="text-align: center; padding: 3rem; color: #666;">
                    <h3>No users found</h3>
                    <p>No users are registered in the system yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function filterUsers() {
            const userTypeFilter = document.getElementById('userTypeFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const verificationFilter = document.getElementById('verificationFilter').value;
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            
            const rows = document.querySelectorAll('#usersTable tbody tr');
            
            rows.forEach(row => {
                const userType = row.dataset.userType;
                const status = row.dataset.status;
                const verification = row.dataset.verification;
                const text = row.textContent.toLowerCase();
                
                let show = true;
                
                if (userTypeFilter && userType !== userTypeFilter) show = false;
                if (statusFilter && status !== statusFilter) show = false;
                if (verificationFilter && verification !== verificationFilter) show = false;
                if (searchInput && !text.includes(searchInput)) show = false;
                
                row.style.display = show ? '' : 'none';
            });
        }
        
        function verifyVoter(userId, status) {
            const action = status === 'verified' ? 'approve' : 'reject';
            
            if (confirm(`Are you sure you want to ${action} this voter?`)) {
                performAction('verify_voter', { user_id: userId, status: status }, function(response) {
                    if (response.success) {
                        showMessage(response.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    }
                });
            }
        }
        
        function toggleUserStatus(userId, currentStatus) {
            const action = currentStatus ? 'deactivate' : 'activate';
            
            if (confirm(`Are you sure you want to ${action} this user?`)) {
                performAction('toggle_status', { user_id: userId }, function(response) {
                    if (response.success) {
                        showMessage(response.message, 'success');
                        
                        // Update button text and status
                        const btn = document.getElementById(`status-btn-${userId}`);
                        if (btn) {
                            btn.textContent = response.new_status ? 'Deactivate' : 'Activate';
                            btn.onclick = () => toggleUserStatus(userId, response.new_status);
                        }
                        
                        // Update status badge
                        const row = btn.closest('tr');
                        const statusBadge = row.querySelector('.status-badge');
                        if (statusBadge) {
                            statusBadge.className = `status-badge status-${response.new_status ? 'active' : 'inactive'}`;
                            statusBadge.textContent = response.new_status ? 'Active' : 'Inactive';
                        }
                    }
                });
            }
        }
        
        function deleteUser(userId, userName) {
            if (confirm(`Are you sure you want to delete user "${userName}"?\n\nThis action cannot be undone and will remove all associated data including votes.`)) {
                performAction('delete_user', { user_id: userId }, function(response) {
                    if (response.success) {
                        showMessage(response.message, 'success');
                        
                        // Remove the row from table
                        const row = document.querySelector(`tr[data-user-id="${userId}"]`);
                        if (row) {
                            row.style.transition = 'opacity 0.3s ease';
                            row.style.opacity = '0';
                            setTimeout(() => {
                                row.remove();
                                updateStats();
                            }, 300);
                        }
                    }
                });
            }
        }
        
        function performAction(action, data, callback) {
            showLoading();
            
            const formData = new FormData();
            formData.append('action', action);
            
            for (const key in data) {
                formData.append(key, data[key]);
            }
            
            fetch('user-actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    callback(data);
                } else {
                    showMessage(data.message || 'An error occurred', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showMessage('Network error: ' + error.message, 'error');
            });
        }
        
        function updateStats() {
            fetch('user-actions.php', {
                method: 'POST',
                body: new FormData().append('action', 'get_user_stats')
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update statistics display
                    const stats = data.stats;
                    document.querySelector('.stats-grid .stat-card:nth-child(1) .stat-number').textContent = stats.total_users;
                    document.querySelector('.stats-grid .stat-card:nth-child(2) .stat-number').textContent = stats.sub_admin_users;
                    document.querySelector('.stats-grid .stat-card:nth-child(3) .stat-number').textContent = stats.pending_verifications;
                    document.querySelector('.stats-grid .stat-card:nth-child(4) .stat-number').textContent = stats.active_users;
                }
            });
        }
        
        function showLoading() {
            let overlay = document.getElementById('loading-overlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'loading-overlay';
                overlay.className = 'loading-overlay';
                overlay.innerHTML = '<div class="loading-spinner"></div>';
                document.body.appendChild(overlay);
            }
            overlay.style.display = 'flex';
        }
        
        function hideLoading() {
            const overlay = document.getElementById('loading-overlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        }
        
        function showMessage(message, type) {
            // Remove existing messages
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => alert.remove());
            
            // Create new message
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            
            // Insert at top of main content
            const main = document.querySelector('main .container');
            main.insertBefore(alert, main.firstChild);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.style.transition = 'opacity 0.3s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }
            }, 5000);
        }
        
        // Auto-refresh functionality
        function refreshUserData() {
            // This would typically use AJAX to refresh data
            console.log('Refreshing user data...');
        }
        
        // Refresh every 30 seconds
        setInterval(refreshUserData, 30000);
    </script>
</body>
</html>