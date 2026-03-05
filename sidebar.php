<nav class="sidebar">
    <div class="sidebar-header">
        <h1 class="logo">₹ MBSBill</h1>
        <p class="tagline">GST Billing System</p>
    </div>
    
    <!-- User Info -->
    <div class="user-profile">
        <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)); ?></div>
        <div class="user-details">
            <p class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></p>
            <p class="user-role"><?php echo ucfirst($_SESSION['role'] ?? 'user'); ?></p>
        </div>
    </div>
    
    <ul class="nav-menu">
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <a href="index.php">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Dashboard</span>
            </a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'sales.php' ? 'active' : ''; ?>">
            <a href="sales.php">
                <span class="nav-icon">💰</span>
                <span class="nav-text">Sales Invoice</span>
            </a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'purchase.php' ? 'active' : ''; ?>">
            <a href="purchase.php">
                <span class="nav-icon">🛒</span>
                <span class="nav-text">Purchase Entry</span>
            </a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
            <a href="products.php">
                <span class="nav-icon">📦</span>
                <span class="nav-text">Products</span>
            </a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active' : ''; ?>">
            <a href="customers.php">
                <span class="nav-icon">👥</span>
                <span class="nav-text">Customers</span>
            </a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'suppliers.php' ? 'active' : ''; ?>">
            <a href="suppliers.php">
                <span class="nav-icon">🏭</span>
                <span class="nav-text">Suppliers</span>
            </a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
            <a href="reports.php">
                <span class="nav-icon">📈</span>
                <span class="nav-text">Reports</span>
            </a>
        </li>
    </ul>
    
    <!-- Logout Button -->
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')">
            <span class="nav-icon">🚪</span>
            <span class="nav-text">Logout</span>
        </a>
    </div>
</nav>
