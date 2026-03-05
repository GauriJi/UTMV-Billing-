<!DOCTYPE html>
<html>
<head>
    <title>System Diagnostic</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        h2 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        pre { background: #f8f8f8; padding: 10px; border-left: 3px solid #667eea; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Invoice System Diagnostic</h1>
    
    <div class="box">
        <h2>1. PHP Version</h2>
        <?php
        echo "<p class='success'>✅ PHP is working!</p>";
        echo "<p>PHP Version: <strong>" . phpversion() . "</strong></p>";
        if (version_compare(phpversion(), '7.0.0', '>=')) {
            echo "<p class='success'>✅ PHP version is compatible (7.0+)</p>";
        } else {
            echo "<p class='error'>❌ PHP version too old. Need 7.0+</p>";
        }
        ?>
    </div>
    
    <div class="box">
        <h2>2. File Structure Check</h2>
        <?php
        $required_files = [
            'login.php',
            'dashboard.php',
            'includes/config.php',
            'css/style.css',
            'database.sql'
        ];
        
        $all_good = true;
        foreach ($required_files as $file) {
            if (file_exists($file)) {
                echo "<p class='success'>✅ $file exists</p>";
            } else {
                echo "<p class='error'>❌ $file NOT FOUND</p>";
                $all_good = false;
            }
        }
        
        if ($all_good) {
            echo "<p class='success'><strong>✅ All required files present!</strong></p>";
        }
        ?>
    </div>
    
    <div class="box">
        <h2>3. Database Connection Test</h2>
        <?php
        $db_host = 'localhost';
        $db_user = 'root';
        $db_pass = '';
        $db_name = 'invoice_system';
        
        echo "<p>Attempting to connect to:</p>";
        echo "<ul>";
        echo "<li>Host: <strong>$db_host</strong></li>";
        echo "<li>User: <strong>$db_user</strong></li>";
        echo "<li>Database: <strong>$db_name</strong></li>";
        echo "</ul>";
        
        $conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);
        
        if ($conn->connect_error) {
            echo "<p class='error'>❌ Database Connection FAILED!</p>";
            echo "<p class='error'>Error: " . $conn->connect_error . "</p>";
            echo "<hr>";
            echo "<h3>🔧 Fix Instructions:</h3>";
            echo "<ol>";
            echo "<li>Open XAMPP Control Panel</li>";
            echo "<li>Make sure MySQL is running (should show green)</li>";
            echo "<li>Open browser and go to: <code>http://localhost/phpmyadmin</code> or <code>http://192.168.29.189/phpmyadmin</code></li>";
            echo "<li>Click 'Import' tab</li>";
            echo "<li>Choose file: <code>database.sql</code> from this folder</li>";
            echo "<li>Click 'Go' button</li>";
            echo "<li>Refresh this page</li>";
            echo "</ol>";
        } else {
            echo "<p class='success'>✅ Database Connection SUCCESSFUL!</p>";
            
            // Check if tables exist
            $tables = ['users', 'products', 'customers', 'invoices', 'invoice_items', 'purchases', 'purchase_items'];
            $tables_exist = 0;
            
            echo "<h3>Checking Tables:</h3>";
            echo "<ul>";
            foreach ($tables as $table) {
                $result = @$conn->query("SHOW TABLES LIKE '$table'");
                if ($result && $result->num_rows > 0) {
                    echo "<li class='success'>✅ Table: $table</li>";
                    $tables_exist++;
                } else {
                    echo "<li class='error'>❌ Table: $table NOT FOUND</li>";
                }
            }
            echo "</ul>";
            
            if ($tables_exist == count($tables)) {
                echo "<p class='success'><strong>✅ All tables exist!</strong></p>";
                
                // Check admin user
                $result = $conn->query("SELECT * FROM users WHERE username = 'admin'");
                if ($result && $result->num_rows > 0) {
                    echo "<p class='success'><strong>✅ Admin user exists!</strong></p>";
                    $user = $result->fetch_assoc();
                    echo "<p>Username: <strong>admin</strong></p>";
                    echo "<p>Full Name: <strong>" . $user['full_name'] . "</strong></p>";
                } else {
                    echo "<p class='error'>❌ Admin user NOT found in database</p>";
                    echo "<p class='warning'>Need to reimport database.sql</p>";
                }
                
                // Check products
                $result = $conn->query("SELECT COUNT(*) as count FROM products");
                if ($result) {
                    $row = $result->fetch_assoc();
                    echo "<p class='success'>✅ Products in database: <strong>" . $row['count'] . "</strong></p>";
                }
                
            } else {
                echo "<p class='error'><strong>❌ Some tables are missing!</strong></p>";
                echo "<p class='warning'>You need to import database.sql file via phpMyAdmin</p>";
            }
            
            $conn->close();
        }
        ?>
    </div>
    
    <div class="box">
        <h2>4. Session Support</h2>
        <?php
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (session_status() == PHP_SESSION_ACTIVE) {
            echo "<p class='success'>✅ PHP Sessions are working</p>";
        } else {
            echo "<p class='error'>❌ PHP Sessions NOT working</p>";
        }
        ?>
    </div>
    
    <div class="box">
        <h2>5. Current Directory</h2>
        <?php
        echo "<p><strong>Current Directory:</strong></p>";
        echo "<pre>" . getcwd() . "</pre>";
        echo "<p><strong>Current File:</strong></p>";
        echo "<pre>" . __FILE__ . "</pre>";
        ?>
    </div>
    
    <div class="box">
        <h2>6. Access URLs</h2>
        <?php
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $uri = $_SERVER['REQUEST_URI'];
        $base_url = dirname($uri);
        
        echo "<p>You are currently accessing:</p>";
        echo "<pre>" . $protocol . "://" . $host . $uri . "</pre>";
        
        echo "<p><strong>Try these URLs to access the system:</strong></p>";
        echo "<ul>";
        echo "<li><a href='login.php' target='_blank'>" . $protocol . "://" . $host . $base_url . "/login.php</a></li>";
        echo "<li><a href='index.php' target='_blank'>" . $protocol . "://" . $host . $base_url . "/index.php</a></li>";
        echo "</ul>";
        ?>
    </div>
    
    <div class="box">
        <h2>✅ Next Steps</h2>
        <?php
        $conn_test = @new mysqli('localhost', 'root', '', 'invoice_system');
        
        if (!$conn_test->connect_error) {
            $result = $conn_test->query("SELECT * FROM users WHERE username = 'admin'");
            if ($result && $result->num_rows > 0) {
                echo "<p class='success'><strong>🎉 SYSTEM IS READY!</strong></p>";
                echo "<h3>You can now:</h3>";
                echo "<ol>";
                echo "<li><strong>Go to Login Page:</strong> <a href='login.php' style='color: blue; text-decoration: underline;'>Click Here</a></li>";
                echo "<li><strong>Login with:</strong>";
                echo "<ul>";
                echo "<li>Username: <code><strong>admin</strong></code></li>";
                echo "<li>Password: <code><strong>admin123</strong></code></li>";
                echo "</ul>";
                echo "</li>";
                echo "</ol>";
            } else {
                echo "<p class='warning'><strong>⚠ Database needs to be imported!</strong></p>";
                echo "<p>Follow the instructions in section 3 above.</p>";
            }
            $conn_test->close();
        } else {
            echo "<p class='warning'><strong>⚠ Fix database connection first!</strong></p>";
            echo "<p>Follow the instructions in section 3 above.</p>";
        }
        ?>
    </div>
    
    <div class="box">
        <h2>📋 Quick Reference</h2>
        <p><strong>If login page shows blank/white:</strong></p>
        <ol>
            <li>Check that all files from section 2 exist</li>
            <li>Check Apache error logs in XAMPP</li>
            <li>Enable error display (see below)</li>
        </ol>
        
        <p><strong>Enable Error Display:</strong></p>
        <p>Add this to the TOP of login.php (after &lt;?php):</p>
        <pre>error_reporting(E_ALL);
ini_set('display_errors', 1);</pre>
        
        <p><strong>MySQL not running:</strong></p>
        <ol>
            <li>Open XAMPP Control Panel</li>
            <li>Click "Start" next to MySQL</li>
            <li>Wait for green indicator</li>
            <li>Refresh this page</li>
        </ol>
    </div>
    
    <hr>
    <p style="text-align: center; color: #666;">
        <small>Diagnostic Page - Invoice Billing System</small>
    </p>
</body>
</html>
