<?php
/**
 * Voting Page - Online Voting System
 * Wollo University - Integrated Project
 */
require_once 'config/config.php';
requireLogin();

$error_message = '';
$success_message = '';
$user_id = $_SESSION['user_id'];
$election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;

if (!$election_id) {
    header('Location: dashboard.php');
    exit();
}

try {
    $db = getDBConnection();
    
    // Check if user is verified
    $stmt = $db->prepare("
        SELECT verification_status FROM voter_profiles WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
    
    if (!$profile || $profile['verification_status'] !== 'verified') {
        $error_message = 'Your account must be verified before you can vote.';
    } else {
        // Get election details
        $stmt = $db->prepare("
            SELECT * FROM elections 
            WHERE election_id = ? AND status = 'active' 
            AND start_date <= NOW() AND end_date >= NOW()
        ");
        $stmt->execute([$election_id]);
        $election = $stmt->fetch();
        
        if (!$election) {
            $error_message = 'Election not found or not active.';
        } else {
            // Check if user has already voted
            $stmt = $db->prepare("
                SELECT vote_id FROM votes WHERE election_id = ? AND voter_id = ?
            ");
            $stmt->execute([$election_id, $user_id]);
            if ($stmt->fetch()) {
                $error_message = 'You have already voted in this election.';
            } else {
                // Get positions and candidates
                $stmt = $db->prepare("
                    SELECT p.*, 
                           (SELECT COUNT(*) FROM candidates c WHERE c.position_id = p.position_id AND c.is_active = 1) as candidate_count
                    FROM positions p 
                    WHERE p.election_id = ? 
                    ORDER BY p.display_order ASC
                ");
                $stmt->execute([$election_id]);
                $positions = $stmt->fetchAll();
                
                // Get candidates for each position
                $candidates_by_position = [];
                foreach ($positions as $position) {
                    $stmt = $db->prepare("
                        SELECT * FROM candidates 
                        WHERE position_id = ? AND is_active = 1 
                        ORDER BY display_order ASC
                    ");
                    $stmt->execute([$position['position_id']]);
                    $candidates_by_position[$position['position_id']] = $stmt->fetchAll();
                }
                
                // Process vote submission
                if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$error_message) {
                    $votes = $_POST['votes'] ?? [];
                    
                    if (empty($votes)) {
                        $error_message = 'Please select at least one candidate.';
                    } else {
                        $db->beginTransaction();
                        
                        try {
                            $receipt_codes = [];
                            
                            foreach ($votes as $position_id => $candidate_id) {
                                if (!empty($candidate_id)) {
                                    // Validate candidate belongs to position
                                    $stmt = $db->prepare("
                                        SELECT c.candidate_id FROM candidates c
                                        JOIN positions p ON c.position_id = p.position_id
                                        WHERE c.candidate_id = ? AND p.position_id = ? 
                                        AND p.election_id = ? AND c.is_active = 1
                                    ");
                                    $stmt->execute([$candidate_id, $position_id, $election_id]);
                                    
                                    if ($stmt->fetch()) {
                                        // Create vote hash for security
                                        $vote_data = $election_id . $position_id . $user_id . $candidate_id . time();
                                        $vote_hash = hash('sha256', $vote_data);
                                        
                                        // Insert vote
                                        $stmt = $db->prepare("
                                            INSERT INTO votes (election_id, position_id, voter_id, candidate_id, vote_hash, ip_address, user_agent)
                                            VALUES (?, ?, ?, ?, ?, ?, ?)
                                        ");
                                        $stmt->execute([
                                            $election_id, $position_id, $user_id, $candidate_id, 
                                            $vote_hash, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']
                                        ]);
                                        
                                        $vote_id = $db->lastInsertId();
                                        
                                        // Generate receipt
                                        $receipt_code = 'RCP' . strtoupper(bin2hex(random_bytes(8)));
                                        $stmt = $db->prepare("
                                            INSERT INTO vote_receipts (vote_id, receipt_code)
                                            VALUES (?, ?)
                                        ");
                                        $stmt->execute([$vote_id, $receipt_code]);
                                        
                                        $receipt_codes[] = $receipt_code;
                                        
                                        logActivity($user_id, 'VOTE_CAST', 'votes', $vote_id);
                                    }
                                }
                            }
                            
                            $db->commit();
                            
                            $_SESSION['vote_receipts'] = $receipt_codes;
                            header('Location: vote-confirmation.php?election_id=' . $election_id);
                            exit();
                            
                        } catch (Exception $e) {
                            $db->rollBack();
                            $error_message = 'Failed to cast vote. Please try again.';
                            error_log("Vote casting error: " . $e->getMessage());
                        }
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    $error_message = 'An error occurred. Please try again.';
    error_log("Vote page error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Clean white background for vote */
        body {
            background-color: #ffffff !important;
        }
        
        .hero {
            background: #f7fafc !important;
            color: #1a202c !important;
            border: 1px solid #e2e8f0 !important;
        }
        
        .ballot-container {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .ballot-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .ballot-content {
            padding: 2rem;
        }
        
        .position-section {
            margin-bottom: 3rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .position-header {
            background: #f8f9fa;
            padding: 1rem;
            border-bottom: 1px solid #ddd;
        }
        
        .candidates-list {
            padding: 1rem;
        }
        
        .candidate-option {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .candidate-option:hover {
            background: #f8f9fa;
            border-color: #667eea;
        }
        
        .candidate-option input[type="radio"] {
            margin-right: 1rem;
            transform: scale(1.2);
        }
        
        .candidate-photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 1rem;
            border: 3px solid #ddd;
            transition: all 0.3s ease;
        }
        
        .candidate-option:hover .candidate-photo {
            border-color: #667eea;
        }
        
        .candidate-option input[type="radio"]:checked ~ .candidate-photo {
            border-color: #667eea;
            box-shadow: 0 0 10px rgba(102, 126, 234, 0.3);
        }
        
        .candidate-info {
            flex: 1;
        }
        
        .candidate-name {
            font-weight: bold;
            margin-bottom: 0.25rem;
        }
        
        .candidate-party {
            color: #666;
            font-size: 0.875rem;
        }
        
        .vote-actions {
            text-align: center;
            padding: 2rem;
            border-top: 1px solid #ddd;
            background: #f8f9fa;
        }
        
        .election-info {
            background: #e3f2fd;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 2rem;
        }
        
        .time-remaining {
            color: #d32f2f;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <h1 class="nav-logo"><?php echo SITE_NAME; ?></h1>
                <ul class="nav-menu">
                    <li><a href="dashboard.php">← Back to Dashboard</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <main>
        <?php if ($error_message): ?>
            <div class="container">
                <div class="alert alert-error"><?php echo $error_message; ?></div>
                <div class="text-center">
                    <a href="dashboard.php" class="btn btn-primary">Return to Dashboard</a>
                </div>
            </div>
        <?php else: ?>
            <div class="ballot-container">
                <div class="ballot-header">
                    <h1><?php echo htmlspecialchars($election['title']); ?></h1>
                    <p><?php echo htmlspecialchars($election['description']); ?></p>
                    <div class="time-remaining">
                        Voting ends: <?php echo date('M j, Y g:i A', strtotime($election['end_date'])); ?>
                    </div>
                </div>
                
                <div class="ballot-content">
                    <div class="election-info">
                        <h3>🗳️ Voting Instructions</h3>
                        <ul>
                            <li>Select one candidate for each position</li>
                            <li>You can skip positions if you choose not to vote for them</li>
                            <li>Review your selections before submitting</li>
                            <li>Once submitted, your vote cannot be changed</li>
                        </ul>
                    </div>
                    
                    <form method="POST" id="voting-form">
                        <?php foreach ($positions as $position): ?>
                            <div class="position-section">
                                <div class="position-header">
                                    <h3><?php echo htmlspecialchars($position['position_name']); ?></h3>
                                    <?php if ($position['description']): ?>
                                        <p><?php echo htmlspecialchars($position['description']); ?></p>
                                    <?php endif; ?>
                                    <small>Select one candidate (<?php echo count($candidates_by_position[$position['position_id']]); ?> candidates)</small>
                                </div>
                                
                                <div class="candidates-list">
                                    <?php if (empty($candidates_by_position[$position['position_id']])): ?>
                                        <p class="no-data">No candidates available for this position.</p>
                                    <?php else: ?>
                                        <?php foreach ($candidates_by_position[$position['position_id']] as $candidate): ?>
                                            <label class="candidate-option">
                                                <input type="radio" 
                                                       name="votes[<?php echo $position['position_id']; ?>]" 
                                                       value="<?php echo $candidate['candidate_id']; ?>">
                                                
                                                <?php if (!empty($candidate['photo_url']) && file_exists('../assets/images/candidates/' . basename($candidate['photo_url']))): ?>
                                                    <img src="../assets/images/candidates/<?php echo htmlspecialchars(basename($candidate['photo_url'])); ?>" 
                                                         alt="<?php echo htmlspecialchars($candidate['candidate_name']); ?>" 
                                                         class="candidate-photo">
                                                <?php else: ?>
                                                    <div class="candidate-photo" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 1.5rem;">
                                                        <?php echo strtoupper(substr($candidate['candidate_name'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="candidate-info">
                                                    <div class="candidate-name">
                                                        <?php echo htmlspecialchars($candidate['candidate_name']); ?>
                                                    </div>
                                                    <?php if ($candidate['party_affiliation']): ?>
                                                        <div class="candidate-party">
                                                            <?php echo htmlspecialchars($candidate['party_affiliation']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($candidate['biography']): ?>
                                                        <div class="candidate-bio">
                                                            <?php echo htmlspecialchars(substr($candidate['biography'], 0, 150)); ?>
                                                            <?php if (strlen($candidate['biography']) > 150) echo '...'; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="vote-actions">
                            <button type="button" onclick="reviewVote()" class="btn btn-secondary">Review My Selections</button>
                            <button type="submit" class="btn btn-primary" onclick="return confirmVote()">Cast My Vote</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script src="../assets/js/main.js"></script>
    <script>
        function reviewVote() {
            const form = document.getElementById('voting-form');
            const formData = new FormData(form);
            const selections = [];
            
            for (let [key, value] of formData.entries()) {
                if (key.startsWith('votes[')) {
                    const positionId = key.match(/\[(\d+)\]/)[1];
                    const candidateOption = document.querySelector(`input[name="${key}"][value="${value}"]`);
                    const candidateLabel = candidateOption.closest('.candidate-option');
                    const candidateName = candidateLabel.querySelector('.candidate-name').textContent;
                    const positionName = candidateLabel.closest('.position-section').querySelector('h3').textContent;
                    
                    selections.push(`${positionName}: ${candidateName}`);
                }
            }
            
            if (selections.length === 0) {
                alert('You have not made any selections yet.');
                return;
            }
            
            const message = 'Your selections:\n\n' + selections.join('\n') + '\n\nWould you like to proceed with these selections?';
            
            if (confirm(message)) {
                document.querySelector('button[type="submit"]').scrollIntoView();
            }
        }
        
        function confirmVote() {
            const form = document.getElementById('voting-form');
            const formData = new FormData(form);
            let hasSelections = false;
            
            for (let [key, value] of formData.entries()) {
                if (key.startsWith('votes[')) {
                    hasSelections = true;
                    break;
                }
            }
            
            if (!hasSelections) {
                alert('Please select at least one candidate before submitting your vote.');
                return false;
            }
            
            const confirmMessage = 'Are you sure you want to cast your vote?\n\nOnce submitted, your vote cannot be changed.\n\nClick OK to confirm or Cancel to review your selections.';
            
            return confirm(confirmMessage);
        }
        
        // Auto-save selections to prevent loss
        document.addEventListener('change', function(e) {
            if (e.target.type === 'radio') {
                const selections = {};
                const radios = document.querySelectorAll('input[type="radio"]:checked');
                radios.forEach(radio => {
                    selections[radio.name] = radio.value;
                });
                localStorage.setItem('vote_selections_<?php echo $election_id; ?>', JSON.stringify(selections));
            }
        });
        
        // Restore selections on page load
        window.addEventListener('load', function() {
            const saved = localStorage.getItem('vote_selections_<?php echo $election_id; ?>');
            if (saved) {
                const selections = JSON.parse(saved);
                for (let name in selections) {
                    const radio = document.querySelector(`input[name="${name}"][value="${selections[name]}"]`);
                    if (radio) {
                        radio.checked = true;
                    }
                }
            }
        });
    </script>
</body>
</html>