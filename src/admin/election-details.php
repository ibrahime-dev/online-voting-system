<?php
/**
 * Election Details - Simple & Clear Admin Panel
 * Online Voting System - Wollo University
 */
require_once '../config/config.php';
requireLogin();

// Check admin access
if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'super_admin' && $_SESSION['user_type'] !== 'sub_admin') {
    header('Location: ../dashboard.php');
    exit();
}

$election_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

if (!$election_id) {
    header('Location: elections.php');
    exit();
}

// Get election details first (needed for validation)
try {
    $db = getDBConnection();
    
    $stmt = $db->prepare("SELECT * FROM elections WHERE election_id = ?");
    $stmt->execute([$election_id]);
    $election = $stmt->fetch();
    
    if (!$election) {
        header('Location: elections.php');
        exit();
    }
} catch (Exception $e) {
    $error = 'Error loading election data: ' . $e->getMessage();
    $election = null;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $election) {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = getDBConnection();
        
        if ($action === 'add_position') {
            // Check if election is still in draft status
            if ($election['status'] !== 'draft') {
                $error = 'Cannot add positions: Election has already started or completed. Positions can only be added to draft elections.';
            } else {
                $position_name = sanitizeInput($_POST['position_name']);
                $description = sanitizeInput($_POST['description']);
                
                if (empty($position_name)) {
                    $error = 'Position name is required.';
                } else {
                    $stmt = $db->prepare("INSERT INTO positions (election_id, position_name, description) VALUES (?, ?, ?)");
                    $stmt->execute([$election_id, $position_name, $description]);
                    $message = 'Position added successfully!';
                }
            }
            
        } elseif ($action === 'add_candidate') {
            // Check if election is still in draft status
            if ($election['status'] !== 'draft') {
                $error = 'Cannot add candidates: Election has already started or completed. Candidates can only be added to draft elections.';
            } else {
            $position_id = (int)$_POST['position_id'];
            $candidate_name = sanitizeInput($_POST['candidate_name']);
            $party_affiliation = sanitizeInput($_POST['party_affiliation']);
            $biography = sanitizeInput($_POST['biography']);
            $username = sanitizeInput($_POST['username']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (empty($candidate_name) || !$position_id) {
                $error = 'Candidate name and position are required.';
            } elseif (empty($username) || empty($password)) {
                $error = 'Username and password are required for candidate login.';
            } elseif ($password !== $confirm_password) {
                $error = 'Passwords do not match.';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters long.';
            } else {
                // Check if username already exists
                $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = 'Username already exists. Please choose a different username.';
                } else {
                    // Handle photo upload
                    $photo_path = null;
                    if (isset($_FILES['candidate_photo']) && $_FILES['candidate_photo']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = '../../assets/images/candidates/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $file_extension = strtolower(pathinfo($_FILES['candidate_photo']['name'], PATHINFO_EXTENSION));
                        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                        
                        if (in_array($file_extension, $allowed_extensions)) {
                            $filename = 'candidate_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                            $photo_path = $upload_dir . $filename;
                            
                            if (move_uploaded_file($_FILES['candidate_photo']['tmp_name'], $photo_path)) {
                                $photo_path = 'assets/images/candidates/' . $filename;
                            } else {
                                $photo_path = null;
                            }
                        }
                    }
                    
                    $db->beginTransaction();
                    
                    try {
                        // Create user account for candidate
                        $password_hash = hashPassword($password);
                        $stmt = $db->prepare("
                            INSERT INTO users (username, email, password_hash, first_name, last_name, date_of_birth, user_type, is_verified, is_active) 
                            VALUES (?, ?, ?, ?, '', '1990-01-01', 'candidate', 1, 1)
                        ");
                        $candidate_email = $username . '@candidate.local';
                        $stmt->execute([$username, $candidate_email, $password_hash, $candidate_name]);
                        $user_id = $db->lastInsertId();
                        
                        // Add candidate record with user_id
                        $stmt = $db->prepare("INSERT INTO candidates (position_id, candidate_name, party_affiliation, biography, photo_url, user_id) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$position_id, $candidate_name, $party_affiliation, $biography, $photo_path, $user_id]);
                        
                        $db->commit();
                        $message = "Candidate added successfully! Login credentials - Username: $username, Password: $password";
                        
                        // Log the activity
                        logActivity($_SESSION['user_id'], 'CREATE', 'candidates', $db->lastInsertId());
                        
                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                }
            }
        }
            
        } elseif ($action === 'delete_position' && $_SESSION['user_type'] === 'super_admin') {
            if ($election['status'] !== 'draft') {
                $error = 'Cannot delete positions: Election has already started or completed. Positions can only be deleted from draft elections.';
            } else {
                $position_id = (int)$_POST['position_id'];
                $stmt = $db->prepare("DELETE FROM positions WHERE position_id = ? AND election_id = ?");
                $stmt->execute([$position_id, $election_id]);
                $message = 'Position deleted successfully!';
            }
            
        } elseif ($action === 'delete_candidate' && $_SESSION['user_type'] === 'super_admin') {
            if ($election['status'] !== 'draft') {
                $error = 'Cannot delete candidates: Election has already started or completed. Candidates can only be deleted from draft elections.';
            } else {
                $candidate_id = (int)$_POST['candidate_id'];
                $stmt = $db->prepare("DELETE FROM candidates WHERE candidate_id = ? AND position_id IN (SELECT position_id FROM positions WHERE election_id = ?)");
                $stmt->execute([$candidate_id, $election_id]);
                $message = 'Candidate deleted successfully!';
            }
        }
        
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

try {
    $db = getDBConnection();
    
    // Get positions with candidates (election already loaded above)
    $stmt = $db->prepare("SELECT * FROM positions WHERE election_id = ? ORDER BY position_id ASC");
    $stmt->execute([$election_id]);
    $positions = $stmt->fetchAll();
    
    // Get candidates for each position
    $candidates_by_position = [];
    foreach ($positions as $position) {
        $stmt = $db->prepare("
            SELECT c.*, u.username, u.is_active as user_active,
                   (SELECT COUNT(*) FROM votes v WHERE v.candidate_id = c.candidate_id) as vote_count 
            FROM candidates c 
            LEFT JOIN users u ON c.user_id = u.user_id
            WHERE c.position_id = ? 
            ORDER BY c.candidate_id ASC
        ");
        $stmt->execute([$position['position_id']]);
        $candidates_by_position[$position['position_id']] = $stmt->fetchAll();
    }
    
    // Get vote statistics
    $stmt = $db->prepare("SELECT COUNT(DISTINCT voter_id) as total_voters, COUNT(*) as total_votes FROM votes WHERE election_id = ?");
    $stmt->execute([$election_id]);
    $vote_stats = $stmt->fetch();
    
} catch (Exception $e) {
    $error = 'Error loading data: ' . $e->getMessage();
    $positions = [];
    $candidates_by_position = [];
    $vote_stats = ['total_voters' => 0, 'total_votes' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Election Details - Admin Panel</title>
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
        
        .election-header {
            background: linear-gradient(135deg, #667eea 0%, #4ba296 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin: 2rem 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .stat-box {
            background: rgba(255,255,255,0.1);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            display: block;
        }
        
        .position-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin: 2rem 0;
            overflow: hidden;
        }
        
        .position-title {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .candidate-card {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #f1f1f1;
        }
        
        .candidate-photo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 1rem;
            border: 2px solid #dee2e6;
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
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .candidate-bio {
            color: #666;
            font-size: 0.85rem;
        }
        
        .vote-count {
            text-align: center;
            margin-right: 1rem;
        }
        
        .vote-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
            display: block;
        }
        
        .form-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 2rem 0;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="container">
            <h1>🗳️ Election Details</h1>
        </div>
    </header>
    
    <nav class="admin-nav">
        <div class="container">
            <ul>
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="users.php">User Management</a></li>
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

    <script>
        // Password validation
        function validatePasswords(form) {
            const password = form.querySelector('input[name="password"]').value;
            const confirmPassword = form.querySelector('input[name="confirm_password"]').value;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                alert('Password must be at least 6 characters long!');
                return false;
            }
            
            return true;
        }
        
        // Add validation to all candidate forms
        document.addEventListener('DOMContentLoaded', function() {
            const candidateForms = document.querySelectorAll('form[method="POST"]');
            candidateForms.forEach(form => {
                if (form.querySelector('input[name="password"]')) {
                    form.addEventListener('submit', function(e) {
                        if (!validatePasswords(form)) {
                            e.preventDefault();
                        }
                    });
                }
            });
        });
    </script>

    <main>
        <div class="container">
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($election): ?>
                <!-- Election Overview -->
                <div class="election-header">
                    <h2><?php echo htmlspecialchars($election['title']); ?></h2>
                    <p><?php echo htmlspecialchars($election['description']); ?></p>
                    
                    <div class="stats-grid">
                        <div class="stat-box">
                            <span class="stat-number"><?php echo ucfirst($election['status']); ?></span>
                            <small>Status</small>
                        </div>
                        <div class="stat-box">
                            <span class="stat-number"><?php echo count($positions); ?></span>
                            <small>Positions</small>
                        </div>
                        <div class="stat-box">
                            <span class="stat-number"><?php echo $vote_stats['total_voters']; ?></span>
                            <small>Voters</small>
                        </div>
                        <div class="stat-box">
                            <span class="stat-number"><?php echo $vote_stats['total_votes']; ?></span>
                            <small>Total Votes</small>
                        </div>
                    </div>
                </div>
                
                <!-- Add Position Form -->
                <?php if ($election['status'] === 'draft'): ?>
                    <div class="form-section">
                        <h3>➕ Add New Position</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="add_position">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="position_name">Position Name *</label>
                                    <input type="text" id="position_name" name="position_name" required 
                                           placeholder="e.g., President, Secretary">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="2" 
                                          placeholder="Brief description of this position..."></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-success">Add Position</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="form-section" style="background: #f8d7da; border: 1px solid #f5c6cb;">
                        <h3>➕ Add New Position</h3>
                        <div style="color: #721c24; padding: 1rem; text-align: center;">
                            <strong>⚠️ Cannot Add Positions</strong><br>
                            Positions can only be added to elections in <strong>draft</strong> status.<br>
                            Current status: <strong><?php echo ucfirst($election['status']); ?></strong>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Positions & Candidates -->
                <h2>📋 Positions & Candidates</h2>
                
                <?php if (empty($positions)): ?>
                    <div style="text-align: center; padding: 3rem; color: #666;">
                        <h3>No positions created yet</h3>
                        <p>Add positions using the form above to start managing candidates.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($positions as $position): ?>
                        <div class="position-section">
                            <div class="position-title">
                                <div>
                                    <h3><?php echo htmlspecialchars($position['position_name']); ?></h3>
                                    <?php if ($position['description']): ?>
                                        <p style="margin: 0.5rem 0 0 0; color: #666; font-size: 0.9rem;">
                                            <?php echo htmlspecialchars($position['description']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <span style="background: #e3f2fd; padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.9rem;">
                                        <?php echo count($candidates_by_position[$position['position_id']] ?? []); ?> candidates
                                    </span>
                                    <?php if ($_SESSION['user_type'] === 'super_admin' && $election['status'] === 'draft'): ?>
                                        <form method="POST" style="display: inline; margin-left: 1rem;">
                                            <input type="hidden" name="action" value="delete_position">
                                            <input type="hidden" name="position_id" value="<?php echo $position['position_id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-xs" 
                                                    onclick="return confirm('Delete this position and all candidates?')">
                                                Delete
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Add Candidate Form -->
                            <?php if ($election['status'] === 'draft'): ?>
                                <div style="background: #e8f5e8; padding: 1.5rem;">
                                    <h4>➕ Add Candidate</h4>
                                    <form method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="action" value="add_candidate">
                                        <input type="hidden" name="position_id" value="<?php echo $position['position_id']; ?>">
                                        
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>Candidate Name *</label>
                                                <input type="text" name="candidate_name" required placeholder="Full name">
                                            </div>
                                            
                                            <div class="form-group">
                                                <label>Party/Affiliation</label>
                                                <input type="text" name="party_affiliation" placeholder="Political party">
                                            </div>
                                            
                                            <div class="form-group">
                                                <label>Photo</label>
                                                <input type="file" name="candidate_photo" accept="image/*">
                                            </div>
                                        </div>
                                        
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>Username * <small>(for candidate login)</small></label>
                                                <input type="text" name="username" required placeholder="candidate_username" 
                                                       pattern="[a-zA-Z0-9_]+" title="Only letters, numbers, and underscores allowed">
                                            </div>
                                            
                                            <div class="form-group">
                                                <label>Password * <small>(min 6 characters)</small></label>
                                                <input type="password" name="password" required minlength="6" placeholder="Enter password">
                                            </div>
                                            
                                            <div class="form-group">
                                                <label>Confirm Password *</label>
                                                <input type="password" name="confirm_password" required minlength="6" placeholder="Confirm password">
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Biography</label>
                                            <textarea name="biography" rows="2" placeholder="Brief background..."></textarea>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">Add Candidate with Login Account</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div style="background: #f8d7da; padding: 1.5rem; border: 1px solid #f5c6cb;">
                                    <h4>➕ Add Candidate</h4>
                                    <div style="color: #721c24; text-align: center; padding: 1rem;">
                                        <strong>⚠️ Cannot Add Candidates</strong><br>
                                        Candidates can only be added to elections in <strong>draft</strong> status.<br>
                                        Current status: <strong><?php echo ucfirst($election['status']); ?></strong>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Candidates List -->
                            <div>
                                <?php if (empty($candidates_by_position[$position['position_id']])): ?>
                                    <div style="padding: 2rem; text-align: center; color: #666;">
                                        <p>No candidates added yet for this position.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($candidates_by_position[$position['position_id']] as $candidate): ?>
                                        <div class="candidate-card">
                                            <?php if ($candidate['photo_url']): ?>
                                                <img src="../../<?php echo htmlspecialchars($candidate['photo_url']); ?>" 
                                                     alt="<?php echo htmlspecialchars($candidate['candidate_name']); ?>" 
                                                     class="candidate-photo">
                                            <?php else: ?>
                                                <div class="candidate-photo" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                                                    👤
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="candidate-info">
                                                <div class="candidate-name">
                                                    <?php echo htmlspecialchars($candidate['candidate_name']); ?>
                                                    <?php if ($candidate['username']): ?>
                                                        <small style="color: #28a745; font-weight: normal;">
                                                            (Login: <?php echo htmlspecialchars($candidate['username']); ?>)
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($candidate['party_affiliation']): ?>
                                                    <div class="candidate-party">
                                                        <?php echo htmlspecialchars($candidate['party_affiliation']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($candidate['biography']): ?>
                                                    <div class="candidate-bio">
                                                        <?php echo htmlspecialchars($candidate['biography']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($candidate['username']): ?>
                                                    <div style="margin-top: 0.5rem;">
                                                        <span style="background: <?php echo $candidate['user_active'] ? '#d4edda' : '#f8d7da'; ?>; 
                                                                     color: <?php echo $candidate['user_active'] ? '#155724' : '#721c24'; ?>; 
                                                                     padding: 0.2rem 0.5rem; border-radius: 10px; font-size: 0.75rem;">
                                                            <?php echo $candidate['user_active'] ? '✓ Login Active' : '✗ Login Disabled'; ?>
                                                        </span>
                                                    </div>
                                                <?php else: ?>
                                                    <div style="margin-top: 0.5rem;">
                                                        <span style="background: #fff3cd; color: #856404; padding: 0.2rem 0.5rem; border-radius: 10px; font-size: 0.75rem;">
                                                            ⚠ No Login Account
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="vote-count">
                                                <span class="vote-number"><?php echo number_format($candidate['vote_count']); ?></span>
                                                <small>votes</small>
                                            </div>
                                            
                                            <?php if ($_SESSION['user_type'] === 'super_admin' && $candidate['vote_count'] == 0 && $election['status'] === 'draft'): ?>
                                                <form method="POST" style="margin-left: 1rem;">
                                                    <input type="hidden" name="action" value="delete_candidate">
                                                    <input type="hidden" name="candidate_id" value="<?php echo $candidate['candidate_id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-xs" 
                                                            onclick="return confirm('Delete this candidate?')">
                                                        Delete
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Navigation -->
                <div style="text-align: center; margin: 3rem 0;">
                    <a href="elections.php" class="btn btn-secondary">← Back to Elections</a>
                    <?php if ($election['status'] === 'completed'): ?>
                        <a href="../results.php?election_id=<?php echo $election_id; ?>" class="btn btn-primary">📊 View Results</a>
                    <?php endif; ?>
                </div>
                
            <?php endif; ?>
        </div>
    </main>
</body>
</html>