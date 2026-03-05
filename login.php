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
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['email']     = $user['email'];
                
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
    <title>MBSBill - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            width: 420px;
            max-width: 95vw;
            box-shadow: 0 25px 50px rgba(0,0,0,0.4);
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e40af 100%);
            padding: 40px 30px;
            text-align: center;
        }

        .login-header .logo {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #60a5fa, #3b82f6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 700;
            color: white;
            margin: 0 auto 16px;
            box-shadow: 0 8px 20px rgba(37,99,235,0.4);
        }

        .login-header h1 {
            color: white;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .login-header p {
            color: #93c5fd;
            font-size: 13px;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 500;
        }

        .login-form {
            padding: 36px 32px 28px;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            border-left: 4px solid #ef4444;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 13px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-family: 'Outfit', sans-serif;
            font-size: 15px;
            color: #1e293b;
            transition: all 0.2s;
            background: #f8fafc;
        }

        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            background: white;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 22px;
        }

        .remember-me input[type="checkbox"] {
            width: 17px;
            height: 17px;
            cursor: pointer;
            accent-color: #2563eb;
        }

        .remember-me label {
            font-size: 14px;
            color: #64748b;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            border: none;
            border-radius: 10px;
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            letter-spacing: 0.3px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37,99,235,0.4);
        }

        .btn-login:active { transform: translateY(0); }

        .login-footer {
            text-align: center;
            padding: 16px 30px 24px;
            color: #94a3b8;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">₹</div>
            <h1>UTMV Billing</h1>
            <p>GST Billing System</p>
        </div>

        <form method="POST" class="login-form">
            <?php if ($error): ?>
                <div class="error-message">🚫 <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control"
                       placeholder="Enter your username" required autofocus
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="Enter your password" required>
            </div>

            <div class="remember-me">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Remember me</label>
            </div>

            <button type="submit" class="btn-login">🔐 Sign In</button>
        </form>

        <div class="login-footer">
            © <?php echo date('Y'); ?>UTMV-All Right Reserved 
        </div>
    </div>
</body>
</html>
