<?php
// Authentication middleware - Include this at the top of protected pages

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Function to check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Function to check if user has permission
function hasPermission($required_role = 'user') {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    if ($required_role === 'admin') {
        return $_SESSION['role'] === 'admin';
    }
    
    return true; // All logged-in users have 'user' permission
}

// Function to require admin access
function requireAdmin() {
    if (!isAdmin()) {
        header("Location: index.php?error=access_denied");
        exit;
    }
}
?>
