<?php
require_once 'auth.php';
require_once 'database.php';
$db = new Database();

// ── Handle XML Export ──────────────────────────────────────────────
if (isset($_GET['export'])) {
    $type       = $_GET['export'];     // sales | purchases | both
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date   = $_GET['end_date']   ?? date('Y-m-d');
    $company    = $db->single("SELECT * FROM company_settings LIMIT 1");

    header('Content-Type: application/xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="tally_' . $type . '_' . $start_date . '_to_' . $end_date . '.xml"');

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<ENVELOPE>' . "\n";
    echo '  <HEADER><TALLYREQUEST>Import Data</TALLYREQUEST></HEADER>' . "\n";
    echo '  <BODY><IMPORTDATA><REQUESTDESC><REPORTNAME>Vouchers</REPORTNAME></REQUESTDESC><REQUESTDATA>' . "\n";

    // ── SALES vouchers ──
    if ($type === 'sales' || $type === 'both') {
        $sales = $db->fetchAll("
            SELECT s.*, COALESCE(c.customer_name, s.customer_name_manual, 'Walk-in Customer') AS customer_name,
                   c.gstin as c_gstin, c.address as c_address, c.state as c_state
            FROM sales s
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.sale_date BETWEEN ? AND ?
            ORDER BY s.sale_date ASC
        ", [$start_date, $end_date]);

        foreach ($sales as $sale) {
            $items = $db->fetchAll("SELECT * FROM sales_items WHERE sale_id = ?", [$sale['id']]);
            $customer = htmlspecialchars($sale['customer_name']);
            $date     = date('Ymd', strtotime($sale['sale_date']));
            echo '  <TALLYMESSAGE xmlns:UDF="TallyUDF">' . "\n";
            echo '    <VOUCHER VCHTYPE="Sales" ACTION="Create">' . "\n";
            echo '      <DATE>' . $date . '</DATE>' . "\n";
            echo '      <VOUCHERTYPENAME>Sales</VOUCHERTYPENAME>' . "\n";
            echo '      <VOUCHERNUMBER>' . htmlspecialchars($sale['invoice_no']) . '</VOUCHERNUMBER>' . "\n";
            echo '      <PARTYLEDGERNAME>' . $customer . '</PARTYLEDGERNAME>' . "\n";
            echo '      <NARRATION>Invoice: ' . htmlspecialchars($sale['invoice_no']) . '</NARRATION>' . "\n";
            echo '      <ALLLEDGERENTRIES.LIST>' . "\n";
            echo '        <LEDGERNAME>' . $customer . '</LEDGERNAME>' . "\n";
            echo '        <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>' . "\n";
            echo '        <AMOUNT>-' . number_format($sale['grand_total'], 2, '.', '') . '</AMOUNT>' . "\n";
            echo '      </ALLLEDGERENTRIES.LIST>' . "\n";
            foreach ($items as $item) {
                echo '      <ALLINVENTORYENTRIES.LIST>' . "\n";
                echo '        <STOCKITEMNAME>' . htmlspecialchars($item['product_name']) . '</STOCKITEMNAME>' . "\n";
                echo '        <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>' . "\n";
                echo '        <RATE>' . number_format($item['rate'], 2, '.', '') . '</RATE>' . "\n";
                echo '        <AMOUNT>' . number_format($item['total'], 2, '.', '') . '</AMOUNT>' . "\n";
                echo '        <ACTUALQTY>' . $item['quantity'] . '</ACTUALQTY>' . "\n";
                echo '        <BILLEDQTY>' . $item['quantity'] . '</BILLEDQTY>' . "\n";
                echo '      </ALLINVENTORYENTRIES.LIST>' . "\n";
            }
            if ($sale['cgst_amount'] > 0) {
                echo '      <ALLLEDGERENTRIES.LIST><LEDGERNAME>CGST</LEDGERNAME><ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE><AMOUNT>' . number_format($sale['cgst_amount'], 2, '.', '') . '</AMOUNT></ALLLEDGERENTRIES.LIST>' . "\n";
                echo '      <ALLLEDGERENTRIES.LIST><LEDGERNAME>SGST</LEDGERNAME><ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE><AMOUNT>' . number_format($sale['sgst_amount'], 2, '.', '') . '</AMOUNT></ALLLEDGERENTRIES.LIST>' . "\n";
            }
            if ($sale['igst_amount'] > 0) {
                echo '      <ALLLEDGERENTRIES.LIST><LEDGERNAME>IGST</LEDGERNAME><ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE><AMOUNT>' . number_format($sale['igst_amount'], 2, '.', '') . '</AMOUNT></ALLLEDGERENTRIES.LIST>' . "\n";
            }
            echo '    </VOUCHER>' . "\n";
            echo '  </TALLYMESSAGE>' . "\n";
        }
    }

    // ── PURCHASE vouchers ──
    if ($type === 'purchases' || $type === 'both') {
        $purchases = $db->fetchAll("
            SELECT p.*, COALESCE(s.supplier_name, p.supplier_name_manual, 'Unknown Supplier') AS supplier_name,
                   s.gstin as s_gstin, s.state as s_state
            FROM purchases p
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            WHERE p.purchase_date BETWEEN ? AND ?
            ORDER BY p.purchase_date ASC
        ", [$start_date, $end_date]);

        foreach ($purchases as $purchase) {
            $items    = $db->fetchAll("SELECT * FROM purchase_items WHERE purchase_id = ?", [$purchase['id']]);
            $supplier = htmlspecialchars($purchase['supplier_name']);
            $date     = date('Ymd', strtotime($purchase['purchase_date']));
            echo '  <TALLYMESSAGE xmlns:UDF="TallyUDF">' . "\n";
            echo '    <VOUCHER VCHTYPE="Purchase" ACTION="Create">' . "\n";
            echo '      <DATE>' . $date . '</DATE>' . "\n";
            echo '      <VOUCHERTYPENAME>Purchase</VOUCHERTYPENAME>' . "\n";
            echo '      <VOUCHERNUMBER>' . htmlspecialchars($purchase['purchase_no']) . '</VOUCHERNUMBER>' . "\n";
            echo '      <PARTYLEDGERNAME>' . $supplier . '</PARTYLEDGERNAME>' . "\n";
            echo '      <NARRATION>Purchase: ' . htmlspecialchars($purchase['purchase_no']) . '</NARRATION>' . "\n";
            echo '      <ALLLEDGERENTRIES.LIST>' . "\n";
            echo '        <LEDGERNAME>' . $supplier . '</LEDGERNAME>' . "\n";
            echo '        <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>' . "\n";
            echo '        <AMOUNT>' . number_format($purchase['grand_total'], 2, '.', '') . '</AMOUNT>' . "\n";
            echo '      </ALLLEDGERENTRIES.LIST>' . "\n";
            foreach ($items as $item) {
                echo '      <ALLINVENTORYENTRIES.LIST>' . "\n";
                echo '        <STOCKITEMNAME>' . htmlspecialchars($item['product_name']) . '</STOCKITEMNAME>' . "\n";
                echo '        <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>' . "\n";
                echo '        <RATE>' . number_format($item['rate'], 2, '.', '') . '</RATE>' . "\n";
                echo '        <AMOUNT>-' . number_format($item['total'], 2, '.', '') . '</AMOUNT>' . "\n";
                echo '        <ACTUALQTY>' . $item['quantity'] . '</ACTUALQTY>' . "\n";
                echo '        <BILLEDQTY>' . $item['quantity'] . '</BILLEDQTY>' . "\n";
                echo '      </ALLINVENTORYENTRIES.LIST>' . "\n";
            }
            if ($purchase['cgst_amount'] > 0) {
                echo '      <ALLLEDGERENTRIES.LIST><LEDGERNAME>CGST Input</LEDGERNAME><ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE><AMOUNT>-' . number_format($purchase['cgst_amount'], 2, '.', '') . '</AMOUNT></ALLLEDGERENTRIES.LIST>' . "\n";
                echo '      <ALLLEDGERENTRIES.LIST><LEDGERNAME>SGST Input</LEDGERNAME><ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE><AMOUNT>-' . number_format($purchase['sgst_amount'], 2, '.', '') . '</AMOUNT></ALLLEDGERENTRIES.LIST>' . "\n";
            }
            if ($purchase['igst_amount'] > 0) {
                echo '      <ALLLEDGERENTRIES.LIST><LEDGERNAME>IGST Input</LEDGERNAME><ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE><AMOUNT>-' . number_format($purchase['igst_amount'], 2, '.', '') . '</AMOUNT></ALLLEDGERENTRIES.LIST>' . "\n";
            }
            echo '    </VOUCHER>' . "\n";
            echo '  </TALLYMESSAGE>' . "\n";
        }
    }

    echo '  </REQUESTDATA></IMPORTDATA></BODY>' . "\n";
    echo '</ENVELOPE>';
    exit;
}

// ── Page data ─────────────────────────────────────────────────────
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date   = $_GET['end_date']   ?? date('Y-m-d');
$company    = $db->single("SELECT * FROM company_settings LIMIT 1");

$sales_count     = $db->single("SELECT COUNT(*) as c, SUM(grand_total) as t FROM sales WHERE sale_date BETWEEN ? AND ?", [$start_date, $end_date]);
$purchase_count  = $db->single("SELECT COUNT(*) as c, SUM(grand_total) as t FROM purchases WHERE purchase_date BETWEEN ? AND ?", [$start_date, $end_date]);
$recent_sales    = $db->fetchAll("SELECT s.invoice_no, s.sale_date, s.grand_total, COALESCE(c.customer_name, s.customer_name_manual, 'Walk-in') AS customer_name FROM sales s LEFT JOIN customers c ON s.customer_id = c.id WHERE s.sale_date BETWEEN ? AND ? ORDER BY s.sale_date DESC LIMIT 8", [$start_date, $end_date]);
$recent_purchases= $db->fetchAll("SELECT p.purchase_no, p.purchase_date, p.grand_total, COALESCE(s.supplier_name, p.supplier_name_manual, 'Unknown') AS supplier_name FROM purchases p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.purchase_date BETWEEN ? AND ? ORDER BY p.purchase_date DESC LIMIT 8", [$start_date, $end_date]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MBSBill - Tally Link</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .tally-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            border-radius: 16px;
            padding: 32px;
            color: white;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 24px;
        }
        .tally-hero-icon { font-size: 64px; flex-shrink: 0; }
        .tally-hero h2 { font-size: 26px; font-weight: 700; margin-bottom: 6px; }
        .tally-hero p  { color: #93c5fd; font-size: 14px; line-height: 1.6; }

        /* Date filter */
        .date-filter {
            background: white;
            border-radius: 12px;
            padding: 20px 24px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-end;
            gap: 16px;
            flex-wrap: wrap;
        }
        .date-filter .form-group { margin: 0; }
        .date-filter label { font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px; }
        .date-filter input { padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 8px; font-family: inherit; font-size: 14px; }
        .date-filter input:focus { outline: none; border-color: #2563eb; }

        /* Summary cards */
        .export-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .export-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            overflow: hidden;
            transition: all 0.2s;
        }
        .export-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.12); }
        .export-card-header {
            padding: 20px 24px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .export-card-icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 26px;
            flex-shrink: 0;
        }
        .export-card-title { font-size: 16px; font-weight: 700; color: #1e293b; }
        .export-card-sub   { font-size: 13px; color: #64748b; margin-top: 2px; }
        .export-card-stats {
            display: flex;
            gap: 0;
            border-top: 1px solid #f1f5f9;
        }
        .export-stat {
            flex: 1;
            padding: 14px 20px;
            border-right: 1px solid #f1f5f9;
            text-align: center;
        }
        .export-stat:last-child { border-right: none; }
        .export-stat-num   { font-size: 22px; font-weight: 700; color: #1e293b; font-family: 'JetBrains Mono', monospace; }
        .export-stat-label { font-size: 11px; color: #94a3b8; font-weight: 600; text-transform: uppercase; margin-top: 2px; }
        .export-card-footer { padding: 16px 20px; display: flex; gap: 10px; border-top: 1px solid #f1f5f9; flex-wrap: wrap; }

        /* Export all card */
        .export-all-card {
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            border-radius: 16px;
            padding: 24px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            box-shadow: 0 8px 20px rgba(37,99,235,0.3);
        }
        .export-all-card h3 { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
        .export-all-card p  { font-size: 13px; color: #bfdbfe; }

        /* How to import steps */
        .steps-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .steps-header {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            color: white;
            padding: 16px 24px;
            font-weight: 700;
            font-size: 16px;
        }
        .steps-body { padding: 24px; }
        .steps-list { display: flex; flex-direction: column; gap: 16px; }
        .step-item {
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }
        .step-num {
            width: 32px; height: 32px;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 14px;
            flex-shrink: 0;
        }
        .step-text { font-size: 14px; color: #334155; line-height: 1.6; padding-top: 4px; }
        .step-text strong { color: #1e293b; }

        /* Preview table */
        .preview-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .preview-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            overflow: hidden;
        }
        .preview-header {
            padding: 14px 18px;
            font-weight: 700;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .preview-table { width: 100%; border-collapse: collapse; }
        .preview-table th { padding: 10px 14px; background: #f8fafc; font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .preview-table td { padding: 10px 14px; font-size: 13px; color: #334155; border-bottom: 1px solid #f8fafc; }
        .preview-table tr:last-child td { border-bottom: none; }
        .preview-table tr:hover td { background: #f8fafc; }
        .no-data-sm { text-align: center; padding: 24px; color: #94a3b8; font-size: 13px; }

        @media (max-width: 900px) {
            .preview-section { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <?php $page_title = '🔗 Tally Link'; include 'topbar.php'; ?>

        <div class="content-wrapper">

            <!-- Hero -->
            <div class="tally-hero">
                <div class="tally-hero-icon">📊</div>
                <div>
                    <h2>Tally ERP Export</h2>
                    <p>Export your MBSBill sales and purchase data as Tally-compatible XML vouchers.<br>
                    Select a date range, preview the data, and download the XML file to import directly into Tally ERP 9 or TallyPrime.</p>
                </div>
            </div>

            <!-- Date filter -->
            <form method="GET" class="date-filter" id="filterForm">
                <div class="form-group">
                    <label>From Date</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" onchange="this.form.submit()">
                </div>
                <div class="form-group">
                    <label>To Date</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" onchange="this.form.submit()">
                </div>
                <button type="submit" class="btn btn-primary">🔍 Apply</button>
                <a href="tally.php" class="btn btn-secondary">🔄 Reset</a>
                <!-- Quick filters -->
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-left:auto;">
                    <button type="button" class="btn btn-secondary" onclick="setRange('this_month')">This Month</button>
                    <button type="button" class="btn btn-secondary" onclick="setRange('last_month')">Last Month</button>
                    <button type="button" class="btn btn-secondary" onclick="setRange('this_year')">This Year</button>
                </div>
            </form>

            <!-- Export cards -->
            <div class="export-grid">
                <!-- Sales card -->
                <div class="export-card">
                    <div class="export-card-header">
                        <div class="export-card-icon" style="background:#d1fae5;">💰</div>
                        <div>
                            <div class="export-card-title">Sales Vouchers</div>
                            <div class="export-card-sub"><?php echo date('d M Y', strtotime($start_date)); ?> → <?php echo date('d M Y', strtotime($end_date)); ?></div>
                        </div>
                    </div>
                    <div class="export-card-stats">
                        <div class="export-stat">
                            <div class="export-stat-num"><?php echo $sales_count['c'] ?? 0; ?></div>
                            <div class="export-stat-label">Invoices</div>
                        </div>
                        <div class="export-stat">
                            <div class="export-stat-num">₹<?php echo number_format($sales_count['t'] ?? 0, 0); ?></div>
                            <div class="export-stat-label">Total Value</div>
                        </div>
                    </div>
                    <div class="export-card-footer">
                        <a href="tally.php?export=sales&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>"
                           class="btn btn-success">📥 Export Sales XML</a>
                    </div>
                </div>

                <!-- Purchases card -->
                <div class="export-card">
                    <div class="export-card-header">
                        <div class="export-card-icon" style="background:#fef3c7;">🛒</div>
                        <div>
                            <div class="export-card-title">Purchase Vouchers</div>
                            <div class="export-card-sub"><?php echo date('d M Y', strtotime($start_date)); ?> → <?php echo date('d M Y', strtotime($end_date)); ?></div>
                        </div>
                    </div>
                    <div class="export-card-stats">
                        <div class="export-stat">
                            <div class="export-stat-num"><?php echo $purchase_count['c'] ?? 0; ?></div>
                            <div class="export-stat-label">Purchases</div>
                        </div>
                        <div class="export-stat">
                            <div class="export-stat-num">₹<?php echo number_format($purchase_count['t'] ?? 0, 0); ?></div>
                            <div class="export-stat-label">Total Value</div>
                        </div>
                    </div>
                    <div class="export-card-footer">
                        <a href="tally.php?export=purchases&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>"
                           class="btn btn-primary">📥 Export Purchases XML</a>
                    </div>
                </div>
            </div>

            <!-- Export Both -->
            <div class="export-all-card">
                <div>
                    <h3>📦 Export Everything Together</h3>
                    <p>Download a single XML file containing both sales and purchase vouchers for Tally import.</p>
                </div>
                <a href="tally.php?export=both&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>"
                   style="background:white; color:#1e3a8a; padding:12px 24px; border-radius:10px; font-weight:700; text-decoration:none; font-size:14px; white-space:nowrap; transition:all 0.2s; display:inline-flex; align-items:center; gap:8px;"
                   onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                    📥 Export Both (Sales + Purchases)
                </a>
            </div>

            <!-- Preview tables -->
            <div class="preview-section">
                <div class="preview-card">
                    <div class="preview-header" style="background:#f0fdf4; color:#065f46; border-bottom:1px solid #d1fae5;">
                        💰 Sales Preview (<?php echo count($recent_sales); ?> records)
                    </div>
                    <?php if (empty($recent_sales)): ?>
                        <p class="no-data-sm">No sales in this period</p>
                    <?php else: ?>
                    <table class="preview-table">
                        <thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th>Amount</th></tr></thead>
                        <tbody>
                            <?php foreach ($recent_sales as $s): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($s['invoice_no']); ?></strong></td>
                                <td><?php echo date('d M', strtotime($s['sale_date'])); ?></td>
                                <td><?php echo htmlspecialchars($s['customer_name'] ?? 'Walk-in'); ?></td>
                                <td><strong>₹<?php echo number_format($s['grand_total'], 0); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>

                <div class="preview-card">
                    <div class="preview-header" style="background:#fffbeb; color:#92400e; border-bottom:1px solid #fde68a;">
                        🛒 Purchases Preview (<?php echo count($recent_purchases); ?> records)
                    </div>
                    <?php if (empty($recent_purchases)): ?>
                        <p class="no-data-sm">No purchases in this period</p>
                    <?php else: ?>
                    <table class="preview-table">
                        <thead><tr><th>Purchase No</th><th>Date</th><th>Supplier</th><th>Amount</th></tr></thead>
                        <tbody>
                            <?php foreach ($recent_purchases as $p): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($p['purchase_no']); ?></strong></td>
                                <td><?php echo date('d M', strtotime($p['purchase_date'])); ?></td>
                                <td><?php echo htmlspecialchars($p['supplier_name'] ?? 'Unknown'); ?></td>
                                <td><strong>₹<?php echo number_format($p['grand_total'], 0); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- How to import in Tally -->
            <div class="steps-card">
                <div class="steps-header">📖 How to Import into Tally ERP / TallyPrime</div>
                <div class="steps-body">
                    <div class="steps-list">
                        <div class="step-item">
                            <div class="step-num">1</div>
                            <div class="step-text">Select your <strong>date range</strong> above and click <strong>Export XML</strong> to download the file.</div>
                        </div>
                        <div class="step-item">
                            <div class="step-num">2</div>
                            <div class="step-text">Open <strong>Tally ERP 9</strong> or <strong>TallyPrime</strong> and select your Company.</div>
                        </div>
                        <div class="step-item">
                            <div class="step-num">3</div>
                            <div class="step-text">Go to <strong>Gateway of Tally → Import Data → Vouchers</strong>.</div>
                        </div>
                        <div class="step-item">
                            <div class="step-num">4</div>
                            <div class="step-text">Browse and select the downloaded <strong>.xml file</strong>, then click <strong>Import</strong>.</div>
                        </div>
                        <div class="step-item">
                            <div class="step-num">5</div>
                            <div class="step-text">Tally will import all vouchers automatically. Make sure your <strong>Ledger names</strong> (customer, supplier, CGST, SGST, IGST) match exactly in Tally.</div>
                        </div>
                        <div class="step-item">
                            <div class="step-num">6</div>
                            <div class="step-text">Verify imported entries under <strong>Day Book</strong> in Tally to confirm everything is correct.</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<script src="script.js"></script>
<script>
    function setRange(range) {
        const today = new Date();
        let start, end;
        if (range === 'this_month') {
            start = new Date(today.getFullYear(), today.getMonth(), 1);
            end   = today;
        } else if (range === 'last_month') {
            start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            end   = new Date(today.getFullYear(), today.getMonth(), 0);
        } else if (range === 'this_year') {
            start = new Date(today.getFullYear(), 0, 1);
            end   = today;
        }
        const fmt = d => d.toISOString().split('T')[0];
        document.querySelector('[name=start_date]').value = fmt(start);
        document.querySelector('[name=end_date]').value   = fmt(end);
        document.getElementById('filterForm').submit();
    }
</script>
</body>
</html>