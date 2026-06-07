<?php
/**
 * Reports & Analytics - Admin Panel
 * Online Voting System - Wollo University
 */
require_once '../config/config.php';
requireLogin();

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'super_admin' && $_SESSION['user_type'] !== 'sub_admin') {
    header('Location: ../dashboard.php');
    exit();
}

$selected_report = $_GET['report'] ?? 'overview';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

try {
    $db = getDBConnection();
    
    // System Overview Statistics
    $overview = [];
    
    // Total users by type
    $stmt = $db->query("
        SELECT user_type, COUNT(*) as count 
        FROM users 
        GROUP BY user_type
    ");
    $user_types = $stmt->fetchAll();
    $overview['total_users'] = array_sum(array_column($user_types, 'count'));
    
    // Verification statistics
    $stmt = $db->query("
        SELECT verification_status, COUNT(*) as count 
        FROM voter_profiles 
        GROUP BY verification_status
    ");
    $verification_stats = $stmt->fetchAll();
    
    // Election statistics
    $stmt = $db->query("
        SELECT status, COUNT(*) as count 
        FROM elections 
        GROUP BY status
    ");
    $election_stats = $stmt->fetchAll();
    $overview['total_elections'] = array_sum(array_column($election_stats, 'count'));
    
    // Voting statistics
    $stmt = $db->query("SELECT COUNT(*) as count FROM votes");
    $overview['total_votes'] = $stmt->fetch()['count'];
    
    $stmt = $db->query("SELECT COUNT(DISTINCT voter_id) as count FROM votes");
    $overview['unique_voters'] = $stmt->fetch()['count'];
    
    // Active users (users who have logged in within last 30 days)
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['active_users'] = $stmt->fetch()['count'];
    
    // Recent activity (last 7 days)
    $stmt = $db->query("
        SELECT DATE(created_at) as date, COUNT(*) as registrations
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $recent_registrations = $stmt->fetchAll();
    
    $stmt = $db->query("
        SELECT DATE(cast_at) as date, COUNT(*) as votes
        FROM votes 
        WHERE cast_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(cast_at)
        ORDER BY date DESC
    ");
    $recent_votes = $stmt->fetchAll();
    
    // Election Reports
    $election_reports = [];
    if ($selected_report === 'elections') {
        $stmt = $db->query("
            SELECT e.election_id, e.title, e.election_type, e.status, e.start_date, e.end_date,
                   COUNT(DISTINCT p.position_id) as positions,
                   COUNT(DISTINCT c.candidate_id) as candidates,
                   COUNT(DISTINCT v.voter_id) as voters,
                   COUNT(v.vote_id) as total_votes
            FROM elections e
            LEFT JOIN positions p ON e.election_id = p.election_id
            LEFT JOIN candidates c ON p.position_id = c.position_id
            LEFT JOIN votes v ON e.election_id = v.election_id
            GROUP BY e.election_id
            ORDER BY e.created_at DESC
        ");
        $election_reports = $stmt->fetchAll();
    }
    
    // User Reports
    $user_reports = [];
    if ($selected_report === 'users') {
        $stmt = $db->prepare("
            SELECT u.user_id, u.username, u.first_name, u.last_name, u.email, u.user_type, 
                   u.is_active, u.is_verified, u.created_at, u.last_login,
                   vp.verification_status, vp.voter_id, vp.national_id,
                   COUNT(v.vote_id) as votes_cast
            FROM users u
            LEFT JOIN voter_profiles vp ON u.user_id = vp.user_id
            LEFT JOIN votes v ON u.user_id = v.voter_id
            WHERE u.created_at BETWEEN ? AND ?
            GROUP BY u.user_id
            ORDER BY u.created_at DESC
        ");
        $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
        $user_reports = $stmt->fetchAll();
    }
    
    // Voting Reports
    $voting_reports = [];
    if ($selected_report === 'voting') {
        $stmt = $db->prepare("
            SELECT v.vote_id, v.cast_at, v.ip_address,
                   u.username, u.first_name, u.last_name,
                   e.title as election_title,
                   p.position_name,
                   c.candidate_name,
                   vr.receipt_code
            FROM votes v
            JOIN users u ON v.voter_id = u.user_id
            JOIN elections e ON v.election_id = e.election_id
            JOIN positions p ON v.position_id = p.position_id
            JOIN candidates c ON v.candidate_id = c.candidate_id
            LEFT JOIN vote_receipts vr ON v.vote_id = vr.vote_id
            WHERE v.cast_at BETWEEN ? AND ?
            ORDER BY v.cast_at DESC
            LIMIT 1000
        ");
        $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
        $voting_reports = $stmt->fetchAll();
    }
    
    // Security Reports
    $security_reports = [];
    if ($selected_report === 'security') {
        // Failed login attempts
        $stmt = $db->prepare("
            SELECT username, ip_address, COUNT(*) as attempts, 
                   MAX(attempt_time) as last_attempt
            FROM login_attempts 
            WHERE success = 0 AND attempt_time BETWEEN ? AND ?
            GROUP BY username, ip_address
            HAVING attempts >= 3
            ORDER BY attempts DESC, last_attempt DESC
        ");
        $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
        $security_reports['failed_logins'] = $stmt->fetchAll();
        
        // Recent admin activities
        $stmt = $db->prepare("
            SELECT al.*, u.username, u.first_name, u.last_name
            FROM audit_log al
            JOIN users u ON al.user_id = u.user_id
            WHERE al.timestamp BETWEEN ? AND ?
            AND u.user_type IN ('admin', 'super_admin', 'sub_admin')
            ORDER BY al.timestamp DESC
            LIMIT 100
        ");
        $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
        $security_reports['admin_activities'] = $stmt->fetchAll();
    }
    
} catch (Exception $e) {
    $error_message = 'Error loading reports: ' . $e->getMessage();
    error_log("Reports error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Admin Panel</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .admin-header {
            background: #000000 !important;
            color: white !important;
        }
        
        .reports-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .report-filters {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        .report-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            padding: 0.75rem 1.5rem;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            text-decoration: none;
            color: #495057;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .tab-btn.active {
            background: #ff7200;
            color: white;
            border-color: #ff7200;
        }
        
        .tab-btn:hover {
            background: #e9ecef;
        }
        
        .tab-btn.active:hover {
            background: #e65100;
        }
        
        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .overview-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .overview-number {
            font-size: 3rem;
            font-weight: 700;
            color: #ff7200;
            margin-bottom: 0.5rem;
        }
        
        .overview-label {
            color: #6c757d;
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .chart-container {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .chart-title {
            color: #ff7200;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .report-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .table-header {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table-title {
            color: #ff7200;
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 1px solid #dee2e6;
        }
        
        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #f8f9fa;
            color: #495057;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .export-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
            font-style: italic;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .stat-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ff7200;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        /* Chart Styles */
        .chart-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .chart-tab-btn {
            padding: 0.75rem 1.5rem;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            color: #495057;
        }
        
        .chart-tab-btn.active {
            background: #ff7200;
            color: white;
            border-color: #ff7200;
        }
        
        .chart-tab-btn:hover {
            background: #e9ecef;
        }
        
        .chart-tab-btn.active:hover {
            background: #e65100;
        }
        
        .chart-section {
            display: none;
            padding: 2rem;
            background: #f8f9fa;
            border-radius: 10px;
            margin-top: 1rem;
        }
        
        .chart-section.active {
            display: block;
        }
        
        .chart-section canvas {
            max-width: 100%;
            height: auto !important;
        }
        
        /* Performance Cards */
        .performance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 1rem 0;
        }
        
        .performance-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .performance-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .performance-card:hover::before {
            left: 100%;
        }
        
        .performance-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 35px rgba(0,0,0,0.25);
        }
        
        .performance-card:active {
            transform: translateY(-5px) scale(0.98);
        }
        
        .performance-card:nth-child(2) {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .performance-card:nth-child(3) {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .performance-card:nth-child(4) {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        
        .performance-icon {
            font-size: 3rem;
            opacity: 0.9;
        }
        
        .performance-content {
            flex: 1;
        }
        
        .performance-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .performance-label {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .performance-trend {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .performance-arrow {
            font-size: 1.5rem;
            opacity: 0.7;
            transition: all 0.3s ease;
        }
        
        .performance-card:hover .performance-arrow {
            opacity: 1;
            transform: translateX(5px);
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            background: linear-gradient(135deg, #ff7200 0%, #ff9500 100%);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 1.3rem;
        }
        
        .modal-close {
            font-size: 2rem;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }
        
        .modal-close:hover {
            opacity: 1;
        }
        
        .modal-body {
            padding: 2rem;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        
        .detail-stat {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .detail-stat-label {
            font-weight: 500;
            color: #495057;
        }
        
        .detail-stat-value {
            font-weight: 700;
            color: #ff7200;
            font-size: 1.1rem;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #ff7200, #ff9500);
            transition: width 0.5s ease;
        }
        
        @media (max-width: 768px) {
            .reports-container {
                padding: 1rem;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .report-tabs {
                flex-direction: column;
            }
            
            .overview-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-tabs {
                flex-direction: column;
                align-items: center;
            }
            
            .chart-tab-btn {
                width: 100%;
                max-width: 200px;
                text-align: center;
            }
            
            .performance-grid {
                grid-template-columns: 1fr;
            }
            
            .performance-card {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .performance-icon {
                font-size: 2.5rem;
            }
            
            .performance-number {
                font-size: 2rem;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
            
            .modal-header {
                padding: 1rem 1.5rem;
            }
            
            .modal-body {
                padding: 1.5rem;
            }
            
            .modal-footer {
                padding: 1rem 1.5rem;
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .modal-footer button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="container">
            <h1>📊 Reports & Analytics</h1>
        </div>
    </header>
    
    <nav class="admin-nav">
        <div class="container">
            <ul>
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="users.php">User Management</a></li>
                <li><a href="elections.php">Elections</a></li>
                <li><a href="candidates.php">Candidates</a></li>
                <li><a href="reports.php" class="active">Reports</a></li>
                <?php if ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'super_admin'): ?>
                <li><a href="settings.php">Settings</a></li>
                <?php endif; ?>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <main>
        <div class="reports-container">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <!-- Report Filters -->
            <div class="report-filters">
                <form method="GET" class="filter-grid">
                    <div class="form-group">
                        <label for="report">Report Type:</label>
                        <select id="report" name="report" onchange="this.form.submit()">
                            <option value="overview" <?php echo $selected_report === 'overview' ? 'selected' : ''; ?>>System Overview</option>
                            <option value="elections" <?php echo $selected_report === 'elections' ? 'selected' : ''; ?>>Election Reports</option>
                            <option value="users" <?php echo $selected_report === 'users' ? 'selected' : ''; ?>>User Reports</option>
                            <option value="voting" <?php echo $selected_report === 'voting' ? 'selected' : ''; ?>>Voting Activity</option>
                            <option value="security" <?php echo $selected_report === 'security' ? 'selected' : ''; ?>>Security Reports</option>
                        </select>
                    </div>
                    
                    <?php if ($selected_report !== 'overview'): ?>
                    <div class="form-group">
                        <label for="date_from">From Date:</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to">To Date:</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Generate Report</button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- System Overview -->
            <?php if ($selected_report === 'overview'): ?>
                <div class="overview-grid">
                    <div class="overview-card">
                        <div class="overview-number"><?php echo number_format($overview['total_users']); ?></div>
                        <div class="overview-label">Total Users</div>
                    </div>
                    <div class="overview-card">
                        <div class="overview-number"><?php echo number_format($overview['total_elections']); ?></div>
                        <div class="overview-label">Total Elections</div>
                    </div>
                    <div class="overview-card">
                        <div class="overview-number"><?php echo number_format($overview['total_votes']); ?></div>
                        <div class="overview-label">Total Votes Cast</div>
                    </div>
                    <div class="overview-card">
                        <div class="overview-number"><?php echo number_format($overview['unique_voters']); ?></div>
                        <div class="overview-label">Unique Voters</div>
                    </div>
                </div>
                
                <!-- Quick Stats Summary -->
                <div class="chart-container">
                    <h3 class="chart-title">📈 System Performance Summary</h3>
                    <div class="performance-grid">
                        <div class="performance-card" onclick="showActiveUsersDetails()" data-action="active-users">
                            <div class="performance-icon">👥</div>
                            <div class="performance-content">
                                <div class="performance-number"><?php echo number_format(($stats['active_users'] ?? 0)); ?></div>
                                <div class="performance-label">Active Users</div>
                                <div class="performance-trend">
                                    <?php 
                                    $active_percentage = $overview['total_users'] > 0 ? round((($stats['active_users'] ?? 0) / $overview['total_users']) * 100, 1) : 0;
                                    echo $active_percentage . '% of total';
                                    ?>
                                </div>
                            </div>
                            <div class="performance-arrow">→</div>
                        </div>
                        
                        <div class="performance-card" onclick="showVoterParticipation()" data-action="voter-participation">
                            <div class="performance-icon">🗳️</div>
                            <div class="performance-content">
                                <div class="performance-number"><?php echo number_format($overview['unique_voters']); ?></div>
                                <div class="performance-label">Unique Voters</div>
                                <div class="performance-trend">
                                    <?php 
                                    $participation_rate = $overview['total_users'] > 0 ? round(($overview['unique_voters'] / $overview['total_users']) * 100, 1) : 0;
                                    echo $participation_rate . '% participation';
                                    ?>
                                </div>
                            </div>
                            <div class="performance-arrow">→</div>
                        </div>
                        
                        <div class="performance-card" onclick="showVotingStats()" data-action="voting-stats">
                            <div class="performance-icon">📊</div>
                            <div class="performance-content">
                                <div class="performance-number"><?php echo number_format($overview['total_votes']); ?></div>
                                <div class="performance-label">Total Votes</div>
                                <div class="performance-trend">
                                    <?php 
                                    $avg_votes = $overview['unique_voters'] > 0 ? round($overview['total_votes'] / $overview['unique_voters'], 1) : 0;
                                    echo $avg_votes . ' avg per voter';
                                    ?>
                                </div>
                            </div>
                            <div class="performance-arrow">→</div>
                        </div>
                        
                        <div class="performance-card" onclick="showRecentActivity()" data-action="recent-activity">
                            <div class="performance-icon">⚡</div>
                            <div class="performance-content">
                                <div class="performance-number"><?php echo count($recent_votes); ?></div>
                                <div class="performance-label">Recent Activity</div>
                                <div class="performance-trend">Last 7 days</div>
                            </div>
                            <div class="performance-arrow">→</div>
                        </div>
                    </div>
                </div>
                
                <!-- User Types Breakdown -->
                <div class="chart-container">
                    <h3 class="chart-title">User Types Distribution</h3>
                    <div class="stats-grid">
                        <?php foreach ($user_types as $type): ?>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo number_format($type['count']); ?></div>
                                <div class="stat-label"><?php echo ucfirst(str_replace('_', ' ', $type['user_type'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Verification Status -->
                <div class="chart-container">
                    <h3 class="chart-title">Voter Verification Status</h3>
                    <div class="stats-grid">
                        <?php foreach ($verification_stats as $status): ?>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo number_format($status['count']); ?></div>
                                <div class="stat-label"><?php echo ucfirst($status['verification_status']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Election Status -->
                <div class="chart-container">
                    <h3 class="chart-title">Election Status Distribution</h3>
                    <div class="stats-grid">
                        <?php foreach ($election_stats as $status): ?>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo number_format($status['count']); ?></div>
                                <div class="stat-label"><?php echo ucfirst($status['status']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Graphical Reports Section -->
                <div class="chart-container">
                    <h3 class="chart-title">📊 Graphical Analytics</h3>
                    
                    <!-- Chart Navigation Tabs -->
                    <div class="chart-tabs">
                        <button class="chart-tab-btn active" onclick="showChart('userTypes')">User Distribution</button>
                        <button class="chart-tab-btn" onclick="showChart('verificationStatus')">Verification Status</button>
                        <button class="chart-tab-btn" onclick="showChart('electionStatus')">Election Status</button>
                        <button class="chart-tab-btn" onclick="showChart('votingTrends')">Voting Trends</button>
                        <button class="chart-tab-btn" onclick="showChart('registrationTrends')">Registration Trends</button>
                    </div>
                    
                    <!-- User Types Chart -->
                    <div id="userTypesChart" class="chart-section active">
                        <canvas id="userTypesCanvas" width="400" height="200"></canvas>
                    </div>
                    
                    <!-- Verification Status Chart -->
                    <div id="verificationStatusChart" class="chart-section">
                        <canvas id="verificationStatusCanvas" width="400" height="200"></canvas>
                    </div>
                    
                    <!-- Election Status Chart -->
                    <div id="electionStatusChart" class="chart-section">
                        <canvas id="electionStatusCanvas" width="400" height="200"></canvas>
                    </div>
                    
                    <!-- Voting Trends Chart -->
                    <div id="votingTrendsChart" class="chart-section">
                        <canvas id="votingTrendsCanvas" width="400" height="200"></canvas>
                    </div>
                    
                    <!-- Registration Trends Chart -->
                    <div id="registrationTrendsChart" class="chart-section">
                        <canvas id="registrationTrendsCanvas" width="400" height="200"></canvas>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Election Reports -->
            <?php if ($selected_report === 'elections'): ?>
                <div class="report-table">
                    <div class="table-header">
                        <h3 class="table-title">Election Performance Report</h3>
                    </div>
                    <?php if (empty($election_reports)): ?>
                        <div class="no-data">No election data available.</div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Election Title</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Positions</th>
                                    <th>Candidates</th>
                                    <th>Voters</th>
                                    <th>Total Votes</th>
                                    <th>Participation Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($election_reports as $election): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($election['title']); ?></strong></td>
                                        <td><?php echo ucfirst($election['election_type']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $election['status']; ?>">
                                                <?php echo ucfirst($election['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $election['positions']; ?></td>
                                        <td><?php echo $election['candidates']; ?></td>
                                        <td><?php echo $election['voters']; ?></td>
                                        <td><?php echo $election['total_votes']; ?></td>
                                        <td>
                                            <?php 
                                            $participation = $election['candidates'] > 0 ? 
                                                round(($election['voters'] / $overview['unique_voters']) * 100, 1) : 0;
                                            echo $participation . '%';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- User Reports -->
            <?php if ($selected_report === 'users'): ?>
                <div class="report-table">
                    <div class="table-header">
                        <h3 class="table-title">User Registration Report (<?php echo $date_from; ?> to <?php echo $date_to; ?>)</h3>
                    </div>
                    <?php if (empty($user_reports)): ?>
                        <div class="no-data">No user data available for the selected date range.</div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Type</th>
                                    <th>Voter ID</th>
                                    <th>Status</th>
                                    <th>Votes Cast</th>
                                    <th>Registered</th>
                                    <th>Last Login</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_reports as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo ucfirst($user['user_type']); ?></td>
                                        <td><?php echo htmlspecialchars($user['voter_id'] ?: 'N/A'); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $user['verification_status'] ?: 'inactive'; ?>">
                                                <?php echo ucfirst($user['verification_status'] ?: 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $user['votes_cast']; ?></td>
                                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                        <td><?php echo $user['last_login'] ? date('M j, Y', strtotime($user['last_login'])) : 'Never'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Voting Reports -->
            <?php if ($selected_report === 'voting'): ?>
                <div class="report-table">
                    <div class="table-header">
                        <h3 class="table-title">Voting Activity Report (<?php echo $date_from; ?> to <?php echo $date_to; ?>)</h3>
                    </div>
                    <?php if (empty($voting_reports)): ?>
                        <div class="no-data">No voting activity for the selected date range.</div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Voter</th>
                                    <th>Election</th>
                                    <th>Position</th>
                                    <th>Candidate</th>
                                    <th>Vote Time</th>
                                    <th>IP Address</th>
                                    <th>Receipt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($voting_reports as $vote): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($vote['first_name'] . ' ' . $vote['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($vote['election_title']); ?></td>
                                        <td><?php echo htmlspecialchars($vote['position_name']); ?></td>
                                        <td><?php echo htmlspecialchars($vote['candidate_name']); ?></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($vote['cast_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($vote['ip_address']); ?></td>
                                        <td><?php echo htmlspecialchars($vote['receipt_code'] ?: 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Security Reports -->
            <?php if ($selected_report === 'security'): ?>
                <!-- Failed Login Attempts -->
                <div class="report-table">
                    <div class="table-header">
                        <h3 class="table-title">Failed Login Attempts (<?php echo $date_from; ?> to <?php echo $date_to; ?>)</h3>
                    </div>
                    <?php if (empty($security_reports['failed_logins'])): ?>
                        <div class="no-data">No suspicious login activity detected.</div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>IP Address</th>
                                    <th>Failed Attempts</th>
                                    <th>Last Attempt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($security_reports['failed_logins'] as $attempt): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($attempt['username']); ?></td>
                                        <td><?php echo htmlspecialchars($attempt['ip_address']); ?></td>
                                        <td><strong style="color: #dc3545;"><?php echo $attempt['attempts']; ?></strong></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($attempt['last_attempt'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <!-- Admin Activities -->
                <div class="report-table">
                    <div class="table-header">
                        <h3 class="table-title">Admin Activities (<?php echo $date_from; ?> to <?php echo $date_to; ?>)</h3>
                    </div>
                    <?php if (empty($security_reports['admin_activities'])): ?>
                        <div class="no-data">No admin activities recorded.</div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Admin</th>
                                    <th>Action</th>
                                    <th>Table</th>
                                    <th>Record ID</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($security_reports['admin_activities'] as $activity): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['action']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['table_name'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($activity['record_id'] ?: 'N/A'); ?></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($activity['timestamp'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Performance Details Modal -->
    <div id="performanceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Performance Details</h3>
                <span class="modal-close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Dynamic content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">Close</button>
                <button class="btn btn-primary" id="modalActionBtn" onclick="navigateToSection()">View Details</button>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh reports every 5 minutes
        setTimeout(() => {
            location.reload();
        }, 300000);
        
        // Print functionality
        function printReport() {
            window.print();
        }
        
        // Export functionality (placeholder)
        function exportReport(format) {
            alert('Export to ' + format + ' functionality would be implemented here.');
        }
        
        // Chart data from PHP
        const chartData = {
            userTypes: <?php echo json_encode($user_types); ?>,
            verificationStats: <?php echo json_encode($verification_stats); ?>,
            electionStats: <?php echo json_encode($election_stats); ?>,
            recentRegistrations: <?php echo json_encode($recent_registrations); ?>,
            recentVotes: <?php echo json_encode($recent_votes); ?>
        };
        
        // Chart instances
        let charts = {};
        
        // Show specific chart
        function showChart(chartType) {
            // Hide all chart sections
            document.querySelectorAll('.chart-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.chart-tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected chart section
            document.getElementById(chartType + 'Chart').classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
            
            // Initialize chart if not already done
            if (!charts[chartType]) {
                initializeChart(chartType);
            }
        }
        
        // Initialize specific chart
        function initializeChart(chartType) {
            const ctx = document.getElementById(chartType + 'Canvas').getContext('2d');
            
            switch(chartType) {
                case 'userTypes':
                    charts.userTypes = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: chartData.userTypes.map(item => item.user_type.replace('_', ' ').toUpperCase()),
                            datasets: [{
                                data: chartData.userTypes.map(item => item.count),
                                backgroundColor: [
                                    '#ff7200',
                                    '#3498db',
                                    '#27ae60',
                                    '#e74c3c',
                                    '#9b59b6',
                                    '#f39c12'
                                ],
                                borderWidth: 2,
                                borderColor: '#fff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'User Types Distribution',
                                    font: { size: 16, weight: 'bold' }
                                },
                                legend: {
                                    position: 'bottom',
                                    labels: { padding: 20 }
                                }
                            }
                        }
                    });
                    break;
                    
                case 'verificationStatus':
                    charts.verificationStatus = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: chartData.verificationStats.map(item => item.verification_status.toUpperCase()),
                            datasets: [{
                                data: chartData.verificationStats.map(item => item.count),
                                backgroundColor: [
                                    '#f39c12', // Pending - Orange
                                    '#27ae60', // Verified - Green
                                    '#e74c3c'  // Rejected - Red
                                ],
                                borderWidth: 2,
                                borderColor: '#fff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Voter Verification Status',
                                    font: { size: 16, weight: 'bold' }
                                },
                                legend: {
                                    position: 'bottom',
                                    labels: { padding: 20 }
                                }
                            }
                        }
                    });
                    break;
                    
                case 'electionStatus':
                    charts.electionStatus = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: chartData.electionStats.map(item => item.status.toUpperCase()),
                            datasets: [{
                                label: 'Number of Elections',
                                data: chartData.electionStats.map(item => item.count),
                                backgroundColor: [
                                    '#95a5a6', // Draft - Gray
                                    '#27ae60', // Active - Green
                                    '#3498db', // Completed - Blue
                                    '#e74c3c'  // Cancelled - Red
                                ],
                                borderWidth: 1,
                                borderColor: '#fff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Election Status Distribution',
                                    font: { size: 16, weight: 'bold' }
                                },
                                legend: { display: false }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { stepSize: 1 }
                                }
                            }
                        }
                    });
                    break;
                    
                case 'votingTrends':
                    const votingLabels = chartData.recentVotes.map(item => {
                        const date = new Date(item.date);
                        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    });
                    
                    charts.votingTrends = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: votingLabels,
                            datasets: [{
                                label: 'Votes Cast',
                                data: chartData.recentVotes.map(item => item.votes),
                                borderColor: '#ff7200',
                                backgroundColor: 'rgba(255, 114, 0, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: '#ff7200',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                                pointRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Voting Activity (Last 7 Days)',
                                    font: { size: 16, weight: 'bold' }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { stepSize: 1 }
                                }
                            }
                        }
                    });
                    break;
                    
                case 'registrationTrends':
                    const registrationLabels = chartData.recentRegistrations.map(item => {
                        const date = new Date(item.date);
                        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    });
                    
                    charts.registrationTrends = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: registrationLabels,
                            datasets: [{
                                label: 'New Registrations',
                                data: chartData.recentRegistrations.map(item => item.registrations),
                                backgroundColor: 'rgba(52, 152, 219, 0.8)',
                                borderColor: '#3498db',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'User Registrations (Last 7 Days)',
                                    font: { size: 16, weight: 'bold' }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { stepSize: 1 }
                                }
                            }
                        }
                    });
                    break;
            }
        }
        
        // Initialize default chart on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializeChart('userTypes');
        });
        
        // Performance Card Functions
        let currentModalAction = '';
        
        function showActiveUsersDetails() {
            const totalUsers = <?php echo $overview['total_users']; ?>;
            const activeUsers = <?php echo $stats['active_users'] ?? 0; ?>;
            const inactiveUsers = totalUsers - activeUsers;
            const activePercentage = totalUsers > 0 ? ((activeUsers / totalUsers) * 100).toFixed(1) : 0;
            
            document.getElementById('modalTitle').textContent = '👥 Active Users Analysis';
            document.getElementById('modalBody').innerHTML = `
                <div class="detail-stat">
                    <span class="detail-stat-label">Total Registered Users</span>
                    <span class="detail-stat-value">${totalUsers.toLocaleString()}</span>
                </div>
                <div class="detail-stat">
                    <span class="detail-stat-label">Active Users (Last 30 Days)</span>
                    <span class="detail-stat-value">${activeUsers.toLocaleString()}</span>
                </div>
                <div class="detail-stat">
                    <span class="detail-stat-label">Inactive Users</span>
                    <span class="detail-stat-value">${inactiveUsers.toLocaleString()}</span>
                </div>
                <div class="detail-stat">
                    <div>
                        <span class="detail-stat-label">Activity Rate</span>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${activePercentage}%"></div>
                        </div>
                    </div>
                    <span class="detail-stat-value">${activePercentage}%</span>
                </div>
                <p style="margin-top: 1.5rem; color: #6c757d; font-style: italic;">
                    Active users are those who have logged in within the last 30 days. 
                    This metric helps measure user engagement and system adoption.
                </p>
            `;
            currentModalAction = 'users';
            document.getElementById('modalActionBtn').textContent = 'Manage Users';
            showModal();
        }
        
        function showVoterParticipation() {
            const totalUsers = <?php echo $overview['total_users']; ?>;
            const uniqueVoters = <?php echo $overview['unique_voters']; ?>;
            const nonVoters = totalUsers - uniqueVoters;
            const participationRate = totalUsers > 0 ? ((uniqueVoters / totalUsers) * 100).toFixed(1) : 0;
            
            document.getElementById('modalTitle').textContent = '🗳️ Voter Participation Analysis';
            document.getElementById('modalBody').innerHTML = `
                <div class="detail-stat">
                    <span class="detail-stat-label">Total Registered Users</span>
                    <span class="detail-stat-value">${totalUsers.toLocaleString()}</span>
                </div>
                <div class="detail-stat">
                    <span class="detail-stat-label">Users Who Have Voted</span>
                    <span class="detail-stat-value">${uniqueVoters.toLocaleString()}</span>
                </div>
                <div class="detail-stat">
                    <span class="detail-stat-label">Users Who Haven't Voted</span>
                    <span class="detail-stat-value">${nonVoters.toLocaleString()}</span>
                </div>
                <div class="detail-stat">
                    <div>
                        <span class="detail-stat-label">Participation Rate</span>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${participationRate}%"></div>
                        </div>
                    </div>
                    <span class="detail-stat-value">${participationRate}%</span>
                </div>
                <p style="margin-top: 1.5rem; color: #6c757d; font-style: italic;">
                    Participation rate shows the percentage of registered users who have cast at least one vote. 
                    Higher participation indicates better democratic engagement.
                </p>
            `;
            currentModalAction = 'elections';
            document.getElementById('modalActionBtn').textContent = 'View Elections';
            showModal();
        }
        
        function showVotingStats() {
            const totalVotes = <?php echo $overview['total_votes']; ?>;
            const uniqueVoters = <?php echo $overview['unique_voters']; ?>;
            const avgVotes = uniqueVoters > 0 ? (totalVotes / uniqueVoters).toFixed(1) : 0;
            const totalElections = <?php echo $overview['total_elections']; ?>;
            
            document.getElementById('modalTitle').textContent = '📊 Voting Statistics Analysis';
            document.getElementById('modalBody').innerHTML = `
                <div class="detail-stat">
                    <span class="detail-stat-label">Total Votes Cast</span>
                    <span class="detail-stat-value">${totalVotes.toLocaleString()}</span>
                </div>
                <div class="detail-stat">
                    <span class="detail-stat-label">Unique Voters</span>
                    <span class="detail-stat-value">${uniqueVoters.toLocaleString()}</span>
                </div>
                <div class="detail-stat">
                    <span class="detail-stat-label">Average Votes per Voter</span>
                    <span class="detail-stat-value">${avgVotes}</span>
                </div>
                <div class="detail-stat">
                    <span class="detail-stat-label">Total Elections</span>
                    <span class="detail-stat-value">${totalElections.toLocaleString()}</span>
                </div>
                <div class="detail-stat">
                    <div>
                        <span class="detail-stat-label">Voting Efficiency</span>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${Math.min(avgVotes * 20, 100)}%"></div>
                        </div>
                    </div>
                    <span class="detail-stat-value">${Math.min(avgVotes * 20, 100).toFixed(0)}%</span>
                </div>
                <p style="margin-top: 1.5rem; color: #6c757d; font-style: italic;">
                    These statistics show the overall voting activity. Higher average votes per voter 
                    indicates active participation across multiple elections.
                </p>
            `;
            currentModalAction = 'voting';
            document.getElementById('modalActionBtn').textContent = 'View Voting Reports';
            showModal();
        }
        
        function showRecentActivity() {
            const recentVotes = <?php echo count($recent_votes); ?>;
            const recentRegistrations = <?php echo count($recent_registrations); ?>;
            
            document.getElementById('modalTitle').textContent = '⚡ Recent Activity Analysis';
            document.getElementById('modalBody').innerHTML = `
                <div class="detail-stat">
                    <span class="detail-stat-label">Votes in Last 7 Days</span>
                    <span class="detail-stat-value">${recentVotes.toLocaleString()}</span>
                </div>
                <div class="detail-stat">
                    <span class="detail-stat-label">New Registrations (7 Days)</span>
                    <span class="detail-stat-value">${recentRegistrations.toLocaleString()}</span>
                </div>
                <div class="detail-stat">
                    <span class="detail-stat-label">Daily Average Votes</span>
                    <span class="detail-stat-value">${(recentVotes / 7).toFixed(1)}</span>
                </div>
                <div class="detail-stat">
                    <span class="detail-stat-label">Daily Average Registrations</span>
                    <span class="detail-stat-value">${(recentRegistrations / 7).toFixed(1)}</span>
                </div>
                <div class="detail-stat">
                    <div>
                        <span class="detail-stat-label">Activity Level</span>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${Math.min((recentVotes + recentRegistrations) * 5, 100)}%"></div>
                        </div>
                    </div>
                    <span class="detail-stat-value">${recentVotes + recentRegistrations > 20 ? 'High' : recentVotes + recentRegistrations > 10 ? 'Medium' : 'Low'}</span>
                </div>
                <p style="margin-top: 1.5rem; color: #6c757d; font-style: italic;">
                    Recent activity shows system engagement over the past week. This helps identify 
                    trends and peak usage periods for better system management.
                </p>
            `;
            currentModalAction = 'activity';
            document.getElementById('modalActionBtn').textContent = 'View Activity Reports';
            showModal();
        }
        
        function showModal() {
            document.getElementById('performanceModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal() {
            document.getElementById('performanceModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        function navigateToSection() {
            switch(currentModalAction) {
                case 'users':
                    window.location.href = 'users.php';
                    break;
                case 'elections':
                    window.location.href = 'elections.php';
                    break;
                case 'voting':
                    // Change to voting report
                    closeModal();
                    document.querySelector('select[name="report"]').value = 'voting';
                    document.querySelector('form').submit();
                    break;
                case 'activity':
                    // Change to security report for activity
                    closeModal();
                    document.querySelector('select[name="report"]').value = 'security';
                    document.querySelector('form').submit();
                    break;
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('performanceModal');
            if (event.target === modal) {
                closeModal();
            }
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>