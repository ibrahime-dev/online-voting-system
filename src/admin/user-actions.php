<?php
/**
 * User Actions Handler - AJAX Endpoint
 * Online Voting System - Wollo University
 */
require_once '../config/config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isLoggedIn() || ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'super_admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$action = $_POST['action'] ?? '';
$user_id = (int)($_POST['user_id'] ?? 0);

try {
    $db = getDBConnection();
    
    switch ($action) {
        case 'verify_voter':
            $status = $_POST['status'] ?? 'verified';
            
            if (!in_array($status, ['verified', 'rejected'])) {
                throw new Exception('Invalid verification status');
            }
            
            $stmt = $db->prepare("
                UPDATE voter_profiles 
                SET verification_status = ?, verification_date = NOW(), verified_by = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$status, $_SESSION['user_id'], $user_id]);
            
            $stmt = $db->prepare("UPDATE users SET is_verified = ? WHERE user_id = ?");
            $stmt->execute([$status === 'verified' ? 1 : 0, $user_id]);
            
            logActivity($_SESSION['user_id'], 'VOTER_' . strtoupper($status), 'voter_profiles', $user_id);
            
            echo json_encode([
                'success' => true, 
                'message' => "Voter " . ($status === 'verified' ? 'approved' : 'rejected') . " successfully."
            ]);
            break;
            
        case 'toggle_status':
            if ($user_id == $_SESSION['user_id']) {
                throw new Exception('Cannot change your own status');
            }
            
            $stmt = $db->prepare("SELECT is_active FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $current_status = $stmt->fetchColumn();
            
            $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            logActivity($_SESSION['user_id'], 'USER_STATUS_TOGGLE', 'users', $user_id);
            
            $new_status = $current_status ? 'deactivated' : 'activated';
            echo json_encode([
                'success' => true, 
                'message' => "User {$new_status} successfully.",
                'new_status' => !$current_status
            ]);
            break;
            
        case 'delete_user':
            if ($user_id == $_SESSION['user_id']) {
                throw new Exception('Cannot delete your own account');
            }
            
            // Start transaction for safe deletion
            $db->beginTransaction();
            
            try {
                // Get user info before deletion
                $stmt = $db->prepare("SELECT username, first_name, last_name, user_type FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $user_info = $stmt->fetch();
                
                if (!$user_info) {
                    throw new Exception('User not found');
                }
                
                // Check permissions: regular admin can only delete voters, super_admin can delete anyone
                if ($_SESSION['user_type'] === 'admin' && in_array($user_info['user_type'], ['admin', 'super_admin'])) {
                    throw new Exception('Admins cannot delete other admins or super admins');
                }
                
                if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'super_admin') {
                    throw new Exception('Insufficient permissions to delete users');
                }
                
                // Delete related records first (to handle foreign key constraints)
                $stmt = $db->prepare("DELETE FROM votes WHERE voter_id = ?");
                $stmt->execute([$user_id]);
                
                $stmt = $db->prepare("DELETE FROM audit_log WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                $stmt = $db->prepare("DELETE FROM voter_profiles WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                $stmt = $db->prepare("DELETE FROM login_attempts WHERE username = ?");
                $stmt->execute([$user_info['username']]);
                
                // Finally delete the user
                $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                if ($stmt->rowCount() > 0) {
                    $db->commit();
                    logActivity($_SESSION['user_id'], 'USER_DELETE', 'users', $user_id);
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => "User '{$user_info['first_name']} {$user_info['last_name']}' and all associated data deleted successfully."
                    ]);
                } else {
                    $db->rollback();
                    throw new Exception('Failed to delete user');
                }
                
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;
            
        case 'get_user_stats':
            // Return updated user statistics
            $stats = [];
            
            $stmt = $db->query("SELECT COUNT(*) as total FROM users");
            $stats['total_users'] = $stmt->fetch()['total'];
            
            $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE user_type IN ('admin', 'super_admin')");
            $stats['admin_users'] = $stmt->fetch()['count'];
            
            $stmt = $db->query("SELECT COUNT(*) as count FROM voter_profiles WHERE verification_status = 'pending'");
            $stats['pending_verifications'] = $stmt->fetch()['count'];
            
            $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
            $stats['active_users'] = $stmt->fetch()['count'];
            
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>