<?php
/**
 * Candidate Dashboard - Online Voting System
 * Wollo University - Integrated Project
 */
require_once 'config/config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Redirect non-candidates
if ($user_type !== 'candidate') {
    header('Location: dashboard.php');
    exit();
}

$message = '';
$error = '';

// Handle advertisement actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = getDBConnection();
        
        if ($action === 'create_ad') {
            $title = trim($_POST['ad_title'] ?? '');
            $content = trim($_POST['ad_content'] ?? '');
            $ad_type = $_POST['ad_type'] ?? 'text';
            
            if (empty($title) || empty($content)) {
                throw new Exception("Title and content are required.");
            }
            
            // Get candidate ID
            $stmt = $db->prepare("SELECT candidate_id FROM candidates WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $candidate = $stmt->fetch();
            
            if (!$candidate) {
                throw new Exception("Candidate information not found.");
            }
            
            // Create advertisements table if it doesn't exist
            $db->exec("
                CREATE TABLE IF NOT EXISTS candidate_advertisements (
                    ad_id INT PRIMARY KEY AUTO_INCREMENT,
                    candidate_id INT NOT NULL,
                    title VARCHAR(200) NOT NULL,
                    content TEXT NOT NULL,
                    ad_type ENUM('text', 'image', 'video') DEFAULT 'text',
                    image_url VARCHAR(255),
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (candidate_id) REFERENCES candidates(candidate_id) ON DELETE CASCADE
                )
            ");
            
            $stmt = $db->prepare("
                INSERT INTO candidate_advertisements (candidate_id, title, content, ad_type, is_active) 
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->execute([$candidate['candidate_id'], $title, $content, $ad_type]);
            
            logActivity($user_id, 'ADVERTISEMENT_CREATED', 'candidate_advertisements', $db->lastInsertId());
            $message = "Advertisement created successfully!";
            
        } elseif ($action === 'toggle_ad') {
            $ad_id = (int)($_POST['ad_id'] ?? 0);
            
            $stmt = $db->prepare("
                UPDATE candidate_advertisements ca
                JOIN candidates c ON ca.candidate_id = c.candidate_id
                SET ca.is_active = NOT ca.is_active
                WHERE ca.ad_id = ? AND c.user_id = ?
            ");
            $stmt->execute([$ad_id, $user_id]);
            
            if ($stmt->rowCount() > 0) {
                logActivity($user_id, 'ADVERTISEMENT_TOGGLED', 'candidate_advertisements', $ad_id);
                $message = "Advertisement status updated successfully!";
            } else {
                throw new Exception("Advertisement not found or access denied.");
            }
            
        } elseif ($action === 'delete_ad') {
            $ad_id = (int)($_POST['ad_id'] ?? 0);
            
            $stmt = $db->prepare("
                DELETE ca FROM candidate_advertisements ca
                JOIN candidates c ON ca.candidate_id = c.candidate_id
                WHERE ca.ad_id = ? AND c.user_id = ?
            ");
            $stmt->execute([$ad_id, $user_id]);
            
            if ($stmt->rowCount() > 0) {
                logActivity($user_id, 'ADVERTISEMENT_DELETED', 'candidate_advertisements', $ad_id);
                $message = "Advertisement deleted successfully!";
            } else {
                throw new Exception("Advertisement not found or access denied.");
            }
        }
        
    } catch (Exception $e) {
        $error = 'Action failed: ' . $e->getMessage();
    }
}

try {
    $db = getDBConnection();
    
    // Get candidate information
    $stmt = $db->prepare("
        SELECT c.*, p.position_name, e.title as election_title, e.election_id, e.status as election_status,
               e.start_date, e.end_date, u.first_name, u.last_name, u.username
        FROM candidates c
        JOIN positions p ON c.position_id = p.position_id
        JOIN elections e ON p.election_id = e.election_id
        JOIN users u ON c.user_id = u.user_id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $candidate_info = $stmt->fetch();
    
    if (!$candidate_info) {
        $error_message = 'Candidate information not found.';
    }
    
    // Get candidate advertisements
    $advertisements = [];
    if ($candidate_info) {
        // Ensure table exists
        $db->exec("
            CREATE TABLE IF NOT EXISTS candidate_advertisements (
                ad_id INT PRIMARY KEY AUTO_INCREMENT,
                candidate_id INT NOT NULL,
                title VARCHAR(200) NOT NULL,
                content TEXT NOT NULL,
                ad_type ENUM('text', 'image', 'video') DEFAULT 'text',
                image_url VARCHAR(255),
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (candidate_id) REFERENCES candidates(candidate_id) ON DELETE CASCADE
            )
        ");
        
        $stmt = $db->prepare("
            SELECT * FROM candidate_advertisements 
            WHERE candidate_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$candidate_info['candidate_id']]);
        $advertisements = $stmt->fetchAll();
    }
    
    // Get candidate information
    $stmt = $db->prepare("
        SELECT c.*, p.position_name, e.title as election_title, e.election_id, e.status as election_status,
               e.start_date, e.end_date, u.first_name, u.last_name, u.username
        FROM candidates c
        JOIN positions p ON c.position_id = p.position_id
        JOIN elections e ON p.election_id = e.election_id
        JOIN users u ON c.user_id = u.user_id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $candidate_info = $stmt->fetch();
    
    if (!$candidate_info) {
        $error_message = 'Candidate information not found.';
    }
    
    // Get election results for completed elections
    $election_results = [];
    if ($candidate_info && $candidate_info['election_status'] === 'completed') {
        $stmt = $db->prepare("
            SELECT c.candidate_name, c.party_affiliation, COUNT(v.vote_id) as vote_count,
                   (SELECT COUNT(*) FROM votes v2 
                    JOIN candidates c2 ON v2.candidate_id = c2.candidate_id 
                    WHERE c2.position_id = ?) as total_votes_for_position
            FROM candidates c
            LEFT JOIN votes v ON c.candidate_id = v.candidate_id
            WHERE c.position_id = ?
            GROUP BY c.candidate_id
            ORDER BY vote_count DESC
        ");
        $stmt->execute([$candidate_info['position_id'], $candidate_info['position_id']]);
        $election_results = $stmt->fetchAll();
    }
    
    // Get candidate's vote count
    $candidate_votes = 0;
    if ($candidate_info) {
        $stmt = $db->prepare("SELECT COUNT(*) as vote_count FROM votes WHERE candidate_id = ?");
        $stmt->execute([$candidate_info['candidate_id']]);
        $vote_result = $stmt->fetch();
        $candidate_votes = $vote_result['vote_count'];
    }
    
} catch (Exception $e) {
    $error_message = 'Error loading candidate data: ' . $e->getMessage();
    error_log("Candidate dashboard error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        /* Clean white background for candidate dashboard */
        body {
            background-color: #ffffff !important;
        }
        
        .hero {
            background: #f7fafc !important;
            color: #1a202c !important;
            border: 1px solid #e2e8f0 !important;
        }
        
        /* Ensure black text color for dashboard-grid content */
        .dashboard-grid {
            color: black !important;
        }
        
        .dashboard-grid * {
            color: black !important;
        }
        
        /* Ensure black text color for results-list content */
        .results-list {
            color: black !important;
        }
        
        .results-list * {
            color: black !important;
        }
        
        /* Specifically target candidate result text */
        .results-list .candidate-result {
            color: black !important;
        }
        
        .results-list .candidate-result * {
            color: black !important;
        }
        
        /* Ensure candidate names and percentages are black */
        .results-list .candidate-result div {
            color: black !important;
        }
        
        /* Maintain specific colors for status badges and special elements */
        .dashboard-grid .status-badge {
            color: white !important;
        }
        
        .dashboard-grid .winner-badge {
            color: #333 !important;
        }
        
        .results-list .winner-badge {
            color: #333 !important;
        }
        
        .dashboard-grid .progress-fill {
            /* Keep progress bar colors as they are */
        }
        
        .results-list .progress-fill {
            /* Keep progress bar colors as they are */
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="nav-logo-container">
                    <img src="../assets/images/logo.png" alt="<?php echo SITE_NAME; ?> Logo" class="nav-logo-img">
                </div>
                <h1 class="nav-logo"><?php echo SITE_NAME; ?></h1>
                <ul class="nav-menu">
                    
                    <li><a href="candidate-dashboard.php" class="active">Dashboard</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <li><a href="logout.php">Logout</a></li>
                    
                </ul>
            </div>
        </nav>
    </header>

    <main class="dashboard">
        <div class="container">
            <div class="dashboard-header">
                <h1>🏆 Candidate Dashboard</h1>
                <?php if ($candidate_info): ?>
                    <div class="user-info">
                        <span class="user-type">Candidate</span>
                        <span class="voter-id">Running for: <?php echo htmlspecialchars($candidate_info['position_name']); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($candidate_info): ?>
                <div class="dashboard-grid">
                    <!-- Candidate Information -->
                    <section class="dashboard-section">
                        <h2>📋 Your Candidacy Information</h2>
                        <div class="candidate-profile">
                            <?php if ($candidate_info['photo_url']): ?>
                                <img src="../<?php echo htmlspecialchars($candidate_info['photo_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($candidate_info['candidate_name']); ?>" 
                                     style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; margin-bottom: 1rem;">
                            <?php endif; ?>
                            
                            <div class="info-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                                <div>
                                    <strong>Name:</strong><br>
                                    <?php echo htmlspecialchars($candidate_info['candidate_name']); ?>
                                </div>
                                <div>
                                    <strong>Position:</strong><br>
                                    <?php echo htmlspecialchars($candidate_info['position_name']); ?>
                                </div>
                                <div>
                                    <strong>Election:</strong><br>
                                    <?php echo htmlspecialchars($candidate_info['election_title']); ?>
                                </div>
                                <div>
                                    <strong>Party/Affiliation:</strong><br>
                                    <?php echo htmlspecialchars($candidate_info['party_affiliation'] ?: 'Independent'); ?>
                                </div>
                                <div>
                                    <strong>Election Status:</strong><br>
                                    <span class="status-badge status-<?php echo $candidate_info['election_status']; ?>">
                                        <?php echo ucfirst($candidate_info['election_status']); ?>
                                    </span>
                                </div>
                                <div>
                                    <strong>Your Votes:</strong><br>
                                    <span style="font-size: 1.5rem; font-weight: bold; color: #27ae60;">
                                        <?php echo number_format($candidate_votes); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($candidate_info['biography']): ?>
                                <div style="margin-top: 1.5rem;">
                                    <strong>Biography:</strong><br>
                                    <p style="margin-top: 0.5rem; line-height: 1.6;">
                                        <?php echo nl2br(htmlspecialchars($candidate_info['biography'])); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- Campaign Advertisements -->
                    <section class="dashboard-section">
                        <h2>📢 Campaign Advertisements</h2>
                        <p style="color: #666; margin-bottom: 1.5rem;">
                            Create and manage your campaign advertisements that will be visible to voters before the election starts.
                        </p>
                        
                        <?php 
                        $now = new DateTime();
                        $start = new DateTime($candidate_info['start_date']);
                        $can_create_ads = ($candidate_info['election_status'] === 'draft' || 
                                          ($candidate_info['election_status'] === 'active' && $now < $start));
                        ?>
                        
                        <?php if ($can_create_ads): ?>
                            <!-- Create New Advertisement Form -->
                            <div class="ad-form-container" style="background: #f8f9fa; padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem;">
                                <h3 style="margin-bottom: 1rem;">✨ Create New Advertisement</h3>
                                <form method="POST" class="ad-form">
                                    <input type="hidden" name="action" value="create_ad">
                                    
                                    <div class="form-group" style="margin-bottom: 1rem;">
                                        <label for="ad_title" style="display: block; font-weight: bold; margin-bottom: 0.5rem;">Advertisement Title:</label>
                                        <input type="text" id="ad_title" name="ad_title" required 
                                               style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;"
                                               placeholder="Enter a catchy title for your advertisement">
                                    </div>
                                    
                                    <div class="form-group" style="margin-bottom: 1rem;">
                                        <label for="ad_type" style="display: block; font-weight: bold; margin-bottom: 0.5rem;">Advertisement Type:</label>
                                        <select id="ad_type" name="ad_type" 
                                                style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;">
                                            <option value="text">Text Advertisement</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group" style="margin-bottom: 1rem;">
                                        <label for="ad_content" style="display: block; font-weight: bold; margin-bottom: 0.5rem;">Advertisement Content:</label>
                                        <textarea id="ad_content" name="ad_content" required rows="4"
                                                  style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; resize: vertical;"
                                                  placeholder="Write your campaign message, promises, or any information you want voters to know..."></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary" style="background: #28a745; border-color: #28a745;">
                                        🚀 Create Advertisement
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info" style="background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 1rem; border-radius: 5px; margin-bottom: 2rem;">
                                <strong>ℹ️ Advertisement Creation Disabled</strong><br>
                                You can only create advertisements before the election starts. The election is currently 
                                <?php echo $candidate_info['election_status'] === 'active' ? 'active' : $candidate_info['election_status']; ?>.
                            </div>
                        <?php endif; ?>
                        
                        <!-- Existing Advertisements -->
                        <div class="advertisements-list">
                            <h3 style="margin-bottom: 1rem;">📋 Your Advertisements</h3>
                            
                            <?php if (empty($advertisements)): ?>
                                <div class="no-ads" style="text-align: center; padding: 2rem; color: #666; font-style: italic;">
                                    <div style="font-size: 3rem; margin-bottom: 1rem;">📢</div>
                                    <p>You haven't created any advertisements yet.</p>
                                    <?php if ($can_create_ads): ?>
                                        <p>Use the form above to create your first campaign advertisement!</p>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="ads-grid" style="display: grid; gap: 1.5rem;">
                                    <?php foreach ($advertisements as $ad): ?>
                                        <div class="ad-card" style="border: 1px solid #dee2e6; border-radius: 10px; padding: 1.5rem; background: white; <?php echo $ad['is_active'] ? '' : 'opacity: 0.6;'; ?>">
                                            <div class="ad-header" style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                                <div>
                                                    <h4 style="margin: 0; color: #333; font-size: 1.2rem;">
                                                        <?php echo htmlspecialchars($ad['title']); ?>
                                                    </h4>
                                                    <div style="margin-top: 0.25rem;">
                                                        <span class="ad-type-badge" style="background: #007bff; color: white; padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.75rem;">
                                                            <?php echo ucfirst($ad['ad_type']); ?>
                                                        </span>
                                                        <span class="status-badge status-<?php echo $ad['is_active'] ? 'active' : 'inactive'; ?>" style="margin-left: 0.5rem;">
                                                            <?php echo $ad['is_active'] ? 'Active' : 'Inactive'; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                <div class="ad-actions" style="display: flex; gap: 0.5rem;">
                                                    <?php if ($can_create_ads): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="toggle_ad">
                                                            <input type="hidden" name="ad_id" value="<?php echo $ad['ad_id']; ?>">
                                                            <button type="submit" class="btn btn-sm" 
                                                                    style="background: <?php echo $ad['is_active'] ? '#ffc107' : '#28a745'; ?>; color: white; border: none; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">
                                                                <?php echo $ad['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                            </button>
                                                        </form>
                                                        
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this advertisement?')">
                                                            <input type="hidden" name="action" value="delete_ad">
                                                            <input type="hidden" name="ad_id" value="<?php echo $ad['ad_id']; ?>">
                                                            <button type="submit" class="btn btn-sm" 
                                                                    style="background: #dc3545; color: white; border: none; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">
                                                                Delete
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="ad-content" style="margin-bottom: 1rem;">
                                                <p style="margin: 0; line-height: 1.6; color: #333;">
                                                    <?php echo nl2br(htmlspecialchars($ad['content'])); ?>
                                                </p>
                                            </div>
                                            
                                            <div class="ad-meta" style="font-size: 0.85rem; color: #666; border-top: 1px solid #eee; padding-top: 1rem;">
                                                <div style="display: flex; justify-content: space-between;">
                                                    <span>Created: <?php echo date('M j, Y g:i A', strtotime($ad['created_at'])); ?></span>
                                                    <?php if ($ad['updated_at'] !== $ad['created_at']): ?>
                                                        <span>Updated: <?php echo date('M j, Y g:i A', strtotime($ad['updated_at'])); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- Election Timeline -->
                    <section class="dashboard-section">
                        <h2>📅 Election Timeline</h2>
                        <div class="timeline-info">
                            <div class="timeline-item">
                                <strong>Start Date:</strong><br>
                                <?php echo date('M j, Y g:i A', strtotime($candidate_info['start_date'])); ?>
                            </div>
                            <div class="timeline-item" style="margin-top: 1rem;">
                                <strong>End Date:</strong><br>
                                <?php echo date('M j, Y g:i A', strtotime($candidate_info['end_date'])); ?>
                            </div>
                            
                            <?php
                            $now = new DateTime();
                            $start = new DateTime($candidate_info['start_date']);
                            $end = new DateTime($candidate_info['end_date']);
                            ?>
                            
                            <div style="margin-top: 1.5rem; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                                <?php if ($candidate_info['election_status'] === 'completed'): ?>
                                    <div style="color: #6c757d;">
                                        <strong>✅ Election has ended</strong><br>
                                        <small>Election was completed by administrator</small>
                                    </div>
                                <?php elseif ($candidate_info['election_status'] === 'cancelled'): ?>
                                    <div style="color: #dc3545;">
                                        <strong>❌ Election was cancelled</strong><br>
                                        <small>This election has been cancelled by administrator</small>
                                    </div>
                                <?php elseif ($now < $start): ?>
                                    <div style="color: #ffc107;">
                                        <strong>⏳ Election hasn't started yet</strong><br>
                                        <small>Voting will begin on <?php echo $start->format('M j, Y'); ?></small>
                                    </div>
                                <?php elseif ($now >= $start && $now <= $end && $candidate_info['election_status'] === 'active'): ?>
                                    <div style="color: #28a745;">
                                        <strong>🗳️ Election is currently active</strong><br>
                                        <small>Voting ends on <?php echo $end->format('M j, Y'); ?></small>
                                    </div>
                                <?php else: ?>
                                    <div style="color: #6c757d;">
                                        <strong>✅ Election has ended</strong><br>
                                        <small>Voting ended on <?php echo $end->format('M j, Y'); ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <!-- Election Results (if completed) -->
                    <?php if ($candidate_info['election_status'] === 'completed' && !empty($election_results)): ?>
                        <section class="dashboard-section">
                            <h2>📊 Election Results</h2>
                            <div class="results-container">
                                <h3><?php echo htmlspecialchars($candidate_info['position_name']); ?> Results</h3>
                                
                                <?php 
                                $total_votes = 0;
                                foreach ($election_results as $result) {
                                    $total_votes += $result['vote_count'];
                                }
                                ?>
                                
                                <div class="results-list">
                                    <?php foreach ($election_results as $index => $result): ?>
                                        <?php 
                                        $percentage = $total_votes > 0 ? ($result['vote_count'] / $total_votes) * 100 : 0;
                                        $is_current_candidate = $result['candidate_name'] === $candidate_info['candidate_name'];
                                        $is_winner = $index === 0 && $result['vote_count'] > 0;
                                        ?>
                                        <div class="candidate-result <?php echo $is_winner ? 'winner' : ''; ?> <?php echo $is_current_candidate ? 'current-candidate' : ''; ?>"
                                             style="display: flex; align-items: center; padding: 1rem; margin-bottom: 1rem; border: 1px solid #dee2e6; border-radius: 8px; <?php echo $is_current_candidate ? 'background: #e8f5e8; border-color: #28a745;' : ''; ?>">
                                            
                                            <div style="flex: 1;">
                                                <div style="font-weight: bold; font-size: 1.1rem; margin-bottom: 0.25rem;">
                                                    <?php echo htmlspecialchars($result['candidate_name']); ?>
                                                    <?php if ($is_current_candidate): ?>
                                                        <span style="color: #28a745; font-size: 0.9rem;">(You)</span>
                                                    <?php endif; ?>
                                                    <?php if ($is_winner): ?>
                                                        <span class="winner-badge">🏆 Winner</span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($result['party_affiliation']): ?>
                                                    <div style="color: #666; font-size: 0.9rem; margin-bottom: 0.25rem;">
                                                        <?php echo htmlspecialchars($result['party_affiliation']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="progress-bar" style="width: 200px; height: 8px; background: #f0f0f0; border-radius: 4px; overflow: hidden; margin: 0.5rem 0;">
                                                    <div class="progress-fill" style="height: 100%; background: <?php echo $is_current_candidate ? '#28a745' : '#007bff'; ?>; width: <?php echo $percentage; ?>%; border-radius: 4px; transition: width 0.8s ease;"></div>
                                                </div>
                                            </div>
                                            
                                            <div style="text-align: right; margin-left: 1rem;">
                                                <div style="font-size: 1.5rem; font-weight: bold; color: <?php echo $is_current_candidate ? '#28a745' : '#333'; ?>;">
                                                    <?php echo number_format($result['vote_count']); ?>
                                                </div>
                                                <div style="font-size: 0.9rem; color: #666;">
                                                    <?php echo number_format($percentage, 1); ?>%
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div style="text-align: center; margin-top: 1.5rem; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                                    <strong>Total Votes Cast: <?php echo number_format($total_votes); ?></strong>
                                </div>
                            </div>
                        </section>
                    <?php endif; ?>
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
</body>
</html>