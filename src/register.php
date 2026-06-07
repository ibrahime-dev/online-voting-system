<?php
/**
 * Registration Page - Online Voting System
 * Wollo University - Integrated Project
 */
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
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $date_of_birth = $_POST['date_of_birth'];
    $phone_number = sanitizeInput($_POST['phone_number']);
    $address = sanitizeInput($_POST['address']);
    $national_id = sanitizeInput($_POST['national_id']);
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($first_name) || 
        empty($last_name) || empty($date_of_birth) || empty($national_id)) {
        $error_message = 'Please fill in all required fields.';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error_message = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } elseif (!empty($phone_number) && !validatePhoneNumber($phone_number)) {
        $error_message = getPhoneValidationError($phone_number);
    } else {
        // Age validation - must be 18 or older
        $birth_date = new DateTime($date_of_birth);
        $today = new DateTime();
        $age = $today->diff($birth_date)->y;
        
        if ($age < 18) {
            $error_message = 'You must be at least 18 years old to register for voting.';
        } else {
        try {
            $db = getDBConnection();
            
            // Check if username or email already exists
            $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error_message = 'Username or email already exists.';
            } else {
                // Check if national ID already exists
                $stmt = $db->prepare("SELECT profile_id FROM voter_profiles WHERE national_id = ?");
                $stmt->execute([$national_id]);
                if ($stmt->fetch()) {
                    $error_message = 'National ID already registered.';
                } else {
                    $db->beginTransaction();
                    
                    // Insert user
                    $password_hash = hashPassword($password);
                    $stmt = $db->prepare("
                        INSERT INTO users (username, email, password_hash, first_name, last_name, 
                                         date_of_birth, phone_number, address, user_type, is_verified) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'voter', 0)
                    ");
                    $stmt->execute([$username, $email, $password_hash, $first_name, $last_name, 
                                  $date_of_birth, $phone_number, $address]);
                    
                    $user_id = $db->lastInsertId();
                    
                    // Generate voter ID
                    $voter_id = 'VTR' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
                    
                    // Insert voter profile
                    $stmt = $db->prepare("
                        INSERT INTO voter_profiles (user_id, national_id, voter_id, verification_status) 
                        VALUES (?, ?, ?, 'pending')
                    ");
                    $stmt->execute([$user_id, $national_id, $voter_id]);
                    
                    $db->commit();
                    
                    logActivity($user_id, 'REGISTER');
                    
                    // Show success message instead of auto-login
                    $success_message = "
                        <strong>Registration Successful!</strong><br>
                        Your account has been created with Voter ID: <strong>$voter_id</strong><br>
                        Please wait for admin approval before you can login and vote.
                    ";
                    
                    // Clear form data after successful registration
                    $_POST = array();
                }
            }
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = 'Registration failed. Please try again.';
            error_log("Registration error: " . $e->getMessage());
        }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Clean white background for register */
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
        <div class="auth-card register-card">
            <div class="auth-header">
                <h1><?php echo SITE_NAME; ?></h1>
                <h2>Create Your Account</h2>
            </div>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name: *</label>
                        <input type="text" id="first_name" name="first_name" required 
                               value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name: *</label>
                        <input type="text" id="last_name" name="last_name" required 
                               value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="username">Username: *</label>
                    <input type="text" id="username" name="username" required 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address: *</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password: *</label>
                        <input type="password" id="password" name="password" required 
                               minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                        <small>Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password: *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth: *</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" required 
                               max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>"
                               value="<?php echo isset($_POST['date_of_birth']) ? $_POST['date_of_birth'] : ''; ?>">
                        <small>You must be at least 18 years old to register</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone_number">Phone Number:</label>
                        <input type="tel" id="phone_number" name="phone_number" 
                               pattern="[0-9]{1,10}" 
                               maxlength="10"
                               title="Phone number must contain only numbers and be maximum 10 digits"
                               placeholder="Enter phone number (max 10 digits)"
                               value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>">
                        <small>Numbers only, maximum 10 digits</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="national_id">National ID Number: *</label>
                    <input type="text" id="national_id" name="national_id" required 
                           placeholder="Enter your National ID number"
                           value="<?php echo isset($_POST['national_id']) ? htmlspecialchars($_POST['national_id']) : ''; ?>">
                    <small>Enter your National ID number</small>
                </div>
                
                <div class="form-group">
                    <label for="address">Address:</label>
                    <textarea id="address" name="address" rows="3"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">Register</button>
            </form>
            
            <div class="auth-links">
                <p>Already have an account? <a href="login.php">Login here</a></p>
                <p><a href="index.php">← Back to Home</a></p>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Age validation (18+ years old)
        document.getElementById('date_of_birth').addEventListener('change', function() {
            const birthDate = new Date(this.value);
            const today = new Date();
            const age = Math.floor((today - birthDate) / (365.25 * 24 * 60 * 60 * 1000));
            
            if (age < 18) {
                this.setCustomValidity('You must be at least 18 years old to register for voting');
                // Show visual feedback
                this.style.borderColor = '#e74c3c';
                
                // Show age message
                let ageMessage = document.getElementById('age-message');
                if (!ageMessage) {
                    ageMessage = document.createElement('div');
                    ageMessage.id = 'age-message';
                    ageMessage.style.color = '#e74c3c';
                    ageMessage.style.fontSize = '0.8rem';
                    ageMessage.style.marginTop = '0.25rem';
                    this.parentNode.appendChild(ageMessage);
                }
                ageMessage.textContent = `You are ${age} years old. You must be at least 18 to register.`;
            } else {
                this.setCustomValidity('');
                this.style.borderColor = '#27ae60';
                
                // Remove age message if exists
                const ageMessage = document.getElementById('age-message');
                if (ageMessage) {
                    ageMessage.remove();
                }
            }
        });
        
        // Set maximum date to 18 years ago
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('date_of_birth');
            const eighteenYearsAgo = new Date();
            eighteenYearsAgo.setFullYear(eighteenYearsAgo.getFullYear() - 18);
            dateInput.max = eighteenYearsAgo.toISOString().split('T')[0];
            
            // Phone validation is now handled by main.js
        });
    </script>
</body>
</html>