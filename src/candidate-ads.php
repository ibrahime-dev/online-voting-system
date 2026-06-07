<?php
/**
 * Candidate Advertisements - Public View
 * Online Voting System - Wollo University
 */
require_once 'config/config.php';
requireLogin(); // Require login to view candidate advertisements

try {
    $db = getDBConnection();
    
    // Ensure advertisements table exists
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
    
    // Get all active advertisements from candidates in upcoming or active elections
    $stmt = $db->prepare("
        SELECT ca.*, c.candidate_name, c.party_affiliation, c.photo_url, c.biography,
               p.position_name, e.title as election_title, e.election_id, e.status as election_status,
               e.start_date, e.end_date
        FROM candidate_advertisements ca
        JOIN candidates c ON ca.candidate_id = c.candidate_id
        JOIN positions p ON c.position_id = p.position_id
        JOIN elections e ON p.election_id = e.election_id
        WHERE ca.is_active = 1 
        AND e.status IN ('draft', 'active')
        AND (e.status = 'draft' OR (e.status = 'active' AND e.start_date > NOW()))
        ORDER BY ca.created_at DESC
    ");
    $stmt->execute();
    $advertisements = $stmt->fetchAll();
    
    // Group advertisements by election
    $ads_by_election = [];
    foreach ($advertisements as $ad) {
        $ads_by_election[$ad['election_id']]['election'] = [
            'title' => $ad['election_title'],
            'status' => $ad['election_status'],
            'start_date' => $ad['start_date'],
            'end_date' => $ad['end_date']
        ];
        $ads_by_election[$ad['election_id']]['ads'][] = $ad;
    }
    
} catch (Exception $e) {
    $error_message = 'Error loading advertisements: ' . $e->getMessage();
    error_log("Candidate ads error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Advertisements - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        /* Clean white background for candidate-ads */
        body {
            background-color: #ffffff !important;
        }
        
        .hero {
            background: #f7fafc !important;
            color: #1a202c !important;
            border: 1px solid #e2e8f0 !important;
        }
        
        .ads-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .election-section {
            margin-bottom: 3rem;
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .election-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .election-title {
            color: #ff7200;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .election-status {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            margin-bottom: 1rem;
        }
        
        .status-draft {
            background: #f8f9fa;
            color: #6c757d;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .ads-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }
        
        .ad-card {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            background: white;
        }
        
        .ad-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .candidate-header {
            background: linear-gradient(135deg, #ff7200 0%, #ff9500 100%);
            color: white;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .candidate-photo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.3);
        }
        
        .candidate-info h3 {
            margin: 0;
            font-size: 1.2rem;
        }
        
        .candidate-info p {
            margin: 0.25rem 0 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .ad-content {
            padding: 1.5rem;
        }
        
        .ad-title {
            color: #333;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .ad-text {
            color: #555;
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        
        .ad-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid #f0f0f0;
            font-size: 0.85rem;
            color: #666;
        }
        
        .ad-type-badge {
            background: #007bff;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .no-ads {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .no-ads-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .ads-container {
                padding: 1rem;
            }
            
            .ads-grid {
                grid-template-columns: 1fr;
            }
            
            .election-title {
                font-size: 1.5rem;
            }
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
                    <li><a href="index.php">Home</a></li>
                    <li><a href="candidate-ads.php" class="active">Candidate Ads</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
    </header>

    <main>
        <div class="ads-container">
            <div class="page-header" style="text-align: center; margin-bottom: 3rem;">
                <h1 style="color: #ff7200; font-size: 2.5rem; margin-bottom: 1rem;">📢 Candidate Advertisements</h1>
                <p style="color: #ff7200; font-size: 1.1rem; max-width: 600px; margin: 0 auto;">
                    Get to know the candidates and their campaign messages before the elections begin.
                </p>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error" style="text-align: center; margin-bottom: 2rem;">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($ads_by_election)): ?>
                <div class="no-ads">
                    <div class="no-ads-icon">🗳️</div>
                    <h2>No Campaign Advertisements Available</h2>
                    <p>There are currently no active candidate advertisements to display.</p>
                    <p>Candidates can create advertisements for upcoming elections.</p>
                </div>
            <?php else: ?>
                <?php foreach ($ads_by_election as $election_id => $election_data): ?>
                    <div class="election-section">
                        <div class="election-header">
                            <h2 class="election-title"><?php echo htmlspecialchars($election_data['election']['title']); ?></h2>
                            <div class="election-status status-<?php echo $election_data['election']['status']; ?>">
                                <?php echo ucfirst($election_data['election']['status']); ?> Election
                            </div>
                            <p style="color: #666; margin: 0;">
                                <?php if ($election_data['election']['status'] === 'draft'): ?>
                                    Scheduled to start: <?php echo date('M j, Y g:i A', strtotime($election_data['election']['start_date'])); ?>
                                <?php else: ?>
                                    Voting starts: <?php echo date('M j, Y g:i A', strtotime($election_data['election']['start_date'])); ?>
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="ads-grid">
                            <?php foreach ($election_data['ads'] as $ad): ?>
                                <div class="ad-card">
                                    <div class="candidate-header">
                                        <?php if ($ad['photo_url']): ?>
                                            <img src="../<?php echo htmlspecialchars($ad['photo_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($ad['candidate_name']); ?>" 
                                                 class="candidate-photo">
                                        <?php else: ?>
                                            <div class="candidate-photo" style="background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                                                👤
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="candidate-info">
                                            <h3><?php echo htmlspecialchars($ad['candidate_name']); ?></h3>
                                            <p><?php echo htmlspecialchars($ad['position_name']); ?></p>
                                            <?php if ($ad['party_affiliation']): ?>
                                                <p><?php echo htmlspecialchars($ad['party_affiliation']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="ad-content">
                                        <h4 class="ad-title"><?php echo htmlspecialchars($ad['title']); ?></h4>
                                        <div class="ad-text">
                                            <?php echo nl2br(htmlspecialchars($ad['content'])); ?>
                                        </div>
                                        
                                        <div class="ad-meta">
                                            <span class="ad-type-badge"><?php echo ucfirst($ad['ad_type']); ?></span>
                                            <span><?php echo date('M j, Y', strtotime($ad['created_at'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
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