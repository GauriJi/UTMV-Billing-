<?php
require_once 'config.php';
require_once 'database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        try {
            $db = new Database();
            $user = $db->single("SELECT * FROM users WHERE username = ? AND is_active = 1", [$username]);
            
            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['email'] = $user['email'];
                
                // Update last login
                $db->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
                
                header("Location: index.php");
                exit;
            } else {
                $error = "Invalid username or password";
            }
        } catch (Exception $e) {
            $error = "Login error. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BillPro GST Billing System</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 450px;
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }
        
        .logo {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        .login-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .login-header p {
            font-size: 14px;
            opacity: 0.9;
            font-weight: 400;
        }
        
        .login-form {
            padding: 40px 30px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            font-family: 'Outfit', sans-serif;
            transition: all 0.3s ease;
            background: #f8fafc;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #ef4444;
            animation: shake 0.5s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Outfit', sans-serif;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .login-footer {
            text-align: center;
            padding: 20px 30px 30px;
            color: #64748b;
            font-size: 13px;
        }
        
        .default-credentials {
            background: #f1f5f9;
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
            border: 2px dashed #cbd5e1;
        }
        
        .default-credentials h3 {
            font-size: 14px;
            color: #475569;
            margin-bottom: 12px;
            font-weight: 600;
        }
        
        .credential-item {
            background: white;
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 8px;
            font-size: 13px;
            color: #334155;
            border: 1px solid #e2e8f0;
        }
        
        .credential-item:last-child {
            margin-bottom: 0;
        }
        
        .credential-item strong {
            color: #2563eb;
            font-weight: 600;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .remember-me input[type="checkbox"] {
            margin-right: 8px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .remember-me label {
            font-size: 14px;
            color: #64748b;
            cursor: pointer;
            font-weight: 500;
        }
        
        @media (max-width: 480px) {
            .login-container {
                border-radius: 0;
            }
            
            .login-header {
                padding: 30px 20px;
            }
            
            .login-form {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">₹</div>
            <h1>MBSBill</h1>
            <p>GST Billing System</p>
        </div>
        
        <form method="POST" class="login-form">
            <?php if ($error): ?>
                <div class="error-message">
                    🚫 <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
            </div>
            
            <div class="remember-me">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Remember me</label>
            </div>
            
            <button type="submit" class="btn-login">🔐 Sign In</button>
            
            <div class="default-credentials">
                <h3>📋 Default Login Credentials</h3>
                <div class="credential-item">
                    <strong>Admin:</strong> username: <code>admin</code> | password: <code>admin123</code>
                </div>
                <div class="credential-item">
                    <strong>User:</strong> username: <code>user</code> | password: <code>user123</code>
                </div>
            </div>
        </form>
        
        <div class="login-footer">
            <p>© 2026 MBSBill - All rights reserved</p>
        </div>
    </div>
</body>
</html>
