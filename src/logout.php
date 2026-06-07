<?php
/**
 * Logout Page - Online Voting System
 * Wollo University - Integrated Project
 */
require_once 'config/config.php';

// Log the logout activity if user is logged in
if (isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'LOGOUT');
}

// Clear all session data
session_unset();
session_destroy();

// Clear any remember me cookies if they exist
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Redirect to home page with logout message
header('Location: index.php?logged_out=1');
exit();
?>