<?php
/**
 * User Profile Page - Online Voting System
 * Wollo University - Integrated Project
 */
require_once 'config/config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

try {
    $db = getDBConnection();
    
    // Get user profile information
    $stmt = $db->prepare("
        SELECT u.*, vp.national_id, vp.voter_id, vp.verification_status, vp.verification_date
        FROM users u
        LEFT JOIN voter_profiles vp ON u.user_id = vp.user_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $user_profile = $stmt->fetch();
    
    if (!$user_profile) {
        header('Location: logout.php');
        exit();
    }
    
    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $first_name = sanitizeInput($_POST['first_name']);
        $last_name = sanitizeInput($_POST['last_name']);
        $email = sanitizeInput($_POST['email']);
        $phone_number = sanitizeInput($_POST['phone_number']);
        $address = sanitizeInput($_POST['address']);
        
        if (empty($first_name) || empty($last_name) || empty($email)) {
            $error_message = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Please enter a valid email address.';
        } elseif (!empty($phone_number) && !validatePhoneNumber($phone_number)) {
            $error_message = getPhoneValidationError($phone_number);
        } else {
            // Check if email is already taken by another user
            $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $error_message = 'Email address is already in use by another account.';
            } else {
                // Update user profile
                $stmt = $db->prepare("
                    UPDATE users 
                    SET first_name = ?, last_name = ?, email = ?, phone_number = ?, address = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$first_name, $last_name, $email, $phone_number, $address, $user_id]);
                
                // Update session data
                $_SESSION['first_name'] = $first_name;
                $_SESSION['last_name'] = $last_name;
                
                logActivity($user_id, 'PROFILE_UPDATE');
                
                $success_message = 'Profile updated successfully.';
                
                // Refresh user data
                $stmt = $db->prepare("
                    SELECT u.*, vp.national_id, vp.voter_id, vp.verification_status, vp.verification_date
                    FROM users u
                    LEFT JOIN voter_profiles vp ON u.user_id = vp.user_id
                    WHERE u.user_id = ?
                ");
                $stmt->execute([$user_id]);
                $user_profile = $stmt->fetch();
            }
        }
    }
    
} catch (Exception $e) {
    $error_message = 'Error loading profile data.';
    error_log("Profile page error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        /* Clean white background for profile */
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
                    
                    <?php if ($_SESSION['user_type'] === 'candidate'): ?>
                        <li><a href="candidate-dashboard.php">Dashboard</a></li>
                    <?php elseif ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'super_admin' || $_SESSION['user_type'] === 'sub_admin'): ?>
                        <li><a href="admin/index.php">Dashboard</a></li>
                    <?php else: ?>
                        <li><a href="dashboard.php">Dashboard</a></li>
                    <?php endif; ?>
                    <li><a href="profile.php" class="active">Profile</a></li>
                    <li><a href="logout.php">Logout</a></li>
                    
            </div>
        </nav>
    </header>

    <main>
        <div class="container" style="max-width: 800px; margin: 2rem auto;">
            <div class="auth-card" style="max-width: none;">
                <div class="auth-header">
                    <h1>👤 My Profile</h1>
                    <p>Manage your account information</p>
                </div>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-error"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <!-- Account Status -->
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-bottom: 2rem;">
                    <h3>Account Status</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                        <div>
                            <strong>Account Type:</strong> <?php echo ucfirst($user_profile['user_type']); ?>
                        </div>
                        <div>
                            <strong>Account Status:</strong> 
                            <span style="color: <?php echo $user_profile['is_active'] ? '#28a745' : '#dc3545'; ?>;">
                                <?php echo $user_profile['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        <?php if (isset($user_profile['voter_id'])): ?>
                            <div>
                                <strong>Voter ID:</strong> <?php echo htmlspecialchars($user_profile['voter_id']); ?>
                            </div>
                            <div>
                                <strong>Verification Status:</strong>
                                <span style="color: <?php 
                                    echo $user_profile['verification_status'] === 'verified' ? '#28a745' : 
                                        ($user_profile['verification_status'] === 'rejected' ? '#dc3545' : '#ffc107'); 
                                ?>;">
                                    <?php echo ucfirst($user_profile['verification_status']); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Profile Form -->
                <form method="POST" class="auth-form">
                    <h3 style="margin-bottom: 1rem;">Personal Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name: *</label>
                            <input type="text" id="first_name" name="first_name" required 
                                   value="<?php echo htmlspecialchars($user_profile['first_name']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name: *</label>
                            <input type="text" id="last_name" name="last_name" required 
                                   value="<?php echo htmlspecialchars($user_profile['last_name']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" value="<?php echo htmlspecialchars($user_profile['username']); ?>" 
                               disabled style="background: #f8f9fa; color: #666;">
                        <small>Username cannot be changed</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address: *</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo htmlspecialchars($user_profile['email']); ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth:</label>
                            <input type="date" id="date_of_birth" 
                                   value="<?php echo $user_profile['date_of_birth']; ?>" 
                                   disabled style="background: #f8f9fa; color: #666;">
                            <small>Date of birth cannot be changed</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone_number">Phone Number:</label>
                            <input type="tel" id="phone_number" name="phone_number" 
                                   pattern="[0-9]{1,10}" 
                                   maxlength="10"
                                   title="Phone number must contain only numbers and be maximum 10 digits"
                                   placeholder="Enter phone number (max 10 digits)"
                                   value="<?php echo htmlspecialchars($user_profile['phone_number']); ?>">
                            <small>Numbers only, maximum 10 digits</small>
                        </div>
                    </div>
                    
                    <?php if (isset($user_profile['national_id'])): ?>
                        <div class="form-group">
                            <label for="national_id">National ID:</label>
                            <input type="text" id="national_id" 
                                   value="<?php echo htmlspecialchars($user_profile['national_id']); ?>" 
                                   disabled style="background: #f8f9fa; color: #666;">
                            <small>National ID cannot be changed</small>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="address">Address:</label>
                        <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($user_profile['address']); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-full">Update Profile</button>
                </form>
                
                <!-- Account Information -->
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-top: 2rem;">
                    <h3>Account Information</h3>
                    <div style="margin-top: 1rem;">
                        <p><strong>Member since:</strong> <?php echo date('M j, Y', strtotime($user_profile['created_at'])); ?></p>
                        <?php if ($user_profile['last_login']): ?>
                            <p><strong>Last login:</strong> <?php echo date('M j, Y g:i A', strtotime($user_profile['last_login'])); ?></p>
                        <?php endif; ?>
                        <?php if (isset($user_profile['verification_date']) && $user_profile['verification_date']): ?>
                            <p><strong>Verified on:</strong> <?php echo date('M j, Y', strtotime($user_profile['verification_date'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Actions -->
                <div style="text-align: center; margin-top: 2rem;">
                    <?php if ($_SESSION['user_type'] === 'candidate'): ?>
                        <a href="candidate-dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                    <?php elseif ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'super_admin' || $_SESSION['user_type'] === 'sub_admin'): ?>
                        <a href="admin/index.php" class="btn btn-secondary">← Back to Dashboard</a>
                    <?php else: ?>
                        <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                    <?php endif; ?>
                    <a href="change-password.php" class="btn btn-primary">Change Password</a>
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
        // Phone number validation
        document.getElementById('phone_number').addEventListener('input', function() {
            const phoneNumber = this.value;
            
            // Remove any non-digit characters
            const cleanedNumber = phoneNumber.replace(/\D/g, '');
            
            // Update the input value with cleaned number
            if (cleanedNumber !== phoneNumber) {
                this.value = cleanedNumber;
            }
            
            // Validate length and format
            if (cleanedNumber.length > 10) {
                this.value = cleanedNumber.substring(0, 10);
                this.setCustomValidity('Phone number cannot exceed 10 digits');
                this.style.borderColor = '#e74c3c';
                
                // Show error message
                let phoneMessage = document.getElementById('phone-message');
                if (!phoneMessage) {
                    phoneMessage = document.createElement('div');
                    phoneMessage.id = 'phone-message';
                    phoneMessage.style.color = '#e74c3c';
                    phoneMessage.style.fontSize = '0.8rem';
                    phoneMessage.style.marginTop = '0.25rem';
                    this.parentNode.appendChild(phoneMessage);
                }
                phoneMessage.textContent = 'Phone number cannot exceed 10 digits';
            } else if (cleanedNumber.length > 0 && !/^[0-9]+$/.test(cleanedNumber)) {
                this.setCustomValidity('Phone number must contain only numbers');
                this.style.borderColor = '#e74c3c';
                
                // Show error message
                let phoneMessage = document.getElementById('phone-message');
                if (!phoneMessage) {
                    phoneMessage = document.createElement('div');
                    phoneMessage.id = 'phone-message';
                    phoneMessage.style.color = '#e74c3c';
                    phoneMessage.style.fontSize = '0.8rem';
                    phoneMessage.style.marginTop = '0.25rem';
                    this.parentNode.appendChild(phoneMessage);
                }
                phoneMessage.textContent = 'Phone number must contain only numbers';
            } else {
                this.setCustomValidity('');
                this.style.borderColor = cleanedNumber.length > 0 ? '#27ae60' : '';
                
                // Remove error message if exists
                const phoneMessage = document.getElementById('phone-message');
                if (phoneMessage) {
                    phoneMessage.remove();
                }
            }
        });
        
        // Prevent non-numeric input on keypress
        document.getElementById('phone_number').addEventListener('keypress', function(e) {
            // Allow backspace, delete, tab, escape, enter
            if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
                // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true)) {
                return;
            }
            
            // Ensure that it is a number and stop the keypress
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
            
            // Check if adding this digit would exceed 10 characters
            if (this.value.length >= 10) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>