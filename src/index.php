<?php
/**
 * Home Page - Online Voting System
 * Wollo University - Integrated Project
 */
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Home</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        /* Clean white background for index */
        body {
            background-color: #ffffff !important;
        }
        
        .hero {
            background: #f7fafc !important;
            color: #1a202c !important;
            border: 1px solid #e2e8f0 !important;
        
}
    </style>
</head>
<body class="homepage">
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="nav-logo-container">
                    <img src="../assets/images/logo.png" alt="<?php echo SITE_NAME; ?> Logo" class="nav-logo-img">
                </div>
                <h1 class="nav-logo"><?php echo SITE_NAME; ?></h1>
                <ul class="nav-menu">
                    <li><a href="index.php">Home</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Register</a></li>
                    <?php endif; ?>
                    <li><a href="about.php">About</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div class="hero-content">
                <h1>Welcome to Online Voting System</h1>
                <p class="subtitle">Wollo University - Informatics College</p>
                
                <?php if (isset($_GET['logged_out'])): ?>
                    <div class="alert alert-success" style="max-width: 600px; margin: 2rem auto;">
                        You have been successfully logged out. Thank you for using the Online Voting System!
                    </div>
                <?php endif; ?>
                
                <?php if (isLoggedIn()): ?>
                    <div class="user-welcome">
                        <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h2>
                        <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                    </div>
                <?php else: ?>
                    <div class="auth-buttons">
                        <a href="login.php" class="btn btn-primary">Login to Vote</a>
                        <a href="register.php" class="btn btn-secondary">Register Now</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="features">
            <div class="container">
                <h2>System Features</h2>
                <div class="features-grid">
                    <div class="feature-card">
                        <h3>🔒 Secure Voting</h3>
                        <p>Advanced encryption and security measures to protect your vote</p>
                    </div>
                    <div class="feature-card">
                        <h3>📱 Accessible</h3>
                        <p>Vote from anywhere with internet connection on any device</p>
                    </div>
                    <div class="feature-card">
                        <h3>⚡ Real-time Results</h3>
                        <p>Instant vote counting and transparent result reporting</p>
                    </div>
                    <div class="feature-card">
                        <h3>📊 Audit Trail</h3>
                        <p>Complete audit logs for transparency and verification</p>
                    </div>
                </div>
            </div>
        </section>

        <?php
        // Display active elections for logged-in users
        if (isLoggedIn()) {
            try {
                $db = getDBConnection();
                $stmt = $db->prepare("
                    SELECT election_id, title, description, start_date, end_date 
                    FROM elections 
                    WHERE status = 'active' AND start_date <= NOW() AND end_date >= NOW()
                    ORDER BY start_date ASC
                ");
                $stmt->execute();
                $active_elections = $stmt->fetchAll();
                
                if ($active_elections): ?>
                    <section class="active-elections">
                        <div class="container">
                            <h2>Active Elections</h2>
                            <div class="elections-grid">
                                <?php foreach ($active_elections as $election): ?>
                                    <div class="election-card">
                                        <h3><?php echo htmlspecialchars($election['title']); ?></h3>
                                        <p><?php echo htmlspecialchars($election['description']); ?></p>
                                        <p class="election-dates">
                                            <strong>Ends:</strong> <?php echo date('M j, Y g:i A', strtotime($election['end_date'])); ?>
                                        </p>
                                        <a href="vote.php?election_id=<?php echo $election['election_id']; ?>" class="btn btn-primary">Vote Now</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </section>
                <?php endif;
            } catch (Exception $e) {
                error_log("Error fetching elections: " . $e->getMessage());
            }
        }
        ?>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2018 Wollo University - Informatics College. All rights reserved.</p>
            <p>Integrated Project: Internet Programming, Advanced Database & System Analysis Design</p>
        </div>
    </footer>

    <script src="../assets/js/main.js"></script>
</body>
</html>