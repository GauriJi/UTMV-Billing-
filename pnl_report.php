<?php
require_once 'auth.php';
require_once 'database.php';
$db = new Database();

// ── Date Filters ──────────────────────────────────────────────────────────
$start_date      = $_GET['start_date']  ?? date('Y-m-01');
$end_date        = $_GET['end_date']    ?? date('Y-m-d');
$active_tab      = $_GET['tab']         ?? 'pnl';
$filter_customer = trim($_GET['customer'] ?? '');
$filter_supplier = trim($_GET['supplier'] ?? '');
$filter_status_r = $_GET['status_r'] ?? '';
$filter_status_p = $_GET['status_p'] ?? '';

// ── CSV Downloads ─────────────────────────────────────────────────────────
if (isset($_GET['download'])) {
    $dl = $_GET['download'];

    if ($dl === 'receivables') {
        $rows = $db->fetchAll("
            SELECT s.invoice_no, s.sale_date,
                   COALESCE(c.customer_name, s.customer_name_manual, 'Walk-in') AS customer_name,
                   s.grand_total,
                   COALESCE(SUM(pr.amount),0) AS amount_received,
                   s.grand_total - COALESCE(SUM(pr.amount),0) AS balance_due,
                   s.payment_status,
                   MAX(pr.receipt_date) AS last_payment_date
            FROM sales s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN payment_receipts pr ON pr.sale_id = s.id
            WHERE s.sale_date BETWEEN ? AND ?
            GROUP BY s.id
            ORDER BY s.sale_date DESC
        ", [$start_date, $end_date]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Receivables_'.date('Y-m-d').'.csv"');
        $out = fopen('php://output','w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['Invoice No','Date','Customer','Invoice Total','Received','Balance Due','Status','Last Payment']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['invoice_no'], date('d-m-Y', strtotime($r['sale_date'])), $r['customer_name'],
                number_format($r['grand_total'],2,'.',''),
                number_format($r['amount_received'],2,'.',''),
                number_format($r['balance_due'],2,'.',''),
                ucfirst($r['payment_status']),
                $r['last_payment_date'] ? date('d-m-Y', strtotime($r['last_payment_date'])) : '—'
            ]);
        }
        fclose($out); exit;
    }

    if ($dl === 'payables') {
        $rows = $db->fetchAll("
            SELECT p.purchase_no, p.purchase_date,
                   COALESCE(s.supplier_name, p.supplier_name_manual, 'Unknown') AS supplier_name,
                   p.grand_total,
                   COALESCE(SUM(pd.amount),0) AS amount_paid,
                   p.grand_total - COALESCE(SUM(pd.amount),0) AS balance_due,
                   COALESCE(p.payment_status,'pending') AS payment_status,
                   MAX(pd.payment_date) AS last_payment_date
            FROM purchases p
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            LEFT JOIN payment_disbursements pd ON pd.purchase_id = p.id
            WHERE p.purchase_date BETWEEN ? AND ?
            GROUP BY p.id
            ORDER BY p.purchase_date DESC
        ", [$start_date, $end_date]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Payables_'.date('Y-m-d').'.csv"');
        $out = fopen('php://output','w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['Purchase No','Date','Supplier','Purchase Total','Paid','Balance Due','Status','Last Payment']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['purchase_no'], date('d-m-Y', strtotime($r['purchase_date'])), $r['supplier_name'],
                number_format($r['grand_total'],2,'.',''),
                number_format($r['amount_paid'],2,'.',''),
                number_format($r['balance_due'],2,'.',''),
                ucfirst($r['payment_status']),
                $r['last_payment_date'] ? date('d-m-Y', strtotime($r['last_payment_date'])) : '—'
            ]);
        }
        fclose($out); exit;
    }

    if ($dl === 'pnl') {
        // Month-wise P&L CSV
        $rows = $db->fetchAll("
            SELECT DATE_FORMAT(s.sale_date,'%Y-%m') AS mnth,
                   SUM(s.total_amount) AS revenue,
                   SUM(s.cgst_amount+s.sgst_amount+s.igst_amount) AS gst_collected
            FROM sales s WHERE s.sale_date BETWEEN ? AND ?
            GROUP BY mnth ORDER BY mnth
        ", [$start_date, $end_date]);
        $prows = $db->fetchAll("
            SELECT DATE_FORMAT(p.purchase_date,'%Y-%m') AS mnth,
                   SUM(p.total_amount) AS cost,
                   SUM(p.cgst_amount+p.sgst_amount+p.igst_amount) AS gst_paid
            FROM purchases p WHERE p.purchase_date BETWEEN ? AND ?
            GROUP BY mnth ORDER BY mnth
        ", [$start_date, $end_date]);
        $pmap = [];
        foreach ($prows as $p) $pmap[$p['mnth']] = $p;
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="PnL_Report_'.date('Y-m-d').'.csv"');
        $out = fopen('php://output','w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['Month','Revenue','Purchase Cost','Gross Profit','GST Collected','GST Paid']);
        foreach ($rows as $r) {
            $cost = $pmap[$r['mnth']]['cost'] ?? 0;
            $gpaid = $pmap[$r['mnth']]['gst_paid'] ?? 0;
            fputcsv($out, [
                date('M Y', strtotime($r['mnth'].'-01')),
                number_format($r['revenue'],2,'.',''),
                number_format($cost,2,'.',''),
                number_format($r['revenue']-$cost,2,'.',''),
                number_format($r['gst_collected'],2,'.',''),
                number_format($gpaid,2,'.','')
            ]);
        }
        fclose($out); exit;
    }
}

// ── P&L Summary Data ──────────────────────────────────────────────────────
$sales_sum = $db->single("
    SELECT COALESCE(SUM(total_amount),0) AS revenue,
           COALESCE(SUM(grand_total),0)  AS grand_total,
           COALESCE(SUM(cgst_amount+sgst_amount+igst_amount),0) AS gst_collected,
           COUNT(*) AS total_invoices
    FROM sales WHERE sale_date BETWEEN ? AND ?
", [$start_date, $end_date]);

$purchase_sum = $db->single("
    SELECT COALESCE(SUM(total_amount),0) AS cost,
           COALESCE(SUM(grand_total),0)  AS grand_total,
           COALESCE(SUM(cgst_amount+sgst_amount+igst_amount),0) AS gst_paid,
           COUNT(*) AS total_purchases
    FROM purchases WHERE purchase_date BETWEEN ? AND ?
", [$start_date, $end_date]);

$gross_profit  = ($sales_sum['revenue'] ?? 0) - ($purchase_sum['cost'] ?? 0);
$net_gst       = ($sales_sum['gst_collected'] ?? 0) - ($purchase_sum['gst_paid'] ?? 0);

// Month-wise breakdown
$monthly_sales = $db->fetchAll("
    SELECT DATE_FORMAT(sale_date,'%Y-%m') AS mnth, DATE_FORMAT(sale_date,'%b %Y') AS label,
           SUM(total_amount) AS revenue, SUM(cgst_amount+sgst_amount+igst_amount) AS gst_collected
    FROM sales WHERE sale_date BETWEEN ? AND ?
    GROUP BY mnth ORDER BY mnth
", [$start_date, $end_date]);

$monthly_purchases = $db->fetchAll("
    SELECT DATE_FORMAT(purchase_date,'%Y-%m') AS mnth,
           SUM(total_amount) AS cost, SUM(cgst_amount+sgst_amount+igst_amount) AS gst_paid
    FROM purchases WHERE purchase_date BETWEEN ? AND ?
    GROUP BY mnth ORDER BY mnth
", [$start_date, $end_date]);
$pmap = [];
foreach ($monthly_purchases as $p) $pmap[$p['mnth']] = $p;

// ── Receivables ───────────────────────────────────────────────────────────
$r_where  = ["s.sale_date BETWEEN ? AND ?"];
$r_params = [$start_date, $end_date];
if ($filter_customer) { $r_where[] = "COALESCE(c.customer_name, s.customer_name_manual,'') LIKE ?"; $r_params[] = "%$filter_customer%"; }
if ($filter_status_r) { $r_where[] = "s.payment_status = ?"; $r_params[] = $filter_status_r; }
$r_whereStr = implode(' AND ', $r_where);

$receivables = $db->fetchAll("
    SELECT s.id, s.invoice_no, s.sale_date, s.grand_total, s.payment_status,
           COALESCE(c.customer_name, s.customer_name_manual, 'Walk-in') AS customer_name,
           COALESCE(SUM(pr.amount),0)                  AS amount_received,
           s.grand_total - COALESCE(SUM(pr.amount),0)  AS balance_due,
           MAX(pr.receipt_date)                         AS last_payment_date
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN payment_receipts pr ON pr.sale_id = s.id
    WHERE $r_whereStr
    GROUP BY s.id
    ORDER BY balance_due DESC, s.sale_date DESC
", $r_params);

$recv_summary = $db->single("
    SELECT COALESCE(SUM(s.grand_total),0) AS total_invoiced,
           COALESCE(SUM(pr_totals.total_received),0) AS total_received,
           COALESCE(SUM(s.grand_total - COALESCE(pr_totals.total_received,0)),0) AS total_outstanding
    FROM sales s
    LEFT JOIN (SELECT sale_id, SUM(amount) AS total_received FROM payment_receipts GROUP BY sale_id) pr_totals ON pr_totals.sale_id = s.id
    WHERE s.sale_date BETWEEN ? AND ?
", [$start_date, $end_date]);

// ── Payables ──────────────────────────────────────────────────────────────
$p_where  = ["p.purchase_date BETWEEN ? AND ?"];
$p_params = [$start_date, $end_date];
if ($filter_supplier) { $p_where[] = "COALESCE(s.supplier_name, p.supplier_name_manual,'') LIKE ?"; $p_params[] = "%$filter_supplier%"; }
if ($filter_status_p) { $p_where[] = "COALESCE(p.payment_status,'pending') = ?"; $p_params[] = $filter_status_p; }
$p_whereStr = implode(' AND ', $p_where);

$payables = $db->fetchAll("
    SELECT p.id, p.purchase_no, p.purchase_date, p.grand_total,
           COALESCE(p.payment_status,'pending') AS payment_status,
           COALESCE(s.supplier_name, p.supplier_name_manual, 'Unknown') AS supplier_name,
           COALESCE(SUM(pd.amount),0)                   AS amount_paid,
           p.grand_total - COALESCE(SUM(pd.amount),0)   AS balance_due,
           MAX(pd.payment_date)                          AS last_payment_date
    FROM purchases p
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    LEFT JOIN payment_disbursements pd ON pd.purchase_id = p.id
    WHERE $p_whereStr
    GROUP BY p.id
    ORDER BY balance_due DESC, p.purchase_date DESC
", $p_params);

$pay_summary = $db->single("
    SELECT COALESCE(SUM(p.grand_total),0) AS total_purchases,
           COALESCE(SUM(pd_totals.total_paid),0) AS total_paid,
           COALESCE(SUM(p.grand_total - COALESCE(pd_totals.total_paid,0)),0) AS total_outstanding
    FROM purchases p
    LEFT JOIN (SELECT purchase_id, SUM(amount) AS total_paid FROM payment_disbursements GROUP BY purchase_id) pd_totals ON pd_totals.purchase_id = p.id
    WHERE p.purchase_date BETWEEN ? AND ?
", [$start_date, $end_date]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>P&L Report - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ── Date Filter ────────────────────────────────── */
        .date-filter-card {
            background:white; border-radius:14px; padding:22px 24px;
            margin-bottom:24px; box-shadow:0 2px 10px rgba(0,0,0,0.08);
        }
        .date-filter-card h3 { margin:0 0 16px; font-size:15px; color:#1e293b; }
        .quick-btns { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
        .qbtn { padding:6px 14px; border:2px solid #e2e8f0; background:white; border-radius:8px;
                cursor:pointer; font-size:12px; font-weight:600; color:#475569; transition:all 0.2s; }
        .qbtn:hover { border-color:#2563eb; background:#eff6ff; color:#2563eb; }
        .date-form-row { display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; }
        .date-form-row .fg { display:flex; flex-direction:column; gap:5px; }
        .date-form-row label { font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; }
        .date-form-row input { padding:8px 12px; border:2px solid #e2e8f0; border-radius:8px; font-size:13px; font-family:inherit; }
        .date-form-row input:focus { outline:none; border-color:#2563eb; }

        /* ── Tabs ────────────────────────────────────────── */
        .tabs-bar { display:flex; gap:4px; background:white; border-radius:14px; padding:6px;
                    box-shadow:0 2px 10px rgba(0,0,0,0.08); margin-bottom:24px; width:fit-content; }
        .tab-btn { padding:10px 24px; border:none; background:transparent; border-radius:10px;
                   cursor:pointer; font-size:14px; font-weight:600; color:#64748b; transition:all 0.2s; }
        .tab-btn.active { background:linear-gradient(135deg,#2563eb,#0f766e); color:white;
                          box-shadow:0 4px 12px rgba(37,99,235,0.3); }
        .tab-btn:hover:not(.active) { background:#f1f5f9; color:#1e293b; }
        .tab-panel { display:none; }
        .tab-panel.active { display:block; }

        /* ── Summary Cards ───────────────────────────────── */
        .kpi-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:16px; margin-bottom:28px; }
        .kpi-card { border-radius:14px; padding:20px; box-shadow:0 4px 16px rgba(0,0,0,0.1); color:white; position:relative; overflow:hidden; }
        .kpi-card::before { content:''; position:absolute; top:-20px; right:-20px; width:80px; height:80px;
                            border-radius:50%; background:rgba(255,255,255,0.15); }
        .kpi-card.blue   { background:linear-gradient(135deg,#2563eb,#1d4ed8); }
        .kpi-card.red    { background:linear-gradient(135deg,#dc2626,#b91c1c); }
        .kpi-card.green  { background:linear-gradient(135deg,#16a34a,#15803d); }
        .kpi-card.purple { background:linear-gradient(135deg,#7c3aed,#6d28d9); }
        .kpi-card.orange { background:linear-gradient(135deg,#ea580c,#c2410c); }
        .kpi-card.teal   { background:linear-gradient(135deg,#0f766e,#0d9488); }
        .kpi-card p { margin:0 0 4px; font-size:11px; font-weight:700; opacity:0.85; text-transform:uppercase; letter-spacing:0.5px; }
        .kpi-card h2 { margin:0; font-size:22px; font-weight:800; }
        .kpi-card span { font-size:11px; opacity:0.8; }

        /* ── P&L Table ───────────────────────────────────── */
        .pnl-table { width:100%; border-collapse:collapse; }
        .pnl-table th { background:#f8fafc; padding:12px 16px; text-align:left; font-size:12px;
                        font-weight:700; color:#64748b; text-transform:uppercase; border-bottom:2px solid #e2e8f0; }
        .pnl-table td { padding:12px 16px; border-bottom:1px solid #f1f5f9; font-size:14px; color:#1e293b; }
        .pnl-table tbody tr:hover { background:#f8fafc; }
        .pnl-table .profit-row td { font-weight:700; }
        .pnl-table .profit-pos { color:#16a34a; }
        .pnl-table .profit-neg { color:#dc2626; }
        .pnl-table tfoot td { font-weight:800; background:#1e293b; color:white; padding:14px 16px; }

        /* ── Filter Bars ─────────────────────────────────── */
        .sub-filter { background:white; border-radius:12px; padding:16px 20px;
                      margin-bottom:18px; box-shadow:0 2px 8px rgba(0,0,0,0.07);
                      display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; }
        .sub-filter .fg { display:flex; flex-direction:column; gap:4px; }
        .sub-filter label { font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; }
        .sub-filter input, .sub-filter select {
            padding:8px 12px; border:2px solid #e2e8f0; border-radius:8px;
            font-size:13px; font-family:inherit; min-width:140px; color:#1e293b; }
        .sub-filter input:focus, .sub-filter select:focus { outline:none; border-color:#2563eb; }

        /* ── Recv/Pay Summary Strip ───────────────────────── */
        .summary-strip { display:flex; gap:14px; margin-bottom:20px; flex-wrap:wrap; }
        .sum-strip-card { flex:1; min-width:150px; background:white; border-radius:12px;
                          padding:16px 18px; box-shadow:0 2px 10px rgba(0,0,0,0.07); border-top:3px solid #e2e8f0; }
        .sum-strip-card.teal   { border-color:#0f766e; }
        .sum-strip-card.green  { border-color:#16a34a; }
        .sum-strip-card.orange { border-color:#ea580c; }
        .sum-strip-card p { margin:0; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; }
        .sum-strip-card h3 { margin:4px 0 0; font-size:18px; font-weight:700; color:#1e293b; }

        /* ── Status Badges ───────────────────────────────── */
        .status-paid    { background:#d1fae5; color:#065f46; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
        .status-pending { background:#fef3c7; color:#92400e; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
        .status-partial { background:#dbeafe; color:#1e40af; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }

        .export-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 16px;
                      background:#16a34a; color:white; border:none; border-radius:8px;
                      font-weight:600; font-size:13px; cursor:pointer; text-decoration:none; transition:all 0.2s; }
        .export-btn:hover { background:#15803d; transform:translateY(-1px); }
        .export-btn.blue { background:#2563eb; }
        .export-btn.blue:hover { background:#1d4ed8; }

        .table-section-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }

        .balance-zero { color:#94a3b8; font-style:italic; }
        .balance-pos  { color:#dc2626; font-weight:700; }
        .balance-recv { color:#ea580c; font-weight:700; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <?php $page_title = '📉 Profit & Loss Report'; include 'topbar.php'; ?>
        <div class="content-wrapper">

            <!-- Date Filter -->
            <div class="date-filter-card">
                <h3>📅 Date Range Filter</h3>
                <div class="quick-btns">
                    <button class="qbtn" onclick="setRange('today')">Today</button>
                    <button class="qbtn" onclick="setRange('this_week')">This Week</button>
                    <button class="qbtn" onclick="setRange('this_month')">This Month</button>
                    <button class="qbtn" onclick="setRange('last_month')">Last Month</button>
                    <button class="qbtn" onclick="setRange('this_quarter')">This Quarter</button>
                    <button class="qbtn" onclick="setRange('this_year')">This Year</button>
                </div>
                <form method="GET" id="dateForm" class="date-form-row">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($active_tab); ?>">
                    <div class="fg">
                        <label>From</label>
                        <input type="date" name="start_date" id="sd" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="fg">
                        <label>To</label>
                        <input type="date" name="end_date" id="ed" value="<?php echo $end_date; ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">🔍 Apply</button>
                    <a href="pnl_report.php" class="btn btn-secondary">🔄 Reset</a>
                </form>
            </div>

            <!-- Tabs Navigation -->
            <div class="tabs-bar">
                <button class="tab-btn <?php echo $active_tab==='pnl'?'active':''; ?>"
                        onclick="switchTab('pnl')">📊 P&amp;L Statement</button>
                <button class="tab-btn <?php echo $active_tab==='recv'?'active':''; ?>"
                        onclick="switchTab('recv')">💳 Receivables</button>
                <button class="tab-btn <?php echo $active_tab==='pay'?'active':''; ?>"
                        onclick="switchTab('pay')">💸 Payables</button>
            </div>

            <!-- ═══════════════════════ TAB 1: P&L Statement ═══════════════════════ -->
            <div id="tab-pnl" class="tab-panel <?php echo $active_tab==='pnl'?'active':''; ?>">

                <!-- KPI Cards -->
                <div class="kpi-grid">
                    <div class="kpi-card blue">
                        <p>💰 Total Revenue</p>
                        <h2>₹<?php echo number_format($sales_sum['revenue'] ?? 0, 2); ?></h2>
                        <span><?php echo $sales_sum['total_invoices']; ?> Invoices</span>
                    </div>
                    <div class="kpi-card red">
                        <p>🛒 Purchase Cost</p>
                        <h2>₹<?php echo number_format($purchase_sum['cost'] ?? 0, 2); ?></h2>
                        <span><?php echo $purchase_sum['total_purchases']; ?> Purchases</span>
                    </div>
                    <div class="kpi-card <?php echo $gross_profit >= 0 ? 'green' : 'red'; ?>">
                        <p>📈 Gross Profit</p>
                        <h2>₹<?php echo number_format(abs($gross_profit), 2); ?></h2>
                        <span><?php echo $gross_profit >= 0 ? '▲ Profit' : '▼ Loss'; ?></span>
                    </div>
                    <div class="kpi-card purple">
                        <p>🧾 GST Collected</p>
                        <h2>₹<?php echo number_format($sales_sum['gst_collected'] ?? 0, 2); ?></h2>
                        <span>On Sales</span>
                    </div>
                    <div class="kpi-card orange">
                        <p>🧾 GST Paid</p>
                        <h2>₹<?php echo number_format($purchase_sum['gst_paid'] ?? 0, 2); ?></h2>
                        <span>On Purchases</span>
                    </div>
                    <div class="kpi-card teal">
                        <p>💹 Net GST Liability</p>
                        <h2>₹<?php echo number_format(abs($net_gst), 2); ?></h2>
                        <span><?php echo $net_gst >= 0 ? 'Payable to Govt' : 'Refund Due'; ?></span>
                    </div>
                </div>

                <!-- Month-wise P&L Table -->
                <div class="table-section">
                    <div class="table-section-header">
                        <h3 class="section-title" style="margin:0;">📅 Month-wise Breakdown</h3>
                        <a href="?tab=pnl&download=pnl&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>"
                           class="export-btn blue">📥 Export CSV</a>
                    </div>
                    <div class="table-container">
                        <table class="pnl-table">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Revenue (Excl. GST)</th>
                                    <th>Purchase Cost (Excl. GST)</th>
                                    <th>Gross Profit / (Loss)</th>
                                    <th>GST Collected</th>
                                    <th>GST Paid</th>
                                    <th>Net GST</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $t_rev=0; $t_cost=0; $t_gstc=0; $t_gstp=0;
                                if (empty($monthly_sales)): ?>
                                <tr><td colspan="7" class="no-data">No data for selected period.</td></tr>
                                <?php else: foreach ($monthly_sales as $m):
                                    $cost  = $pmap[$m['mnth']]['cost']     ?? 0;
                                    $gstp  = $pmap[$m['mnth']]['gst_paid'] ?? 0;
                                    $gp    = $m['revenue'] - $cost;
                                    $netg  = $m['gst_collected'] - $gstp;
                                    $t_rev  += $m['revenue']; $t_cost += $cost;
                                    $t_gstc += $m['gst_collected']; $t_gstp += $gstp;
                                ?>
                                <tr>
                                    <td><strong><?php echo $m['label']; ?></strong></td>
                                    <td>₹<?php echo number_format($m['revenue'],2); ?></td>
                                    <td>₹<?php echo number_format($cost,2); ?></td>
                                    <td class="profit-row <?php echo $gp>=0?'profit-pos':'profit-neg'; ?>">
                                        <?php echo $gp>=0?'▲':'▼'; ?> ₹<?php echo number_format(abs($gp),2); ?>
                                    </td>
                                    <td>₹<?php echo number_format($m['gst_collected'],2); ?></td>
                                    <td>₹<?php echo number_format($gstp,2); ?></td>
                                    <td class="<?php echo $netg>=0?'profit-neg':'profit-pos'; ?>">
                                        ₹<?php echo number_format($netg,2); ?>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                            <?php if (!empty($monthly_sales)):
                                $t_gp = $t_rev - $t_cost; $t_net = $t_gstc - $t_gstp;
                            ?>
                            <tfoot>
                                <tr>
                                    <td>TOTAL</td>
                                    <td>₹<?php echo number_format($t_rev,2); ?></td>
                                    <td>₹<?php echo number_format($t_cost,2); ?></td>
                                    <td>₹<?php echo number_format($t_gp,2); ?></td>
                                    <td>₹<?php echo number_format($t_gstc,2); ?></td>
                                    <td>₹<?php echo number_format($t_gstp,2); ?></td>
                                    <td>₹<?php echo number_format($t_net,2); ?></td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════ TAB 2: Receivables ═══════════════════════ -->
            <div id="tab-recv" class="tab-panel <?php echo $active_tab==='recv'?'active':''; ?>">

                <div class="summary-strip">
                    <div class="sum-strip-card teal">
                        <p>📋 Total Invoiced</p>
                        <h3>₹<?php echo number_format($recv_summary['total_invoiced']??0,2); ?></h3>
                    </div>
                    <div class="sum-strip-card green">
                        <p>✅ Received</p>
                        <h3>₹<?php echo number_format($recv_summary['total_received']??0,2); ?></h3>
                    </div>
                    <div class="sum-strip-card orange">
                        <p>⏳ Outstanding</p>
                        <h3>₹<?php echo number_format($recv_summary['total_outstanding']??0,2); ?></h3>
                    </div>
                </div>

                <form method="GET" class="sub-filter">
                    <input type="hidden" name="tab" value="recv">
                    <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
                    <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
                    <div class="fg">
                        <label>Customer</label>
                        <input type="text" name="customer" value="<?php echo htmlspecialchars($filter_customer); ?>" placeholder="Customer name...">
                    </div>
                    <div class="fg">
                        <label>Status</label>
                        <select name="status_r">
                            <option value="">All</option>
                            <option value="pending" <?php echo $filter_status_r==='pending'?'selected':''; ?>>Pending</option>
                            <option value="partial" <?php echo $filter_status_r==='partial'?'selected':''; ?>>Partial</option>
                            <option value="paid"    <?php echo $filter_status_r==='paid'?'selected':''; ?>>Paid</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">🔍 Filter</button>
                    <a href="pnl_report.php?tab=recv&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-secondary">🔄 Reset</a>
                    <a href="?tab=recv&download=receivables&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&customer=<?php echo urlencode($filter_customer); ?>&status_r=<?php echo $filter_status_r; ?>"
                       class="export-btn" style="margin-left:auto;">📥 Export CSV</a>
                </form>

                <div class="table-section">
                    <div class="table-container">
                        <table class="data-table">
                            <thead><tr>
                                <th>#</th><th>Invoice No</th><th>Date</th><th>Customer</th>
                                <th>Invoice Total</th><th>Received</th><th>Balance Due</th>
                                <th>Status</th><th>Last Payment</th><th>Action</th>
                            </tr></thead>
                            <tbody>
                                <?php if (empty($receivables)): ?>
                                <tr><td colspan="10" class="no-data">No records found.</td></tr>
                                <?php else: foreach ($receivables as $i => $r): ?>
                                <tr>
                                    <td><?php echo $i+1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($r['invoice_no']); ?></strong></td>
                                    <td><?php echo date('d M Y', strtotime($r['sale_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($r['customer_name']); ?></td>
                                    <td>₹<?php echo number_format($r['grand_total'],2); ?></td>
                                    <td style="color:#16a34a;font-weight:600;">₹<?php echo number_format($r['amount_received'],2); ?></td>
                                    <td>
                                        <?php if ($r['balance_due'] <= 0): ?>
                                            <span class="balance-zero">₹0.00</span>
                                        <?php else: ?>
                                            <span class="balance-recv">₹<?php echo number_format($r['balance_due'],2); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="status-<?php echo $r['payment_status']; ?>"><?php echo ucfirst($r['payment_status']); ?></span></td>
                                    <td><?php echo $r['last_payment_date'] ? date('d M Y', strtotime($r['last_payment_date'])) : '<span style="color:#94a3b8;">—</span>'; ?></td>
                                    <td>
                                        <a href="payments_received.php?sale_id=<?php echo $r['id']; ?>"
                                           style="background:#eff6ff; display:inline-flex; align-items:center; padding:4px 10px; border-radius:8px; text-decoration:none; font-size:12px; font-weight:600; color:#2563eb; gap:4px;"
                                           title="Add Payment">💳 Pay</a>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════ TAB 3: Payables ═══════════════════════ -->
            <div id="tab-pay" class="tab-panel <?php echo $active_tab==='pay'?'active':''; ?>">

                <div class="summary-strip">
                    <div class="sum-strip-card" style="border-color:#7c3aed;">
                        <p>📋 Total Purchases</p>
                        <h3>₹<?php echo number_format($pay_summary['total_purchases']??0,2); ?></h3>
                    </div>
                    <div class="sum-strip-card green">
                        <p>✅ Paid</p>
                        <h3>₹<?php echo number_format($pay_summary['total_paid']??0,2); ?></h3>
                    </div>
                    <div class="sum-strip-card orange">
                        <p>⏳ Outstanding</p>
                        <h3>₹<?php echo number_format($pay_summary['total_outstanding']??0,2); ?></h3>
                    </div>
                </div>

                <form method="GET" class="sub-filter">
                    <input type="hidden" name="tab" value="pay">
                    <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
                    <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
                    <div class="fg">
                        <label>Supplier</label>
                        <input type="text" name="supplier" value="<?php echo htmlspecialchars($filter_supplier); ?>" placeholder="Supplier name...">
                    </div>
                    <div class="fg">
                        <label>Status</label>
                        <select name="status_p">
                            <option value="">All</option>
                            <option value="pending" <?php echo $filter_status_p==='pending'?'selected':''; ?>>Pending</option>
                            <option value="partial" <?php echo $filter_status_p==='partial'?'selected':''; ?>>Partial</option>
                            <option value="paid"    <?php echo $filter_status_p==='paid'?'selected':''; ?>>Paid</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">🔍 Filter</button>
                    <a href="pnl_report.php?tab=pay&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-secondary">🔄 Reset</a>
                    <a href="?tab=pay&download=payables&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&supplier=<?php echo urlencode($filter_supplier); ?>&status_p=<?php echo $filter_status_p; ?>"
                       class="export-btn" style="margin-left:auto; background:#7c3aed;">📥 Export CSV</a>
                </form>

                <div class="table-section">
                    <div class="table-container">
                        <table class="data-table">
                            <thead><tr>
                                <th>#</th><th>Purchase No</th><th>Date</th><th>Supplier</th>
                                <th>Purchase Total</th><th>Paid</th><th>Balance Due</th>
                                <th>Status</th><th>Last Payment</th><th>Action</th>
                            </tr></thead>
                            <tbody>
                                <?php if (empty($payables)): ?>
                                <tr><td colspan="10" class="no-data">No records found.</td></tr>
                                <?php else: foreach ($payables as $i => $p): ?>
                                <tr>
                                    <td><?php echo $i+1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($p['purchase_no']); ?></strong></td>
                                    <td><?php echo date('d M Y', strtotime($p['purchase_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($p['supplier_name']); ?></td>
                                    <td>₹<?php echo number_format($p['grand_total'],2); ?></td>
                                    <td style="color:#16a34a;font-weight:600;">₹<?php echo number_format($p['amount_paid'],2); ?></td>
                                    <td>
                                        <?php if ($p['balance_due'] <= 0): ?>
                                            <span class="balance-zero">₹0.00</span>
                                        <?php else: ?>
                                            <span class="balance-pos">₹<?php echo number_format($p['balance_due'],2); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="status-<?php echo $p['payment_status']; ?>"><?php echo ucfirst($p['payment_status']); ?></span></td>
                                    <td><?php echo $p['last_payment_date'] ? date('d M Y', strtotime($p['last_payment_date'])) : '<span style="color:#94a3b8;">—</span>'; ?></td>
                                    <td>
                                        <a href="payments_made.php?purchase_id=<?php echo $p['id']; ?>"
                                           style="background:#f5f3ff; display:inline-flex; align-items:center; padding:4px 10px; border-radius:8px; text-decoration:none; font-size:12px; font-weight:600; color:#7c3aed; gap:4px;"
                                           title="Pay Supplier">💸 Pay</a>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    event.target.classList.add('active');
    document.querySelector('[name="tab"]').value = tab;
}

function setRange(r) {
    const today = new Date();
    let s, e = today.toISOString().split('T')[0];
    if (r === 'today') { s = e; }
    else if (r === 'this_week') {
        const w = new Date(today); w.setDate(w.getDate() - w.getDay());
        s = w.toISOString().split('T')[0];
    } else if (r === 'this_month') {
        s = today.getFullYear() + '-' + String(today.getMonth()+1).padStart(2,'0') + '-01';
    } else if (r === 'last_month') {
        const lm = new Date(today.getFullYear(), today.getMonth()-1, 1);
        const lme= new Date(today.getFullYear(), today.getMonth(), 0);
        s = lm.toISOString().split('T')[0]; e = lme.toISOString().split('T')[0];
    } else if (r === 'this_quarter') {
        const q = Math.floor(today.getMonth()/3);
        s = today.getFullYear() + '-' + String(q*3+1).padStart(2,'0') + '-01';
    } else if (r === 'this_year') {
        s = today.getFullYear() + '-01-01';
    }
    document.getElementById('sd').value = s;
    document.getElementById('ed').value = e;
    document.getElementById('dateForm').submit();
}
</script>
</body>
</html>
