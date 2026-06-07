<?php
/**
 * Vote Confirmation Page - Online Voting System
 * Wollo University - Integrated Project
 */
require_once 'config/config.php';
requireLogin();

$election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;
$receipt_codes = $_SESSION['vote_receipts'] ?? [];

// Clear the receipts from session after displaying
unset($_SESSION['vote_receipts']);

if (!$election_id || empty($receipt_codes)) {
    header('Location: dashboard.php');
    exit();
}

try {
    $db = getDBConnection();
    
    // Get election details
    $stmt = $db->prepare("SELECT title, description FROM elections WHERE election_id = ?");
    $stmt->execute([$election_id]);
    $election = $stmt->fetch();
    
    if (!$election) {
        header('Location: dashboard.php');
        exit();
    }
    
} catch (Exception $e) {
    error_log("Vote confirmation error: " . $e->getMessage());
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote Confirmation - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Clean white background for vote confirmation */
        body {
            background-color: #ffffff !important;
        }
        
        .hero {
            background: #f7fafc !important;
            color: #1a202c !important;
            border: 1px solid #e2e8f0 !important;
        }
        
        .confirmation-container {
            max-width: 600px;
            margin: 2rem auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .confirmation-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .confirmation-content {
            padding: 2rem;
        }
        
        .success-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .receipt-section {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 1.5rem;
            margin: 2rem 0;
        }
        
        .receipt-code {
            font-family: monospace;
            font-size: 1.1rem;
            background: white;
            padding: 0.5rem;
            border: 1px solid #ccc;
            border-radius: 3px;
            margin: 0.5rem 0;
            text-align: center;
        }
        
        .important-note {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
        }
        
        .actions {
            text-align: center;
            margin-top: 2rem;
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
                    <li><a href="logout.php">Logout</a></li>
                    <li><a href="about.php">About</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <main>
        <div class="confirmation-container">
            <div class="confirmation-header">
                <div class="success-icon">✅</div>
                <h1>Vote Successfully Cast!</h1>
                <p>Thank you for participating in the democratic process</p>
            </div>
            
            <div class="confirmation-content">
                <h2>Election: <?php echo htmlspecialchars($election['title']); ?></h2>
                
                <div class="important-note">
                    <h3>⚠️ Important Information</h3>
                    <ul>
                        <li>Your vote has been securely recorded and encrypted</li>
                        <li>Save your receipt codes for verification purposes</li>
                        <li>You cannot change your vote once submitted</li>
                        <li>Results will be available after the election ends</li>
                    </ul>
                </div>
                
                <div class="receipt-section">
                    <h3>📋 Vote Receipt Codes</h3>
                    <p>Please save these receipt codes for your records:</p>
                    
                    <?php foreach ($receipt_codes as $index => $code): ?>
                        <div class="receipt-code">
                            Receipt #<?php echo $index + 1; ?>: <strong><?php echo htmlspecialchars($code); ?></strong>
                        </div>
                    <?php endforeach; ?>
                    
                    <small>
                        <strong>Note:</strong> These receipt codes can be used to verify that your vote was counted 
                        without revealing how you voted. Keep them safe and confidential.
                    </small>
                </div>
                
                <div class="actions">
                    <button onclick="window.print()" class="btn btn-secondary">🖨️ Print Receipt</button>
                    <a href="dashboard.php" class="btn btn-primary">Return to Dashboard</a>
                </div>
                
                <div style="margin-top: 2rem; text-align: center; color: #666;">
                    <p><strong>Vote Cast Time:</strong> <?php echo date('M j, Y g:i:s A'); ?></p>
                    <p><strong>Voter ID:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Clear any saved vote selections
        localStorage.removeItem('vote_selections_<?php echo $election_id; ?>');
        
        // Auto-focus on print button for accessibility
        document.addEventListener('DOMContentLoaded', function() {
            // Show a brief success animation
            const header = document.querySelector('.confirmation-header');
            header.style.transform = 'scale(0.9)';
            header.style.opacity = '0';
            
            setTimeout(() => {
                header.style.transition = 'all 0.5s ease';
                header.style.transform = 'scale(1)';
                header.style.opacity = '1';
            }, 100);
        });
    </script>
</body>
</html>