<?php
require_once 'auth.php';
require_once 'database.php';
$db = new Database();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_purchase') {
    try {
        $db->beginTransaction();

        $settings = $db->single("SELECT * FROM company_settings LIMIT 1");
        $prefix = $settings['purchase_prefix'] ?? 'PUR';

        $last = $db->single("SELECT purchase_no FROM purchases ORDER BY id DESC LIMIT 1");
        if ($last) {
            $last_num = intval(substr($last['purchase_no'], strlen($prefix)));
            $purchase_no = $prefix . str_pad($last_num + 1, 5, '0', STR_PAD_LEFT);
        } else {
            $purchase_no = $prefix . '00001';
        }

        $supp_id = !empty($_POST['supplier_id']) ? (int) $_POST['supplier_id'] : null;
        $supp_manual = (!$supp_id && !empty($_POST['supplier_name_manual']))
            ? trim($_POST['supplier_name_manual']) : null;

        $sql = "INSERT INTO purchases (
                    purchase_no, supplier_id, supplier_name_manual, purchase_date,
                    total_amount, cgst_amount, sgst_amount, igst_amount, grand_total,
                    payment_status, notes,
                    bill_address, bill_city, bill_state, bill_pincode, bill_phone, bill_gstin,
                    ship_name, ship_address, ship_city, ship_state, ship_pincode, ship_phone, ship_gstin,
                    delivery_note, buyer_order_no, dispatch_doc_no, dispatched_thru, destination,
                    created_by
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $db->query($sql, [
            $purchase_no,
            $supp_id,
            $supp_manual,
            $_POST['purchase_date'],
            $_POST['total_amount'],
            $_POST['cgst_total'],
            $_POST['sgst_total'],
            $_POST['igst_total'],
            $_POST['grand_total'],
            $_POST['payment_status'],
            $_POST['notes'],
            trim($_POST['bill_address'] ?? ''),
            trim($_POST['bill_city'] ?? ''),
            trim($_POST['bill_state'] ?? ''),
            trim($_POST['bill_pincode'] ?? ''),
            trim($_POST['bill_phone'] ?? ''),
            trim($_POST['bill_gstin'] ?? ''),
            trim($_POST['ship_name'] ?? ''),
            trim($_POST['ship_address'] ?? ''),
            trim($_POST['ship_city'] ?? ''),
            trim($_POST['ship_state'] ?? ''),
            trim($_POST['ship_pincode'] ?? ''),
            trim($_POST['ship_phone'] ?? ''),
            trim($_POST['ship_gstin'] ?? ''),
            trim($_POST['delivery_note'] ?? ''),
            trim($_POST['buyer_order_no'] ?? ''),
            trim($_POST['dispatch_doc_no'] ?? ''),
            trim($_POST['dispatched_thru'] ?? ''),
            trim($_POST['destination'] ?? ''),
            $_SESSION['user_id'] ?? null,
        ]);

        $purchase_id = $db->lastInsertId();

        $items = json_decode($_POST['items'], true);
        foreach ($items as $item) {
            $db->query(
                "INSERT INTO purchase_items (purchase_id,product_id,product_name,hsn_code,batch_no,quantity,rate,amount,gst_rate,cgst,sgst,igst,total)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $purchase_id,
                    $item['product_id'] ?: null,
                    $item['product_name'],
                    $item['hsn_code'],
                    $item['batch_no'] ?? '',
                    $item['quantity'],
                    $item['rate'],
                    $item['amount'],
                    $item['gst_rate'],
                    $item['cgst'],
                    $item['sgst'],
                    $item['igst'],
                    $item['total']
                ]
            );
            if ($item['product_id']) {
                $db->query(
                    "UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?",
                    [$item['quantity'], $item['product_id']]
                );
            }
        }

        $db->commit();
        header("Location: purchase_print.php?id=" . $purchase_id);
        exit;

    } catch (Exception $e) {
        $db->rollback();
        $error = "Error saving purchase: " . $e->getMessage();
    }
}

$suppliers = $db->fetchAll("SELECT * FROM suppliers ORDER BY supplier_name");
$products = $db->fetchAll("SELECT * FROM products ORDER BY product_name");
$company = $db->single("SELECT * FROM company_settings LIMIT 1");
$recent_purchases = $db->fetchAll("SELECT p.*, s.supplier_name FROM purchases p LEFT JOIN suppliers s ON p.supplier_id = s.id ORDER BY p.created_at DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Entry - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .product-search-container {
            position: relative;
            width: 100%;
        }

        .product-search-input {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .product-search-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
        }

        .product-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .15);
            max-height: 280px;
            overflow-y: auto;
            z-index: 1000;
            margin-top: 4px;
            display: none;
        }

        .product-suggestions.active {
            display: block;
        }

        .product-suggestion-item {
            padding: 10px 14px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            transition: background .2s;
        }

        .product-suggestion-item:hover {
            background: #f8fafc;
        }

        .product-suggestion-item:last-child {
            border-bottom: none;
        }

        .product-name {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 3px;
        }

        .product-details {
            font-size: 11px;
            color: #64748b;
        }

        .product-details span {
            margin-right: 10px;
        }

        .no-results {
            padding: 14px;
            text-align: center;
            color: #94a3b8;
            font-size: 13px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .global-search-container {
            position: relative;
        }

        #globalSuggestions {
            min-width: 280px;
            max-width: 100%;
        }

        #supp_drop {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, .12);
            max-height: 220px;
            overflow-y: auto;
            z-index: 999;
            margin-top: 3px;
            display: none;
        }

        .supp-item {
            padding: 10px 14px;
            cursor: pointer;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
            transition: background .15s;
        }

        .supp-item:hover {
            background: #f8fafc;
        }

        .supp-item:last-child {
            border-bottom: none;
        }

        .supp-manual {
            color: #64748b;
        }

        .supp-badge {
            display: none;
            margin-top: 5px;
            padding: 4px 12px;
            background: #eff6ff;
            border-radius: 6px;
            font-size: 12px;
            color: #1d4ed8;
            font-weight: 600;
        }

        .batch-input-cell {
            width: 100%;
            padding: 5px 6px;
            border: 2px solid #e2e8f0;
            border-radius: 5px;
            font-size: 12px;
            transition: border-color .2s;
        }

        .batch-input-cell:focus {
            outline: none;
            border-color: #2563eb;
        }

        #itemsTable td {
            padding: 4px 5px;
            vertical-align: middle;
        }

        #itemsTable td .form-control {
            padding: 5px 6px;
            font-size: 12px;
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
        }

        #itemsTable td .product-search-input {
            padding: 5px 6px;
            font-size: 12px;
        }

        #itemsTable td input[type=number] {
            text-align: right;
        }

        #itemsTable td strong {
            font-size: 12px;
            white-space: nowrap;
            display: block;
            text-align: right;
        }

        /* ── Mobile Responsive ── */
        @media (max-width: 768px) {
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .section-header>div {
                width: 100%;
                flex-wrap: wrap;
            }

            .global-search-container {
                width: 100% !important;
            }

            .global-search-container .product-search-input {
                width: 100% !important;
            }

            #globalSuggestions {
                min-width: unset;
                width: 100%;
            }

            .invoice-form .form-grid {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        <main class="main-content">
            <?php $page_title = 'Purchase Entry';
            include 'topbar.php'; ?>
            <div class="content-wrapper">
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div><?php
                endif; ?>
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success'];
                    unset($_SESSION['success']); ?></div><?php
                endif; ?>

                <form id="purchaseForm" method="POST" class="invoice-form">
                    <input type="hidden" name="action" value="save_purchase">

                    <!-- ── Row 1: Supplier / Date / Payment ── -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Supplier</label>
                            <div style="position:relative;">
                                <input type="text" id="supplier_search" class="form-control"
                                    placeholder="🔍 Search or type supplier name..." autocomplete="off"
                                    oninput="searchSupplier(this.value)"
                                    onblur="setTimeout(()=>{document.getElementById('supp_drop').style.display='none'},200)">
                                <div id="supp_drop"></div>
                            </div>
                            <input type="hidden" name="supplier_id" id="supplier_id">
                            <input type="hidden" name="supplier_name_manual" id="supplier_name_manual">
                            <div id="supp_badge" class="supp-badge"></div>
                        </div>
                        <div class="form-group">
                            <label for="purchase_date">Purchase Date *</label>
                            <input type="date" name="purchase_date" id="purchase_date" class="form-control"
                                value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="payment_status">Payment Status *</label>
                            <select name="payment_status" id="payment_status" class="form-control" required>
                                <option value="pending">Pending</option>
                                <option value="partial">Partial</option>
                                <option value="paid">Paid</option>
                            </select>
                        </div>
                    </div>

                    <!-- GST type for manual supplier -->
                    <div id="gst_type_row"
                        style="display:none;align-items:center;gap:12px;background:#fef9c3;border:1px solid #fbbf24;border-radius:8px;padding:12px 16px;margin-bottom:20px;">
                        <span style="font-size:18px;">⚠️</span>
                        <span style="font-weight:600;color:#92400e;">Manual supplier — choose GST type:</span>
                        <select id="gst_type_selector" class="form-control" style="width:auto;"
                            onchange="items.forEach(i=>calculateRow(i.id))">
                            <option value="cgst_sgst">CGST + SGST (Uttarakhand — Intra-state)</option>
                            <option value="igst">IGST (Outside Uttarakhand — Inter-state)</option>
                        </select>
                    </div>

                    <!-- ── Billing & Shipping ── -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                        <div style="background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:16px;">
                            <div
                                style="font-weight:700;font-size:13px;color:#1e293b;margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid #e2e8f0;">
                                🧾 Bill To (Billing Address)</div>
                            <div class="form-group" style="margin-bottom:10px;"><label
                                    style="font-size:12px;">Address</label>
                                <input type="text" name="bill_address" id="bill_address" class="form-control"
                                    placeholder="Street / Area" oninput="syncShip()">
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                                <div><label style="font-size:12px;">City</label><input type="text" name="bill_city"
                                        id="bill_city" class="form-control" placeholder="City" oninput="syncShip()">
                                </div>
                                <div><label style="font-size:12px;">State</label><input type="text" name="bill_state"
                                        id="bill_state" class="form-control" placeholder="State" oninput="syncShip()">
                                </div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                                <div><label style="font-size:12px;">Pincode</label><input type="text"
                                        name="bill_pincode" id="bill_pincode" class="form-control" placeholder="Pincode"
                                        oninput="syncShip()"></div>
                                <div><label style="font-size:12px;">Phone</label><input type="text" name="bill_phone"
                                        id="bill_phone" class="form-control" placeholder="Phone" oninput="syncShip()">
                                </div>
                            </div>
                            <div><label style="font-size:12px;">GSTIN</label><input type="text" name="bill_gstin"
                                    id="bill_gstin" class="form-control" placeholder="GSTIN/UIN" oninput="syncShip()">
                            </div>
                        </div>
                        <div style="background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;padding:16px;">
                            <div
                                style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid #bbf7d0;">
                                <span style="font-weight:700;font-size:13px;color:#14532d;">🚚 Ship To (Shipping
                                    Address)</span>
                                <label
                                    style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#166534;cursor:pointer;">
                                    <input type="checkbox" id="same_address" onchange="toggleSameAddress(this.checked)"
                                        style="width:15px;height:15px;accent-color:#16a34a;">
                                    Same as Billing
                                </label>
                            </div>
                            <div class="form-group" style="margin-bottom:10px;"><label style="font-size:12px;">Consignee
                                    Name</label><input type="text" name="ship_name" id="ship_name" class="form-control"
                                    placeholder="Name of consignee"></div>
                            <div class="form-group" style="margin-bottom:10px;"><label
                                    style="font-size:12px;">Address</label><input type="text" name="ship_address"
                                    id="ship_address" class="form-control" placeholder="Street / Area"></div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                                <div><label style="font-size:12px;">City</label><input type="text" name="ship_city"
                                        id="ship_city" class="form-control" placeholder="City"></div>
                                <div><label style="font-size:12px;">State</label><input type="text" name="ship_state"
                                        id="ship_state" class="form-control" placeholder="State"></div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                                <div><label style="font-size:12px;">Pincode</label><input type="text"
                                        name="ship_pincode" id="ship_pincode" class="form-control"
                                        placeholder="Pincode"></div>
                                <div><label style="font-size:12px;">Phone</label><input type="text" name="ship_phone"
                                        id="ship_phone" class="form-control" placeholder="Phone"></div>
                            </div>
                            <div><label style="font-size:12px;">GSTIN</label><input type="text" name="ship_gstin"
                                    id="ship_gstin" class="form-control" placeholder="GSTIN/UIN"></div>
                        </div>
                    </div>

                    <!-- ── Items ── -->
                    <div class="items-section">
                        <div class="section-header">
                            <h3>Purchase Items</h3>
                            <div style="display:flex;gap:12px;align-items:center;">
                                <div class="global-search-container" style="position:relative;flex:1;min-width:0;">
                                    <input type="text" id="globalProductSearch" class="product-search-input"
                                        placeholder="🔍 Search products to add..." autocomplete="off"
                                        oninput="globalSearchProduct(this.value)" onfocus="showGlobalSuggestions()">
                                    <div class="product-suggestions" id="globalSuggestions"></div>
                                </div>
                                <button type="button" class="btn btn-primary" onclick="addItem()">+ Add
                                    Manually</button>
                            </div>
                        </div>
                        <div class="table-container" style="overflow-x:auto;">
                            <table class="items-table" id="itemsTable" style="min-width:900px;table-layout:fixed;">
                                <colgroup>
                                    <col style="width:260px"> <!-- Product -->
                                    <col style="width:110px"> <!-- Batch -->
                                    <col style="width:100px"> <!-- HSN -->
                                    <col style="width:70px"> <!-- Qty -->
                                    <col style="width:100px"> <!-- Rate -->
                                    <col style="width:70px"> <!-- GST% -->
                                    <col style="width:100px"> <!-- Amount -->
                                    <col style="width:100px"> <!-- Total -->
                                    <col style="width:40px"> <!-- Del -->
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
                            <textarea name="notes" id="notes" class="form-control" rows="2"
                                placeholder="Additional notes..."></textarea>
                        </div>
                        <div class="totals-grid">
                            <div class="total-item"><span>Subtotal:</span><strong id="subtotal_display">₹0.00</strong>
                            </div>
                            <div class="total-item"><span>CGST:</span><strong id="cgst_display">₹0.00</strong></div>
                            <div class="total-item"><span>SGST:</span><strong id="sgst_display">₹0.00</strong></div>
                            <div class="total-item"><span>IGST:</span><strong id="igst_display">₹0.00</strong></div>
                            <div class="total-item grand-total"><span>Grand Total:</span><strong
                                    id="grand_total_display">₹0.00</strong></div>
                        </div>
                    </div>

                    <!-- ── Delivery / Dispatch Details ── -->
                    <div
                        style="background:#fefce8;border:1.5px solid #fde68a;border-radius:10px;padding:16px;margin-bottom:20px;">
                        <div
                            style="font-weight:700;font-size:13px;color:#713f12;margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid #fde68a;">
                            📦 Delivery &amp; Dispatch Details <span
                                style="font-weight:400;font-size:11px;color:#92400e;">(optional)</span>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                            <div><label style="font-size:12px;">Delivery Note No.</label><input type="text"
                                    name="delivery_note" class="form-control" placeholder="Delivery note no."></div>
                            <div><label style="font-size:12px;">Supplier's Order No.</label><input type="text"
                                    name="buyer_order_no" class="form-control" placeholder="Order number"></div>
                            <div><label style="font-size:12px;">Dispatch Doc No.</label><input type="text"
                                    name="dispatch_doc_no" class="form-control" placeholder="Dispatch doc no."></div>
                            <div><label style="font-size:12px;">Dispatched Through</label><input type="text"
                                    name="dispatched_thru" class="form-control" placeholder="e.g. DTDC, By Hand"></div>
                            <div><label style="font-size:12px;">Destination</label><input type="text" name="destination"
                                    class="form-control" placeholder="Destination city"></div>
                        </div>
                    </div>

                    <input type="hidden" name="total_amount" id="total_amount">
                    <input type="hidden" name="cgst_total" id="cgst_total">
                    <input type="hidden" name="sgst_total" id="sgst_total">
                    <input type="hidden" name="igst_total" id="igst_total">
                    <input type="hidden" name="grand_total" id="grand_total">
                    <input type="hidden" name="items" id="items_json">

                    <div class="form-actions">
                        <button type="submit" class="btn btn-success btn-lg">💾 Save &amp; Print Purchase</button>
                        <button type="button" class="btn btn-secondary btn-lg"
                            onclick="window.location.href='index.php'">Cancel</button>
                    </div>
                </form>

                <!-- Recent Purchases -->
                <div class="table-section" style="margin-top:3rem;">
                    <h3 class="section-title">Recent Purchases</h3>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Purchase No</th>
                                    <th>Supplier</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>GST</th>
                                    <th>Grand Total</th>
                                    <th>Status</th>
                                    <th style="width:130px">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_purchases)): ?>
                                    <tr>
                                        <td colspan="8" class="no-data">No purchases found</td>
                                    </tr>
                                    <?php
                                else:
                                    foreach ($recent_purchases as $p):
                                        $status_colors = ['paid' => '#dcfce7;color:#166534', 'partial' => '#fef9c3;color:#713f12', 'pending' => '#fee2e2;color:#991b1b'];
                                        $sc = $status_colors[$p['payment_status'] ?? 'pending'] ?? $status_colors['pending'];
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($p['purchase_no']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($p['supplier_name'] ?? $p['supplier_name_manual'] ?? 'N/A'); ?>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($p['purchase_date'])); ?></td>
                                            <td>₹<?php echo number_format($p['total_amount'], 2); ?></td>
                                            <td>₹<?php echo number_format($p['cgst_amount'] + $p['sgst_amount'] + $p['igst_amount'], 2); ?>
                                            </td>
                                            <td><strong>₹<?php echo number_format($p['grand_total'], 2); ?></strong></td>
                                            <td><span
                                                    style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;background:<?php echo $sc; ?>"><?php echo ucfirst($p['payment_status'] ?? 'pending'); ?></span>
                                            </td>
                                            <td style="white-space:nowrap;">
                                                <a href="edit_purchase.php?id=<?php echo $p['id']; ?>" class="btn btn-secondary"
                                                    style="padding:4px 10px;font-size:12px;margin-right:4px;">✏️ Edit</a>
                                                <a href="purchase_print.php?id=<?php echo $p['id']; ?>" target="_blank"
                                                    class="btn btn-secondary" style="padding:4px 10px;font-size:12px;">🖨️
                                                    Print</a>
                                            </td>
                                        </tr>
                                        <?php
                                    endforeach;
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const products = <?php echo json_encode($products); ?>;
        const companyState = 'Uttarakhand';
        let itemCounter = 0;
        let items = [];

        // ── Supplier search ──────────────────────────────────────────────────────────
        const supplierList = <?php echo json_encode(array_map(function ($s) {
            return [
                'id' => $s['id'],
                'name' => $s['supplier_name'],
                'gstin' => $s['gstin'] ?? '',
                'state' => $s['state'] ?? '',
                'address' => $s['address'] ?? '',
                'city' => $s['city'] ?? '',
                'pincode' => $s['pincode'] ?? '',
                'phone' => $s['phone'] ?? ''
            ];
        }, $suppliers)); ?>;

        let selectedSupplierState = null;

        function searchSupplier(val) {
            const drop = document.getElementById('supp_drop');
            const hidId = document.getElementById('supplier_id');
            const hidName = document.getElementById('supplier_name_manual');
            const badge = document.getElementById('supp_badge');

            hidId.value = '';
            hidName.value = val.trim();
            selectedSupplierState = null;
            badge.style.display = 'none';
            showGstSelector();

            if (!val.trim()) { drop.style.display = 'none'; return; }

            const matches = supplierList.filter(s => s.name.toLowerCase().includes(val.toLowerCase()));
            drop.innerHTML = '';

            const manualDiv = document.createElement('div');
            manualDiv.className = 'supp-item supp-manual';
            manualDiv.innerHTML = `✏️ Use <b>"${val}"</b> as manual supplier name`;
            manualDiv.onmousedown = () => {
                hidId.value = ''; hidName.value = val.trim();
                selectedSupplierState = null;
                document.getElementById('supplier_search').value = val.trim();
                badge.textContent = '✏️ Manual: ' + val.trim();
                badge.style.display = 'block';
                drop.style.display = 'none';
                showGstSelector();
                items.forEach(i => calculateRow(i.id));
            };
            drop.appendChild(manualDiv);

            matches.forEach(s => {
                const d = document.createElement('div');
                d.className = 'supp-item';
                const stateLabel = s.state ? ` — ${s.state}` : '';
                d.innerHTML = `🏭 <strong>${s.name}</strong><span style="color:#94a3b8;font-size:11px;">${stateLabel}${s.gstin ? ' | ' + s.gstin : ''}</span>`;
                d.onmousedown = () => {
                    hidId.value = s.id; hidName.value = '';
                    selectedSupplierState = s.state || '';
                    document.getElementById('supplier_search').value = s.name;
                    badge.textContent = '✅ ' + s.name + (s.state ? ' (' + s.state + ')' : '');
                    badge.style.display = 'block';
                    drop.style.display = 'none';
                    hideGstSelector();
                    fillBillingFromSupplier(s);
                    items.forEach(i => calculateRow(i.id));
                };
                drop.appendChild(d);
            });
            drop.style.display = 'block';
        }

        function fillBillingFromSupplier(s) {
            const map = { bill_address: s.address, bill_city: s.city, bill_state: s.state, bill_pincode: s.pincode, bill_phone: s.phone, bill_gstin: s.gstin };
            Object.entries(map).forEach(([id, val]) => { const el = document.getElementById(id); if (el) el.value = val || ''; });
            syncShip();
        }

        // ── Same-address ─────────────────────────────────────────────────────────────
        const shipFields = ['address', 'city', 'state', 'pincode', 'phone', 'gstin'];
        function toggleSameAddress(checked) {
            if (checked) {
                copyBillToShip();
                shipFields.forEach(f => { const el = document.getElementById('ship_' + f); if (el) { el.readOnly = true; el.style.background = '#e9f7ef'; } });
                const sn = document.getElementById('ship_name'); if (sn) { sn.readOnly = true; sn.style.background = '#e9f7ef'; }
            } else {
                shipFields.forEach(f => { const el = document.getElementById('ship_' + f); if (el) { el.readOnly = false; el.style.background = ''; } });
                const sn = document.getElementById('ship_name'); if (sn) { sn.readOnly = false; sn.style.background = ''; }
            }
        }
        function copyBillToShip() {
            shipFields.forEach(f => { const b = document.getElementById('bill_' + f), s = document.getElementById('ship_' + f); if (b && s) s.value = b.value; });
            const sn = document.getElementById('ship_name');
            if (sn && !sn.value) sn.value = document.getElementById('supplier_search').value || '';
        }
        function syncShip() { if (document.getElementById('same_address')?.checked) copyBillToShip(); }
        function showGstSelector() { document.getElementById('gst_type_row').style.display = 'flex'; }
        function hideGstSelector() { document.getElementById('gst_type_row').style.display = 'none'; }

        // ── Product global search ─────────────────────────────────────────────────────
        function globalSearchProduct(query) {
            const div = document.getElementById('globalSuggestions');
            if (!query) { div.classList.remove('active'); return; }
            const filtered = products.filter(p => p.product_name.toLowerCase().includes(query.toLowerCase()) || (p.hsn_code && p.hsn_code.toLowerCase().includes(query.toLowerCase())));
            if (!filtered.length) { div.innerHTML = '<div class="no-results">No products found</div>'; div.classList.add('active'); return; }
            div.innerHTML = filtered.slice(0, 10).map(p => `
        <div class="product-suggestion-item" onclick="addProductFromGlobalSearch(${p.id})">
            <div class="product-name">${p.product_name}</div>
            <div class="product-details"><span>HSN: ${p.hsn_code || 'N/A'}</span><span>Rate: ₹${parseFloat(p.rate).toFixed(2)}</span><span>GST: ${p.gst_rate}%</span><span>Stock: ${p.stock_quantity || 0}</span>${p.batch_no ? `<span>Batch: ${p.batch_no}</span>` : ''}</div>
        </div>`).join('');
            div.classList.add('active');
        }
        function showGlobalSuggestions() { const i = document.getElementById('globalProductSearch'); if (i.value.length >= 1) globalSearchProduct(i.value); }

        function addProductFromGlobalSearch(productId) {
            const p = products.find(x => x.id == productId);
            if (!p) return;
            itemCounter++;
            const row = document.createElement('tr');
            row.id = 'item_' + itemCounter;
            row.innerHTML = itemRowHTML(itemCounter, p.product_name, p.hsn_code, p.rate, p.gst_rate, p.id, p.batch_no || '');
            document.getElementById('itemsBody').appendChild(row);
            items.push({ id: itemCounter, product_id: p.id, product_name: p.product_name, hsn_code: p.hsn_code, batch_no: p.batch_no || '', quantity: 1, rate: parseFloat(p.rate), amount: 0, gst_rate: parseFloat(p.gst_rate), cgst: 0, sgst: 0, igst: 0, total: 0 });
            calculateRow(itemCounter);
            document.getElementById('globalProductSearch').value = '';
            document.getElementById('globalSuggestions').classList.remove('active');
            document.getElementById('qty_' + itemCounter).focus();
            document.getElementById('qty_' + itemCounter).select();
        }

        function itemRowHTML(id, name = '', hsn = '', rate = 0, gst = 18, pid = '', batch = '') {
            const safeName = name.replace(/"/g, '&quot;'), safeBatch = batch.replace(/"/g, '&quot;');
            return `
        <td><div class="product-search-container" id="sc_${id}">
            <input type="text" class="product-search-input" id="search_${id}" value="${safeName}" placeholder="🔍 Search..." maxlength="500" autocomplete="off" oninput="searchProduct(${id},this.value)" onfocus="showSuggestions(${id})">
            <div class="product-suggestions" id="suggestions_${id}"></div>
            <input type="hidden" id="product_id_${id}" value="${pid}">
        </div></td>
        <td><input type="text" class="batch-input-cell" id="batch_${id}" value="${safeBatch}" placeholder="Batch No." oninput="updateBatch(${id})"></td>
        <td><input type="text" class="form-control" id="hsn_${id}" value="${hsn}" placeholder="HSN"></td>
        <td><input type="number" class="form-control" id="qty_${id}" value="1" min="1" onchange="calculateRow(${id})"></td>
        <td><input type="number" class="form-control" id="rate_${id}" value="${rate}" step="0.01" onchange="calculateRow(${id})"></td>
        <td><input type="number" class="form-control" id="gst_${id}" value="${gst}" step="0.01" onchange="calculateRow(${id})"></td>
        <td><strong id="amount_${id}">₹0.00</strong></td>
        <td><strong id="total_${id}">₹0.00</strong></td>
        <td><button type="button" class="btn-remove" onclick="removeItem(${id})">✕</button></td>`;
        }

        function addItem() {
            itemCounter++;
            const row = document.createElement('tr'); row.id = 'item_' + itemCounter;
            row.innerHTML = itemRowHTML(itemCounter);
            document.getElementById('itemsBody').appendChild(row);
            items.push({ id: itemCounter, product_id: '', product_name: '', hsn_code: '', batch_no: '', quantity: 1, rate: 0, amount: 0, gst_rate: 18, cgst: 0, sgst: 0, igst: 0, total: 0 });
            document.getElementById('search_' + itemCounter).focus();
        }

        function searchProduct(itemId, query) {
            const div = document.getElementById('suggestions_' + itemId);
            if (!query) { div.classList.remove('active'); return; }
            const filtered = products.filter(p => p.product_name.toLowerCase().includes(query.toLowerCase()) || (p.hsn_code && p.hsn_code.toLowerCase().includes(query.toLowerCase())));
            if (!filtered.length) { div.innerHTML = '<div class="no-results">No products found</div>'; div.classList.add('active'); return; }
            div.innerHTML = filtered.slice(0, 10).map(p => `<div class="product-suggestion-item" onclick="selectSearchedProduct(${itemId},${p.id})"><div class="product-name">${p.product_name}</div><div class="product-details"><span>HSN:${p.hsn_code || 'N/A'}</span><span>₹${parseFloat(p.rate).toFixed(2)}</span><span>GST:${p.gst_rate}%</span>${p.batch_no ? `<span>Batch:${p.batch_no}</span>` : ''}</div></div>`).join('');
            div.classList.add('active');
        }
        function showSuggestions(id) { const i = document.getElementById('search_' + id); if (i.value.length >= 1) searchProduct(id, i.value); }

        function selectSearchedProduct(itemId, productId) {
            const p = products.find(x => x.id == productId), item = items.find(i => i.id === itemId);
            if (!p || !item) return;
            item.product_id = p.id; item.product_name = p.product_name; item.hsn_code = p.hsn_code; item.batch_no = p.batch_no || ''; item.rate = parseFloat(p.rate); item.gst_rate = parseFloat(p.gst_rate);
            document.getElementById('search_' + itemId).value = p.product_name;
            document.getElementById('product_id_' + itemId).value = p.id;
            document.getElementById('hsn_' + itemId).value = p.hsn_code;
            document.getElementById('batch_' + itemId).value = p.batch_no || '';
            document.getElementById('rate_' + itemId).value = p.rate;
            document.getElementById('gst_' + itemId).value = p.gst_rate;
            document.getElementById('suggestions_' + itemId).classList.remove('active');
            calculateRow(itemId);
        }
        function updateBatch(id) { const item = items.find(i => i.id === id); if (item) item.batch_no = document.getElementById('batch_' + id).value; }

        function calculateRow(itemId) {
            const item = items.find(i => i.id === itemId); if (!item) return;
            item.product_name = document.getElementById('search_' + itemId).value || 'Manual Entry';
            item.hsn_code = document.getElementById('hsn_' + itemId).value;
            item.batch_no = document.getElementById('batch_' + itemId).value;
            item.quantity = parseFloat(document.getElementById('qty_' + itemId).value) || 0;
            item.rate = parseFloat(document.getElementById('rate_' + itemId).value) || 0;
            item.gst_rate = parseFloat(document.getElementById('gst_' + itemId).value) || 0;
            item.amount = item.quantity * item.rate;

            let isInterState;
            if (selectedSupplierState === null) isInterState = document.getElementById('gst_type_selector').value === 'igst';
            else isInterState = selectedSupplierState.trim().toLowerCase() !== 'uttarakhand';

            if (isInterState) { item.igst = (item.amount * item.gst_rate) / 100; item.cgst = 0; item.sgst = 0; }
            else { item.cgst = (item.amount * item.gst_rate) / 200; item.sgst = (item.amount * item.gst_rate) / 200; item.igst = 0; }
            item.total = item.amount + item.cgst + item.sgst + item.igst;

            document.getElementById('amount_' + itemId).textContent = '₹' + item.amount.toFixed(2);
            document.getElementById('total_' + itemId).textContent = '₹' + item.total.toFixed(2);
            calculateTotals();
        }

        function removeItem(id) { document.getElementById('item_' + id).remove(); items = items.filter(i => i.id !== id); calculateTotals(); }

        function calculateTotals() {
            let sub = 0, cgst = 0, sgst = 0, igst = 0;
            items.forEach(i => { sub += i.amount; cgst += i.cgst; sgst += i.sgst; igst += i.igst; });
            const gt = sub + cgst + sgst + igst;
            document.getElementById('subtotal_display').textContent = '₹' + sub.toFixed(2);
            document.getElementById('cgst_display').textContent = '₹' + cgst.toFixed(2);
            document.getElementById('sgst_display').textContent = '₹' + sgst.toFixed(2);
            document.getElementById('igst_display').textContent = '₹' + igst.toFixed(2);
            document.getElementById('grand_total_display').textContent = '₹' + gt.toFixed(2);
            document.getElementById('total_amount').value = sub.toFixed(2);
            document.getElementById('cgst_total').value = cgst.toFixed(2);
            document.getElementById('sgst_total').value = sgst.toFixed(2);
            document.getElementById('igst_total').value = igst.toFixed(2);
            document.getElementById('grand_total').value = gt.toFixed(2);
        }

        document.getElementById('purchaseForm').onsubmit = function (e) {
            if (items.length === 0) { alert('Please add at least one item'); e.preventDefault(); return false; }
            document.getElementById('items_json').value = JSON.stringify(items);
        };

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.product-search-container') && !e.target.closest('.global-search-container'))
                document.querySelectorAll('.product-suggestions').forEach(d => d.classList.remove('active'));
            if (!e.target.closest('#supplier_search') && !e.target.closest('#supp_drop'))
                document.getElementById('supp_drop').style.display = 'none';
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.product-suggestions').forEach(d => d.classList.remove('active'));
                document.getElementById('supp_drop').style.display = 'none';
                document.getElementById('globalSuggestions').classList.remove('active');
                document.getElementById('globalProductSearch').value = '';
            }
        });
    </script>
</body>

</html>