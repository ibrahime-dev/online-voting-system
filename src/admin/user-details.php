<?php
/**
 * User Details View - Admin Panel
 * Online Voting System - Wollo University
 */
require_once '../config/config.php';
requireLogin();

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'super_admin' && $_SESSION['user_type'] !== 'sub_admin') {
    header('Location: ../dashboard.php');
    exit();
}

$user_id = (int)($_GET['id'] ?? 0);
$message = '';
$error = '';

if (!$user_id) {
    header('Location: users.php');
    exit();
}

// Handle verification actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = getDBConnection();
        
        if ($action === 'verify_user') {
            $status = $_POST['status'];
            $notes = sanitizeInput($_POST['notes'] ?? '');
            
            if (!in_array($status, ['verified', 'rejected'])) {
                throw new Exception("Invalid verification status.");
            }
            
            $stmt = $db->prepare("
                UPDATE voter_profiles 
                SET verification_status = ?, verification_date = NOW(), verified_by = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$status, $_SESSION['user_id'], $user_id]);
            
            // Also update the is_verified field in users table
            $stmt = $db->prepare("UPDATE users SET is_verified = ? WHERE user_id = ?");
            $stmt->execute([$status === 'verified' ? 1 : 0, $user_id]);
            
            // Add notes to audit log if provided
            if (!empty($notes)) {
                $stmt = $db->prepare("
                    INSERT INTO audit_log (user_id, action, table_name, record_id, new_values, ip_address, user_agent)
                    VALUES (?, ?, 'voter_profiles', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['user_id'], 
                    'VERIFICATION_NOTES', 
                    $user_id, 
                    json_encode(['notes' => $notes, 'status' => $status]),
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            }
            
            logActivity($_SESSION['user_id'], 'VOTER_' . strtoupper($status), 'voter_profiles', $user_id);
            
            // Send notification
            sendApprovalNotification($user_id, $status);
            
            $message = "User " . ($status === 'verified' ? 'approved' : 'rejected') . " successfully.";
            
        } elseif ($action === 'toggle_status') {
            $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            logActivity($_SESSION['user_id'], 'USER_STATUS_TOGGLE', 'users', $user_id);
            $message = "User status updated successfully.";
        }
        
    } catch (Exception $e) {
        $error = 'Action failed: ' . $e->getMessage();
    }
}

try {
    $db = getDBConnection();
    
    // Get detailed user information
    $stmt = $db->prepare("
        SELECT u.*, vp.national_id, vp.voter_id, vp.verification_status, vp.verification_date, vp.verified_by,
               verifier.first_name as verifier_first_name, verifier.last_name as verifier_last_name
        FROM users u
        LEFT JOIN voter_profiles vp ON u.user_id = vp.user_id
        LEFT JOIN users verifier ON vp.verified_by = verifier.user_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $user_details = $stmt->fetch();
    
    if (!$user_details) {
        header('Location: users.php');
        exit();
    }
    
    // Get user's voting history
    $stmt = $db->prepare("
        SELECT v.vote_id, v.cast_at, e.title as election_title, p.position_name, c.candidate_name,
               vr.receipt_code
        FROM votes v
        JOIN elections e ON v.election_id = e.election_id
        JOIN positions p ON v.position_id = p.position_id
        JOIN candidates c ON v.candidate_id = c.candidate_id
        LEFT JOIN vote_receipts vr ON v.vote_id = vr.vote_id
        WHERE v.voter_id = ?
        ORDER BY v.cast_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $voting_history = $stmt->fetchAll();
    
    // Get user's activity log
    $stmt = $db->prepare("
        SELECT al.*, u.first_name as actor_first_name, u.last_name as actor_last_name
        FROM audit_log al
        LEFT JOIN users u ON al.user_id = u.user_id
        WHERE al.record_id = ? OR al.user_id = ?
        ORDER BY al.timestamp DESC
        LIMIT 15
    ");
    $stmt->execute([$user_id, $user_id]);
    $activity_log = $stmt->fetchAll();
    
    // Get login attempts
    $stmt = $db->prepare("
        SELECT * FROM login_attempts 
        WHERE username = ? 
        ORDER BY attempt_time DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_details['username']]);
    $login_attempts = $stmt->fetchAll();
    
    // Calculate user statistics
    $stats = [];
    
    // Total votes cast
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM votes WHERE voter_id = ?");
    $stmt->execute([$user_id]);
    $stats['total_votes'] = $stmt->fetch()['count'];
    
    // Elections participated
    $stmt = $db->prepare("SELECT COUNT(DISTINCT election_id) as count FROM votes WHERE voter_id = ?");
    $stmt->execute([$user_id]);
    $stats['elections_participated'] = $stmt->fetch()['count'];
    
    // Account age in days
    $created_date = new DateTime($user_details['created_at']);
    $now = new DateTime();
    $stats['account_age_days'] = $now->diff($created_date)->days;
    
    // Last login
    $stats['last_login'] = $user_details['last_login'] ? date('M j, Y g:i A', strtotime($user_details['last_login'])) : 'Never';
    
} catch (Exception $e) {
    $error = 'Error loading user details: ' . $e->getMessage();
    error_log("User details error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - Admin Panel</title>
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
        
        .admin-header {
            background: #000000 !important;
            color: white !important;
        }
        
        .user-details-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .user-header {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
        }
        
        .user-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ff7200, #e65100);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: bold;
            flex-shrink: 0;
        }
        
        .user-info {
            flex: 1;
            min-width: 300px;
        }
        
        .user-name {
            font-size: 2rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 0.5rem;
        }
        
        .user-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
        }
        
        .meta-label {
            font-size: 0.8rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }
        
        .meta-value {
            font-size: 1rem;
            color: #1a202c;
            font-weight: 500;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .details-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .section-title {
            color: #ff7200;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #ff7200;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .info-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-size: 1rem;
            color: #1a202c;
            font-weight: 500;
        }
        
        .verification-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border-left: 5px solid #ff7200;
        }
        
        .verification-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .activity-item {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-action {
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 0.25rem;
        }
        
        .activity-details {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: #6c757d;
            text-align: right;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #e9ecef;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #ff7200;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        .no-data {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 2rem;
        }
        
        .btn-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .notes-textarea {
            width: 100%;
            min-height: 80px;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: inherit;
            resize: vertical;
        }
        
        @media (max-width: 768px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .user-header {
                flex-direction: column;
                text-align: center;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="container">
            <h1>👤 User Details</h1>
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
        <div class="user-details-container">
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- User Header -->
            <div class="user-header">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user_details['first_name'], 0, 1) . substr($user_details['last_name'], 0, 1)); ?>
                </div>
                <div class="user-info">
                    <div class="user-name">
                        <?php echo htmlspecialchars($user_details['first_name'] . ' ' . $user_details['last_name']); ?>
                    </div>
                    <div class="user-meta">
                        <div class="meta-item">
                            <div class="meta-label">Username</div>
                            <div class="meta-value"><?php echo htmlspecialchars($user_details['username']); ?></div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">User Type</div>
                            <div class="meta-value"><?php echo ucfirst($user_details['user_type']); ?></div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Status</div>
                            <div class="meta-value">
                                <span class="status-badge status-<?php echo $user_details['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $user_details['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                        </div>
                        <?php if ($user_details['voter_id']): ?>
                        <div class="meta-item">
                            <div class="meta-label">Voter ID</div>
                            <div class="meta-value"><?php echo htmlspecialchars($user_details['voter_id']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="btn-group">
                    <a href="users.php" class="btn btn-secondary">← Back to Users</a>
                    <?php if ($user_details['user_id'] !== $_SESSION['user_id']): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_status">
                            <button type="submit" class="btn btn-warning" 
                                    onclick="return confirm('Toggle user status?')">
                                <?php echo $user_details['is_active'] ? '🔒 Deactivate' : '🔓 Activate'; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="details-section">
                <h3 class="section-title">📊 User Statistics</h3>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_votes']; ?></div>
                        <div class="stat-label">Total Votes</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['elections_participated']; ?></div>
                        <div class="stat-label">Elections Participated</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['account_age_days']; ?></div>
                        <div class="stat-label">Account Age (Days)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['last_login'] !== 'Never' ? '✅' : '❌'; ?></div>
                        <div class="stat-label">Login Status</div>
                    </div>
                </div>
            </div>
            
            <!-- Details Grid -->
            <div class="details-grid">
                <!-- Personal Information -->
                <div class="details-section">
                    <h3 class="section-title">👤 Personal Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">First Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($user_details['first_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Last Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($user_details['last_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($user_details['email']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Phone</div>
                            <div class="info-value"><?php echo htmlspecialchars($user_details['phone_number'] ?: 'Not provided'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Date of Birth</div>
                            <div class="info-value"><?php echo date('M j, Y', strtotime($user_details['date_of_birth'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Age</div>
                            <div class="info-value">
                                <?php 
                                $birth_date = new DateTime($user_details['date_of_birth']);
                                $now = new DateTime();
                                echo $now->diff($birth_date)->y . ' years';
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($user_details['address']): ?>
                    <div class="info-item" style="margin-top: 1rem;">
                        <div class="info-label">Address</div>
                        <div class="info-value"><?php echo nl2br(htmlspecialchars($user_details['address'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Account Information -->
                <div class="details-section">
                    <h3 class="section-title">🔐 Account Information</h3>
                    <div class="info-grid">
                        <?php if ($user_details['national_id']): ?>
                        <div class="info-item">
                            <div class="info-label">National ID</div>
                            <div class="info-value"><?php echo htmlspecialchars($user_details['national_id']); ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <div class="info-label">Registration Date</div>
                            <div class="info-value"><?php echo date('M j, Y g:i A', strtotime($user_details['created_at'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Last Login</div>
                            <div class="info-value"><?php echo $stats['last_login']; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Account Verified</div>
                            <div class="info-value">
                                <span class="status-badge status-<?php echo $user_details['is_verified'] ? 'verified' : 'pending'; ?>">
                                    <?php echo $user_details['is_verified'] ? 'Yes' : 'No'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Verification Section -->
            <?php if ($user_details['user_type'] === 'voter'): ?>
            <div class="details-section">
                <h3 class="section-title">✅ Verification Status</h3>
                <div class="verification-section">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Current Status</div>
                            <div class="info-value">
                                <span class="status-badge status-<?php echo $user_details['verification_status']; ?>">
                                    <?php echo ucfirst($user_details['verification_status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php if ($user_details['verification_date']): ?>
                        <div class="info-item">
                            <div class="info-label">Verification Date</div>
                            <div class="info-value"><?php echo date('M j, Y g:i A', strtotime($user_details['verification_date'])); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($user_details['verifier_first_name']): ?>
                        <div class="info-item">
                            <div class="info-label">Verified By</div>
                            <div class="info-value"><?php echo htmlspecialchars($user_details['verifier_first_name'] . ' ' . $user_details['verifier_last_name']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($user_details['verification_status'] === 'pending'): ?>
                    <form method="POST" style="margin-top: 1.5rem;">
                        <input type="hidden" name="action" value="verify_user">
                        <div class="form-group">
                            <label for="notes">Verification Notes (Optional):</label>
                            <textarea id="notes" name="notes" class="notes-textarea" 
                                      placeholder="Add any notes about this verification decision..."></textarea>
                        </div>
                        <div class="verification-actions">
                            <button type="submit" name="status" value="verified" 
                                    class="btn btn-success" onclick="return confirm('Approve this user?')">
                                ✅ Approve User
                            </button>
                            <button type="submit" name="status" value="rejected" 
                                    class="btn btn-danger" onclick="return confirm('Reject this user?')">
                                ❌ Reject User
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Voting History -->
            <?php if (!empty($voting_history)): ?>
            <div class="details-section">
                <h3 class="section-title">🗳️ Voting History</h3>
                <?php foreach ($voting_history as $vote): ?>
                    <div class="activity-item">
                        <div class="activity-content">
                            <div class="activity-action"><?php echo htmlspecialchars($vote['election_title']); ?></div>
                            <div class="activity-details">
                                Voted for <strong><?php echo htmlspecialchars($vote['candidate_name']); ?></strong> 
                                in <strong><?php echo htmlspecialchars($vote['position_name']); ?></strong>
                                <?php if ($vote['receipt_code']): ?>
                                    <br><small>Receipt: <?php echo htmlspecialchars($vote['receipt_code']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="activity-time">
                            <?php echo date('M j, Y g:i A', strtotime($vote['cast_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Activity Log -->
            <div class="details-section">
                <h3 class="section-title">📋 Recent Activity</h3>
                <?php if (empty($activity_log)): ?>
                    <div class="no-data">No recent activity found.</div>
                <?php else: ?>
                    <?php foreach ($activity_log as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-content">
                                <div class="activity-action"><?php echo htmlspecialchars($activity['action']); ?></div>
                                <div class="activity-details">
                                    <?php if ($activity['actor_first_name']): ?>
                                        by <?php echo htmlspecialchars($activity['actor_first_name'] . ' ' . $activity['actor_last_name']); ?>
                                    <?php endif; ?>
                                    <?php if ($activity['table_name']): ?>
                                        on <?php echo htmlspecialchars($activity['table_name']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="activity-time">
                                <?php echo date('M j, g:i A', strtotime($activity['timestamp'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Login Attempts -->
            <?php if (!empty($login_attempts)): ?>
            <div class="details-section">
                <h3 class="section-title">🔑 Recent Login Attempts</h3>
                <?php foreach ($login_attempts as $attempt): ?>
                    <div class="activity-item">
                        <div class="activity-content">
                            <div class="activity-action">
                                <?php echo $attempt['success'] ? '✅ Successful Login' : '❌ Failed Login'; ?>
                            </div>
                            <div class="activity-details">
                                IP: <?php echo htmlspecialchars($attempt['ip_address']); ?>
                                <?php if ($attempt['user_agent']): ?>
                                    <br><small><?php echo htmlspecialchars(substr($attempt['user_agent'], 0, 100)); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="activity-time">
                            <?php echo date('M j, g:i A', strtotime($attempt['attempt_time'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Auto-refresh page every 2 minutes to show latest data
        setTimeout(() => {
            location.reload();
        }, 120000);
    </script>
</body>
</html>