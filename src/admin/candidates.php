<?php
/**
 * Candidates Management - Admin Panel
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

// Handle candidate actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = getDBConnection();
        
        if ($action === 'update_candidate') {
            $candidate_id = (int)$_POST['candidate_id'];
            $candidate_name = sanitizeInput($_POST['candidate_name']);
            $party_affiliation = sanitizeInput($_POST['party_affiliation']);
            $biography = sanitizeInput($_POST['biography']);
            $display_order = (int)$_POST['display_order'];
            
            if (empty($candidate_name)) {
                $error = 'Candidate name is required.';
            } else {
                $stmt = $db->prepare("
                    UPDATE candidates 
                    SET candidate_name = ?, party_affiliation = ?, biography = ?, display_order = ?
                    WHERE candidate_id = ?
                ");
                $stmt->execute([$candidate_name, $party_affiliation, $biography, $display_order, $candidate_id]);
                
                logActivity($_SESSION['user_id'], 'CANDIDATE_UPDATE', 'candidates', $candidate_id);
                $message = 'Candidate updated successfully!';
            }
        }
        
    } catch (Exception $e) {
        $error = 'Action failed: ' . $e->getMessage();
    }
}

try {
    $db = getDBConnection();
    
    // Get all elections with positions
    $stmt = $db->prepare("
        SELECT e.election_id, e.title, e.status,
               COUNT(p.position_id) as position_count,
               COUNT(c.candidate_id) as candidate_count
        FROM elections e
        LEFT JOIN positions p ON e.election_id = p.election_id
        LEFT JOIN candidates c ON p.position_id = c.position_id
        GROUP BY e.election_id
        ORDER BY e.created_at DESC
    ");
    $stmt->execute();
    $elections = $stmt->fetchAll();
    
    // Get all candidates with position and election info
    $stmt = $db->prepare("
        SELECT c.*, p.position_name, p.election_id, e.title as election_title, e.status as election_status,
               (SELECT COUNT(*) FROM votes v WHERE v.candidate_id = c.candidate_id) as vote_count
        FROM candidates c
        JOIN positions p ON c.position_id = p.position_id
        JOIN elections e ON p.election_id = e.election_id
        ORDER BY e.created_at DESC, p.display_order ASC, c.display_order ASC
    ");
    $stmt->execute();
    $all_candidates = $stmt->fetchAll();
    
    // Get positions for dropdown
    $stmt = $db->prepare("
        SELECT p.*, e.title as election_title, e.status as election_status
        FROM positions p
        JOIN elections e ON p.election_id = e.election_id
        ORDER BY e.created_at DESC, p.display_order ASC
    ");
    $stmt->execute();
    $positions = $stmt->fetchAll();
    
    // Statistics
    $stats = [];
    $stmt = $db->query("SELECT COUNT(*) as count FROM candidates");
    $stats['total_candidates'] = $stmt->fetch()['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM candidates WHERE is_active = 1");
    $stats['active_candidates'] = $stmt->fetch()['count'];
    
    $stmt = $db->query("SELECT COUNT(DISTINCT position_id) as count FROM candidates");
    $stats['positions_with_candidates'] = $stmt->fetch()['count'];
    
} catch (Exception $e) {
    $error = 'Error loading candidate data: ' . $e->getMessage();
    $elections = [];
    $all_candidates = [];
    $positions = [];
    $stats = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidates Management - Admin Panel</title>
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
            <h1>👥 Candidates Management</h1>
        </div>
    </header>
    
    <nav class="admin-nav">
        <div class="container">
            <ul>
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="users.php">User Management</a></li>
                <li><a href="elections.php">Elections</a></li>
                <li><a href="candidates.php" class="active">Candidates</a></li>
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
                    <div class="stat-number"><?php echo $stats['total_candidates'] ?? 0; ?></div>
                    <div>Total Candidates</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['active_candidates'] ?? 0; ?></div>
                    <div>Active Candidates</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['positions_with_candidates'] ?? 0; ?></div>
                    <div>Positions with Candidates</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($elections); ?></div>
                    <div>Total Elections</div>
                </div>
            </div>
            
            <!-- Elections Overview -->
            <h2>🗳️ Elections Overview</h2>
            <div class="candidates-grid">
                <?php foreach ($elections as $election): ?>
                    <div class="candidate-card">
                        <div class="candidate-header">
                            <div class="candidate-name"><?php echo htmlspecialchars($election['title']); ?></div>
                            <div class="candidate-position">
                                Status: <?php echo ucfirst($election['status']); ?>
                            </div>
                        </div>
                        <div class="candidate-content">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                                <div>
                                    <strong><?php echo $election['position_count']; ?></strong><br>
                                    <small>Positions</small>
                                </div>
                                <div>
                                    <strong><?php echo $election['candidate_count']; ?></strong><br>
                                    <small>Candidates</small>
                                </div>
                            </div>
                            <a href="election-details.php?id=<?php echo $election['election_id']; ?>" 
                               class="btn btn-primary btn-full">Manage Candidates</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- All Candidates Table -->
            <h2>👥 All Candidates</h2>
            
            <!-- Filters -->
            <div class="filters">
                <label>Filter by:</label>
                <select id="electionFilter" onchange="filterCandidates()">
                    <option value="">All Elections</option>
                    <?php foreach ($elections as $election): ?>
                        <option value="<?php echo $election['election_id']; ?>">
                            <?php echo htmlspecialchars($election['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select id="statusFilter" onchange="filterCandidates()">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                
                <input type="text" id="searchInput" placeholder="Search candidates..." 
                       onkeyup="filterCandidates()" style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 3px;">
            </div>
            
            <?php if (empty($all_candidates)): ?>
                <div style="text-align: center; padding: 3rem; color: #666;">
                    <h3>No candidates found</h3>
                    <p>Add candidates using the bulk add form above or through individual elections.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="candidates-table" id="candidatesTable">
                        <thead>
                            <tr>
                                <th>Candidate</th>
                                <th>Position</th>
                                <th>Election</th>
                                <th>Party</th>
                                <th>Status</th>
                                <th>Votes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_candidates as $candidate): ?>
                                <tr data-election-id="<?php echo $candidate['election_id']; ?>"
                                    data-status="<?php echo $candidate['is_active'] ? 'active' : 'inactive'; ?>">
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($candidate['candidate_name']); ?></strong>
                                            <?php if ($candidate['biography']): ?>
                                                <div style="font-size: 0.875rem; color: #666; margin-top: 0.25rem;">
                                                    <?php echo htmlspecialchars(substr($candidate['biography'], 0, 100)); ?>
                                                    <?php if (strlen($candidate['biography']) > 100) echo '...'; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($candidate['position_name']); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($candidate['election_title']); ?></div>
                                        <small style="color: #666;"><?php echo ucfirst($candidate['election_status']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($candidate['party_affiliation']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $candidate['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $candidate['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong style="color: #28a745;"><?php echo number_format($candidate['vote_count']); ?></strong>
                                    </td>
                                    <td>
                                        <button onclick="toggleEditForm(<?php echo $candidate['candidate_id']; ?>)" 
                                                class="btn btn-secondary btn-xs">Edit</button>
                                        <a href="election-details.php?id=<?php echo $candidate['election_id']; ?>" 
                                           class="btn btn-primary btn-xs">View Election</a>
                                    </td>
                                </tr>
                                <tr id="edit-form-<?php echo $candidate['candidate_id']; ?>" style="display: none;">
                                    <td colspan="7">
                                        <div class="edit-form">
                                            <h4>Edit Candidate</h4>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="update_candidate">
                                                <input type="hidden" name="candidate_id" value="<?php echo $candidate['candidate_id']; ?>">
                                                
                                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                                                    <div class="form-group">
                                                        <label>Candidate Name:</label>
                                                        <input type="text" name="candidate_name" 
                                                               value="<?php echo htmlspecialchars($candidate['candidate_name']); ?>" required>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label>Party Affiliation:</label>
                                                        <input type="text" name="party_affiliation" 
                                                               value="<?php echo htmlspecialchars($candidate['party_affiliation']); ?>">
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label>Display Order:</label>
                                                        <input type="number" name="display_order" 
                                                               value="<?php echo $candidate['display_order']; ?>" min="1">
                                                    </div>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label>Biography:</label>
                                                    <textarea name="biography" rows="2"><?php echo htmlspecialchars($candidate['biography']); ?></textarea>
                                                </div>
                                                
                                                <div style="display: flex; gap: 1rem;">
                                                    <button type="submit" class="btn btn-success">Update Candidate</button>
                                                    <button type="button" onclick="toggleEditForm(<?php echo $candidate['candidate_id']; ?>)" 
                                                            class="btn btn-secondary">Cancel</button>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function toggleEditForm(candidateId) {
            const editRow = document.getElementById(`edit-form-${candidateId}`);
            if (editRow.style.display === 'none') {
                // Hide all other edit forms
                document.querySelectorAll('[id^="edit-form-"]').forEach(row => {
                    row.style.display = 'none';
                });
                editRow.style.display = 'table-row';
            } else {
                editRow.style.display = 'none';
            }
        }
        
        function filterCandidates() {
            const electionFilter = document.getElementById('electionFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            
            const rows = document.querySelectorAll('#candidatesTable tbody tr:not([id^="edit-form-"])');
            
            rows.forEach(row => {
                const electionId = row.dataset.electionId;
                const status = row.dataset.status;
                const text = row.textContent.toLowerCase();
                
                let show = true;
                
                if (electionFilter && electionId !== electionFilter) show = false;
                if (statusFilter && status !== statusFilter) show = false;
                if (searchInput && !text.includes(searchInput)) show = false;
                
                row.style.display = show ? '' : 'none';
                
                // Also hide corresponding edit form
                const editRow = document.getElementById(`edit-form-${row.querySelector('button[onclick]')?.onclick.toString().match(/\d+/)?.[0]}`);
                if (editRow) {
                    editRow.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>