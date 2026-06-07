<?php
/**
 * Unauthorized Access Page - Online Voting System
 * Wollo University - Integrated Project
 */
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Clean white background */
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
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <h1 class="nav-logo"><?php echo SITE_NAME; ?></h1>
                <ul class="nav-menu">
                    <li><a href="index.php">Home</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                    <?php endif; ?>
                    <li><a href="about.php">About</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <main class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="login-icon">🚫</div>
                <h1>Access Denied</h1>
                <p>You don't have permission to access this resource</p>
            </div>
            
            <div class="alert alert-error">
                <h3>Unauthorized Access</h3>
                <p>You do not have the required permissions to view this page. Please contact your administrator if you believe this is an error.</p>
            </div>
            
            <div class="auth-links">
                <?php if (isLoggedIn()): ?>
                    <p><a href="dashboard.php">← Return to Dashboard</a></p>
                    <p><a href="profile.php">View Profile</a></p>
                <?php else: ?>
                    <p><a href="login.php">Login with proper credentials</a></p>
                    <p><a href="register.php">Register for an account</a></p>
                <?php endif; ?>
                <p><a href="index.php">← Back to Home</a></p>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2018 Wollo University - Informatics College. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>