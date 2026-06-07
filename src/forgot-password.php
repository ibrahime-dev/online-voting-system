<?php
/**
 * Forgot Password Page - Online Voting System
 * Wollo University - Integrated Project
 */
require_once 'config/config.php';

$message = '';
$error = '';
$step = 'request'; // request, reset, complete
$user_data = null;

// Check if user is already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db = getDBConnection();
        
        if (isset($_POST['action']) && $_POST['action'] === 'check_email') {
            $email = sanitizeInput($_POST['email']);
            
            if (empty($email)) {
                $error = 'Please enter your email address.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                // Check if email exists in database
                $stmt = $db->prepare("SELECT user_id, username, first_name, last_name, email FROM users WHERE email = ? AND is_active = 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Email found - store user data in session and show password reset form
                    $_SESSION['password_reset_user'] = [
                        'user_id' => $user['user_id'],
                        'email' => $user['email'],
                        'username' => $user['username'],
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name'],
                        'timestamp' => time()
                    ];
                    
                    $user_data = $user;
                    $step = 'reset';
                    
                    logActivity($user['user_id'], 'PASSWORD_RESET_REQUEST');
                } else {
                    $error = 'No account found with that email address. Please check your email and try again.';
                }
            }
            
        } elseif (isset($_POST['action']) && $_POST['action'] === 'reset_password') {
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Check if we have valid session data
            if (!isset($_SESSION['password_reset_user']) || 
                (time() - $_SESSION['password_reset_user']['timestamp']) > 1800) { // 30 minutes timeout
                $error = 'Session expired. Please start the password reset process again.';
                unset($_SESSION['password_reset_user']);
            } elseif (empty($new_password) || empty($confirm_password)) {
                $error = 'Please fill in all fields.';
                $step = 'reset';
                $user_data = $_SESSION['password_reset_user'];
            } elseif ($new_password !== $confirm_password) {
                $error = 'Passwords do not match.';
                $step = 'reset';
                $user_data = $_SESSION['password_reset_user'];
            } elseif (strlen($new_password) < 6) {
                $error = 'Password must be at least 6 characters long.';
                $step = 'reset';
                $user_data = $_SESSION['password_reset_user'];
            } else {
                $user_id = $_SESSION['password_reset_user']['user_id'];
                $password_hash = hashPassword($new_password);
                
                // Update password in database
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $stmt->execute([$password_hash, $user_id]);
                
                if ($stmt->rowCount() > 0) {
                    // Clear session data
                    unset($_SESSION['password_reset_user']);
                    
                    logActivity($user_id, 'PASSWORD_RESET_COMPLETE');
                    
                    $message = "Password reset successfully! You can now login with your new password.";
                    $step = 'complete';
                } else {
                    $error = 'Failed to update password. Please try again.';
                    $step = 'reset';
                    $user_data = $_SESSION['password_reset_user'];
                }
            }
        }
        
    } catch (Exception $e) {
        $error = 'An error occurred. Please try again.';
        error_log("Password reset error: " . $e->getMessage());
    }
}

// Check if we have existing session data
if (isset($_SESSION['password_reset_user']) && 
    (time() - $_SESSION['password_reset_user']['timestamp']) <= 1800) {
    $user_data = $_SESSION['password_reset_user'];
    if ($step === 'request') {
        $step = 'reset';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        /* Clean white background for forgot password */
        body {
            background-color: #ffffff !important;
        }
        
        .auth-container {
            background-color: #f7fafc !important;
        }
        
        .auth-card {
            background: rgba(255, 255, 255, 0.98);
            border: 1px solid #e2e8f0;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .hero {
            background: #f7fafc !important;
            color: #1a202c !important;
            border: 1px solid #e2e8f0 !important;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="login-icon">🔐</div>
                <h1>Forgot Password</h1>
                <?php if ($step === 'request'): ?>
                    <p>Enter your email address to reset your password</p>
                <?php elseif ($step === 'reset'): ?>
                    <p>Enter your new password</p>
                <?php else: ?>
                    <p>Password Reset</p>
                <?php endif; ?>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($step === 'request'): ?>
                <!-- Step 1: Enter Email Address -->
                <form method="POST" class="auth-form">
                    <input type="hidden" name="action" value="check_email">
                    
                    <div class="form-group">
                        <label for="email">Email Address:</label>
                        <input type="email" id="email" name="email" required 
                               placeholder="Enter your registered email address"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-full">
                        🔍 Find My Account
                    </button>
                </form>
                
            <?php elseif ($step === 'reset'): ?>
                <!-- Step 2: Reset Password -->
                <div style="background: #e3f2fd; padding: 1rem; border-radius: 5px; margin-bottom: 1.5rem;">
                    <p><strong>Account Found:</strong></p>
                    <p>📧 <strong>Email:</strong> <?php echo htmlspecialchars($user_data['email']); ?></p>
                    <p>👤 <strong>Name:</strong> <?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></p>
                    <p>🔑 <strong>Username:</strong> <?php echo htmlspecialchars($user_data['username']); ?></p>
                </div>
                
                <form method="POST" class="auth-form">
                    <input type="hidden" name="action" value="reset_password">
                    
                    <div class="form-group">
                        <label for="new_password">New Password:</label>
                        <input type="password" id="new_password" name="new_password" required 
                               placeholder="Enter your new password" minlength="6">
                        <small>Password must be at least 6 characters long</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password:</label>
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               placeholder="Confirm your new password">
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-full">
                        🔑 Update Password
                    </button>
                </form>
                
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="forgot-password.php" class="btn btn-secondary">← Use Different Email</a>
                </div>
                
            <?php elseif ($step === 'complete'): ?>
                <!-- Step 3: Success Message -->
                <div style="text-align: center; padding: 2rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">✅</div>
                    <h3 style="color: #28a745; margin-bottom: 1rem;">Password Reset Complete!</h3>
                    <p>Your password has been successfully updated.</p>
                    <div style="margin-top: 2rem;">
                        <a href="login.php" class="btn btn-success">🔑 Login Now</a>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="auth-links">
                <p><a href="login.php">← Back to Login</a></p>
                <p><a href="index.php">← Back to Home</a></p>
            </div>
        </div>
    </div>
    
    <script>
        // Password confirmation validation
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (newPassword && confirmPassword) {
                function validatePasswords() {
                    if (newPassword.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Passwords do not match');
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                }
                
                newPassword.addEventListener('input', validatePasswords);
                confirmPassword.addEventListener('input', validatePasswords);
            }
        });
    </script>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>