<?php
/**
 * Voter Verification Handler - Online Voting System
 * Wollo University - Integrated Project
 */
require_once '../config/config.php';
requireLogin();

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'super_admin') {
    header('Location: ../dashboard.php');
    exit();
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';

if (!$user_id || !in_array($status, ['verified', 'rejected'])) {
    header('Location: index.php?error=invalid_parameters');
    exit();
}

try {
    $db = getDBConnection();
    
    // Update voter verification status
    $stmt = $db->prepare("
        UPDATE voter_profiles 
        SET verification_status = ?, verification_date = NOW(), verified_by = ?
        WHERE user_id = ?
    ");
    $stmt->execute([$status, $_SESSION['user_id'], $user_id]);
    
    if ($stmt->rowCount() > 0) {
        // Update user verification status
        $is_verified = ($status === 'verified') ? 1 : 0;
        $stmt = $db->prepare("UPDATE users SET is_verified = ? WHERE user_id = ?");
        $stmt->execute([$is_verified, $user_id]);
        
        // Log the activity
        logActivity($_SESSION['user_id'], 'VOTER_' . strtoupper($status), 'voter_profiles', $user_id);
        
        $message = ($status === 'verified') ? 'Voter approved successfully.' : 'Voter rejected successfully.';
        header('Location: index.php?success=' . urlencode($message));
    } else {
        header('Location: index.php?error=verification_failed');
    }
    
} catch (Exception $e) {
    error_log("Voter verification error: " . $e->getMessage());
    header('Location: index.php?error=system_error');
}
exit();
?>