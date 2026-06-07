<?php
/**
 * Admin Dashboard - Online Voting System
 * Wollo University - Integrated Project
 */
require_once '../config/config.php';
requireLogin();

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'super_admin' && $_SESSION['user_type'] !== 'sub_admin') {
    header('Location: ../dashboard.php');
    exit();
}

try {
    $db = getDBConnection();
    
    // Get system statistics
    $stats = [];
    
    // Total users
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = $stmt->fetch()['count'];
    
    // Pending verifications
    $stmt = $db->query("SELECT COUNT(*) as count FROM voter_profiles WHERE verification_status = 'pending'");
    $stats['pending_verifications'] = $stmt->fetch()['count'];
    
    // Active elections
    $stmt = $db->query("SELECT COUNT(*) as count FROM elections WHERE status = 'active'");
    $stats['active_elections'] = $stmt->fetch()['count'];
    
    // Total votes cast
    $stmt = $db->query("SELECT COUNT(*) as count FROM votes");
    $stats['total_votes'] = $stmt->fetch()['count'];
    
    // Recent activities
    $stmt = $db->prepare("
        SELECT al.*, u.username, u.first_name, u.last_name
        FROM audit_log al
        LEFT JOIN users u ON al.user_id = u.user_id
        ORDER BY al.timestamp DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_activities = $stmt->fetchAll();
    
    // Pending voter verifications
    $stmt = $db->prepare("
        SELECT vp.*, u.username, u.first_name, u.last_name, u.email, u.created_at
        FROM voter_profiles vp
        JOIN users u ON vp.user_id = u.user_id
        WHERE vp.verification_status = 'pending'
        ORDER BY u.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $pending_voters = $stmt->fetchAll();
    
    // Recent elections
    $stmt = $db->prepare("
        SELECT e.*, u.username as created_by_username,
               (SELECT COUNT(*) FROM votes v WHERE v.election_id = e.election_id) as vote_count
        FROM elections e
        LEFT JOIN users u ON e.created_by = u.user_id
        ORDER BY e.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_elections = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = 'Error loading admin dashboard.';
    error_log("Admin dashboard error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
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
            <h1>🛡️ Admin Dashboard - <?php echo SITE_NAME; ?></h1>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?> (<?php echo ucfirst($_SESSION['user_type']); ?>)</p>
        </div>
    </header>
    
    <nav class="admin-nav">
        <div class="container">
            <ul>
                <li><a href="index.php" class="active">Dashboard</a></li>
                <li><a href="users.php">User Management</a></li>
                <li><a href="elections.php">Elections</a></li>
                <li><a href="candidates.php">Candidates</a></li>
                <li><a href="reports.php">Reports</a></li>
                <?php if ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'super_admin'): ?>
                <li><a href="settings.php">Settings</a></li>
                <li><a href="backup.php">Backup</a></li>
                <?php endif; ?>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <main>
        <div class="container">
            <!-- Statistics Overview -->
            <div class="stats-overview">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['total_users']); ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['pending_verifications']); ?></div>
                    <div class="stat-label">Pending Verifications</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['active_elections']); ?></div>
                    <div class="stat-label">Active Elections</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['total_votes']); ?></div>
                    <div class="stat-label">Total Votes Cast</div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <!-- Pending Voter Verifications -->
                <div class="admin-section">
                    <div class="section-header">
                        📋 Pending Voter Verifications
                        <?php if ($stats['pending_verifications'] > 0): ?>
                            <span style="background: #dc3545; color: white; padding: 0.25rem 0.5rem; border-radius: 10px; font-size: 0.75rem; margin-left: 1rem;">
                                <?php echo $stats['pending_verifications']; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="section-content">
                        <?php if (empty($pending_voters)): ?>
                            <p class="no-data">No pending verifications.</p>
                        <?php else: ?>
                            <?php foreach ($pending_voters as $voter): ?>
                                <div class="voter-item">
                                    <div class="voter-info">
                                        <h4><?php echo htmlspecialchars($voter['first_name'] . ' ' . $voter['last_name']); ?></h4>
                                        <p><strong>Username:</strong> <?php echo htmlspecialchars($voter['username']); ?></p>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($voter['email']); ?></p>
                                        <p><strong>National ID:</strong> <?php echo htmlspecialchars($voter['national_id']); ?></p>
                                        <small>Registered: <?php echo date('M j, Y', strtotime($voter['created_at'])); ?></small>
                                    </div>
                                    <div class="voter-actions" style="display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">
                                        <div style="display: flex; gap: 1rem;">
                                            <button onclick="verifyVoter(<?php echo $voter['user_id']; ?>, 'verified')" class="btn btn-success btn-sm">✓ Approve</button>
                                            <button onclick="verifyVoter(<?php echo $voter['user_id']; ?>, 'rejected')" class="btn btn-danger btn-sm">✗ Reject</button>
                                        </div>
                                        <div>
                                            <a href="user-details.php?id=<?php echo $voter['user_id']; ?>" class="btn btn-info btn-sm">👁️ View Details</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div style="text-align: center; margin-top: 1rem;">
                                <a href="users.php" class="btn btn-secondary">View All Users</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent System Activities -->
                <div class="admin-section">
                    <div class="section-header">📊 Recent System Activities</div>
                    <div class="section-content">
                        <?php if (empty($recent_activities)): ?>
                            <p class="no-data">No recent activities.</p>
                        <?php else: ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div>
                                        <strong><?php echo htmlspecialchars($activity['action']); ?></strong>
                                        <?php if ($activity['username']): ?>
                                            by <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                                        <?php endif; ?>
                                        <?php if ($activity['table_name']): ?>
                                            <small>(<?php echo htmlspecialchars($activity['table_name']); ?>)</small>
                                        <?php endif; ?>
                                    </div>
                                    <small><?php echo date('M j, g:i A', strtotime($activity['timestamp'])); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Elections -->
            <div class="admin-section">
                <div class="section-header">🗳️ Recent Elections</div>
                <div class="section-content">
                    <?php if (empty($recent_elections)): ?>
                        <p class="no-data">No elections found. <a href="elections.php">Create your first election</a></p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #f8f9fa;">
                                        <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #dee2e6;">Title</th>
                                        <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #dee2e6;">Status</th>
                                        <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #dee2e6;">Votes</th>
                                        <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #dee2e6;">Created</th>
                                        <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #dee2e6;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_elections as $election): ?>
                                        <tr>
                                            <td style="padding: 1rem; border-bottom: 1px solid #f1f1f1;">
                                                <strong><?php echo htmlspecialchars($election['title']); ?></strong>
                                                <br><small><?php echo htmlspecialchars(substr($election['description'], 0, 50)); ?>...</small>
                                            </td>
                                            <td style="padding: 1rem; border-bottom: 1px solid #f1f1f1;">
                                                <span style="padding: 0.25rem 0.5rem; border-radius: 10px; font-size: 0.75rem; 
                                                      background: <?php echo $election['status'] === 'active' ? '#28a745' : ($election['status'] === 'completed' ? '#6c757d' : '#ffc107'); ?>; 
                                                      color: white;">
                                                    <?php echo ucfirst($election['status']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 1rem; border-bottom: 1px solid #f1f1f1;">
                                                <?php echo number_format($election['vote_count']); ?>
                                            </td>
                                            <td style="padding: 1rem; border-bottom: 1px solid #f1f1f1;">
                                                <?php echo date('M j, Y', strtotime($election['created_at'])); ?>
                                            </td>
                                            <td style="padding: 1rem; border-bottom: 1px solid #f1f1f1;">
                                                <a href="election-details.php?id=<?php echo $election['election_id']; ?>" class="btn btn-secondary btn-sm">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="../../assets/js/main.js"></script>
    <script>
        function verifyVoter(userId, status) {
            const action = status === 'verified' ? 'approve' : 'reject';
            
            if (confirm(`Are you sure you want to ${action} this voter?`)) {
                // In a real implementation, this would make an AJAX call
                // For now, we'll redirect to a verification handler
                window.location.href = `verify-voter.php?user_id=${userId}&status=${status}`;
            }
        }
        
        // Auto-refresh stats every 30 seconds
        setInterval(() => {
            // In a real implementation, this would update stats via AJAX
            console.log('Stats would be refreshed here');
        }, 30000);
    </script>
</body>
</html>