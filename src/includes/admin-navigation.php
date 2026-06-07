<?php
/**
 * Admin Navigation Component
 * Online Voting System - Wollo University
 */

// Ensure user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'super_admin', 'sub_admin'])) {
    header('Location: ../login.php');
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="admin-sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-vote-yea"></i>
            <span>Admin Panel</span>
        </div>
        <div class="user-info">
            <div class="user-avatar-small">
                <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)); ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                <div class="user-role"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['user_type'])); ?></div>
            </div>
        </div>
    </div>

    <div class="sidebar-menu">
        <div class="menu-section">
            <div class="menu-title">Dashboard</div>
            <a href="index.php" class="menu-item <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Overview</span>
            </a>
        </div>

        <div class="menu-section">
            <div class="menu-title">User Management</div>
            <a href="users.php" class="menu-item <?php echo in_array($current_page, ['users.php', 'users-new.php']) ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>All Users</span>
            </a>
            <a href="verify-voter.php" class="menu-item <?php echo $current_page === 'verify-voter.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-check"></i>
                <span>Verify Voters</span>
            </a>
            <a href="user-details.php" class="menu-item <?php echo $current_page === 'user-details.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-cog"></i>
                <span>User Details</span>
            </a>
        </div>

        <div class="menu-section">
            <div class="menu-title">Elections</div>
            <a href="elections.php" class="menu-item <?php echo $current_page === 'elections.php' ? 'active' : ''; ?>">
                <i class="fas fa-poll"></i>
                <span>Elections</span>
            </a>
            <a href="candidates.php" class="menu-item <?php echo $current_page === 'candidates.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-tie"></i>
                <span>Candidates</span>
            </a>
            <a href="election-details.php" class="menu-item <?php echo $current_page === 'election-details.php' ? 'active' : ''; ?>">
                <i class="fas fa-info-circle"></i>
                <span>Election Details</span>
            </a>
        </div>

        <div class="menu-section">
            <div class="menu-title">Reports & Analytics</div>
            <a href="reports.php" class="menu-item <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
        </div>

        <div class="menu-section">
            <div class="menu-title">Security</div>
            <a href="database-security.php" class="menu-item <?php echo $current_page === 'database-security.php' ? 'active' : ''; ?>">
                <i class="fas fa-shield-alt"></i>
                <span>Database Security</span>
            </a>
            <a href="user-actions.php" class="menu-item <?php echo $current_page === 'user-actions.php' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i>
                <span>User Actions</span>
            </a>
        </div>

        <div class="menu-section">
            <div class="menu-title">System</div>
            <a href="settings.php" class="menu-item <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="backup.php" class="menu-item <?php echo $current_page === 'backup.php' ? 'active' : ''; ?>">
                <i class="fas fa-database"></i>
                <span>Backup</span>
            </a>
            <a href="backup-scheduler.php" class="menu-item <?php echo $current_page === 'backup-scheduler.php' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i>
                <span>Backup Scheduler</span>
            </a>
        </div>
    </div>

    <div class="sidebar-footer">
        <a href="../dashboard.php" class="menu-item">
            <i class="fas fa-home"></i>
            <span>Main Dashboard</span>
        </a>
        <a href="../logout.php" class="menu-item logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</nav>

<style>
.admin-sidebar {
    width: 280px;
    height: 100vh;
    background: linear-gradient(180deg, #1e293b 0%, #334155 100%);
    position: fixed;
    left: 0;
    top: 0;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
}

.sidebar-header {
    padding: 2rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    color: white;
    font-size: 1.25rem;
    font-weight: 700;
}

.logo i {
    font-size: 1.5rem;
    color: #3b82f6;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.user-avatar-small {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #3b82f6;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.875rem;
}

.user-details {
    flex: 1;
}

.user-name {
    color: white;
    font-weight: 600;
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
}

.user-role {
    color: #94a3b8;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.sidebar-menu {
    padding: 1rem 0;
}

.menu-section {
    margin-bottom: 2rem;
}

.menu-title {
    color: #94a3b8;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    padding: 0 1.5rem;
    margin-bottom: 0.75rem;
}

.menu-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.5rem;
    color: #cbd5e1;
    text-decoration: none;
    transition: all 0.2s;
    border-left: 3px solid transparent;
}

.menu-item:hover {
    background: rgba(255, 255, 255, 0.05);
    color: white;
    border-left-color: #3b82f6;
}

.menu-item.active {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
    border-left-color: #3b82f6;
}

.menu-item i {
    width: 20px;
    text-align: center;
    font-size: 1rem;
}

.menu-item span {
    font-weight: 500;
    font-size: 0.875rem;
}

.sidebar-footer {
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding: 1rem 0;
    margin-top: auto;
}

.menu-item.logout:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border-left-color: #ef4444;
}

/* Responsive */
@media (max-width: 1024px) {
    .admin-sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    
    .admin-sidebar.open {
        transform: translateX(0);
    }
}

/* Scrollbar Styling */
.admin-sidebar::-webkit-scrollbar {
    width: 6px;
}

.admin-sidebar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
}

.admin-sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
}

.admin-sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}
</style>