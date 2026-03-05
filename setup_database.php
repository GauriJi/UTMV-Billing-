<?php
require_once 'config.php';

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

echo "✅ Connected to database: " . DB_NAME . "<br><br>";

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'user') DEFAULT 'user',
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "✅ Users table created successfully<br>";
} else {
    echo "❌ Error creating table: " . $conn->error . "<br>";
}

// Insert admin user
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);
$sql = "INSERT INTO users (username, password, full_name, role, email, is_active) 
        VALUES ('admin', '$admin_password', 'Administrator', 'admin', 'admin@billpro.com', 1)
        ON DUPLICATE KEY UPDATE password = '$admin_password'";

if ($conn->query($sql) === TRUE) {
    echo "✅ Admin user created/updated<br>";
} else {
    echo "❌ Error: " . $conn->error . "<br>";
}

// Insert regular user
$user_password = password_hash('user123', PASSWORD_DEFAULT);
$sql = "INSERT INTO users (username, password, full_name, role, email, is_active) 
        VALUES ('user', '$user_password', 'Regular User', 'user', 'user@billpro.com', 1)
        ON DUPLICATE KEY UPDATE password = '$user_password'";

if ($conn->query($sql) === TRUE) {
    echo "✅ Regular user created/updated<br>";
} else {
    echo "❌ Error: " . $conn->error . "<br>";
}

echo "<br><strong>Login Credentials:</strong><br>";
echo "Admin: admin / admin123<br>";
echo "User: user / user123<br>";
echo "<br><a href='login.php' style='padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px;'>Go to Login Page</a>";

$conn->close();
?>
