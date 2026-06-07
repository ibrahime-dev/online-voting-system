<?php
/**
 * User Dashboard - Online Voting System
 * Wollo University - Integrated Project
 */
require_once 'config/config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

try {
    $db = getDBConnection();
    
    // Get user profile information
    $stmt = $db->prepare("
        SELECT u.*, vp.national_id, vp.voter_id, vp.verification_status, vp.verification_date
        FROM users u
        LEFT JOIN voter_profiles vp ON u.user_id = vp.user_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $user_profile = $stmt->fetch();
    
    // Get active elections
    $stmt = $db->prepare("
        SELECT e.*, 
               (SELECT COUNT(*) FROM votes v WHERE v.election_id = e.election_id AND v.voter_id = ?) as has_voted
        FROM elections e
        WHERE e.status = 'active' AND e.start_date <= NOW() AND e.end_date >= NOW()
        ORDER BY e.start_date ASC
    ");
    $stmt->execute([$user_id]);
    $active_elections = $stmt->fetchAll();
    
    // Get completed elections with results
    $stmt = $db->prepare("
        SELECT e.*, 
               (SELECT COUNT(*) FROM votes v WHERE v.election_id = e.election_id AND v.voter_id = ?) as has_voted
        FROM elections e
        WHERE e.status = 'completed'
        ORDER BY e.end_date DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $completed_elections = $stmt->fetchAll();
    
    // Get voting history
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
    
} catch (Exception $e) {
    $error_message = 'Error loading dashboard data.';
    error_log("Dashboard error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        /* Dashboard specific styles */
        .dashboard {
            background: transparent !important;
        }
    </style>
</head>
<body class="dashboard-page">
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <h1 class="nav-logo"><?php echo SITE_NAME; ?></h1>
                <ul class="nav-menu">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="dashboard.php" class="active">Dashboard</a></li>
                    <li><a href="candidate-ads.php">Candidate Ads</a></li>
                    <?php if ($user_type === 'admin' || $user_type === 'super_admin'): ?>
                        <li><a href="admin/">Admin Panel</a></li>
                    <?php endif; ?>
                    <li><a href="profile.php">Profile</a></li>
                    <li><a href="logout.php">Logout</a></li>
                    <li><a href="about.php">About</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <main class="dashboard">
        <div class="container">
            <div class="dashboard-header">
                <h1>Welcome, <?php echo htmlspecialchars($user_profile['first_name'] . ' ' . $user_profile['last_name']); ?>!</h1>
                <div class="user-info">
                    <span class="user-type"><?php echo ucfirst($user_type); ?></span>
                    <?php if (isset($user_profile['voter_id'])): ?>
                        <span class="voter-id">Voter ID: <?php echo htmlspecialchars($user_profile['voter_id']); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (isset($user_profile['verification_status']) && $user_profile['verification_status'] !== 'verified'): ?>
                <div class="alert alert-warning">
                    <h3>Account Verification Status: <?php echo ucfirst($user_profile['verification_status']); ?></h3>
                    <?php if ($user_profile['verification_status'] === 'pending'): ?>
                        <p>Your account is pending verification. You will be able to vote once your account is verified by an administrator.</p>
                    <?php elseif ($user_profile['verification_status'] === 'rejected'): ?>
                        <p>Your account verification was rejected. Please contact the administrator for more information.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($user_type === 'admin' || $user_type === 'super_admin'): ?>
                <div class="alert admin-alert">
                    <h3>🛡️ Administrator Access</h3>
                    <p>You have administrator privileges. Access admin features below:</p>
                    <div class="admin-links">
                        <a href="admin/index.php" class="admin-link">📊 Admin Dashboard</a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="dashboard-grid">
                <!-- Active Elections -->
                <section class="dashboard-section">
                    <h2>Active Elections</h2>
                    <?php if (empty($active_elections)): ?>
                        <p class="no-data">No active elections at this time.</p>
                    <?php else: ?>
                        <div class="elections-list">
                            <?php foreach ($active_elections as $election): ?>
                                <div class="election-item">
                                    <h3><?php echo htmlspecialchars($election['title']); ?></h3>
                                    <p><?php echo htmlspecialchars($election['description']); ?></p>
                                    <div class="election-meta">
                                        <span>Ends: <?php echo date('M j, Y g:i A', strtotime($election['end_date'])); ?></span>
                                        <?php if ($election['has_voted'] > 0): ?>
                                            <span class="voted-badge">✓ Voted</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($user_profile['verification_status'] === 'verified'): ?>
                                        <?php if ($election['has_voted'] == 0): ?>
                                            <a href="vote.php?election_id=<?php echo $election['election_id']; ?>" class="btn btn-primary">Vote Now</a>
                                        <?php else: ?>
                                            <a href="results.php?election_id=<?php echo $election['election_id']; ?>" class="btn btn-secondary">View Results</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="btn btn-disabled">Verification Required</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Quick Stats -->
                <section class="dashboard-section">
                    <h2>Quick Stats</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3><?php echo count($voting_history); ?></h3>
                            <p>Total Votes Cast</p>
                        </div>
                        <div class="stat-card">
                            <h3><?php echo count($active_elections); ?></h3>
                            <p>Active Elections</p>
                        </div>
                        <div class="stat-card">
                            <h3><?php echo count($completed_elections); ?></h3>
                            <p>Completed Elections</p>
                        </div>
                        <div class="stat-card">
                            <h3><?php echo ucfirst($user_profile['verification_status'] ?? 'N/A'); ?></h3>
                            <p>Verification Status</p>
                        </div>
                    </div>
                </section>

                <!-- Recent Voting History -->
                <section class="dashboard-section">
                    <h2>Recent Voting History</h2>
                    <?php if (empty($voting_history)): ?>
                        <p class="no-data">No voting history available.</p>
                    <?php else: ?>
                        <div class="history-list">
                            <?php foreach ($voting_history as $vote): ?>
                                <div class="history-item">
                                    <div class="vote-info">
                                        <h4><?php echo htmlspecialchars($vote['election_title']); ?></h4>
                                        <p><strong><?php echo htmlspecialchars($vote['position_name']); ?>:</strong> 
                                           <?php echo htmlspecialchars($vote['candidate_name']); ?></p>
                                        <small>Voted on: <?php echo date('M j, Y g:i A', strtotime($vote['cast_at'])); ?></small>
                                    </div>
                                    <?php if ($vote['receipt_code']): ?>
                                        <div class="receipt-code">
                                            <small>Receipt: <?php echo htmlspecialchars($vote['receipt_code']); ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="voting-history.php" class="btn btn-secondary">View Full History</a>
                    <?php endif; ?>
                </section>

                <!-- Completed Elections -->
                <section class="dashboard-section">
                    <h2>Recent Completed Elections</h2>
                    <?php if (empty($completed_elections)): ?>
                        <p class="no-data">No completed elections.</p>
                    <?php else: ?>
                        <div class="elections-list">
                            <?php foreach ($completed_elections as $election): ?>
                                <div class="election-item completed">
                                    <h3><?php echo htmlspecialchars($election['title']); ?></h3>
                                    <p><?php echo htmlspecialchars($election['description']); ?></p>
                                    <div class="election-meta">
                                        <span>Completed: <?php echo date('M j, Y', strtotime($election['end_date'])); ?></span>
                                        <?php if ($election['has_voted'] > 0): ?>
                                            <span class="voted-badge">✓ Participated</span>
                                        <?php endif; ?>
                                    </div>
                                    <a href="results.php?election_id=<?php echo $election['election_id']; ?>" class="btn btn-secondary">View Results</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2018 Wollo University - Informatics College. All rights reserved.</p>
        </div>
    </footer>

    <script src="../assets/js/main.js"></script>
    <style>
        /* View Results and View Full History Button Styling - Only for dashboard */
        a[href*="results.php"].btn-secondary,
        a[href*="voting-history.php"].btn-secondary {
            background-color: #0da112 !important;
            border-color: #0da112 !important;
            color: white !important;
        }
        
        a[href*="results.php"].btn-secondary:hover,
        a[href*="voting-history.php"].btn-secondary:hover {
            background-color: #0b8f10 !important;
            border-color: #0b8f10 !important;
            color: white !important;
        }
        
        a[href*="results.php"].btn-secondary:focus,
        a[href*="results.php"].btn-secondary:active,
        a[href*="voting-history.php"].btn-secondary:focus,
        a[href*="voting-history.php"].btn-secondary:active {
            background-color: #097d0e !important;
            border-color: #097d0e !important;
            color: white !important;
            box-shadow: 0 0 0 0.2rem rgba(13, 161, 18, 0.25) !important;
        }
    </style>
</body>
</html>