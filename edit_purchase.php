<?php
require_once 'auth.php';
require_once 'database.php';
$db = new Database();

$purchase_id = (int)($_GET['id'] ?? 0);
if (!$purchase_id) {
    header('Location: purchase.php');
    exit;
}

$purchase = $db->single("SELECT p.*, s.supplier_name as s_name FROM purchases p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.id = ?", [$purchase_id]);
if (!$purchase) {
    header('Location: purchase.php');
    exit;
}

$existing_items = $db->fetchAll("SELECT * FROM purchase_items WHERE purchase_id = ?", [$purchase_id]);

// ── Handle UPDATE ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_purchase') {
    try {
        $db->beginTransaction();

        $supp_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        $supp_manual = (!$supp_id && !empty($_POST['supplier_name_manual'])) ? trim($_POST['supplier_name_manual']) : null;

        $db->query("UPDATE purchases SET
            supplier_id=?, supplier_name_manual=?, purchase_date=?,
            total_amount=?, cgst_amount=?, sgst_amount=?, igst_amount=?, grand_total=?,
            payment_status=?, notes=?,
            bill_address=?, bill_city=?, bill_state=?, bill_pincode=?, bill_phone=?, bill_gstin=?,
            ship_name=?, ship_address=?, ship_city=?, ship_state=?, ship_pincode=?, ship_phone=?, ship_gstin=?,
            delivery_note=?, buyer_order_no=?, dispatch_doc_no=?, dispatched_thru=?, destination=?
            WHERE id=?",
        [
            $supp_id, $supp_manual, $_POST['purchase_date'],
            $_POST['total_amount'], $_POST['cgst_total'], $_POST['sgst_total'],
            $_POST['igst_total'], $_POST['grand_total'],
            $_POST['payment_status'], $_POST['notes'],
            trim($_POST['bill_address'] ?? ''), trim($_POST['bill_city'] ?? ''),
            trim($_POST['bill_state'] ?? ''), trim($_POST['bill_pincode'] ?? ''),
            trim($_POST['bill_phone'] ?? ''), trim($_POST['bill_gstin'] ?? ''),
            trim($_POST['ship_name'] ?? ''), trim($_POST['ship_address'] ?? ''),
            trim($_POST['ship_city'] ?? ''), trim($_POST['ship_state'] ?? ''),
            trim($_POST['ship_pincode'] ?? ''), trim($_POST['ship_phone'] ?? ''),
            trim($_POST['ship_gstin'] ?? ''),
            trim($_POST['delivery_note'] ?? ''), trim($_POST['buyer_order_no'] ?? ''),
            trim($_POST['dispatch_doc_no'] ?? ''), trim($_POST['dispatched_thru'] ?? ''),
            trim($_POST['destination'] ?? ''),
            $purchase_id
        ]);

        // Reverse old stock, delete old items
        $old_items = $db->fetchAll("SELECT * FROM purchase_items WHERE purchase_id=?", [$purchase_id]);
        foreach ($old_items as $oi) {
            if ($oi['product_id']) {
                $db->query("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id=?", [$oi['quantity'], $oi['product_id']]);
            }
        }
        $db->query("DELETE FROM purchase_items WHERE purchase_id=?", [$purchase_id]);

        // Re-insert new items + update stock
        $new_items = json_decode($_POST['items'], true);
        foreach ($new_items as $item) {
            $db->query(
                "INSERT INTO purchase_items (purchase_id,product_id,product_name,hsn_code,batch_no,quantity,rate,amount,gst_rate,cgst,sgst,igst,total) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $purchase_id, $item['product_id'] ?: null,
                $item['product_name'], $item['hsn_code'], $item['batch_no'] ?? '',
                $item['quantity'], $item['rate'], $item['amount'],
                $item['gst_rate'], $item['cgst'], $item['sgst'], $item['igst'], $item['total']
            ]
            );
            if ($item['product_id']) {
                $db->query("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id=?", [$item['quantity'], $item['product_id']]);
            }
        }

        $db->commit();
        header("Location: purchase_print.php?id=" . $purchase_id);
        exit;

    }
    catch (Exception $e) {
        $db->rollback();
        $error = "Error updating purchase: " . $e->getMessage();
    }
}

$suppliers = $db->fetchAll("SELECT * FROM suppliers ORDER BY supplier_name");
$products = $db->fetchAll("SELECT * FROM products ORDER BY product_name");
$company = $db->single("SELECT * FROM company_settings LIMIT 1");

// Supplier display name for the search field
$supp_display = $purchase['s_name'] ?? $purchase['supplier_name_manual'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Purchase - <?php echo htmlspecialchars($purchase['purchase_no']); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .product-search-container { position:relative; width:100%; }
        .product-search-input { width:100%; padding:5px 6px; border:2px solid #e2e8f0; border-radius:6px; font-size:12px; transition:all 0.3s; }
        .product-search-input:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.1); }
        .product-suggestions { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #e2e8f0; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,.15); max-height:280px; overflow-y:auto; z-index:1000; margin-top:4px; display:none; }
        .product-suggestions.active { display:block; }
        .product-suggestion-item { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; transition:background .2s; }
        .product-suggestion-item:hover { background:#f8fafc; }
        .product-suggestion-item:last-child { border-bottom:none; }
        .product-name { font-weight:600; color:#1e293b; margin-bottom:2px; font-size:12px; }
        .product-details { font-size:10px; color:#64748b; }
        .product-details span { margin-right:8px; }
        .no-results { padding:12px; text-align:center; color:#94a3b8; font-size:12px; }
        .section-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
        .global-search-container { position:relative; }
        #globalSuggestions { min-width:380px; }
        #supp_drop { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #e2e8f0; border-radius:8px; box-shadow:0 6px 16px rgba(0,0,0,.12); max-height:220px; overflow-y:auto; z-index:999; margin-top:3px; display:none; }
        .supp-item { padding:10px 14px; cursor:pointer; font-size:13px; border-bottom:1px solid #f1f5f9; transition:background .15s; }
        .supp-item:hover { background:#f8fafc; }
        .supp-item:last-child { border-bottom:none; }
        .supp-manual { color:#64748b; }
        .supp-badge { display:none; margin-top:5px; padding:4px 12px; background:#eff6ff; border-radius:6px; font-size:12px; color:#1d4ed8; font-weight:600; }
        .batch-input-cell { width:100%; padding:5px 6px; border:2px solid #e2e8f0; border-radius:5px; font-size:12px; transition:border-color .2s; }
        .batch-input-cell:focus { outline:none; border-color:#2563eb; }
        #itemsTable td { padding:4px 5px; vertical-align:middle; }
        #itemsTable td .form-control { padding:5px 6px; font-size:12px; width:100%; min-width:0; box-sizing:border-box; }
        #itemsTable td .product-search-input { padding:5px 6px; font-size:12px; }
        #itemsTable td input[type=number] { text-align:right; }
        #itemsTable td strong { font-size:12px; white-space:nowrap; display:block; text-align:right; }
        .edit-banner { background:#fef3c7; border:1px solid #f59e0b; border-radius:8px; padding:10px 16px; margin-bottom:20px; display:flex; align-items:center; gap:10px; font-size:13px; font-weight:600; color:#92400e; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <?php $page_title = 'Edit Purchase — ' . htmlspecialchars($purchase['purchase_no']);
include 'topbar.php'; ?>
        <div class="content-wrapper">

            <div class="edit-banner">
                ✏️ Editing: <strong><?php echo htmlspecialchars($purchase['purchase_no']); ?></strong>
                &nbsp;|&nbsp;
                <a href="purchase_print.php?id=<?php echo $purchase_id; ?>" target="_blank" style="color:#1d4ed8;">🖨️ View Print</a>
                &nbsp;|&nbsp;
                <a href="purchase.php" style="color:#1d4ed8;">← Back to Purchases</a>
            </div>

            <?php if (isset($error)): ?><div class="alert alert-error"><?php echo $error; ?></div><?php
endif; ?>

            <form id="purchaseForm" method="POST" class="invoice-form">
                <input type="hidden" name="action" value="update_purchase">

                <!-- ── Supplier / Date / Payment ── -->
                <div class="form-grid">
                    <div class="form-group">
                        <label>Supplier</label>
                        <div style="position:relative;">
                            <input type="text" id="supplier_search" class="form-control"
                                   placeholder="🔍 Search or type supplier name..."
                                   autocomplete="off"
                                   value="<?php echo htmlspecialchars($supp_display); ?>"
                                   oninput="searchSupplier(this.value)"
                                   onblur="setTimeout(()=>{document.getElementById('supp_drop').style.display='none'},200)">
                            <div id="supp_drop"></div>
                        </div>
                        <input type="hidden" name="supplier_id"          id="supplier_id"          value="<?php echo $purchase['supplier_id'] ?? ''; ?>">
                        <input type="hidden" name="supplier_name_manual" id="supplier_name_manual" value="<?php echo htmlspecialchars($purchase['supplier_name_manual'] ?? ''); ?>">
                        <div id="supp_badge" class="supp-badge" style="display:<?php echo $supp_display ? 'block' : 'none'; ?>">
                            <?php echo $purchase['supplier_id'] ? '✅ ' . htmlspecialchars($supp_display) : '✏️ Manual: ' . htmlspecialchars($supp_display); ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="purchase_date">Purchase Date *</label>
                        <input type="date" name="purchase_date" id="purchase_date" class="form-control" value="<?php echo $purchase['purchase_date']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="payment_status">Payment Status *</label>
                        <select name="payment_status" id="payment_status" class="form-control" required>
                            <option value="pending"  <?php echo($purchase['payment_status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="partial"  <?php echo($purchase['payment_status'] ?? '') === 'partial' ? 'selected' : ''; ?>>Partial</option>
                            <option value="paid"     <?php echo($purchase['payment_status'] ?? '') === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        </select>
                    </div>
                </div>

                <!-- GST type for manual supplier -->
                <div id="gst_type_row" style="display:<?php echo empty($purchase['supplier_id']) ? 'flex' : 'none'; ?>;align-items:center;gap:12px;background:#fef9c3;border:1px solid #fbbf24;border-radius:8px;padding:12px 16px;margin-bottom:20px;">
                    <span style="font-size:18px;">⚠️</span>
                    <span style="font-weight:600;color:#92400e;">Manual supplier — choose GST type:</span>
                    <select id="gst_type_selector" class="form-control" style="width:auto;" onchange="items.forEach(i=>calculateRow(i.id))">
                        <option value="cgst_sgst" <?php echo $purchase['igst_amount'] == 0 ? 'selected' : ''; ?>>CGST + SGST (Uttarakhand — Intra-state)</option>
                        <option value="igst"      <?php echo $purchase['igst_amount'] > 0 ? 'selected' : ''; ?>>IGST (Outside Uttarakhand — Inter-state)</option>
                    </select>
                </div>

                <!-- ── Billing & Shipping ── -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                    <div style="background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:16px;">
                        <div style="font-weight:700;font-size:13px;color:#1e293b;margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid #e2e8f0;">🧾 Bill To (Billing Address)</div>
                        <div class="form-group" style="margin-bottom:10px;"><label style="font-size:12px;">Address</label>
                        <input type="text" name="bill_address" id="bill_address" class="form-control" value="<?php echo htmlspecialchars($purchase['bill_address'] ?? ''); ?>" placeholder="Street / Area" oninput="syncShip()"></div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                            <div><label style="font-size:12px;">City</label><input type="text" name="bill_city" id="bill_city" class="form-control" value="<?php echo htmlspecialchars($purchase['bill_city'] ?? ''); ?>" placeholder="City" oninput="syncShip()"></div>
                            <div><label style="font-size:12px;">State</label><input type="text" name="bill_state" id="bill_state" class="form-control" value="<?php echo htmlspecialchars($purchase['bill_state'] ?? ''); ?>" placeholder="State" oninput="syncShip()"></div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                            <div><label style="font-size:12px;">Pincode</label><input type="text" name="bill_pincode" id="bill_pincode" class="form-control" value="<?php echo htmlspecialchars($purchase['bill_pincode'] ?? ''); ?>" placeholder="Pincode" oninput="syncShip()"></div>
                            <div><label style="font-size:12px;">Phone</label><input type="text" name="bill_phone" id="bill_phone" class="form-control" value="<?php echo htmlspecialchars($purchase['bill_phone'] ?? ''); ?>" placeholder="Phone" oninput="syncShip()"></div>
                        </div>
                        <div><label style="font-size:12px;">GSTIN</label><input type="text" name="bill_gstin" id="bill_gstin" class="form-control" value="<?php echo htmlspecialchars($purchase['bill_gstin'] ?? ''); ?>" placeholder="GSTIN/UIN" oninput="syncShip()"></div>
                    </div>
                    <div style="background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;padding:16px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid #bbf7d0;">
                            <span style="font-weight:700;font-size:13px;color:#14532d;">🚚 Ship To (Shipping Address)</span>
                            <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#166534;cursor:pointer;">
                                <input type="checkbox" id="same_address" onchange="toggleSameAddress(this.checked)" style="width:15px;height:15px;accent-color:#16a34a;">
                                Same as Billing
                            </label>
                        </div>
                        <div class="form-group" style="margin-bottom:10px;"><label style="font-size:12px;">Consignee Name</label><input type="text" name="ship_name" id="ship_name" class="form-control" value="<?php echo htmlspecialchars($purchase['ship_name'] ?? ''); ?>" placeholder="Name of consignee"></div>
                        <div class="form-group" style="margin-bottom:10px;"><label style="font-size:12px;">Address</label><input type="text" name="ship_address" id="ship_address" class="form-control" value="<?php echo htmlspecialchars($purchase['ship_address'] ?? ''); ?>" placeholder="Street / Area"></div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                            <div><label style="font-size:12px;">City</label><input type="text" name="ship_city" id="ship_city" class="form-control" value="<?php echo htmlspecialchars($purchase['ship_city'] ?? ''); ?>" placeholder="City"></div>
                            <div><label style="font-size:12px;">State</label><input type="text" name="ship_state" id="ship_state" class="form-control" value="<?php echo htmlspecialchars($purchase['ship_state'] ?? ''); ?>" placeholder="State"></div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                            <div><label style="font-size:12px;">Pincode</label><input type="text" name="ship_pincode" id="ship_pincode" class="form-control" value="<?php echo htmlspecialchars($purchase['ship_pincode'] ?? ''); ?>" placeholder="Pincode"></div>
                            <div><label style="font-size:12px;">Phone</label><input type="text" name="ship_phone" id="ship_phone" class="form-control" value="<?php echo htmlspecialchars($purchase['ship_phone'] ?? ''); ?>" placeholder="Phone"></div>
                        </div>
                        <div><label style="font-size:12px;">GSTIN</label><input type="text" name="ship_gstin" id="ship_gstin" class="form-control" value="<?php echo htmlspecialchars($purchase['ship_gstin'] ?? ''); ?>" placeholder="GSTIN/UIN"></div>
                    </div>
                </div>


                <!-- ── Items ── -->
                <div class="items-section">
                    <div class="section-header">
                        <h3>Purchase Items</h3>
                        <div style="display:flex;gap:12px;align-items:center;">
                            <div class="global-search-container" style="position:relative;width:350px;">
                                <input type="text" id="globalProductSearch" class="product-search-input"
                                       placeholder="🔍 Search products to add..."
                                       autocomplete="off"
                                       oninput="globalSearchProduct(this.value)"
                                       onfocus="showGlobalSuggestions()">
                                <div class="product-suggestions" id="globalSuggestions"></div>
                            </div>
                            <button type="button" class="btn btn-primary" onclick="addItem()">+ Add Manually</button>
                        </div>
                    </div>
                    <div class="table-container" style="overflow-x:auto;">
                        <table class="items-table" id="itemsTable" style="min-width:900px;table-layout:fixed;">
                            <colgroup>
                                <col style="width:260px">
                                <col style="width:110px">
                                <col style="width:100px">
                                <col style="width:70px">
                                <col style="width:100px">
                                <col style="width:70px">
                                <col style="width:100px">
                                <col style="width:100px">
                                <col style="width:40px">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Product / Description</th>
                                    <th>Batch No.</th>
                                    <th>HSN Code</th>
                                    <th>Qty</th>
                                    <th>Rate</th>
                                    <th>GST %</th>
                                    <th>Amount</th>
                                    <th>Total</th>
                                    <th>Del</th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- ── Totals ── -->
                <div class="totals-section">
                    <div class="form-group full-width">
                        <label for="notes">Notes</label>
                        <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="Additional notes..."><?php echo htmlspecialchars($purchase['notes'] ?? ''); ?></textarea>
                    </div>
                    <div class="totals-grid">
                        <div class="total-item"><span>Subtotal:</span><strong id="subtotal_display">₹0.00</strong></div>
                        <div class="total-item"><span>CGST:</span><strong id="cgst_display">₹0.00</strong></div>
                        <div class="total-item"><span>SGST:</span><strong id="sgst_display">₹0.00</strong></div>
                        <div class="total-item"><span>IGST:</span><strong id="igst_display">₹0.00</strong></div>
                        <div class="total-item grand-total"><span>Grand Total:</span><strong id="grand_total_display">₹0.00</strong></div>
                    </div>
                </div>

                <!-- ── Delivery / Dispatch ── -->
                <div style="background:#fefce8;border:1.5px solid #fde68a;border-radius:10px;padding:16px;margin-bottom:20px;">
                    <div style="font-weight:700;font-size:13px;color:#713f12;margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid #fde68a;">
                        📦 Delivery &amp; Dispatch Details <span style="font-weight:400;font-size:11px;color:#92400e;">(optional)</span>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                        <div><label style="font-size:12px;">Delivery Note No.</label><input type="text" name="delivery_note" class="form-control" value="<?php echo htmlspecialchars($purchase['delivery_note'] ?? ''); ?>" placeholder="Delivery note no."></div>
                        <div><label style="font-size:12px;">Supplier's Order No.</label><input type="text" name="buyer_order_no" class="form-control" value="<?php echo htmlspecialchars($purchase['buyer_order_no'] ?? ''); ?>" placeholder="Order number"></div>
                        <div><label style="font-size:12px;">Dispatch Doc No.</label><input type="text" name="dispatch_doc_no" class="form-control" value="<?php echo htmlspecialchars($purchase['dispatch_doc_no'] ?? ''); ?>" placeholder="Dispatch doc no."></div>
                        <div><label style="font-size:12px;">Dispatched Through</label><input type="text" name="dispatched_thru" class="form-control" value="<?php echo htmlspecialchars($purchase['dispatched_thru'] ?? ''); ?>" placeholder="e.g. DTDC, By Hand"></div>
                        <div><label style="font-size:12px;">Destination</label><input type="text" name="destination" class="form-control" value="<?php echo htmlspecialchars($purchase['destination'] ?? ''); ?>" placeholder="Destination city"></div>
                    </div>
                </div>

                                <input type="hidden" name="total_amount" id="total_amount">
                <input type="hidden" name="cgst_total"   id="cgst_total">
                <input type="hidden" name="sgst_total"   id="sgst_total">
                <input type="hidden" name="igst_total"   id="igst_total">
                <input type="hidden" name="grand_total"  id="grand_total">
                <input type="hidden" name="items"        id="items_json">

                <div class="form-actions">
                    <button type="submit" class="btn btn-success btn-lg">💾 Update &amp; Print Purchase</button>
                    <a href="purchase_print.php?id=<?php echo $purchase_id; ?>" target="_blank" class="btn btn-secondary btn-lg">🖨️ Print Current</a>
                    <button type="button" class="btn btn-secondary btn-lg" onclick="window.location.href='purchase.php'">← Back</button>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
const products     = <?php echo json_encode($products); ?>;
const companyState = 'Uttarakhand';
let itemCounter = 0;
let items = [];

// ── Pre-load existing items ──────────────────────────────────────────────────
const existingItems = <?php echo json_encode($existing_items); ?>;

window.addEventListener('DOMContentLoaded', () => {
    existingItems.forEach(ei => {
        itemCounter++;
        const id = itemCounter;
        const row = document.createElement('tr');
        row.id = 'item_' + id;
        row.innerHTML = itemRowHTML(id, ei.product_name, ei.hsn_code, ei.rate, ei.gst_rate, ei.product_id || '', ei.batch_no || '');
        document.getElementById('itemsBody').appendChild(row);
        // Set quantity (itemRowHTML defaults to 1)
        document.getElementById('qty_' + id).value = ei.quantity;
        items.push({id, product_id:ei.product_id||'', product_name:ei.product_name, hsn_code:ei.hsn_code, batch_no:ei.batch_no||'',
                    quantity:parseFloat(ei.quantity), rate:parseFloat(ei.rate), amount:parseFloat(ei.amount),
                    gst_rate:parseFloat(ei.gst_rate), cgst:parseFloat(ei.cgst), sgst:parseFloat(ei.sgst), igst:parseFloat(ei.igst), total:parseFloat(ei.total)});
        calculateRow(id);
    });
});

// ── Supplier search ──────────────────────────────────────────────────────────
const supplierList = <?php echo json_encode(array_map(function ($s) {
    return ['id' => $s['id'], 'name' => $s['supplier_name'], 'gstin' => $s['gstin'] ?? '', 'state' => $s['state'] ?? '',
        'address' => $s['address'] ?? '', 'city' => $s['city'] ?? '', 'pincode' => $s['pincode'] ?? '', 'phone' => $s['phone'] ?? ''];
}, $suppliers)); ?>;

let selectedSupplierState = <?php echo json_encode($purchase['supplier_id']
    ? ($db->single("SELECT state FROM suppliers WHERE id=?", [$purchase['supplier_id']])['state'] ?? null)
    : null); ?>;

function searchSupplier(val) {
    const drop=document.getElementById('supp_drop'), hidId=document.getElementById('supplier_id'),
          hidName=document.getElementById('supplier_name_manual'), badge=document.getElementById('supp_badge');
    hidId.value=''; hidName.value=val.trim(); selectedSupplierState=null;
    badge.style.display='none'; showGstSelector();
    if (!val.trim()) { drop.style.display='none'; return; }
    const matches=supplierList.filter(s=>s.name.toLowerCase().includes(val.toLowerCase()));
    drop.innerHTML='';
    const md=document.createElement('div'); md.className='supp-item supp-manual';
    md.innerHTML=`✏️ Use <b>"${val}"</b> as manual supplier name`;
    md.onmousedown=()=>{ hidId.value=''; hidName.value=val.trim(); selectedSupplierState=null;
        document.getElementById('supplier_search').value=val.trim();
        badge.textContent='✏️ Manual: '+val.trim(); badge.style.display='block';
        drop.style.display='none'; showGstSelector(); items.forEach(i=>calculateRow(i.id)); };
    drop.appendChild(md);
    matches.forEach(s=>{
        const d=document.createElement('div'); d.className='supp-item';
        d.innerHTML=`🏭 <strong>${s.name}</strong><span style="color:#94a3b8;font-size:11px;">${s.state?' — '+s.state:''}${s.gstin?' | '+s.gstin:''}</span>`;
        d.onmousedown=()=>{ hidId.value=s.id; hidName.value=''; selectedSupplierState=s.state||'';
            document.getElementById('supplier_search').value=s.name;
            badge.textContent='✅ '+s.name+(s.state?' ('+s.state+')':''); badge.style.display='block';
            drop.style.display='none'; hideGstSelector();
            fillBillingFromSupplier(s); items.forEach(i=>calculateRow(i.id)); };
        drop.appendChild(d);
    });
    drop.style.display='block';
}
function fillBillingFromSupplier(s) {
    const map={bill_address:s.address,bill_city:s.city,bill_state:s.state,bill_pincode:s.pincode,bill_phone:s.phone,bill_gstin:s.gstin};
    Object.entries(map).forEach(([id,val])=>{ const el=document.getElementById(id); if(el) el.value=val||''; }); syncShip();
}

const shipFields=['address','city','state','pincode','phone','gstin'];
function toggleSameAddress(checked) {
    if(checked){ copyBillToShip(); shipFields.forEach(f=>{const el=document.getElementById('ship_'+f);if(el){el.readOnly=true;el.style.background='#e9f7ef';}}); const sn=document.getElementById('ship_name');if(sn){sn.readOnly=true;sn.style.background='#e9f7ef';} }
    else { shipFields.forEach(f=>{const el=document.getElementById('ship_'+f);if(el){el.readOnly=false;el.style.background='';}}); const sn=document.getElementById('ship_name');if(sn){sn.readOnly=false;sn.style.background='';} }
}
function copyBillToShip() {
    shipFields.forEach(f=>{const b=document.getElementById('bill_'+f),s=document.getElementById('ship_'+f);if(b&&s) s.value=b.value;});
    const sn=document.getElementById('ship_name'); if(sn&&!sn.value) sn.value=document.getElementById('supplier_search').value||'';
}
function syncShip() { if(document.getElementById('same_address')?.checked) copyBillToShip(); }
function showGstSelector() { document.getElementById('gst_type_row').style.display='flex'; }
function hideGstSelector() { document.getElementById('gst_type_row').style.display='none'; }

// ── Global product search ────────────────────────────────────────────────────
function globalSearchProduct(query) {
    const div=document.getElementById('globalSuggestions');
    if(!query){div.classList.remove('active');return;}
    const filtered=products.filter(p=>p.product_name.toLowerCase().includes(query.toLowerCase())||(p.hsn_code&&p.hsn_code.toLowerCase().includes(query.toLowerCase())));
    if(!filtered.length){div.innerHTML='<div class="no-results">No products found</div>';div.classList.add('active');return;}
    div.innerHTML=filtered.slice(0,10).map(p=>`<div class="product-suggestion-item" onclick="addProductFromGlobalSearch(${p.id})"><div class="product-name">${p.product_name}</div><div class="product-details"><span>HSN:${p.hsn_code||'N/A'}</span><span>₹${parseFloat(p.rate).toFixed(2)}</span><span>GST:${p.gst_rate}%</span>${p.batch_no?`<span>Batch:${p.batch_no}</span>`:''}</div></div>`).join('');
    div.classList.add('active');
}
function showGlobalSuggestions(){const i=document.getElementById('globalProductSearch');if(i.value.length>=1)globalSearchProduct(i.value);}
function addProductFromGlobalSearch(productId){
    const p=products.find(x=>x.id==productId); if(!p)return;
    itemCounter++;
    const row=document.createElement('tr'); row.id='item_'+itemCounter;
    row.innerHTML=itemRowHTML(itemCounter,p.product_name,p.hsn_code,p.rate,p.gst_rate,p.id,p.batch_no||'');
    document.getElementById('itemsBody').appendChild(row);
    items.push({id:itemCounter,product_id:p.id,product_name:p.product_name,hsn_code:p.hsn_code,batch_no:p.batch_no||'',quantity:1,rate:parseFloat(p.rate),amount:0,gst_rate:parseFloat(p.gst_rate),cgst:0,sgst:0,igst:0,total:0});
    calculateRow(itemCounter);
    document.getElementById('globalProductSearch').value='';
    document.getElementById('globalSuggestions').classList.remove('active');
    document.getElementById('qty_'+itemCounter).focus(); document.getElementById('qty_'+itemCounter).select();
}

function itemRowHTML(id,name='',hsn='',rate=0,gst=18,pid='',batch=''){
    const safeName=name.replace(/"/g,'&quot;'),safeBatch=batch.replace(/"/g,'&quot;');
    return `<td><div class="product-search-container" id="sc_${id}">
        <input type="text" class="product-search-input" id="search_${id}" value="${safeName}" placeholder="🔍 Search..." maxlength="500" autocomplete="off" oninput="searchProduct(${id},this.value)" onfocus="showSuggestions(${id})">
        <div class="product-suggestions" id="suggestions_${id}"></div>
        <input type="hidden" id="product_id_${id}" value="${pid}">
    </div></td>
    <td><input type="text" class="batch-input-cell" id="batch_${id}" value="${safeBatch}" placeholder="Batch No." oninput="updateBatch(${id})"></td>
    <td><input type="text" class="form-control" id="hsn_${id}" value="${hsn}" placeholder="HSN"></td>
    <td><input type="number" class="form-control" id="qty_${id}" value="1" min="0.001" step="any" onchange="calculateRow(${id})"></td>
    <td><input type="number" class="form-control" id="rate_${id}" value="${rate}" step="0.01" onchange="calculateRow(${id})"></td>
    <td><input type="number" class="form-control" id="gst_${id}" value="${gst}" step="0.01" onchange="calculateRow(${id})"></td>
    <td><strong id="amount_${id}">₹0.00</strong></td>
    <td><strong id="total_${id}">₹0.00</strong></td>
    <td><button type="button" class="btn-remove" onclick="removeItem(${id})">✕</button></td>`;
}
function addItem(){
    itemCounter++;
    const row=document.createElement('tr'); row.id='item_'+itemCounter;
    row.innerHTML=itemRowHTML(itemCounter);
    document.getElementById('itemsBody').appendChild(row);
    items.push({id:itemCounter,product_id:'',product_name:'',hsn_code:'',batch_no:'',quantity:1,rate:0,amount:0,gst_rate:18,cgst:0,sgst:0,igst:0,total:0});
    document.getElementById('search_'+itemCounter).focus();
}
function searchProduct(itemId,query){
    const div=document.getElementById('suggestions_'+itemId);
    if(!query){div.classList.remove('active');return;}
    const filtered=products.filter(p=>p.product_name.toLowerCase().includes(query.toLowerCase())||(p.hsn_code&&p.hsn_code.toLowerCase().includes(query.toLowerCase())));
    if(!filtered.length){div.innerHTML='<div class="no-results">No products found</div>';div.classList.add('active');return;}
    div.innerHTML=filtered.slice(0,10).map(p=>`<div class="product-suggestion-item" onclick="selectSearchedProduct(${itemId},${p.id})"><div class="product-name">${p.product_name}</div><div class="product-details"><span>HSN:${p.hsn_code||'N/A'}</span><span>₹${parseFloat(p.rate).toFixed(2)}</span><span>GST:${p.gst_rate}%</span></div></div>`).join('');
    div.classList.add('active');
}
function showSuggestions(id){const i=document.getElementById('search_'+id);if(i.value.length>=1)searchProduct(id,i.value);}
function selectSearchedProduct(itemId,productId){
    const p=products.find(x=>x.id==productId),item=items.find(i=>i.id===itemId); if(!p||!item)return;
    item.product_id=p.id; item.product_name=p.product_name; item.hsn_code=p.hsn_code; item.batch_no=p.batch_no||''; item.rate=parseFloat(p.rate); item.gst_rate=parseFloat(p.gst_rate);
    document.getElementById('search_'+itemId).value=p.product_name;
    document.getElementById('product_id_'+itemId).value=p.id;
    document.getElementById('hsn_'+itemId).value=p.hsn_code;
    document.getElementById('batch_'+itemId).value=p.batch_no||'';
    document.getElementById('rate_'+itemId).value=p.rate;
    document.getElementById('gst_'+itemId).value=p.gst_rate;
    document.getElementById('suggestions_'+itemId).classList.remove('active');
    calculateRow(itemId);
}
function updateBatch(id){const item=items.find(i=>i.id===id);if(item)item.batch_no=document.getElementById('batch_'+id).value;}
function calculateRow(itemId){
    const item=items.find(i=>i.id===itemId); if(!item)return;
    item.product_name=document.getElementById('search_'+itemId).value||'Manual Entry';
    item.hsn_code=document.getElementById('hsn_'+itemId).value;
    item.batch_no=document.getElementById('batch_'+itemId).value;
    item.quantity=parseFloat(document.getElementById('qty_'+itemId).value)||0;
    item.rate=parseFloat(document.getElementById('rate_'+itemId).value)||0;
    item.gst_rate=parseFloat(document.getElementById('gst_'+itemId).value)||0;
    item.amount=item.quantity*item.rate;
    let isInterState;
    if(selectedSupplierState===null) isInterState=document.getElementById('gst_type_selector').value==='igst';
    else isInterState=selectedSupplierState.trim().toLowerCase()!=='uttarakhand';
    if(isInterState){item.igst=(item.amount*item.gst_rate)/100;item.cgst=0;item.sgst=0;}
    else{item.cgst=(item.amount*item.gst_rate)/200;item.sgst=(item.amount*item.gst_rate)/200;item.igst=0;}
    item.total=item.amount+item.cgst+item.sgst+item.igst;
    document.getElementById('amount_'+itemId).textContent='₹'+item.amount.toFixed(2);
    document.getElementById('total_'+itemId).textContent='₹'+item.total.toFixed(2);
    calculateTotals();
}
function removeItem(id){document.getElementById('item_'+id).remove();items=items.filter(i=>i.id!==id);calculateTotals();}
function calculateTotals(){
    let sub=0,cgst=0,sgst=0,igst=0;
    items.forEach(i=>{sub+=i.amount;cgst+=i.cgst;sgst+=i.sgst;igst+=i.igst;});
    const gt=sub+cgst+sgst+igst;
    document.getElementById('subtotal_display').textContent='₹'+sub.toFixed(2);
    document.getElementById('cgst_display').textContent='₹'+cgst.toFixed(2);
    document.getElementById('sgst_display').textContent='₹'+sgst.toFixed(2);
    document.getElementById('igst_display').textContent='₹'+igst.toFixed(2);
    document.getElementById('grand_total_display').textContent='₹'+gt.toFixed(2);
    document.getElementById('total_amount').value=sub.toFixed(2);
    document.getElementById('cgst_total').value=cgst.toFixed(2);
    document.getElementById('sgst_total').value=sgst.toFixed(2);
    document.getElementById('igst_total').value=igst.toFixed(2);
    document.getElementById('grand_total').value=gt.toFixed(2);
}
document.getElementById('purchaseForm').onsubmit=function(e){
    if(items.length===0){alert('Please add at least one item');e.preventDefault();return false;}
    document.getElementById('items_json').value=JSON.stringify(items);
};
document.addEventListener('click',function(e){
    if(!e.target.closest('.product-search-container')&&!e.target.closest('.global-search-container'))
        document.querySelectorAll('.product-suggestions').forEach(d=>d.classList.remove('active'));
    if(!e.target.closest('#supplier_search')&&!e.target.closest('#supp_drop'))
        document.getElementById('supp_drop').style.display='none';
});
document.addEventListener('keydown',function(e){
    if(e.key==='Escape'){
        document.querySelectorAll('.product-suggestions').forEach(d=>d.classList.remove('active'));
        document.getElementById('supp_drop').style.display='none';
        document.getElementById('globalSuggestions').classList.remove('active');
        document.getElementById('globalProductSearch').value='';
    }
});
</script>
</body>
</html>