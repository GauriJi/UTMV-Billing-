<?php
require_once 'auth.php';
require_once 'database.php';
$db = new Database();

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date   = $_GET['end_date']   ?? date('Y-m-d');

// ── Excel/CSV Download ────────────────────────────────────────────────────────
if (isset($_GET['download'])) {
    $type = $_GET['download']; // 'sales_excel' or 'purchase_excel'

    if ($type === 'sales_excel') {
        // One row per ITEM (product line), not per invoice
        $rows = $db->fetchAll("
            SELECT
                s.invoice_no,
                s.sale_date,
                COALESCE(c.customer_name, s.customer_name_manual, 'Walk-in') AS customer_name,
                COALESCE(c.gstin, '')    AS gstin,
                si.product_name          AS item_desc,
                si.hsn_code,
                si.rate                  AS unit_price,
                si.gst_rate              AS gst_percent,
                si.quantity              AS qty,
                si.sgst,
                si.cgst,
                si.igst,
                si.total,
                s.grand_total            AS invoice_total,
                s.payment_status
            FROM sales s
            LEFT JOIN customers c  ON s.customer_id = c.id
            JOIN  sales_items si   ON si.sale_id = s.id
            WHERE s.sale_date BETWEEN ? AND ?
            ORDER BY s.sale_date DESC, s.id DESC, si.id ASC
        ", [$start_date, $end_date]);

        $filename = "Sales_Report_{$start_date}_to_{$end_date}.csv";
        $headers  = [
            'INV No', 'Date', 'Customer Name', 'GST No.',
            'Item Desc.', 'HSN Code', 'Unit Price', 'GST %', 'QTY',
            'SGST', 'CGST', 'IGST', 'Total', 'Invoice Total', 'Payment Status'
        ];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel UTF-8
        fputcsv($out, $headers);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['invoice_no'],
                date('d-m-Y', strtotime($r['sale_date'])),
                $r['customer_name'],
                $r['gstin'],
                $r['item_desc'],
                $r['hsn_code'],
                number_format($r['unit_price'],   2, '.', ''),
                $r['gst_percent'],
                $r['qty'],
                number_format($r['sgst'],         2, '.', ''),
                number_format($r['cgst'],         2, '.', ''),
                number_format($r['igst'],         2, '.', ''),
                number_format($r['total'],        2, '.', ''),
                number_format($r['invoice_total'],2, '.', ''),
                ucfirst($r['payment_status'])
            ]);
        }
        fclose($out);
        exit;
    }

    if ($type === 'purchase_excel') {
        $rows = $db->fetchAll("
            SELECT
                p.purchase_no,
                p.purchase_date,
                COALESCE(s.supplier_name, p.supplier_name_manual, 'Unknown') AS supplier_name,
                COALESCE(s.gstin, '') AS gstin,
                pi.product_name       AS item_desc,
                pi.hsn_code,
                pi.rate               AS unit_price,
                pi.gst_rate           AS gst_percent,
                pi.quantity           AS qty,
                pi.sgst,
                pi.cgst,
                pi.igst,
                pi.total,
                p.grand_total         AS purchase_total
            FROM purchases p
            LEFT JOIN suppliers s  ON p.supplier_id = s.id
            JOIN  purchase_items pi ON pi.purchase_id = p.id
            WHERE p.purchase_date BETWEEN ? AND ?
            ORDER BY p.purchase_date DESC, p.id DESC, pi.id ASC
        ", [$start_date, $end_date]);

        $filename = "Purchase_Report_{$start_date}_to_{$end_date}.csv";
        $headers  = [
            'Purchase No', 'Date', 'Supplier Name', 'GST No.',
            'Item Desc.', 'HSN Code', 'Unit Price', 'GST %', 'QTY',
            'SGST', 'CGST', 'IGST', 'Total', 'Purchase Total'
        ];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, $headers);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['purchase_no'],
                date('d-m-Y', strtotime($r['purchase_date'])),
                $r['supplier_name'],
                $r['gstin'],
                $r['item_desc'],
                $r['hsn_code'],
                number_format($r['unit_price'],    2, '.', ''),
                $r['gst_percent'],
                $r['qty'],
                number_format($r['sgst'],          2, '.', ''),
                number_format($r['cgst'],          2, '.', ''),
                number_format($r['igst'],          2, '.', ''),
                number_format($r['total'],         2, '.', ''),
                number_format($r['purchase_total'],2, '.', '')
            ]);
        }
        fclose($out);
        exit;
    }
}

// ── Page data ─────────────────────────────────────────────────────────────────
$sales_report = $db->fetchAll("
    SELECT s.*, COALESCE(c.customer_name, s.customer_name_manual, 'Walk-in') AS customer_name
    FROM sales s LEFT JOIN customers c ON s.customer_id = c.id
    WHERE s.sale_date BETWEEN ? AND ?
    ORDER BY s.sale_date DESC
", [$start_date, $end_date]);

$sales_summary = $db->single("
    SELECT COUNT(*) as total_invoices, SUM(total_amount) as total_sales,
           SUM(cgst_amount) as total_cgst, SUM(sgst_amount) as total_sgst,
           SUM(igst_amount) as total_igst, SUM(grand_total) as total_grand
    FROM sales WHERE sale_date BETWEEN ? AND ?
", [$start_date, $end_date]);

$purchase_report = $db->fetchAll("
    SELECT p.*, COALESCE(s.supplier_name, p.supplier_name_manual, 'Unknown') AS supplier_name
    FROM purchases p LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE p.purchase_date BETWEEN ? AND ?
    ORDER BY p.purchase_date DESC
", [$start_date, $end_date]);

$purchase_summary = $db->single("
    SELECT COUNT(*) as total_purchases, SUM(total_amount) as total_amount,
           SUM(cgst_amount) as total_cgst, SUM(sgst_amount) as total_sgst,
           SUM(igst_amount) as total_igst, SUM(grand_total) as total_grand
    FROM purchases WHERE purchase_date BETWEEN ? AND ?
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
            background:white; padding:20px; border-radius:12px;
            margin-bottom:24px; box-shadow:0 2px 8px rgba(0,0,0,0.08);
        }
        .quick-filters { display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; }
        .quick-filter-btn {
            padding:8px 16px; border:2px solid #e2e8f0; background:white;
            border-radius:8px; cursor:pointer; font-size:14px; font-weight:500;
            transition:all 0.2s; color:#475569;
        }
        .quick-filter-btn:hover { border-color:#2563eb; background:#eff6ff; color:#2563eb; }

        .export-bar {
            display:flex; gap:12px; align-items:center; flex-wrap:wrap;
            background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;
            padding:14px 18px; margin-bottom:24px;
        }
        .export-bar span { font-weight:700; color:#1e293b; font-size:14px; }
        .btn-excel {
            display:inline-flex; align-items:center; gap:8px; padding:10px 20px;
            background:#16a34a; color:white; border:none; border-radius:8px;
            font-weight:600; cursor:pointer; text-decoration:none; font-size:14px;
            transition:all 0.2s;
        }
        .btn-excel:hover { background:#15803d; transform:translateY(-1px); box-shadow:0 4px 12px rgba(22,163,74,0.3); }
        .btn-excel-blue { background:#2563eb; }
        .btn-excel-blue:hover { background:#1d4ed8; box-shadow:0 4px 12px rgba(37,99,235,0.3); }

        .excel-note {
            font-size:12px; color:#64748b; margin-top:6px;
        }
        .excel-note strong { color:#16a34a; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <?php $page_title = 'Reports & Analytics'; include 'topbar.php'; ?>

        <div class="content-wrapper">

            <!-- Date Filter -->
            <div class="filter-section">
                <h3 class="section-title" style="margin-bottom:16px;">📅 Filter by Date Range</h3>
                <div class="quick-filters">
                    <button type="button" class="quick-filter-btn" onclick="setDateRange('today')">Today</button>
                    <button type="button" class="quick-filter-btn" onclick="setDateRange('yesterday')">Yesterday</button>
                    <button type="button" class="quick-filter-btn" onclick="setDateRange('this_week')">This Week</button>
                    <button type="button" class="quick-filter-btn" onclick="setDateRange('last_week')">Last Week</button>
                    <button type="button" class="quick-filter-btn" onclick="setDateRange('this_month')">This Month</button>
                    <button type="button" class="quick-filter-btn" onclick="setDateRange('last_month')">Last Month</button>
                    <button type="button" class="quick-filter-btn" onclick="setDateRange('this_year')">This Year</button>
                </div>
                <form method="GET" style="display:flex;gap:1rem;align-items:end;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:13px;color:#64748b;margin-bottom:4px;display:block;">Start Date</label>
                        <input type="date" name="start_date" id="start_date" class="form-control"
                               value="<?php echo $start_date; ?>" style="width:180px;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:13px;color:#64748b;margin-bottom:4px;display:block;">End Date</label>
                        <input type="date" name="end_date" id="end_date" class="form-control"
                               value="<?php echo $end_date; ?>" style="width:180px;">
                    </div>
                    <button type="submit" class="btn btn-primary">🔍 Apply Filter</button>
                    <a href="reports.php" class="btn btn-secondary">🔄 Reset</a>
                </form>
            </div>

            <!-- Excel Export Bar -->
            <div class="export-bar">
                <span>📥 Download Detailed Excel Report:</span>
                <div>
                    <a href="?download=sales_excel&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>"
                       class="btn-excel">
                        📊 Sales Excel (Item-wise)
                    </a>
                    <div class="excel-note">Includes: <strong>INV No, Date, Customer, GST No, Item, HSN, Unit Price, GST%, QTY, SGST, CGST, IGST, Total</strong> — one row per product</div>
                </div>
                <div>
                    <a href="?download=purchase_excel&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>"
                       class="btn-excel btn-excel-blue">
                        📊 Purchase Excel (Item-wise)
                    </a>
                    <div class="excel-note">Includes: <strong>Purchase No, Date, Supplier, GST No, Item, HSN, Unit Price, GST%, QTY, SGST, CGST, IGST, Total</strong> — one row per product</div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="stats-grid" style="margin-bottom:2rem;">
                <div class="stat-card sales-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-details">
                        <p class="stat-label">Total Sales</p>
                        <h3 class="stat-value">₹<?php echo number_format($sales_summary['total_grand'] ?? 0, 2); ?></h3>
                        <p style="font-size:12px;color:#666;"><?php echo $sales_summary['total_invoices'] ?? 0; ?> Invoices</p>
                    </div>
                </div>
                <div class="stat-card purchase-card">
                    <div class="stat-icon">🛒</div>
                    <div class="stat-details">
                        <p class="stat-label">Total Purchases</p>
                        <h3 class="stat-value">₹<?php echo number_format($purchase_summary['total_grand'] ?? 0, 2); ?></h3>
                        <p style="font-size:12px;color:#666;"><?php echo $purchase_summary['total_purchases'] ?? 0; ?> Purchases</p>
                    </div>
                </div>
                <div class="stat-card customer-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-details">
                        <p class="stat-label">Total GST Collected</p>
                        <h3 class="stat-value">₹<?php
                            echo number_format(
                                ($sales_summary['total_cgst'] ?? 0) +
                                ($sales_summary['total_sgst'] ?? 0) +
                                ($sales_summary['total_igst'] ?? 0), 2);
                        ?></h3>
                    </div>
                </div>
                <div class="stat-card product-card">
                    <div class="stat-icon">💹</div>
                    <div class="stat-details">
                        <p class="stat-label">Profit Margin</p>
                        <h3 class="stat-value">₹<?php
                            echo number_format(
                                ($sales_summary['total_sales'] ?? 0) -
                                ($purchase_summary['total_amount'] ?? 0), 2);
                        ?></h3>
                    </div>
                </div>
            </div>

            <!-- Sales Report Table -->
            <div class="table-section">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h3 class="section-title" style="margin:0;">
                        💰 Sales Report
                        <span style="font-size:13px;color:#64748b;font-weight:400;">
                            (<?php echo date('d M Y', strtotime($start_date)); ?> to <?php echo date('d M Y', strtotime($end_date)); ?>)
                        </span>
                    </h3>
                    <a href="invoices.php" class="btn btn-secondary" style="font-size:13px;">🧾 View All Invoices →</a>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Invoice No</th><th>Date</th><th>Customer</th>
                                <th>Amount</th><th>CGST</th><th>SGST</th><th>IGST</th><th>Grand Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($sales_report)): ?>
                            <tr><td colspan="8" class="no-data">No sales found in this period</td></tr>
                            <?php else: foreach($sales_report as $sale): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($sale['invoice_no']); ?></strong></td>
                                <td><?php echo date('d M Y', strtotime($sale['sale_date'])); ?></td>
                                <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                                <td>₹<?php echo number_format($sale['total_amount'], 2); ?></td>
                                <td>₹<?php echo number_format($sale['cgst_amount'], 2); ?></td>
                                <td>₹<?php echo number_format($sale['sgst_amount'], 2); ?></td>
                                <td>₹<?php echo number_format($sale['igst_amount'], 2); ?></td>
                                <td><strong>₹<?php echo number_format($sale['grand_total'], 2); ?></strong></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Purchase Report Table -->
            <div class="table-section" style="margin-top:2rem;">
                <h3 class="section-title" style="margin-bottom:16px;">
                    🛒 Purchase Report
                    <span style="font-size:13px;color:#64748b;font-weight:400;">
                        (<?php echo date('d M Y', strtotime($start_date)); ?> to <?php echo date('d M Y', strtotime($end_date)); ?>)
                    </span>
                </h3>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Purchase No</th><th>Date</th><th>Supplier</th>
                                <th>Amount</th><th>CGST</th><th>SGST</th><th>IGST</th><th>Grand Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($purchase_report)): ?>
                            <tr><td colspan="8" class="no-data">No purchases found in this period</td></tr>
                            <?php else: foreach($purchase_report as $purchase): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($purchase['purchase_no']); ?></strong></td>
                                <td><?php echo date('d M Y', strtotime($purchase['purchase_date'])); ?></td>
                                <td><?php echo htmlspecialchars($purchase['supplier_name']); ?></td>
                                <td>₹<?php echo number_format($purchase['total_amount'], 2); ?></td>
                                <td>₹<?php echo number_format($purchase['cgst_amount'], 2); ?></td>
                                <td>₹<?php echo number_format($purchase['sgst_amount'], 2); ?></td>
                                <td>₹<?php echo number_format($purchase['igst_amount'], 2); ?></td>
                                <td><strong>₹<?php echo number_format($purchase['grand_total'], 2); ?></strong></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
function setDateRange(range) {
    const today = new Date();
    let start, end = today.toISOString().split('T')[0];
    if (range === 'today') { start = end; }
    else if (range === 'yesterday') {
        const y = new Date(today); y.setDate(y.getDate()-1);
        start = end = y.toISOString().split('T')[0];
    } else if (range === 'this_week') {
        const w = new Date(today); w.setDate(w.getDate()-w.getDay());
        start = w.toISOString().split('T')[0];
    } else if (range === 'last_week') {
        const ls = new Date(today); ls.setDate(ls.getDate()-ls.getDay()-7);
        const le = new Date(today); le.setDate(le.getDate()-le.getDay()-1);
        start = ls.toISOString().split('T')[0]; end = le.toISOString().split('T')[0];
    } else if (range === 'this_month') {
        start = today.getFullYear()+'-'+String(today.getMonth()+1).padStart(2,'0')+'-01';
    } else if (range === 'last_month') {
        const lm = new Date(today.getFullYear(), today.getMonth()-1, 1);
        const lme= new Date(today.getFullYear(), today.getMonth(), 0);
        start = lm.toISOString().split('T')[0]; end = lme.toISOString().split('T')[0];
    } else if (range === 'this_year') { start = today.getFullYear()+'-01-01'; }
    document.getElementById('start_date').value = start;
    document.getElementById('end_date').value   = end;
    document.querySelector('form').submit();
}
</script>
</body>
</html>