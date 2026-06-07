<?php
/**
 * Elections Management - Admin Panel
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

// Handle election actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = getDBConnection();
        
        if ($action === 'create_election') {
            $title = sanitizeInput($_POST['title']);
            $description = sanitizeInput($_POST['description']);
            $election_type = $_POST['election_type'];
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            
            if (empty($title) || empty($start_date) || empty($end_date)) {
                $error = 'Please fill in all required fields.';
            } elseif (strtotime($start_date) >= strtotime($end_date)) {
                $error = 'End date must be after start date.';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO elections (title, description, election_type, start_date, end_date, created_by, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'draft')
                ");
                $stmt->execute([$title, $description, $election_type, $start_date, $end_date, $_SESSION['user_id']]);
                
                $election_id = $db->lastInsertId();
                logActivity($_SESSION['user_id'], 'ELECTION_CREATE', 'elections', $election_id);
                $message = 'Election created successfully!';
            }
            
        } elseif ($action === 'update_status') {
            $election_id = (int)$_POST['election_id'];
            $status = $_POST['status'];
            
            // Validate that election has candidates before starting
            if ($status === 'active') {
                $stmt = $db->prepare("
                    SELECT COUNT(*) as candidate_count 
                    FROM candidates c 
                    JOIN positions p ON c.position_id = p.position_id 
                    WHERE p.election_id = ?
                ");
                $stmt->execute([$election_id]);
                $candidate_count = $stmt->fetch()['candidate_count'];
                
                if ($candidate_count == 0) {
                    $error = 'Cannot start election: No candidates have been added to any positions. Please add candidates before starting the election.';
                } else {
                    $stmt = $db->prepare("UPDATE elections SET status = ? WHERE election_id = ?");
                    $stmt->execute([$status, $election_id]);
                    
                    logActivity($_SESSION['user_id'], 'ELECTION_STATUS_UPDATE', 'elections', $election_id);
                    $message = 'Election started successfully!';
                }
            } else {
                $stmt = $db->prepare("UPDATE elections SET status = ? WHERE election_id = ?");
                $stmt->execute([$status, $election_id]);
                
                logActivity($_SESSION['user_id'], 'ELECTION_STATUS_UPDATE', 'elections', $election_id);
                $message = 'Election status updated successfully!';
            }
            
        } elseif ($action === 'delete_election' && $_SESSION['user_type'] === 'super_admin') {
            $election_id = (int)$_POST['election_id'];
            
            $stmt = $db->prepare("DELETE FROM elections WHERE election_id = ?");
            $stmt->execute([$election_id]);
            
            logActivity($_SESSION['user_id'], 'ELECTION_DELETE', 'elections', $election_id);
            $message = 'Election deleted successfully!';
        }
        
    } catch (Exception $e) {
        $error = 'Action failed: ' . $e->getMessage();
    }
}

try {
    $db = getDBConnection();
    
    // Get all elections with statistics
    $stmt = $db->prepare("
        SELECT e.*, u.username as created_by_username,
               (SELECT COUNT(*) FROM positions p WHERE p.election_id = e.election_id) as position_count,
               (SELECT COUNT(*) FROM candidates c JOIN positions p ON c.position_id = p.position_id WHERE p.election_id = e.election_id) as candidate_count,
               (SELECT COUNT(DISTINCT v.voter_id) FROM votes v WHERE v.election_id = e.election_id) as voter_count,
               (SELECT COUNT(*) FROM votes v WHERE v.election_id = e.election_id) as vote_count
        FROM elections e
        LEFT JOIN users u ON e.created_by = u.user_id
        ORDER BY e.created_at DESC
    ");
    $stmt->execute();
    $elections = $stmt->fetchAll();
    
    // Get statistics
    $stats = [];
    $stmt = $db->query("SELECT COUNT(*) as total FROM elections");
    $stats['total_elections'] = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM elections WHERE status = 'active'");
    $stats['active_elections'] = $stmt->fetch()['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM elections WHERE status = 'completed'");
    $stats['completed_elections'] = $stmt->fetch()['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM elections WHERE status = 'draft'");
    $stats['draft_elections'] = $stmt->fetch()['count'];
    
} catch (Exception $e) {
    $error = 'Error loading election data: ' . $e->getMessage();
    $elections = [];
    $stats = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elections Management - Admin Panel</title>
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
            <h1>🗳️ Elections Management</h1>
        </div>
    </header>
    
    <nav class="admin-nav">
        <div class="container">
            <ul>
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="users.php">User Management</a></li>
                <li><a href="elections.php" class="active">Elections</a></li>
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
                    <div class="stat-number"><?php echo $stats['total_elections'] ?? 0; ?></div>
                    <div>Total Elections</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['active_elections'] ?? 0; ?></div>
                    <div>Active Elections</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['completed_elections'] ?? 0; ?></div>
                    <div>Completed Elections</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['draft_elections'] ?? 0; ?></div>
                    <div>Draft Elections</div>
                </div>
            </div>
            
            <!-- Create New Election -->
            <div class="create-election-form">
                <h1>➕ Create New Election</h1>
                <form method="POST">
                    <input type="hidden" name="action" value="create_election">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="title">Election Title: *</label>
                            <input type="text" id="title" name="title" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="election_type">Election Type: *</label>
                            <select id="election_type" name="election_type" required>
                                <option value="general">General Election</option>
                                <option value="local">Local Election</option>
                                <option value="referendum">Referendum</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="start_date">Start Date & Time: *</label>
                            <input type="datetime-local" id="start_date" name="start_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="end_date">End Date & Time: *</label>
                            <input type="datetime-local" id="end_date" name="end_date" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea id="description" name="description" rows="3" placeholder="Enter election description..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Create Election</button>
                </form>
            </div>
            
            <!-- Elections List -->
            <h2>📋 All Elections</h2>
            
            <?php if (empty($elections)): ?>
                <div style="text-align: center; padding: 3rem; color: #666;">
                    <h3>No elections found</h3>
                    <p>Create your first election using the form above.</p>
                </div>
            <?php else: ?>
                <?php foreach ($elections as $election): ?>
                    <div class="election-card">
                        <div class="election-header">
                            <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 1rem;">
                                <div>
                                    <h3 class="election-title"><?php echo htmlspecialchars($election['title']); ?></h3>
                                    <span class="status-badge status-<?php echo $election['status']; ?>">
                                        <?php echo ucfirst($election['status']); ?>
                                    </span>
                                </div>
                                <div style="text-align: right; font-size: 0.875rem; color: #666;">
                                    <div>Created by: <?php echo htmlspecialchars($election['created_by_username']); ?></div>
                                    <div><?php echo date('M j, Y', strtotime($election['created_at'])); ?></div>
                                </div>
                            </div>
                            
                            <?php if ($election['description']): ?>
                                <p style="margin: 1rem 0 0 0; color: #666;">
                                    <?php echo htmlspecialchars($election['description']); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="election-meta">
                                <div><strong>Type:</strong> <?php echo ucfirst($election['election_type']); ?></div>
                                <div><strong>Start:</strong> <?php echo date('M j, Y g:i A', strtotime($election['start_date'])); ?></div>
                                <div><strong>End:</strong> <?php echo date('M j, Y g:i A', strtotime($election['end_date'])); ?></div>
                            </div>
                        </div>
                        
                        <div class="election-stats">
                            <div><strong><?php echo $election['position_count']; ?></strong> Positions</div>
                            <div><strong><?php echo $election['candidate_count']; ?></strong> Candidates</div>
                            <div><strong><?php echo $election['voter_count']; ?></strong> Voters</div>
                            <div><strong><?php echo $election['vote_count']; ?></strong> Total Votes</div>
                        </div>
                        
                        <div class="election-actions">
                            <a href="election-details.php?id=<?php echo $election['election_id']; ?>" class="btn btn-secondary">📊 View Details</a>
                            
                            <?php if ($election['status'] === 'draft'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="election_id" value="<?php echo $election['election_id']; ?>">
                                    <input type="hidden" name="status" value="active">
                                    <button type="submit" class="btn btn-success" 
                                            onclick="return validateElectionStart(<?php echo $election['candidate_count']; ?>)">
                                        ▶️ Start Election
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($election['status'] === 'active'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="election_id" value="<?php echo $election['election_id']; ?>">
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="btn btn-primary" onclick="return confirm('End this election?')">⏹️ End Election</button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($election['status'] === 'completed'): ?>
                                <a href="../results.php?election_id=<?php echo $election['election_id']; ?>" class="btn btn-primary">📈 View Results</a>
                            <?php endif; ?>
                            
                            <?php if ($_SESSION['user_type'] === 'super_admin' && $election['vote_count'] == 0): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_election">
                                    <input type="hidden" name="election_id" value="<?php echo $election['election_id']; ?>">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this election? This cannot be undone!')">🗑️ Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Set minimum date to today
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const minDateTime = now.toISOString().slice(0, 16);
            
            document.getElementById('start_date').min = minDateTime;
            document.getElementById('end_date').min = minDateTime;
            
            // Update end date minimum when start date changes
            document.getElementById('start_date').addEventListener('change', function() {
                document.getElementById('end_date').min = this.value;
            });
        });
        
        // Validate election start - check if candidates exist
        function validateElectionStart(candidateCount) {
            if (candidateCount === 0) {
                alert('Cannot start election: No candidates have been added to any positions.\n\nPlease add candidates before starting the election.');
                return false;
            }
            return confirm('Start this election?\n\nThis will make it available for voting.');
        }
    </script>
</body>
</html>