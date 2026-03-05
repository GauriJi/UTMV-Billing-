<?php
require_once 'auth.php'; // Authentication check
require_once 'database.php';
$db = new Database();

// Get dashboard statistics
$total_sales = $db->single("SELECT COUNT(*) as count, SUM(grand_total) as total FROM sales")['total'] ?? 0;
$total_purchases = $db->single("SELECT COUNT(*) as count, SUM(grand_total) as total FROM purchases")['total'] ?? 0;
$total_customers = $db->single("SELECT COUNT(*) as count FROM customers")['count'] ?? 0;
$total_products = $db->single("SELECT COUNT(*) as count FROM products")['count'] ?? 0;

// Recent sales
$recent_sales = $db->fetchAll("SELECT s.*, c.customer_name FROM sales s LEFT JOIN customers c ON s.customer_id = c.id ORDER BY s.created_at DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h1 class="logo">₹ MBSBill</h1>
                <p class="tagline">GST Billing System</p>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item active">
                    <a href="index.php">
                        <span class="nav-icon">📊</span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="sales.php">
                        <span class="nav-icon">💰</span>
                        <span class="nav-text">Sales Invoice</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="purchase.php">
                        <span class="nav-icon">🛒</span>
                        <span class="nav-text">Purchase Entry</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="products.php">
                        <span class="nav-icon">📦</span>
                        <span class="nav-text">Products</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="customers.php">
                        <span class="nav-icon">👥</span>
                        <span class="nav-text">Customers</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="suppliers.php">
                        <span class="nav-icon">🏭</span>
                        <span class="nav-text">Suppliers</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="reports.php">
                        <span class="nav-icon">📈</span>
                        <span class="nav-text">Reports</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="Calendar.php">
                    <span class="nav-icon">📅</span>
                    <span class="-text">Calendar</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="index.php">
                    <span class="nav-icon">🔔</span>
                    <span class="nav-text">Reminders</span>
                 </a>
                </li>
                <li class="nav-item">
                    <a href="index.php">
                    <span class="btn-icon">📊</span>
                    <span class="btn-text">Tally Link</span>
                 </a>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-bar">
                <h2 class="page-title">Dashboard Overview</h2>
                <div class="user-info">
                    <span class="date-time"><?php echo date('l, F j, Y'); ?></span>
                </div>
            </header>

            <div class="content-wrapper">
                <?php if(isset($_GET['error']) && $_GET['error'] === 'access_denied'): ?>
                    <div class="alert alert-error">You don't have permission to access that page.</div>
                <?php endif; ?>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card sales-card">
                        <div class="stat-icon">💰</div>
                        <div class="stat-details">
                            <p class="stat-label">Total Sales</p>
                            <h3 class="stat-value">₹<?php echo number_format($total_sales, 2); ?></h3>
                        </div>
                    </div>

                    <div class="stat-card purchase-card">
                        <div class="stat-icon">🛒</div>
                        <div class="stat-details">
                            <p class="stat-label">Total Purchases</p>
                            <h3 class="stat-value">₹<?php echo number_format($total_purchases, 2); ?></h3>
                        </div>
                    </div>

                    <div class="stat-card customer-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-details">
                            <p class="stat-label">Total Customers</p>
                            <h3 class="stat-value"><?php echo $total_customers; ?></h3>
                        </div>
                    </div>

                    <div class="stat-card product-card">
                        <div class="stat-icon">📦</div>
                        <div class="stat-details">
                            <p class="stat-label">Total Products</p>
                            <h3 class="stat-value"><?php echo $total_products; ?></h3>
                        </div>
                    </div>
                </div>

                <!-- Recent Sales Table -->
                <div class="table-section">
                    <div class="section-header">
                        <h3 class="section-title">Recent Sales</h3>
                        <a href="sales.php" class="btn btn-primary">+ New Sale</a>
                    </div>
                    
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Invoice No</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>GST</th>
                                    <th>Grand Total</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($recent_sales)): ?>
                                <tr>
                                    <td colspan="8" class="no-data">No sales found. Create your first sale!</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach($recent_sales as $sale): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($sale['invoice_no']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer'); ?></td>
                                        <td><?php echo date('d M Y', strtotime($sale['sale_date'])); ?></td>
                                        <td>₹<?php echo number_format($sale['total_amount'], 2); ?></td>
                                        <td>₹<?php echo number_format($sale['cgst_amount'] + $sale['sgst_amount'] + $sale['igst_amount'], 2); ?></td>
                                        <td><strong>₹<?php echo number_format($sale['grand_total'], 2); ?></strong></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $sale['payment_status']; ?>">
                                                <?php echo ucfirst($sale['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 8px; justify-content: center;">
                                                <a href="edit_sale.php?id=<?php echo $sale['id']; ?>" class="btn-icon btn-edit" title="Edit Invoice">✏️</a>
                                                <a href="invoice_print.php?id=<?php echo $sale['id']; ?>" class="btn-icon btn-print" title="Print Invoice" target="_blank">🖨️</a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h3 class="section-title">Quick Actions</h3>
                    <div class="action-grid">
                        <a href="sales.php" class="action-card">
                            <span class="action-icon">💰</span>
                            <span class="action-text">Create Sale</span>
                        </a>
                        <a href="purchase.php" class="action-card">
                            <span class="action-icon">🛒</span>
                            <span class="action-text">Add Purchase</span>
                        </a>
                        <a href="products.php" class="action-card">
                            <span class="action-icon">📦</span>
                            <span class="action-text">Manage Products</span>
                        </a>
                        <a href="customers.php" class="action-card">
                            <span class="action-icon">👥</span>
                            <span class="action-text">Add Customer</span>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="script.js"></script>
</body>
</html>
