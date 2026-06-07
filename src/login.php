<?php

require_once 'config/config.php';

$error_message = '';
$success_message = '';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error_message = 'Please fill in all fields.';
    } else {
        try {
            $db = getDBConnection();
            // Authenticate user - get user data without role validation
            $stmt = $db->prepare("
                SELECT u.user_id, u.username, u.password_hash, u.first_name, u.last_name, 
                       u.user_type, u.is_verified, u.is_active, vp.verification_status
                FROM users u
                LEFT JOIN voter_profiles vp ON u.user_id = vp.user_id
                WHERE (u.username = ? OR u.email = ?)
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
                
            if ($user && verifyPassword($password, $user['password_hash'])) {
                if (!$user['is_active']) {
                    $error_message = 'Your account has been deactivated. Please contact administrator.';
                } elseif (!$user['is_verified']) {
                    $error_message = 'Your account is not verified. Please check your email for verification instructions.';
                } else {
                    // Successful login - set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['verification_status'] = $user['verification_status'];
                    $_SESSION['login_time'] = time();
                    
                    // Update last login
                    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                    $stmt->execute([$user['user_id']]);
                    
                    // Log successful login
                    $stmt = $db->prepare("
                        INSERT INTO login_attempts (username, ip_address, success, user_agent) 
                        VALUES (?, ?, 1, ?)
                    ");
                    $stmt->execute([$username, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
                    
                    logActivity($user['user_id'], 'LOGIN');
                    
                    // Automatically redirect based on user type
                    if ($user['user_type'] === 'candidate') {
                        header('Location: candidate-dashboard.php');
                    } elseif (in_array($user['user_type'], ['admin', 'super_admin', 'sub_admin'])) {
                        header('Location: admin/index.php');
                    } else {
                        // For voters, always redirect to dashboard
                        header('Location: dashboard.php');
                    }
                    exit();
                }
            } else {
                $error_message = 'Invalid username or password.';
                
                // Log failed login attempt
                $stmt = $db->prepare("
                    INSERT INTO login_attempts (username, ip_address, success, user_agent) 
                    VALUES (?, ?, 0, ?)
                ");
                $stmt->execute([$username, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
            }
        } catch (Exception $e) {
            $error_message = 'An error occurred. Please try again.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        /* Clean white background for login */
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
        <div class="auth-card unified-login-card">
            <div class="auth-header">
                <div class="login-type-indicator">
                    <div class="login-icon unified-icon">🔑</div>
                    <h1><?php echo SITE_NAME; ?></h1>
                    <h2>System Login</h2>
                    <p>Enter your credentials to access the system</p>
                </div>
            </div>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="username">Username or Email:</label>
                    <input type="text" id="username" name="username" required 
                           placeholder="Enter your username or email"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required
                           placeholder="Enter your password">
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">
                    🔑 Login to System
                </button>
            </form>
            
            <div class="auth-links">
                <p>Don't have an account? <a href="register.php">Register as User</a></p>
                <p><a href="forgot-password.php">Forgot your password?</a></p>
                <p><a href="index.php">← Back to Home</a></p>
            </div>
        </div>
    </div>
    <script src="../assets/js/main.js"></script>
</body>
</html>