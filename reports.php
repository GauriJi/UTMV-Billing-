<?php
require_once 'auth.php'; // Authentication check
require_once 'database.php';
$db = new Database();

// Handle CSV Download
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $report_type = $_GET['report_type'] ?? 'sales';
    
    if ($report_type === 'sales') {
        $data = $db->fetchAll("
            SELECT s.invoice_no, s.sale_date, c.customer_name, s.total_amount, 
                   s.cgst_amount, s.sgst_amount, s.igst_amount, s.grand_total, s.payment_status
            FROM sales s 
            LEFT JOIN customers c ON s.customer_id = c.id 
            WHERE s.sale_date BETWEEN ? AND ? 
            ORDER BY s.sale_date DESC
        ", [$start_date, $end_date]);
        
        $filename = "sales_report_" . $start_date . "_to_" . $end_date . ".csv";
        $headers = ['Invoice No', 'Date', 'Customer', 'Amount', 'CGST', 'SGST', 'IGST', 'Grand Total', 'Payment Status'];
        
    } else {
        $data = $db->fetchAll("
            SELECT p.purchase_no, p.purchase_date, s.supplier_name, p.total_amount, 
                   p.cgst_amount, p.sgst_amount, p.igst_amount, p.grand_total, p.payment_status
            FROM purchases p 
            LEFT JOIN suppliers s ON p.supplier_id = s.id 
            WHERE p.purchase_date BETWEEN ? AND ? 
            ORDER BY p.purchase_date DESC
        ", [$start_date, $end_date]);
        
        $filename = "purchase_report_" . $start_date . "_to_" . $end_date . ".csv";
        $headers = ['Purchase No', 'Date', 'Supplier', 'Amount', 'CGST', 'SGST', 'IGST', 'Grand Total', 'Payment Status'];
    }
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Create file pointer
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Output headers
    fputcsv($output, $headers);
    
    // Output data
    foreach ($data as $row) {
        fputcsv($output, array_values($row));
    }
    
    fclose($output);
    exit;
}

// Get date range from query params or default to current month
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Sales Report
$sales_report = $db->fetchAll("
    SELECT s.*, c.customer_name 
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    WHERE s.sale_date BETWEEN ? AND ? 
    ORDER BY s.sale_date DESC
", [$start_date, $end_date]);

$sales_summary = $db->single("
    SELECT 
        COUNT(*) as total_invoices,
        SUM(total_amount) as total_sales,
        SUM(cgst_amount) as total_cgst,
        SUM(sgst_amount) as total_sgst,
        SUM(igst_amount) as total_igst,
        SUM(grand_total) as total_grand
    FROM sales 
    WHERE sale_date BETWEEN ? AND ?
", [$start_date, $end_date]);

// Purchase Report
$purchase_report = $db->fetchAll("
    SELECT p.*, s.supplier_name 
    FROM purchases p 
    LEFT JOIN suppliers s ON p.supplier_id = s.id 
    WHERE p.purchase_date BETWEEN ? AND ? 
    ORDER BY p.purchase_date DESC
", [$start_date, $end_date]);

$purchase_summary = $db->single("
    SELECT 
        COUNT(*) as total_purchases,
        SUM(total_amount) as total_amount,
        SUM(cgst_amount) as total_cgst,
        SUM(sgst_amount) as total_sgst,
        SUM(igst_amount) as total_igst,
        SUM(grand_total) as total_grand
    FROM purchases 
    WHERE purchase_date BETWEEN ? AND ?
", [$start_date, $end_date]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .quick-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .quick-filter-btn {
            padding: 8px 16px;
            border: 2px solid #e2e8f0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            color: #475569;
        }
        
        .quick-filter-btn:hover {
            border-color: #2563eb;
            background: #eff6ff;
            color: #2563eb;
        }
        
        .quick-filter-btn.active {
            background: #2563eb;
            border-color: #2563eb;
            color: white;
        }
        
        .download-buttons {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }
        
        .btn-download {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .btn-download:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .btn-download-secondary {
            background: #3b82f6;
        }
        
        .btn-download-secondary:hover {
            background: #2563eb;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <main class="main-content">
            <header class="top-bar">
                <h2 class="page-title">Reports & Analytics</h2>
                <a href="index.php" class="btn btn-secondary">← Back</a>
            </header>

            <div class="content-wrapper">
                <!-- Date Filter -->
                <div class="filter-section">
                    <h3 class="section-title" style="margin-bottom: 16px;">📅 Filter by Date Range</h3>
                    
                    <!-- Quick Filters -->
                    <div class="quick-filters">
                        <button type="button" class="quick-filter-btn" onclick="setDateRange('today')">Today</button>
                        <button type="button" class="quick-filter-btn" onclick="setDateRange('yesterday')">Yesterday</button>
                        <button type="button" class="quick-filter-btn" onclick="setDateRange('this_week')">This Week</button>
                        <button type="button" class="quick-filter-btn" onclick="setDateRange('last_week')">Last Week</button>
                        <button type="button" class="quick-filter-btn" onclick="setDateRange('this_month')">This Month</button>
                        <button type="button" class="quick-filter-btn" onclick="setDateRange('last_month')">Last Month</button>
                        <button type="button" class="quick-filter-btn" onclick="setDateRange('this_year')">This Year</button>
                    </div>
                    
                    <!-- Custom Date Range -->
                    <form method="GET" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 13px; color: #64748b; margin-bottom: 4px; display: block;">Start Date</label>
                            <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo $start_date; ?>" style="width: 180px;">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 13px; color: #64748b; margin-bottom: 4px; display: block;">End Date</label>
                            <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo $end_date; ?>" style="width: 180px;">
                        </div>
                        <button type="submit" class="btn btn-primary">🔍 Apply Filter</button>
                        <a href="reports.php" class="btn btn-secondary">🔄 Reset</a>
                    </form>
                </div>

                <!-- Sales Summary -->
                <div class="stats-grid" style="margin-bottom: 2rem;">
                    <div class="stat-card sales-card">
                        <div class="stat-icon">💰</div>
                        <div class="stat-details">
                            <p class="stat-label">Total Sales</p>
                            <h3 class="stat-value">₹<?php echo number_format($sales_summary['total_grand'] ?? 0, 2); ?></h3>
                            <p style="font-size: 12px; color: #666;"><?php echo $sales_summary['total_invoices'] ?? 0; ?> Invoices</p>
                        </div>
                    </div>

                    <div class="stat-card purchase-card">
                        <div class="stat-icon">🛒</div>
                        <div class="stat-details">
                            <p class="stat-label">Total Purchases</p>
                            <h3 class="stat-value">₹<?php echo number_format($purchase_summary['total_grand'] ?? 0, 2); ?></h3>
                            <p style="font-size: 12px; color: #666;"><?php echo $purchase_summary['total_purchases'] ?? 0; ?> Purchases</p>
                        </div>
                    </div>

                    <div class="stat-card customer-card">
                        <div class="stat-icon">📊</div>
                        <div class="stat-details">
                            <p class="stat-label">Total GST Collected</p>
                            <h3 class="stat-value">₹<?php 
                                $total_gst = ($sales_summary['total_cgst'] ?? 0) + ($sales_summary['total_sgst'] ?? 0) + ($sales_summary['total_igst'] ?? 0);
                                echo number_format($total_gst, 2); 
                            ?></h3>
                        </div>
                    </div>

                    <div class="stat-card product-card">
                        <div class="stat-icon">💹</div>
                        <div class="stat-details">
                            <p class="stat-label">Profit Margin</p>
                            <h3 class="stat-value">₹<?php 
                                $margin = ($sales_summary['total_sales'] ?? 0) - ($purchase_summary['total_amount'] ?? 0);
                                echo number_format($margin, 2); 
                            ?></h3>
                        </div>
                    </div>
                </div>

                <!-- Sales Report -->
                <div class="table-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h3 class="section-title" style="margin: 0;">💰 Sales Report (<?php echo date('d M Y', strtotime($start_date)); ?> to <?php echo date('d M Y', strtotime($end_date)); ?>)</h3>
                        <a href="?download=csv&report_type=sales&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn-download">
                            📥 Download CSV
                        </a>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Invoice No</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>CGST</th>
                                    <th>SGST</th>
                                    <th>IGST</th>
                                    <th>Grand Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($sales_report)): ?>
                                <tr><td colspan="8" class="no-data">No sales found in this period</td></tr>
                                <?php else: ?>
                                    <?php foreach($sales_report as $sale): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($sale['invoice_no']); ?></strong></td>
                                        <td><?php echo date('d M Y', strtotime($sale['sale_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in'); ?></td>
                                        <td>₹<?php echo number_format($sale['total_amount'], 2); ?></td>
                                        <td>₹<?php echo number_format($sale['cgst_amount'], 2); ?></td>
                                        <td>₹<?php echo number_format($sale['sgst_amount'], 2); ?></td>
                                        <td>₹<?php echo number_format($sale['igst_amount'], 2); ?></td>
                                        <td><strong>₹<?php echo number_format($sale['grand_total'], 2); ?></strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Purchase Report -->
                <div class="table-section" style="margin-top: 2rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h3 class="section-title" style="margin: 0;">🛒 Purchase Report (<?php echo date('d M Y', strtotime($start_date)); ?> to <?php echo date('d M Y', strtotime($end_date)); ?>)</h3>
                        <a href="?download=csv&report_type=purchase&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn-download btn-download-secondary">
                            📥 Download CSV
                        </a>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Purchase No</th>
                                    <th>Date</th>
                                    <th>Supplier</th>
                                    <th>Amount</th>
                                    <th>CGST</th>
                                    <th>SGST</th>
                                    <th>IGST</th>
                                    <th>Grand Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($purchase_report)): ?>
                                <tr><td colspan="8" class="no-data">No purchases found in this period</td></tr>
                                <?php else: ?>
                                    <?php foreach($purchase_report as $purchase): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($purchase['purchase_no']); ?></strong></td>
                                        <td><?php echo date('d M Y', strtotime($purchase['purchase_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($purchase['supplier_name'] ?? 'N/A'); ?></td>
                                        <td>₹<?php echo number_format($purchase['total_amount'], 2); ?></td>
                                        <td>₹<?php echo number_format($purchase['cgst_amount'], 2); ?></td>
                                        <td>₹<?php echo number_format($purchase['sgst_amount'], 2); ?></td>
                                        <td>₹<?php echo number_format($purchase['igst_amount'], 2); ?></td>
                                        <td><strong>₹<?php echo number_format($purchase['grand_total'], 2); ?></strong></td>
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
    <script>
        function setDateRange(range) {
            const today = new Date();
            let startDate, endDate;
            
            switch(range) {
                case 'today':
                    startDate = endDate = today;
                    break;
                    
                case 'yesterday':
                    const yesterday = new Date(today);
                    yesterday.setDate(yesterday.getDate() - 1);
                    startDate = endDate = yesterday;
                    break;
                    
                case 'this_week':
                    const firstDayOfWeek = new Date(today);
                    firstDayOfWeek.setDate(today.getDate() - today.getDay());
                    startDate = firstDayOfWeek;
                    endDate = today;
                    break;
                    
                case 'last_week':
                    const lastWeekEnd = new Date(today);
                    lastWeekEnd.setDate(today.getDate() - today.getDay() - 1);
                    const lastWeekStart = new Date(lastWeekEnd);
                    lastWeekStart.setDate(lastWeekEnd.getDate() - 6);
                    startDate = lastWeekStart;
                    endDate = lastWeekEnd;
                    break;
                    
                case 'this_month':
                    startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                    endDate = today;
                    break;
                    
                case 'last_month':
                    startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    endDate = new Date(today.getFullYear(), today.getMonth(), 0);
                    break;
                    
                case 'this_year':
                    startDate = new Date(today.getFullYear(), 0, 1);
                    endDate = today;
                    break;
            }
            
            // Format dates as YYYY-MM-DD
            const formatDate = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };
            
            // Set the date inputs and submit the form
            document.getElementById('start_date').value = formatDate(startDate);
            document.getElementById('end_date').value = formatDate(endDate);
            document.querySelector('form').submit();
        }
    </script>
</body>
</html>
