<?php
require_once 'auth.php';
require_once 'database.php';
$db = new Database();

// Get dashboard statistics
$total_sales     = $db->single("SELECT SUM(grand_total) as total FROM sales")['total'] ?? 0;
$total_purchases = $db->single("SELECT SUM(grand_total) as total FROM purchases")['total'] ?? 0;
$total_customers = $db->single("SELECT COUNT(*) as count FROM customers")['count'] ?? 0;
$total_products  = $db->single("SELECT COUNT(*) as count FROM products")['count'] ?? 0;

// Recent sales — show saved customer name, or manual name, or 'Walk-in'
$recent_sales = $db->fetchAll("SELECT s.*, COALESCE(c.customer_name, s.customer_name_manual, 'Walk-in') AS customer_name FROM sales s LEFT JOIN customers c ON s.customer_id = c.id ORDER BY s.created_at DESC LIMIT 5");

// Today's reminders (if table exists)
$todays_reminders = [];
try {
    $todays_reminders = $db->fetchAll("SELECT * FROM reminders WHERE DATE(remind_date) = CURDATE() AND status = 'pending' ORDER BY remind_time ASC");
} catch (Exception $e) {}

// Overdue reminders count
$overdue_count = 0;
try {
    $overdue_count = $db->single("SELECT COUNT(*) as c FROM reminders WHERE remind_date < CURDATE() AND status = 'pending'")['c'] ?? 0;
} catch (Exception $e) {}
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

        <?php include 'sidebar.php'; ?>

        <main class="main-content">
            <?php $page_title = 'Dashboard Overview'; include 'topbar.php'; ?>

            <div class="content-wrapper">

                <?php if(isset($_GET['error']) && $_GET['error'] === 'access_denied'): ?>
                    <div class="alert alert-error">You don't have permission to access that page.</div>
                <?php endif; ?>

                <?php if (!empty($todays_reminders)): ?>
                <div class="alert alert-success" style="display:flex; align-items:center; gap:10px; margin-bottom:1.5rem;">
                    <span style="font-size:20px;">🔔</span>
                    <div>
                        <strong>You have <?php echo count($todays_reminders); ?> reminder(s) due today!</strong>
                        <?php foreach($todays_reminders as $r): ?>
                            &nbsp;· <?php echo htmlspecialchars($r['title']); ?>
                        <?php endforeach; ?>
                        &nbsp;<a href="reminders.php?filter=today" style="color:#065f46; font-weight:700; text-decoration:underline;">View all →</a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($overdue_count > 0): ?>
                <div class="alert alert-error" style="display:flex; align-items:center; gap:10px; margin-bottom:1.5rem;">
                    <span style="font-size:20px;">⚠️</span>
                    <div>
                        <strong><?php echo $overdue_count; ?> overdue reminder(s)!</strong>
                        &nbsp;<a href="reminders.php?filter=overdue" style="color:#991b1b; font-weight:700; text-decoration:underline;">View →</a>
                    </div>
                </div>
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

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h3 class="section-title" style="margin-bottom:1rem;">Quick Actions</h3>
                    <div class="action-grid">
                        <a href="sales.php" class="action-card">
                            <div class="action-icon">💰</div>
                            <div class="action-text">New Sale</div>
                        </a>
                        <a href="purchase.php" class="action-card">
                            <div class="action-icon">🛒</div>
                            <div class="action-text">New Purchase</div>
                        </a>
                        <a href="reminders.php" class="action-card">
                            <div class="action-icon">🔔</div>
                            <div class="action-text">Reminders</div>
                        </a>
                        <a href="Calendar.php" class="action-card">
                            <div class="action-icon">📅</div>
                            <div class="action-text">Calendar</div>
                        </a>
                        <a href="reports.php" class="action-card">
                            <div class="action-icon">📈</div>
                            <div class="action-text">Reports</div>
                        </a>
                        <a href="tally.php" class="action-card">
                            <div class="action-icon">🔗</div>
                            <div class="action-text">Tally Export</div>
                        </a>
                    </div>
                </div>

                <!-- Recent Sales Table -->
                <div class="table-section" style="margin-top:2rem;">
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
                                    <?php foreach($recent_sales as $sale):
                                        $gst = $sale['cgst_amount'] + $sale['sgst_amount'] + $sale['igst_amount'];
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($sale['invoice_no']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($sale['sale_date'])); ?></td>
                                        <td>₹<?php echo number_format($sale['total_amount'], 2); ?></td>
                                        <td>₹<?php echo number_format($gst, 2); ?></td>
                                        <td><strong>₹<?php echo number_format($sale['grand_total'], 2); ?></strong></td>
                                        <td>
                                            <span style="padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700;
                                                background:<?php echo $sale['payment_status']==='paid' ? '#d1fae5' : ($sale['payment_status']==='partial' ? '#fef3c7' : '#fee2e2'); ?>;
                                                color:<?php echo $sale['payment_status']==='paid' ? '#065f46' : ($sale['payment_status']==='partial' ? '#92400e' : '#991b1b'); ?>;">
                                                <?php echo strtoupper($sale['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="invoice_print.php?id=<?php echo $sale['id']; ?>" target="_blank" class="btn-icon" title="Print">🖨️</a>
                                            <a href="edit_sale.php?id=<?php echo $sale['id']; ?>" class="btn-icon" title="Edit">✏️</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script src="script.js"></script>
</body>
</html>