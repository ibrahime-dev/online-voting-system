<?php
/**
 * Voting History Page - Online Voting System
 * Wollo University - Integrated Project
 */
require_once 'config/config.php';
requireLogin();

$user_id = $_SESSION['user_id'];

try {
    $db = getDBConnection();
    
    // Get complete voting history
    $stmt = $db->prepare("
        SELECT v.vote_id, v.cast_at, e.title as election_title, e.description as election_description,
               p.position_name, c.candidate_name, c.party_affiliation,
               vr.receipt_code, e.election_type, e.start_date, e.end_date
        FROM votes v
        JOIN elections e ON v.election_id = e.election_id
        JOIN positions p ON v.position_id = p.position_id
        JOIN candidates c ON v.candidate_id = c.candidate_id
        LEFT JOIN vote_receipts vr ON v.vote_id = vr.vote_id
        WHERE v.voter_id = ?
        ORDER BY v.cast_at DESC
    ");
    $stmt->execute([$user_id]);
    $voting_history = $stmt->fetchAll();
    
    // Get voting statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_votes,
            COUNT(DISTINCT v.election_id) as elections_participated,
            MIN(v.cast_at) as first_vote,
            MAX(v.cast_at) as last_vote
        FROM votes v
        WHERE v.voter_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
} catch (Exception $e) {
    $error_message = 'Error loading voting history.';
    error_log("Voting history error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voting History - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Clean white background */
        body {
            background-color: #ffffff !important;
        }
        
        .hero {
            background: #f7fafc !important;
            color: #1a202c !important;
            border: 1px solid #e2e8f0 !important;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <h1 class="nav-logo"><?php echo SITE_NAME; ?></h1>
                <ul class="nav-menu">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="dashboard.php">Dashboard</a></li>
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
                <h1>📊 My Voting History</h1>
                <p>Complete record of your voting participation</p>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <!-- Voting Statistics -->
            <?php if ($stats && $stats['total_votes'] > 0): ?>
                <div class="dashboard-section">
                    <h2>Voting Statistics</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3><?php echo number_format($stats['total_votes']); ?></h3>
                            <p>Total Votes Cast</p>
                        </div>
                        <div class="stat-card">
                            <h3><?php echo number_format($stats['elections_participated']); ?></h3>
                            <p>Elections Participated</p>
                        </div>
                        <div class="stat-card">
                            <h3><?php echo date('M j, Y', strtotime($stats['first_vote'])); ?></h3>
                            <p>First Vote</p>
                        </div>
                        <div class="stat-card">
                            <h3><?php echo date('M j, Y', strtotime($stats['last_vote'])); ?></h3>
                            <p>Most Recent Vote</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Voting History -->
            <div class="dashboard-section">
                <h2>Complete Voting History</h2>
                <?php if (empty($voting_history)): ?>
                    <div class="no-data">
                        <h3>📭 No Voting History</h3>
                        <p>You haven't participated in any elections yet.</p>
                        <a href="dashboard.php" class="btn btn-primary">← Back to Dashboard</a>
                    </div>
                <?php else: ?>
                    <div class="history-list">
                        <?php 
                        $current_election = '';
                        foreach ($voting_history as $vote): 
                            if ($current_election !== $vote['election_title']):
                                if ($current_election !== '') echo '</div>'; // Close previous election group
                                $current_election = $vote['election_title'];
                        ?>
                            <div class="election-group">
                                <div class="election-header">
                                    <h3><?php echo htmlspecialchars($vote['election_title']); ?></h3>
                                    <p><?php echo htmlspecialchars($vote['election_description']); ?></p>
                                    <div class="election-meta">
                                        <span><strong>Type:</strong> <?php echo ucfirst($vote['election_type']); ?></span>
                                        <span><strong>Period:</strong> <?php echo date('M j, Y', strtotime($vote['start_date'])); ?> - <?php echo date('M j, Y', strtotime($vote['end_date'])); ?></span>
                                    </div>
                                </div>
                        <?php endif; ?>
                                
                                <div class="history-item">
                                    <div class="vote-info">
                                        <h4><?php echo htmlspecialchars($vote['position_name']); ?></h4>
                                        <p><strong>Voted for:</strong> <?php echo htmlspecialchars($vote['candidate_name']); ?>
                                        <?php if ($vote['party_affiliation']): ?>
                                            <span class="party-info">(<?php echo htmlspecialchars($vote['party_affiliation']); ?>)</span>
                                        <?php endif; ?>
                                        </p>
                                        <small><strong>Cast on:</strong> <?php echo date('M j, Y g:i A', strtotime($vote['cast_at'])); ?></small>
                                    </div>
                                    <?php if ($vote['receipt_code']): ?>
                                        <div class="receipt-code">
                                            <small><strong>Receipt:</strong> <?php echo htmlspecialchars($vote['receipt_code']); ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                        
                        <?php endforeach; ?>
                        <?php if ($current_election !== '') echo '</div>'; // Close last election group ?>
                    </div>
                    
                    <div style="text-align: center; margin-top: 2rem;">
                        <button onclick="window.print()" class="btn btn-secondary">🖨️ Print History</button>
                        <a href="dashboard.php" class="btn btn-primary">← Back to Dashboard</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2018 Wollo University - Informatics College. All rights reserved.</p>
        </div>
    </footer>

    <script src="../assets/js/main.js"></script>
</body>
</html>