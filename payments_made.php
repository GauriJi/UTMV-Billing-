<?php
require_once 'auth.php';
require_once 'database.php';
$db = new Database();

// ── Handle Form Submission ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment'])) {
    try {
        $purchase_id  = (int)$_POST['purchase_id'];
        $payment_date = $_POST['payment_date'];
        $amount       = (float)$_POST['amount'];
        $payment_mode = $_POST['payment_mode'];
        $reference_no = trim($_POST['reference_no'] ?? '');
        $notes        = trim($_POST['notes'] ?? '');

        if ($purchase_id <= 0 || $amount <= 0) {
            throw new Exception("Please select a purchase and enter a valid amount.");
        }

        $db->beginTransaction();
        $db->query(
            "INSERT INTO payment_disbursements (purchase_id, payment_date, amount, payment_mode, reference_no, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$purchase_id, $payment_date, $amount, $payment_mode, $reference_no, $notes, $_SESSION['user_id']]
        );

        // Recalculate payment status for this purchase
        $purchase = $db->single("SELECT grand_total FROM purchases WHERE id = ?", [$purchase_id]);
        $paid     = $db->single("SELECT COALESCE(SUM(amount),0) AS total_paid FROM payment_disbursements WHERE purchase_id = ?", [$purchase_id]);
        $total_paid  = (float)$paid['total_paid'];
        $grand_total = (float)$purchase['grand_total'];

        if ($total_paid <= 0) {
            $new_status = 'pending';
        } elseif ($total_paid >= $grand_total) {
            $new_status = 'paid';
        } else {
            $new_status = 'partial';
        }
        $db->query("UPDATE purchases SET payment_status = ? WHERE id = ?", [$new_status, $purchase_id]);
        $db->commit();
        $_SESSION['success'] = "Payment of ₹" . number_format($amount, 2) . " recorded successfully!";
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: payments_made.php");
    exit;
}

// ── Handle Delete ──────────────────────────────────────────────────────────
if (isset($_GET['delete']) && isAdmin()) {
    $del_id = (int)$_GET['delete'];
    try {
        $db->beginTransaction();
        $rec = $db->single("SELECT purchase_id FROM payment_disbursements WHERE id = ?", [$del_id]);
        $db->query("DELETE FROM payment_disbursements WHERE id = ?", [$del_id]);
        if ($rec) {
            $purchase = $db->single("SELECT grand_total FROM purchases WHERE id = ?", [$rec['purchase_id']]);
            $paid     = $db->single("SELECT COALESCE(SUM(amount),0) AS total_paid FROM payment_disbursements WHERE purchase_id = ?", [$rec['purchase_id']]);
            $total_paid  = (float)$paid['total_paid'];
            $grand_total = (float)$purchase['grand_total'];
            $new_status  = ($total_paid <= 0) ? 'pending' : (($total_paid >= $grand_total) ? 'paid' : 'partial');
            $db->query("UPDATE purchases SET payment_status = ? WHERE id = ?", [$new_status, $rec['purchase_id']]);
        }
        $db->commit();
        $_SESSION['success'] = "Payment record deleted.";
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: payments_made.php");
    exit;
}

// ── Filters ────────────────────────────────────────────────────────────────
$filter_supplier = trim($_GET['supplier'] ?? '');
$filter_mode     = $_GET['mode'] ?? '';
$filter_status   = $_GET['status'] ?? '';
$filter_start    = $_GET['start_date'] ?? '';
$filter_end      = $_GET['end_date'] ?? '';

// Fetch all purchases for dropdown
$all_purchases = $db->fetchAll("
    SELECT p.id, p.purchase_no, p.grand_total, p.purchase_date,
           COALESCE(p.payment_status, 'pending') AS payment_status,
           COALESCE(s.supplier_name, p.supplier_name_manual, 'Unknown') AS supplier_name,
           COALESCE(SUM(pd.amount), 0) AS amount_paid
    FROM purchases p
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    LEFT JOIN payment_disbursements pd ON pd.purchase_id = p.id
    GROUP BY p.id
    ORDER BY p.payment_status ASC, p.purchase_date DESC
");

// Fetch disbursements list with filters
$where  = ["1=1"];
$params = [];
if ($filter_supplier) {
    $where[]  = "COALESCE(s.supplier_name, p.supplier_name_manual, '') LIKE ?";
    $params[] = "%$filter_supplier%";
}
if ($filter_mode) {
    $where[]  = "pd.payment_mode = ?";
    $params[] = $filter_mode;
}
if ($filter_status) {
    $where[]  = "COALESCE(p.payment_status,'pending') = ?";
    $params[] = $filter_status;
}
if ($filter_start) {
    $where[]  = "pd.payment_date >= ?";
    $params[] = $filter_start;
}
if ($filter_end) {
    $where[]  = "pd.payment_date <= ?";
    $params[] = $filter_end;
}
$whereStr = implode(' AND ', $where);

$disbursements = $db->fetchAll("
    SELECT pd.*, p.purchase_no, p.grand_total,
           COALESCE(p.payment_status,'pending') AS payment_status,
           COALESCE(s.supplier_name, p.supplier_name_manual, 'Unknown') AS supplier_name
    FROM payment_disbursements pd
    JOIN purchases p ON pd.purchase_id = p.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE $whereStr
    ORDER BY pd.payment_date DESC, pd.id DESC
", $params);

// Summary stats
$today       = date('Y-m-d');
$month_start = date('Y-m-01');
$today_total = $db->single("SELECT COALESCE(SUM(amount),0) AS t FROM payment_disbursements WHERE payment_date = ?", [$today]);
$month_total = $db->single("SELECT COALESCE(SUM(amount),0) AS t FROM payment_disbursements WHERE payment_date BETWEEN ? AND ?", [$month_start, $today]);
$outstanding = $db->single("
    SELECT COALESCE(SUM(p.grand_total - COALESCE(paid.total_paid,0)), 0) AS t
    FROM purchases p
    LEFT JOIN (SELECT purchase_id, SUM(amount) AS total_paid FROM payment_disbursements GROUP BY purchase_id) paid ON paid.purchase_id = p.id
    WHERE COALESCE(p.payment_status, 'pending') != 'paid'
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments Made - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .pay-form-card {
            background: linear-gradient(135deg, #7c3aed 0%, #dc2626 100%);
            border-radius: 16px; padding: 28px; margin-bottom: 28px;
            box-shadow: 0 8px 32px rgba(124,58,237,0.25);
        }
        .pay-form-card h3 { color: white; margin: 0 0 20px; font-size: 18px; display:flex; align-items:center; gap:10px; }
        .pay-form-grid  { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 16px; align-items: end; }
        .pay-form-grid2 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-top: 16px; align-items: end; }
        .pf-group { display: flex; flex-direction: column; gap: 6px; }
        .pf-group label { font-size: 11px; font-weight: 700; color: rgba(255,255,255,0.8); text-transform: uppercase; letter-spacing: 0.5px; }
        .pf-group input, .pf-group select {
            padding: 10px 14px; border: 2px solid rgba(255,255,255,0.2); border-radius: 10px;
            font-size: 13px; font-family: inherit; background: rgba(255,255,255,0.95); color: #1e293b;
        }
        .pf-group input:focus, .pf-group select:focus { outline:none; border-color: #fbbf24; }
        .balance-display {
            background: rgba(255,255,255,0.15); border-radius: 10px; padding: 12px 16px;
            color: white; font-size: 13px; border: 1px solid rgba(255,255,255,0.25);
        }
        .balance-display .bal-row { display:flex; justify-content:space-between; margin-bottom:4px; }
        .balance-display .bal-due { font-size:16px; font-weight:700; color:#fbbf24; margin-top:6px; }
        .btn-save-pay {
            background: linear-gradient(135deg, #fbbf24, #f59e0b); color: #1e293b;
            border: none; border-radius: 10px; padding: 10px 28px; font-weight: 700;
            font-size: 14px; cursor: pointer; transition: all 0.2s; white-space: nowrap;
        }
        .btn-save-pay:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(251,191,36,0.4); }
        .filter-bar {
            background: white; padding: 18px 20px; border-radius: 12px;
            margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;
        }
        .filter-bar .fg { display:flex; flex-direction:column; gap:4px; }
        .filter-bar label { font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; }
        .filter-bar input, .filter-bar select {
            padding: 8px 12px; border: 2px solid #e2e8f0; border-radius: 8px;
            font-size: 13px; font-family: inherit; min-width: 130px; color:#1e293b;
        }
        .stats-strip { display:flex; gap:16px; margin-bottom:24px; flex-wrap:wrap; }
        .strip-card {
            background: white; border-radius: 12px; padding: 16px 22px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08); flex:1; min-width:160px;
            border-left: 4px solid #7c3aed;
        }
        .strip-card.red    { border-color:#dc2626; }
        .strip-card.orange { border-color:#ea580c; }
        .strip-card p { margin:0; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; }
        .strip-card h3 { margin:4px 0 0; font-size:20px; color:#1e293b; font-weight:700; }
        .badge-mode { padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
        .mode-Cash   { background:#fef3c7; color:#92400e; }
        .mode-BankTransfer { background:#dbeafe; color:#1e40af; }
        .mode-UPI    { background:#d1fae5; color:#065f46; }
        .mode-Cheque { background:#ede9fe; color:#5b21b6; }
        .mode-NEFT, .mode-RTGS { background:#f0fdf4; color:#14532d; }
        .mode-Other  { background:#f1f5f9; color:#475569; }
        .status-paid    { background:#d1fae5; color:#065f46; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
        .status-pending { background:#fef3c7; color:#92400e; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
        .status-partial { background:#dbeafe; color:#1e40af; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <?php $page_title = '💸 Payments Made'; include 'topbar.php'; ?>
        <div class="content-wrapper">

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <!-- Stats Strip -->
            <div class="stats-strip">
                <div class="strip-card red">
                    <p>💸 Paid Today</p>
                    <h3>₹<?php echo number_format($today_total['t'], 2); ?></h3>
                </div>
                <div class="strip-card">
                    <p>📅 This Month</p>
                    <h3>₹<?php echo number_format($month_total['t'], 2); ?></h3>
                </div>
                <div class="strip-card orange">
                    <p>⏳ Total Payable</p>
                    <h3>₹<?php echo number_format($outstanding['t'], 2); ?></h3>
                </div>
            </div>

            <!-- Payment Entry Form -->
            <div class="pay-form-card">
                <h3>💸 Record New Payment to Supplier</h3>
                <form method="POST" id="payForm">
                    <div class="pay-form-grid">
                        <div class="pf-group">
                            <label>Select Purchase *</label>
                            <select name="purchase_id" id="purchaseSelect" required onchange="updateBalance()">
                                <option value="">— Select Purchase —</option>
                                <?php foreach ($all_purchases as $p): ?>
                                    <?php
                                        $balance = $p['grand_total'] - $p['amount_paid'];
                                        $label   = $p['purchase_no'] . ' | ' . $p['supplier_name'] . ' | Due: ₹' . number_format($balance, 2);
                                    ?>
                                    <option value="<?php echo $p['id']; ?>"
                                            data-total="<?php echo $p['grand_total']; ?>"
                                            data-paid="<?php echo $p['amount_paid']; ?>"
                                            data-balance="<?php echo $balance; ?>">
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="pf-group">
                            <label>Payment Date *</label>
                            <input type="date" name="payment_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="pf-group">
                            <label>Amount (₹) *</label>
                            <input type="number" name="amount" id="amountInput" step="0.01" min="0.01" required placeholder="0.00">
                        </div>
                        <div class="pf-group">
                            <label>Payment Mode *</label>
                            <select name="payment_mode">
                                <option>Cash</option>
                                <option>Bank Transfer</option>
                                <option>UPI</option>
                                <option>Cheque</option>
                                <option>NEFT</option>
                                <option>RTGS</option>
                                <option>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="pay-form-grid2">
                        <div class="pf-group">
                            <label>Reference / UTR / Cheque No.</label>
                            <input type="text" name="reference_no" placeholder="Optional reference number">
                        </div>
                        <div class="pf-group">
                            <label>Notes</label>
                            <input type="text" name="notes" placeholder="Optional notes">
                        </div>
                        <div style="display:flex; gap:16px; align-items:flex-end;">
                            <div class="balance-display" id="balanceCard" style="flex:1;">
                                <div style="color:rgba(255,255,255,0.7); font-size:12px;">Select purchase to see balance</div>
                            </div>
                            <button type="submit" name="save_payment" class="btn-save-pay">💾 Save Payment</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Filter Bar -->
            <form method="GET" class="filter-bar">
                <div class="fg">
                    <label>Supplier</label>
                    <input type="text" name="supplier" value="<?php echo htmlspecialchars($filter_supplier); ?>" placeholder="Supplier name...">
                </div>
                <div class="fg">
                    <label>Payment Mode</label>
                    <select name="mode">
                        <option value="">All Modes</option>
                        <?php foreach (['Cash','Bank Transfer','UPI','Cheque','NEFT','RTGS','Other'] as $m): ?>
                            <option <?php echo $filter_mode === $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fg">
                    <label>Purchase Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $filter_status==='pending'?'selected':''; ?>>Pending</option>
                        <option value="partial" <?php echo $filter_status==='partial'?'selected':''; ?>>Partial</option>
                        <option value="paid"    <?php echo $filter_status==='paid'?'selected':''; ?>>Paid</option>
                    </select>
                </div>
                <div class="fg">
                    <label>From Date</label>
                    <input type="date" name="start_date" value="<?php echo $filter_start; ?>">
                </div>
                <div class="fg">
                    <label>To Date</label>
                    <input type="date" name="end_date" value="<?php echo $filter_end; ?>">
                </div>
                <button type="submit" class="btn btn-primary">🔍 Filter</button>
                <a href="payments_made.php" class="btn btn-secondary">🔄 Reset</a>
            </form>

            <!-- Disbursements Table -->
            <div class="table-section">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                    <h3 class="section-title" style="margin:0;">📋 Payment History (<?php echo count($disbursements); ?> records)</h3>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['download'=>'csv'])); ?>" class="btn btn-secondary" style="font-size:13px;">📥 Export CSV</a>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Purchase No</th>
                                <th>Supplier</th>
                                <th>Purchase Total</th>
                                <th>Amount Paid</th>
                                <th>Mode</th>
                                <th>Reference</th>
                                <th>Status</th>
                                <?php if (isAdmin()): ?><th style="text-align:center;">Action</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($disbursements)): ?>
                            <tr><td colspan="10" class="no-data">No payment records found.</td></tr>
                            <?php else: foreach ($disbursements as $i => $d): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><?php echo date('d M Y', strtotime($d['payment_date'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($d['purchase_no']); ?></strong></td>
                                <td><?php echo htmlspecialchars($d['supplier_name']); ?></td>
                                <td>₹<?php echo number_format($d['grand_total'], 2); ?></td>
                                <td><strong style="color:#dc2626;">₹<?php echo number_format($d['amount'], 2); ?></strong></td>
                                <td><span class="badge-mode mode-<?php echo str_replace(' ','',$d['payment_mode']); ?>"><?php echo $d['payment_mode']; ?></span></td>
                                <td><?php echo htmlspecialchars($d['reference_no'] ?: '—'); ?></td>
                                <td><span class="status-<?php echo $d['payment_status']; ?>"><?php echo ucfirst($d['payment_status']); ?></span></td>
                                <?php if (isAdmin()): ?>
                                <td style="text-align:center;">
                                    <a href="payments_made.php?delete=<?php echo $d['id']; ?>"
                                       style="background:#fee2e2; display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:8px; text-decoration:none;"
                                       onclick="return confirm('Delete this payment record?')" title="Delete">🗑️</a>
                                </td>
                                <?php endif; ?>
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
function updateBalance() {
    const sel  = document.getElementById('purchaseSelect');
    const opt  = sel.options[sel.selectedIndex];
    const card = document.getElementById('balanceCard');
    const amtInput = document.getElementById('amountInput');

    if (!sel.value) {
        card.innerHTML = '<div style="color:rgba(255,255,255,0.7); font-size:12px;">Select purchase to see balance</div>';
        return;
    }
    const total   = parseFloat(opt.dataset.total   || 0);
    const paid    = parseFloat(opt.dataset.paid    || 0);
    const balance = parseFloat(opt.dataset.balance || 0);

    card.innerHTML = `
        <div class="bal-row"><span>Purchase Total:</span><span>₹${total.toFixed(2)}</span></div>
        <div class="bal-row"><span>Already Paid:</span><span>₹${paid.toFixed(2)}</span></div>
        <div class="bal-due">Balance Due: ₹${balance.toFixed(2)}</div>
    `;
    if (balance > 0) amtInput.value = balance.toFixed(2);
}
</script>
<?php
// CSV Export
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Payments_Made_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['#','Date','Purchase No','Supplier','Purchase Total','Amount Paid','Mode','Reference','Status']);
    foreach ($disbursements as $i => $d) {
        fputcsv($out, [
            $i+1,
            date('d-m-Y', strtotime($d['payment_date'])),
            $d['purchase_no'], $d['supplier_name'],
            number_format($d['grand_total'],2,'.',''),
            number_format($d['amount'],2,'.',''),
            $d['payment_mode'], $d['reference_no'] ?: '',
            ucfirst($d['payment_status'])
        ]);
    }
    fclose($out);
    exit;
}
?>
</body>
</html>
