<?php
/**
 * Election Results Page - Online Voting System
 * Wollo University - Integrated Project
 */
require_once 'config/config.php';

$election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;
$error_message = '';

if (!$election_id) {
    header('Location: index.php');
    exit();
}

try {
    $db = getDBConnection();
    
    // Get election details
    $stmt = $db->prepare("SELECT * FROM elections WHERE election_id = ?");
    $stmt->execute([$election_id]);
    $election = $stmt->fetch();
    
    if (!$election) {
        $error_message = 'Election not found.';
    } else {
        // Check if results should be visible
        $show_results = ($election['status'] === 'completed' || 
                        ($election['status'] === 'active' && strtotime($election['end_date']) < time()));
        
        if ($show_results) {
            // Get election results with candidate photos
            $stmt = $db->prepare("
                SELECT er.*, c.photo_url, c.biography
                FROM election_results er
                LEFT JOIN candidates c ON er.candidate_id = c.candidate_id
                WHERE er.election_id = ? 
                ORDER BY er.position_id, er.vote_count DESC
            ");
            $stmt->execute([$election_id]);
            $results = $stmt->fetchAll();
            
            // Group results by position
            $results_by_position = [];
            foreach ($results as $result) {
                $results_by_position[$result['position_id']][] = $result;
            }
            
            // Get total votes and participation
            $stmt = $db->prepare("
                SELECT COUNT(DISTINCT voter_id) as total_voters,
                       COUNT(*) as total_votes
                FROM votes 
                WHERE election_id = ?
            ");
            $stmt->execute([$election_id]);
            $vote_stats = $stmt->fetch();
            
            // Get eligible voters count
            $stmt = $db->query("
                SELECT COUNT(*) as eligible_voters 
                FROM voter_profiles 
                WHERE verification_status = 'verified'
            ");
            $eligible_voters = $stmt->fetch()['eligible_voters'];
            
            $participation_rate = $eligible_voters > 0 ? 
                round(($vote_stats['total_voters'] / $eligible_voters) * 100, 2) : 0;
        }
    }
    
} catch (Exception $e) {
    $error_message = 'Error loading election results.';
    error_log("Results page error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Election Results - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Clean white background for results */
        body {
            background-color: #ffffff !important;
        }
        
        .hero {
            background: #f7fafc !important;
            color: #1a202c !important;
            border: 1px solid #e2e8f0 !important;
        }
        
        .results-container {
            max-width: 1000px;
            margin: 2rem auto;
        }
        
        .results-header {
            background: linear-gradient(135deg, #192d4aff 0%, #133065ff 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px 10px 0 0;
            text-align: center;
        }
        
        .results-content {
            background: white;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .stats-section {
            background: #f8f9fa;
            padding: 2rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .position-results {
            padding: 2rem;
            border-bottom: 1px solid #989696ff;
        }
        
        .position-results:last-child {
            border-bottom: none;
        }
        
        .position-title {
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
        }
        
        .candidate-result {
            display: flex;
            align-items: center;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .candidate-result.winner {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-color: #28a745;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .candidate-result.winner * {
            color: black !important;
        }
        
        .candidate-photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 1rem;
            border: 3px solid #ddd;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }
        
        .candidate-result:hover .candidate-photo {
            border-color: #667eea;
        }
        
        .candidate-result.winner .candidate-photo {
            border-color: rgba(255,255,255,0.8);
            box-shadow: 0 0 15px rgba(255,255,255,0.3);
        }
        
        .candidate-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.8rem;
            margin-right: 1rem;
            flex-shrink: 0;
            border: 3px solid #ddd;
            transition: all 0.3s ease;
        }
        
        .candidate-result:hover .candidate-avatar {
            border-color: #667eea;
        }
        
        .candidate-result.winner .candidate-avatar {
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            color: #333;
            border-color: rgba(255,255,255,0.8);
            box-shadow: 0 0 15px rgba(255,255,255,0.3);
        }
        
        .candidate-info {
            flex: 1;
        }
        
        .candidate-name {
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }
        
        .candidate-party {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .vote-info {
            text-align: right;
            margin-left: 1rem;
        }
        
        .vote-count {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .vote-percentage {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .progress-bar {
            width: 200px;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin: 0.5rem 0;
        }
        
        .progress-fill {
            height: 100%;
            background: #667eea;
            transition: width 0.5s ease;
        }
        
        .candidate-result.winner .progress-fill {
            background: rgba(255,255,255,0.8);
        }
        
        .winner-badge {
            background: #ffd700;
            color: #333;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
            margin-left: 0.5rem;
        }
        
        .no-results {
            text-align: center;
            padding: 3rem;
            color: #666;
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
                    <?php if (isLoggedIn()): ?>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                    <?php endif; ?>
                    <li><a href="about.php">About</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <main>
        <div class="container">
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
                <div class="text-center">
                    <a href="index.php" class="btn btn-primary">← Back to Home</a>
                </div>
            <?php else: ?>
                <div class="results-container">
                    <div class="results-header">
                        <h1>📊 Election Results</h1>
                        <h2><?php echo htmlspecialchars($election['title']); ?></h2>
                        <p><?php echo htmlspecialchars($election['description']); ?></p>
                        <div style="margin-top: 1rem;">
                            <strong>Election Period:</strong> 
                            <?php echo date('M j, Y', strtotime($election['start_date'])); ?> - 
                            <?php echo date('M j, Y', strtotime($election['end_date'])); ?>
                        </div>
                    </div>
                    
                    <div class="results-content">
                        <?php if (!$show_results): ?>
                            <div class="no-results">
                                <h3>🔒 Results Not Available</h3>
                                <p>Election results will be available after the voting period ends.</p>
                                <p><strong>Voting ends:</strong> <?php echo date('M j, Y g:i A', strtotime($election['end_date'])); ?></p>
                                <a href="dashboard.php" class="btn btn-primary">← Back to Dashboard</a>
                            </div>
                        <?php elseif (empty($results)): ?>
                            <div class="no-results">
                                <h3>📭 No Votes Cast</h3>
                                <p>No votes were cast in this election.</p>
                                <a href="dashboard.php" class="btn btn-primary">← Back to Dashboard</a>
                            </div>
                        <?php else: ?>
                            <!-- Election Statistics -->
                            <div class="stats-section">
                                <h3>📈 Election Statistics</h3>
                                <div class="stats-grid">
                                    <div class="stat-item">
                                        <div class="stat-number"><?php echo number_format($vote_stats['total_voters']); ?></div>
                                        <div>Voters Participated</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-number"><?php echo number_format($vote_stats['total_votes']); ?></div>
                                        <div>Total Votes Cast</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-number"><?php echo number_format($eligible_voters); ?></div>
                                        <div>Eligible Voters</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-number"><?php echo $participation_rate; ?>%</div>
                                        <div>Participation Rate</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Results by Position -->
                            <?php foreach ($results_by_position as $position_id => $position_results): ?>
                                <div class="position-results">
                                    <h3 class="position-title"><?php echo htmlspecialchars($position_results[0]['position_name']); ?></h3>
                                    
                                    <?php 
                                    $total_position_votes = array_sum(array_column($position_results, 'vote_count'));
                                    $winner_votes = $position_results[0]['vote_count'];
                                    ?>
                                    
                                    <?php foreach ($position_results as $index => $result): ?>
                                        <?php 
                                        $percentage = $total_position_votes > 0 ? 
                                            round(($result['vote_count'] / $total_position_votes) * 100, 1) : 0;
                                        $is_winner = ($index === 0 && $result['vote_count'] > 0);
                                        ?>
                                        
                                        <div class="candidate-result <?php echo $is_winner ? 'winner' : ''; ?>">
                                            <!-- Candidate Photo -->
                                            <div class="candidate-photo-container">
                                                <?php if (!empty($result['photo_url']) && file_exists('../assets/images/candidates/' . basename($result['photo_url']))): ?>
                                                    <img src="../assets/images/candidates/<?php echo htmlspecialchars(basename($result['photo_url'])); ?>" 
                                                         alt="<?php echo htmlspecialchars($result['candidate_name']); ?>" 
                                                         class="candidate-photo">
                                                <?php else: ?>
                                                    <div class="candidate-avatar">
                                                        <?php echo strtoupper(substr($result['candidate_name'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="candidate-info">
                                                <div class="candidate-name">
                                                    <?php echo htmlspecialchars($result['candidate_name']); ?>
                                                    <?php if ($is_winner): ?>
                                                        <span class="winner-badge">🏆 WINNER</span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($result['party_affiliation']): ?>
                                                    <div class="candidate-party">
                                                        <?php echo htmlspecialchars($result['party_affiliation']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($result['biography'] && strlen($result['biography']) > 0): ?>
                                                    <div class="candidate-bio" style="font-size: 0.85rem; color: <?php echo $is_winner ? 'rgba(255,255,255,0.9)' : '#666'; ?>; margin-top: 0.25rem;">
                                                        <?php echo htmlspecialchars(substr($result['biography'], 0, 100)); ?>
                                                        <?php if (strlen($result['biography']) > 100) echo '...'; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%;"></div>
                                                </div>
                                            </div>
                                            <div class="vote-info">
                                                <div class="vote-count"><?php echo number_format($result['vote_count']); ?></div>
                                                <div class="vote-percentage"><?php echo $percentage; ?>%</div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <div style="text-align: center; margin-top: 1rem; color: #666;">
                                        <small>Total votes for this position: <?php echo number_format($total_position_votes); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div style="padding: 2rem; text-align: center; background: #f8f9fa;">
                                <p><strong>Results generated on:</strong> <?php echo date('M j, Y g:i:s A'); ?></p>
                                <div style="margin-top: 1rem;">
                                    <button onclick="window.print()" class="btn btn-secondary">🖨️ Print Results</button>
                                    <a href="dashboard.php" class="btn btn-primary">← Back to Dashboard</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2018 Wollo University - Informatics College. All rights reserved.</p>
        </div>
    </footer>

    <script src="../assets/js/main.js"></script>
    <script>
        // Animate progress bars on page load
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 500);
            });
        });
    </script>
</body>
</html>