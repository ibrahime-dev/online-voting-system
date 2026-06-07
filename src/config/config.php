<?php
/**
 * System Configuration
 * Online Voting System - Wollo University
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// System Constants
define('SITE_NAME', 'Online Voting System');
define('SITE_URL', 'http://localhost/online-voting-system');
define('ADMIN_EMAIL', 'admin@votingsystem.com');

// Security Settings
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('PASSWORD_MIN_LENGTH', 8);

// File Upload Settings
define('UPLOAD_PATH', '../uploads/');
define('MAX_FILE_SIZE', 2097152); // 2MB

// Database Settings
require_once 'database.php';

// Utility Functions
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generateToken() {
    return bin2hex(random_bytes(32));
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function validatePhoneNumber($phone_number) {
    // Return true if phone number is empty (optional field)
    if (empty($phone_number)) {
        return true;
    }
    
    // Check if phone number contains only digits and is maximum 10 characters
    return ctype_digit($phone_number) && strlen($phone_number) <= 10;
}

function getPhoneValidationError($phone_number) {
    if (empty($phone_number)) {
        return null; // No error for empty phone number
    }
    
    if (!ctype_digit($phone_number)) {
        return 'Phone number must contain only numbers.';
    }
    
    if (strlen($phone_number) > 10) {
        return 'Phone number cannot exceed 10 digits.';
    }
    
    return null; // No error
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function checkUserRole($required_role) {
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== $required_role) {
        header('Location: unauthorized.php');
        exit();
    }
}

function logActivity($user_id, $action, $table_name = null, $record_id = null) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("
            INSERT INTO audit_log (user_id, action, table_name, record_id, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $action,
            $table_name,
            $record_id,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
}

function sendApprovalNotification($user_id, $status) {
    try {
        $db = getDBConnection();
        
        // Get user information
        $stmt = $db->prepare("
            SELECT u.first_name, u.last_name, u.email, vp.voter_id
            FROM users u
            JOIN voter_profiles vp ON u.user_id = vp.user_id
            WHERE u.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        $subject = '';
        $message = '';
        
        if ($status === 'verified') {
            $subject = 'Account Approved - ' . SITE_NAME;
            $message = "
                <h2>🎉 Congratulations! Your account has been approved</h2>
                <p>Dear {$user['first_name']} {$user['last_name']},</p>
                <p>Great news! Your voter registration has been approved by our administrators.</p>
                <p><strong>Your Details:</strong></p>
                <ul>
                    <li><strong>Voter ID:</strong> {$user['voter_id']}</li>
                    <li><strong>Status:</strong> Verified ✅</li>
                    <li><strong>Approval Date:</strong> " . date('M j, Y g:i A') . "</li>
                </ul>
                <p>You can now:</p>
                <ul>
                    <li>✅ Log in to your dashboard</li>
                    <li>✅ Participate in active elections</li>
                    <li>✅ Cast your votes</li>
                    <li>✅ View election results</li>
                </ul>
                <p><a href='" . SITE_URL . "/src/login.php' style='background: #ff7200; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login to Dashboard</a></p>
                <p>Thank you for registering with " . SITE_NAME . "!</p>
                <hr>
                <p><small>This is an automated message. Please do not reply to this email.</small></p>
            ";
        } elseif ($status === 'rejected') {
            $subject = 'Account Verification Update - ' . SITE_NAME;
            $message = "
                <h2>Account Verification Update</h2>
                <p>Dear {$user['first_name']} {$user['last_name']},</p>
                <p>We have reviewed your voter registration application.</p>
                <p>Unfortunately, we were unable to approve your account at this time. This could be due to:</p>
                <ul>
                    <li>Incomplete or incorrect information provided</li>
                    <li>National ID verification issues</li>
                    <li>Duplicate registration detected</li>
                </ul>
                <p>If you believe this is an error or would like to resubmit your application, please contact our support team.</p>
                <p><strong>Contact Information:</strong></p>
                <ul>
                    <li>Email: " . ADMIN_EMAIL . "</li>
                    <li>Phone: +251-XXX-XXXX</li>
                </ul>
                <p>Thank you for your interest in " . SITE_NAME . ".</p>
                <hr>
                <p><small>This is an automated message. Please do not reply to this email.</small></p>
            ";
        }
        
        // In a real implementation, you would send the email here
        // For now, we'll just log it
        error_log("Email notification sent to {$user['email']}: $subject");
        
        return true;
        
    } catch (Exception $e) {
        error_log("Failed to send approval notification: " . $e->getMessage());
        return false;
    }
}
?>