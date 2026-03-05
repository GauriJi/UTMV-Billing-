<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Change if you set a password for MySQL
define('DB_NAME', 'salesbilling');  // Your database name

// Application Configuration
define('APP_NAME', 'BillPro');
define('APP_URL', 'http://localhost/billpro');

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
