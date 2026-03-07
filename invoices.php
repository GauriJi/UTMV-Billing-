<?php
require_once 'auth.php';
require_once 'database.php';
$db = new Database();

// Handle delete invoice/sale
if (isset($_GET['delete_sale'])) {
    $del_id = (int)$_GET['delete_sale'];
    try {
        $db->beginTransaction();
        // Restore stock for linked products
        $del_items = $db->fetchAll("SELECT product_id, quantity FROM sales_items WHERE sale_id = ?", [$del_id]);
        foreach ($del_items as $di) {
            if ($di['product_id']) {
                $db->query("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?", [$di['quantity'], $di['product_id']]);
            }
        }
        $db->query("DELETE FROM sales_items WHERE sale_id = ?", [$del_id]);
        $db->query("DELETE FROM sales WHERE id = ?", [$del_id]);
        $db->commit();
        $_SESSION['success'] = "Invoice deleted successfully.";
    }
    catch (Exception $e) {
        $db->rollback();
        $_SESSION['error'] = "Error deleting invoice: " . $e->getMessage();
    }
    header("Location: invoices.php");
    exit;
}

// Filters
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Build query
$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "(s.invoice_no LIKE ? OR COALESCE(c.customer_name, s.customer_name_manual, '') LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status) {
    $where[] = "s.payment_status = ?";
    $params[] = $status;
}
if ($start_date) {
    $where[] = "s.sale_date >= ?";
    $params[] = $start_date;
}
if ($end_date) {
    $where[] = "s.sale_date <= ?";
    $params[] = $end_date;
}

$whereStr = implode(' AND ', $where);

$invoices = $db->fetchAll("
    SELECT s.*,
           COALESCE(c.customer_name, s.customer_name_manual, 'Walk-in') AS customer_name,
           c.gstin AS customer_gstin
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE $whereStr
    ORDER BY s.sale_date DESC, s.id DESC
", $params);

$totals = $db->single("
    SELECT COUNT(*) as cnt, SUM(grand_total) as total, SUM(cgst_amount+sgst_amount+igst_amount) as gst
    FROM sales s LEFT JOIN customers c ON s.customer_id = c.id
    WHERE $whereStr
", $params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Invoices - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .filter-bar {
            background: white; padding: 18px 20px; border-radius: 12px;
            margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;
        }
        .filter-bar .fg { display: flex; flex-direction: column; gap: 4px; }
        .filter-bar label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; }
        .filter-bar input, .filter-bar select {
            padding: 8px 12px; border: 2px solid #e2e8f0; border-radius: 8px;
            font-size: 13px; font-family: inherit; min-width: 140px;
        }
        .filter-bar input:focus, .filter-bar select:focus { outline: none; border-color: #2563eb; }
        .filter-bar .fg-search input { min-width: 220px; }

        .summary-row {
            display: flex; gap: 16px; margin-bottom: 20px; flex-wrap: wrap;
        }
        .sum-card {
            background: white; border-radius: 10px; padding: 14px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07); flex: 1; min-width: 160px;
        }
        .sum-card p { margin: 0; font-size: 12px; color: #64748b; font-weight: 600; }
        .sum-card h3 { margin: 4px 0 0; font-size: 20px; color: #1e293b; }

        .inv-table th, .inv-table td { padding: 12px 14px; }
        .inv-table tbody tr:hover { background: #f8fafc; }

        .status-paid    { background:#d1fae5; color:#065f46; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
        .status-pending { background:#fef3c7; color:#92400e; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
        .status-partial { background:#dbeafe; color:#1e40af; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }

        .btn-icon-sm {
            display:inline-flex; align-items:center; justify-content:center;
            width:32px; height:32px; border-radius:8px; text-decoration:none;
            font-size:15px; transition: transform 0.15s;
        }
        .btn-icon-sm:hover { transform: scale(1.15); }
        .btn-print { background:#eff6ff; }
        .btn-edit  { background:#fef3c7; }
        .btn-delete { background:#fee2e2; }

        .no-data-msg {
            text-align: center; padding: 60px 20px; color: #94a3b8;
        }
        .no-data-msg .icon { font-size: 48px; margin-bottom: 12px; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <?php $page_title = '🧾 All Invoices';
include 'topbar.php'; ?>

        <div class="content-wrapper">

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success'];
    unset($_SESSION['success']); ?></div>
            <?php
endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><?php echo $_SESSION['error'];
    unset($_SESSION['error']); ?></div>
            <?php
endif; ?>

            <!-- Filter Bar -->
            <form method="GET" class="filter-bar">
                <div class="fg fg-search">
                    <label>Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Invoice No or Customer...">
                </div>
                <div class="fg">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="paid"    <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="partial" <?php echo $status === 'partial' ? 'selected' : ''; ?>>Partial</option>
                    </select>
                </div>
                <div class="fg">
                    <label>From Date</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="fg">
                    <label>To Date</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <button type="submit" class="btn btn-primary">🔍 Filter</button>
                <a href="invoices.php" class="btn btn-secondary">🔄 Reset</a>
                <a href="sales.php" class="btn btn-success" style="margin-left:auto;">➕ New Invoice</a>
            </form>

            <!-- Summary -->
            <div class="summary-row">
                <div class="sum-card">
                    <p>Total Invoices</p>
                    <h3><?php echo $totals['cnt'] ?? 0; ?></h3>
                </div>
                <div class="sum-card">
                    <p>Total Revenue</p>
                    <h3>₹<?php echo number_format($totals['total'] ?? 0, 2); ?></h3>
                </div>
                <div class="sum-card">
                    <p>Total GST</p>
                    <h3>₹<?php echo number_format($totals['gst'] ?? 0, 2); ?></h3>
                </div>
            </div>

            <!-- Table -->
            <div class="table-section">
                <div class="table-container">
                    <table class="data-table inv-table">
                        <thead>
                            <tr>
                                <th>Invoice No</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>GST</th>
                                <th>Grand Total</th>
                                <th>Status</th>
                                <th style="text-align:center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="no-data-msg">
                                        <div class="icon">🧾</div>
                                        <p>No invoices found. <a href="sales.php">Create your first invoice</a></p>
                                    </div>
                                </td>
                            </tr>
                            <?php
else: ?>
                                <?php foreach ($invoices as $inv): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($inv['invoice_no']); ?></strong></td>
                                    <td><?php echo date('d M Y', strtotime($inv['sale_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($inv['customer_name']); ?></td>
                                    <td>₹<?php echo number_format($inv['total_amount'], 2); ?></td>
                                    <td>₹<?php echo number_format($inv['cgst_amount'] + $inv['sgst_amount'] + $inv['igst_amount'], 2); ?></td>
                                    <td><strong>₹<?php echo number_format($inv['grand_total'], 2); ?></strong></td>
                                    <td>
                                        <span class="status-<?php echo $inv['payment_status']; ?>">
                                            <?php echo ucfirst($inv['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;">
                                        <a href="invoice_print.php?id=<?php echo $inv['id']; ?>"
                                           class="btn-icon-sm btn-print" target="_blank" title="Print Invoice">🖨️</a>
                                        <a href="edit_sale.php?id=<?php echo $inv['id']; ?>"
                                           class="btn-icon-sm btn-edit" title="Edit Invoice">✏️</a>
                                        <a href="invoices.php?delete_sale=<?php echo $inv['id']; ?>"
                                           class="btn-icon-sm btn-delete" title="Delete Invoice"
                                           onclick="return confirm('Delete invoice <?php echo htmlspecialchars($inv['invoice_no']); ?>? This action cannot be undone.')">🗑️</a>
                                    </td>
                                </tr>
                                <?php
    endforeach; ?>
                            <?php
endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>
</body>
</html>