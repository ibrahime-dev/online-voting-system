<?php
/**
 * Change Password Page - Online Voting System
 * Wollo University - Integrated Project
 */
require_once 'config/config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = 'Please fill in all fields.';
    } elseif (strlen($new_password) < PASSWORD_MIN_LENGTH) {
        $error_message = 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'New passwords do not match.';
    } elseif ($current_password === $new_password) {
        $error_message = 'New password must be different from current password.';
    } else {
        try {
            $db = getDBConnection();
            
            // Verify current password
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user || !verifyPassword($current_password, $user['password_hash'])) {
                $error_message = 'Current password is incorrect.';
            } else {
                // Update password
                $new_password_hash = hashPassword($new_password);
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $stmt->execute([$new_password_hash, $user_id]);
                
                // Log the activity
                logActivity($user_id, 'PASSWORD_CHANGE');
                
                $success_message = 'Password changed successfully!';
                
                // Clear form data
                $_POST = array();
            }
        } catch (Exception $e) {
            $error_message = 'Error changing password. Please try again.';
            error_log("Change password error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
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
                <div class="nav-logo-container">
                    <img src="../assets/images/logo.png" alt="<?php echo SITE_NAME; ?> Logo" class="nav-logo-img">
                </div>
                <h1 class="nav-logo"><?php echo SITE_NAME; ?></h1>
                <ul class="nav-menu">
                    <li><a href="index.php">Home</a></li>
                    <?php if ($_SESSION['user_type'] === 'candidate'): ?>
                        <li><a href="candidate-dashboard.php">Dashboard</a></li>
                    <?php elseif ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'super_admin' || $_SESSION['user_type'] === 'sub_admin'): ?>
                        <li><a href="admin/index.php">Dashboard</a></li>
                    <?php else: ?>
                        <li><a href="dashboard.php">Dashboard</a></li>
                    <?php endif; ?>
                    <li><a href="profile.php">Profile</a></li>
                    <li><a href="logout.php">Logout</a></li>
                    <li><a href="about.php">About</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <main>
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-header">
                    <div class="login-icon">🔐</div>
                    <h1>Change Password</h1>
                    <p>Update your account password</p>
                </div>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-error"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <form method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="current_password">Current Password: *</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password: *</label>
                        <input type="password" id="new_password" name="new_password" required 
                               minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                        <small>Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password: *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-full">Change Password</button>
                </form>
                
                <div class="auth-links">
                    <p><a href="profile.php">← Back to Profile</a></p>
                    <?php if ($_SESSION['user_type'] === 'candidate'): ?>
                        <p><a href="candidate-dashboard.php">← Back to Dashboard</a></p>
                    <?php elseif ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'super_admin' || $_SESSION['user_type'] === 'sub_admin'): ?>
                        <p><a href="admin/index.php">← Back to Dashboard</a></p>
                    <?php else: ?>
                        <p><a href="dashboard.php">← Back to Dashboard</a></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2018 Wollo University - Informatics College. All rights reserved.</p>
        </div>
    </footer>

    <script src="../assets/js/main.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Password strength indicator
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const minLength = <?php echo PASSWORD_MIN_LENGTH; ?>;
            
            if (password.length < minLength) {
                this.style.borderColor = '#e74c3c';
            } else if (password.length >= minLength && password.length < 8) {
                this.style.borderColor = '#f39c12';
            } else {
                this.style.borderColor = '#27ae60';
            }
        });
        
        // Current password validation
        document.getElementById('current_password').addEventListener('input', function() {
            if (this.value.length > 0) {
                this.style.borderColor = '#3498db';
            }
        });
        
        // Form validation
        document.querySelector('.auth-form').addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (currentPassword === newPassword) {
                e.preventDefault();
                alert('New password must be different from current password.');
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match.');
                return false;
            }
        });
    </script>
</body>
</html>