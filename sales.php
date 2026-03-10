<?php
require_once 'auth.php';
require_once 'database.php';
$db = new Database();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_sale') {
    try {
        $db->beginTransaction();

        $settings = $db->single("SELECT * FROM company_settings LIMIT 1");
        $prefix = $settings['invoice_prefix'] ?? 'INV';

        $last_invoice = $db->single("SELECT invoice_no FROM sales ORDER BY id DESC LIMIT 1");
        if ($last_invoice) {
            $last_num = intval(substr($last_invoice['invoice_no'], strlen($prefix)));
            $invoice_no = $prefix . str_pad($last_num + 1, 5, '0', STR_PAD_LEFT);
        } else {
            $invoice_no = $prefix . '00001';
        }

        $cust_id = !empty($_POST['customer_id']) ? (int) $_POST['customer_id'] : null;
        $cust_manual = (!$cust_id && !empty($_POST['customer_name_manual']))
            ? trim($_POST['customer_name_manual']) : null;

        $sale_sql = "INSERT INTO sales (invoice_no, customer_id, customer_name_manual, sale_date, total_amount, cgst_amount, sgst_amount, igst_amount, grand_total, payment_status, notes,
                         bill_address, bill_city, bill_state, bill_pincode, bill_phone, bill_gstin,
                         ship_name, ship_address, ship_city, ship_state, ship_pincode, ship_phone, ship_gstin,
                         delivery_note, buyer_order_no, dispatch_doc_no, dispatched_thru, destination) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $db->query($sale_sql, [
            $invoice_no,
            $cust_id,
            $cust_manual,
            $_POST['sale_date'],
            $_POST['total_amount'],
            $_POST['cgst_total'],
            $_POST['sgst_total'],
            $_POST['igst_total'],
            $_POST['grand_total'],
            $_POST['payment_status'],
            $_POST['notes'],
            // billing
            trim($_POST['bill_address'] ?? ''),
            trim($_POST['bill_city'] ?? ''),
            trim($_POST['bill_state'] ?? ''),
            trim($_POST['bill_pincode'] ?? ''),
            trim($_POST['bill_phone'] ?? ''),
            trim($_POST['bill_gstin'] ?? ''),
            // shipping
            trim($_POST['ship_name'] ?? ''),
            trim($_POST['ship_address'] ?? ''),
            trim($_POST['ship_city'] ?? ''),
            trim($_POST['ship_state'] ?? ''),
            trim($_POST['ship_pincode'] ?? ''),
            trim($_POST['ship_phone'] ?? ''),
            trim($_POST['ship_gstin'] ?? ''),
            // delivery
            trim($_POST['delivery_note'] ?? ''),
            trim($_POST['buyer_order_no'] ?? ''),
            trim($_POST['dispatch_doc_no'] ?? ''),
            trim($_POST['dispatched_thru'] ?? ''),
            trim($_POST['destination'] ?? ''),
        ]);

        $sale_id = $db->lastInsertId();

        $items = json_decode($_POST['items'], true);
        foreach ($items as $item) {
            $item_sql = "INSERT INTO sales_items (sale_id, product_id, product_name, hsn_code, batch_no, quantity, rate, amount, gst_rate, cgst, sgst, igst, total)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $db->query($item_sql, [
                $sale_id,
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
            ]);
            if ($item['product_id']) {
                $db->query(
                    "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?",
                    [$item['quantity'], $item['product_id']]
                );
            }
        }

        $db->commit();
        header("Location: invoice_print.php?id=" . $sale_id);
        exit;

    } catch (Exception $e) {
        $db->rollback();
        $error = "Error saving sale: " . $e->getMessage();
    }
}

$customers = $db->fetchAll("SELECT * FROM customers ORDER BY customer_name");
$products = $db->fetchAll("SELECT * FROM products ORDER BY product_name");
$company = $db->single("SELECT * FROM company_settings LIMIT 1");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Invoice - <?php echo APP_NAME; ?></title>
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
            transition: all 0.3s ease;
        }

        .product-search-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .product-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            margin-top: 4px;
            display: none;
        }

        .product-suggestions.active {
            display: block;
        }

        .product-suggestion-item {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s ease;
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
            margin-bottom: 4px;
        }

        .product-details {
            font-size: 12px;
            color: #64748b;
        }

        .product-details span {
            margin-right: 12px;
        }

        .no-results {
            padding: 16px;
            text-align: center;
            color: #94a3b8;
            font-size: 14px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .global-search-container {
            position: relative;
        }

        .global-search-container .product-search-input {
            width: 100%;
            padding: 10px 16px;
            font-size: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
        }

        .global-search-container .product-search-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        #globalSuggestions {
            min-width: 280px;
            max-width: 100%;
        }

        #cust_drop {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
            max-height: 220px;
            overflow-y: auto;
            z-index: 999;
            margin-top: 3px;
            display: none;
        }

        .cust-item {
            padding: 10px 14px;
            cursor: pointer;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.15s;
        }

        .cust-item:hover {
            background: #f8fafc;
        }

        .cust-item:last-child {
            border-bottom: none;
        }

        .cust-manual {
            color: #64748b;
        }

        .cust-badge {
            display: none;
            margin-top: 5px;
            padding: 4px 12px;
            background: #eff6ff;
            border-radius: 6px;
            font-size: 12px;
            color: #1d4ed8;
            font-weight: 600;
        }

        .batch-col {
            width: 10%;
        }

        .batch-input-cell {
            width: 100%;
            padding: 6px 8px;
            border: 2px solid #e2e8f0;
            border-radius: 5px;
            font-size: 13px;
            transition: border-color 0.2s;
        }

        .batch-input-cell:focus {
            outline: none;
            border-color: #2563eb;
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
            <?php $page_title = 'Create Sales Invoice';
            include 'topbar.php'; ?>

            <div class="content-wrapper">
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php
                endif; ?>

                <form id="salesForm" method="POST" class="invoice-form">
                    <input type="hidden" name="action" value="save_sale">

                    <!-- ── Row 1: Customer / Date / Payment ── -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Customer</label>
                            <div style="position:relative;">
                                <input type="text" id="customer_search" class="form-control"
                                    placeholder="🔍 Search or type any name manually..." autocomplete="off"
                                    oninput="searchCustomer(this.value)"
                                    onblur="setTimeout(()=>{document.getElementById('cust_drop').style.display='none'},200)">
                                <div id="cust_drop"></div>
                            </div>
                            <input type="hidden" name="customer_id" id="customer_id">
                            <input type="hidden" name="customer_name_manual" id="customer_name_manual">
                            <div id="cust_badge" class="cust-badge"></div>
                        </div>
                        <div class="form-group">
                            <label for="sale_date">Invoice Date *</label>
                            <input type="date" name="sale_date" id="sale_date" class="form-control"
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

                    <!-- GST Type selector for manual customers -->
                    <div id="gst_type_row" style="display:none;align-items:center;gap:12px;
                     background:#fef9c3;border:1px solid #fbbf24;border-radius:8px;
                     padding:12px 16px;margin-bottom:20px;">
                        <span style="font-size:18px;">⚠️</span>
                        <span style="font-weight:600;color:#92400e;">Manual customer — choose GST type:</span>
                        <select id="gst_type_selector" class="form-control" style="width:auto;"
                            onchange="items.forEach(i=>calculateRow(i.id))">
                            <option value="cgst_sgst">CGST + SGST (Uttarakhand — Intra-state)</option>
                            <option value="igst">IGST (Outside Uttarakhand — Inter-state)</option>
                        </select>
                    </div>

                    <!-- ── Billing & Shipping Address ── -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                        <!-- BILL TO -->
                        <div style="background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:16px;">
                            <div
                                style="font-weight:700;font-size:13px;color:#1e293b;margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid #e2e8f0;">
                                🧾 Bill To (Billing Address)
                            </div>
                            <div class="form-group" style="margin-bottom:10px;">
                                <label style="font-size:12px;">Address</label>
                                <input type="text" name="bill_address" id="bill_address" class="form-control"
                                    placeholder="Street / Area" oninput="syncShip()">
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                                <div><label style="font-size:12px;">City</label>
                                    <input type="text" name="bill_city" id="bill_city" class="form-control"
                                        placeholder="City" oninput="syncShip()">
                                </div>
                                <div><label style="font-size:12px;">State</label>
                                    <input type="text" name="bill_state" id="bill_state" class="form-control"
                                        placeholder="State" oninput="syncShip()">
                                </div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                                <div><label style="font-size:12px;">Pincode</label>
                                    <input type="text" name="bill_pincode" id="bill_pincode" class="form-control"
                                        placeholder="Pincode" oninput="syncShip()">
                                </div>
                                <div><label style="font-size:12px;">Phone</label>
                                    <input type="text" name="bill_phone" id="bill_phone" class="form-control"
                                        placeholder="Phone" oninput="syncShip()">
                                </div>
                            </div>
                            <div><label style="font-size:12px;">GSTIN</label>
                                <input type="text" name="bill_gstin" id="bill_gstin" class="form-control"
                                    placeholder="GSTIN/UIN" oninput="syncShip()">
                            </div>
                        </div>
                        <!-- SHIP TO -->
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
                            <div class="form-group" style="margin-bottom:10px;">
                                <label style="font-size:12px;">Consignee Name</label>
                                <input type="text" name="ship_name" id="ship_name" class="form-control"
                                    placeholder="Name of consignee">
                            </div>
                            <div class="form-group" style="margin-bottom:10px;">
                                <label style="font-size:12px;">Address</label>
                                <input type="text" name="ship_address" id="ship_address" class="form-control"
                                    placeholder="Street / Area">
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                                <div><label style="font-size:12px;">City</label>
                                    <input type="text" name="ship_city" id="ship_city" class="form-control"
                                        placeholder="City">
                                </div>
                                <div><label style="font-size:12px;">State</label>
                                    <input type="text" name="ship_state" id="ship_state" class="form-control"
                                        placeholder="State">
                                </div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                                <div><label style="font-size:12px;">Pincode</label>
                                    <input type="text" name="ship_pincode" id="ship_pincode" class="form-control"
                                        placeholder="Pincode">
                                </div>
                                <div><label style="font-size:12px;">Phone</label>
                                    <input type="text" name="ship_phone" id="ship_phone" class="form-control"
                                        placeholder="Phone">
                                </div>
                            </div>
                            <div><label style="font-size:12px;">GSTIN</label>
                                <input type="text" name="ship_gstin" id="ship_gstin" class="form-control"
                                    placeholder="GSTIN/UIN">
                            </div>
                        </div>
                    </div>

                    <!-- Items Section -->
                    <div class="items-section">
                        <div class="section-header">
                            <h3>Invoice Items</h3>
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

                        <div class="table-container">
                            <table class="items-table" id="itemsTable">
                                <thead>
                                    <tr>
                                        <th style="width:28%">Product / Description</th>
                                        <th style="width:10%">Batch No.</th>
                                        <th style="width:11%">HSN Code</th>
                                        <th style="width:8%">Qty</th>
                                        <th style="width:11%">Rate</th>
                                        <th style="width:7%">GST %</th>
                                        <th style="width:11%">Amount</th>
                                        <th style="width:10%">Total</th>
                                        <th style="width:4%">Del</th>
                                    </tr>
                                </thead>
                                <tbody id="itemsBody"></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Totals Section -->
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
                            <div><label style="font-size:12px;">Delivery Note No.</label>
                                <input type="text" name="delivery_note" class="form-control"
                                    placeholder="Delivery note no.">
                            </div>
                            <div><label style="font-size:12px;">Buyer's Order No.</label>
                                <input type="text" name="buyer_order_no" class="form-control"
                                    placeholder="Order number">
                            </div>
                            <div><label style="font-size:12px;">Dispatch Doc No.</label>
                                <input type="text" name="dispatch_doc_no" class="form-control"
                                    placeholder="Dispatch doc no.">
                            </div>
                            <div><label style="font-size:12px;">Dispatched Through</label>
                                <input type="text" name="dispatched_thru" class="form-control"
                                    placeholder="e.g. DTDC, By Hand">
                            </div>
                            <div><label style="font-size:12px;">Destination</label>
                                <input type="text" name="destination" class="form-control"
                                    placeholder="Destination city">
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="total_amount" id="total_amount">
                    <input type="hidden" name="cgst_total" id="cgst_total">
                    <input type="hidden" name="sgst_total" id="sgst_total">
                    <input type="hidden" name="igst_total" id="igst_total">
                    <input type="hidden" name="grand_total" id="grand_total">
                    <input type="hidden" name="items" id="items_json">

                    <div class="form-actions">
                        <button type="submit" class="btn btn-success btn-lg">💾 Save &amp; Print Invoice</button>
                        <button type="button" class="btn btn-secondary btn-lg"
                            onclick="window.location.href='index.php'">Cancel</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        const products = <?php echo json_encode($products); ?>;
        const companyState = 'Uttarakhand';
        let itemCounter = 0;
        let items = [];

        // ─── Customer search ──────────────────────────────────────────────────────────
        const customerList = <?php echo json_encode(array_map(function ($c) {
            return [
                'id' => $c['id'],
                'name' => $c['customer_name'],
                'gstin' => $c['gstin'],
                'state' => $c['state'],
                'address' => $c['address'] ?? '',
                'city' => $c['city'] ?? '',
                'pincode' => $c['pincode'] ?? '',
                'phone' => $c['phone'] ?? '',
            ];
        }, $customers)); ?>;

        let selectedCustomerState = null;

        function searchCustomer(val) {
            const drop = document.getElementById('cust_drop');
            const hidId = document.getElementById('customer_id');
            const hidName = document.getElementById('customer_name_manual');
            const badge = document.getElementById('cust_badge');

            hidId.value = '';
            hidName.value = val.trim();
            selectedCustomerState = null;
            badge.style.display = 'none';
            showGstSelector();

            if (!val.trim()) { drop.style.display = 'none'; return; }

            const matches = customerList.filter(c =>
                c.name.toLowerCase().includes(val.toLowerCase())
            );

            drop.innerHTML = '';

            const manualDiv = document.createElement('div');
            manualDiv.className = 'cust-item cust-manual';
            manualDiv.innerHTML = `✏️ Use <b>"${val}"</b> as manual customer name`;
            manualDiv.onmousedown = () => {
                hidId.value = '';
                hidName.value = val.trim();
                selectedCustomerState = null;
                document.getElementById('customer_search').value = val.trim();
                badge.textContent = '✏️ Manual: ' + val.trim();
                badge.style.display = 'block';
                drop.style.display = 'none';
                showGstSelector();
                items.forEach(i => calculateRow(i.id));
            };
            drop.appendChild(manualDiv);

            matches.forEach(c => {
                const d = document.createElement('div');
                d.className = 'cust-item';
                const stateLabel = c.state ? ` — ${c.state}` : '';
                d.innerHTML = `👤 <strong>${c.name}</strong><span style="color:#94a3b8;font-size:11px;">${stateLabel}${c.gstin ? ' | ' + c.gstin : ''}</span>`;
                d.onmousedown = () => {
                    hidId.value = c.id;
                    hidName.value = '';
                    selectedCustomerState = c.state || '';
                    document.getElementById('customer_search').value = c.name;
                    badge.textContent = '✅ ' + c.name + (c.state ? ' (' + c.state + ')' : '');
                    badge.style.display = 'block';
                    drop.style.display = 'none';
                    hideGstSelector();
                    fillBillingFromCustomer(c);
                    items.forEach(i => calculateRow(i.id));
                };
                drop.appendChild(d);
            });

            drop.style.display = 'block';
        }

        // ─── Product search (global bar) ─────────────────────────────────────────────
        function globalSearchProduct(query) {
            const suggestionsDiv = document.getElementById('globalSuggestions');
            if (!query || query.length < 1) { suggestionsDiv.classList.remove('active'); return; }

            const filtered = products.filter(p => {
                const s = query.toLowerCase();
                return p.product_name.toLowerCase().includes(s) ||
                    (p.hsn_code && p.hsn_code.toLowerCase().includes(s));
            });

            if (filtered.length === 0) {
                suggestionsDiv.innerHTML = '<div class="no-results">No products found</div>';
                suggestionsDiv.classList.add('active');
                return;
            }

            suggestionsDiv.innerHTML = filtered.slice(0, 10).map(p => `
        <div class="product-suggestion-item" onclick="addProductFromGlobalSearch(${p.id})">
            <div class="product-name">${p.product_name}</div>
            <div class="product-details">
                <span>HSN: ${p.hsn_code || 'N/A'}</span>
                <span>Rate: ₹${parseFloat(p.rate).toFixed(2)}</span>
                <span>GST: ${p.gst_rate}%</span>
                <span>Stock: ${p.stock_quantity || 0}</span>
                ${p.batch_no ? `<span>Batch: ${p.batch_no}</span>` : ''}
            </div>
        </div>`).join('');
            suggestionsDiv.classList.add('active');
        }

        function showGlobalSuggestions() {
            const input = document.getElementById('globalProductSearch');
            if (input.value.length >= 1) globalSearchProduct(input.value);
        }

        function addProductFromGlobalSearch(productId) {
            const product = products.find(p => p.id == productId);
            if (!product) return;

            itemCounter++;
            const row = document.createElement('tr');
            row.id = 'item_' + itemCounter;
            row.innerHTML = itemRowHTML(itemCounter, product.product_name, product.hsn_code, product.rate, product.gst_rate, product.id, product.batch_no || '');
            document.getElementById('itemsBody').appendChild(row);

            items.push({
                id: itemCounter, product_id: product.id, product_name: product.product_name,
                hsn_code: product.hsn_code, batch_no: product.batch_no || '',
                quantity: 1, rate: parseFloat(product.rate),
                amount: 0, gst_rate: parseFloat(product.gst_rate),
                cgst: 0, sgst: 0, igst: 0, total: 0
            });

            calculateRow(itemCounter);
            document.getElementById('globalProductSearch').value = '';
            document.getElementById('globalSuggestions').classList.remove('active');
            document.getElementById('qty_' + itemCounter).focus();
            document.getElementById('qty_' + itemCounter).select();
        }

        function itemRowHTML(id, name = '', hsn = '', rate = 0, gst = 18, pid = '', batch = '') {
            // Escape for HTML attribute
            const safeName = name.replace(/"/g, '&quot;');
            const safeBatch = batch.replace(/"/g, '&quot;');
            return `
        <td>
            <div class="product-search-container" id="search_container_${id}">
                <input type="text" class="product-search-input" id="search_${id}"
                       value="${safeName}" placeholder="🔍 Search product..."
                       maxlength="500"
                       autocomplete="off"
                       oninput="searchProduct(${id}, this.value)"
                       onfocus="showSuggestions(${id})">
                <div class="product-suggestions" id="suggestions_${id}"></div>
                <input type="hidden" id="product_id_${id}" value="${pid}">
            </div>
        </td>
        <td class="batch-col">
            <input type="text" class="batch-input-cell" id="batch_${id}"
                   value="${safeBatch}" placeholder="Batch No."
                   oninput="updateBatch(${id})">
        </td>
        <td><input type="text"   class="form-control hsn-input"  id="hsn_${id}"  value="${hsn}"  placeholder="HSN"></td>
        <td><input type="number" class="form-control qty-input"  id="qty_${id}"  value="1" min="1" onchange="calculateRow(${id})"></td>
        <td><input type="number" class="form-control rate-input" id="rate_${id}" value="${rate}" step="0.01" onchange="calculateRow(${id})"></td>
        <td><input type="number" class="form-control gst-input"  id="gst_${id}"  value="${gst}"  step="0.01" onchange="calculateRow(${id})"></td>
        <td><strong id="amount_${id}">₹0.00</strong></td>
        <td><strong id="total_${id}">₹0.00</strong></td>
        <td><button type="button" class="btn-remove" onclick="removeItem(${id})">✕</button></td>`;
        }

        function addItem() {
            itemCounter++;
            const row = document.createElement('tr');
            row.id = 'item_' + itemCounter;
            row.innerHTML = itemRowHTML(itemCounter);
            document.getElementById('itemsBody').appendChild(row);
            items.push({
                id: itemCounter, product_id: '', product_name: '', hsn_code: '', batch_no: '',
                quantity: 1, rate: 0, amount: 0, gst_rate: 18,
                cgst: 0, sgst: 0, igst: 0, total: 0
            });
            document.getElementById('search_' + itemCounter).focus();
        }

        function searchProduct(itemId, query) {
            const suggestionsDiv = document.getElementById('suggestions_' + itemId);
            if (!query || query.length < 1) { suggestionsDiv.classList.remove('active'); return; }

            const filtered = products.filter(p => {
                const s = query.toLowerCase();
                return p.product_name.toLowerCase().includes(s) ||
                    (p.hsn_code && p.hsn_code.toLowerCase().includes(s));
            });

            if (filtered.length === 0) {
                suggestionsDiv.innerHTML = '<div class="no-results">No products found</div>';
                suggestionsDiv.classList.add('active');
                return;
            }

            suggestionsDiv.innerHTML = filtered.slice(0, 10).map(p => `
        <div class="product-suggestion-item" onclick="selectSearchedProduct(${itemId}, ${p.id})">
            <div class="product-name">${p.product_name}</div>
            <div class="product-details">
                <span>HSN: ${p.hsn_code || 'N/A'}</span>
                <span>Rate: ₹${parseFloat(p.rate).toFixed(2)}</span>
                <span>GST: ${p.gst_rate}%</span>
                <span>Stock: ${p.stock_quantity || 0}</span>
                ${p.batch_no ? `<span>Batch: ${p.batch_no}</span>` : ''}
            </div>
        </div>`).join('');
            suggestionsDiv.classList.add('active');
        }

        function showSuggestions(itemId) {
            const input = document.getElementById('search_' + itemId);
            if (input.value.length >= 1) searchProduct(itemId, input.value);
        }

        function selectSearchedProduct(itemId, productId) {
            const product = products.find(p => p.id == productId);
            const item = items.find(i => i.id === itemId);
            if (!product || !item) return;

            item.product_id = product.id;
            item.product_name = product.product_name;
            item.hsn_code = product.hsn_code;
            item.batch_no = product.batch_no || '';
            item.rate = parseFloat(product.rate);
            item.gst_rate = parseFloat(product.gst_rate);

            document.getElementById('search_' + itemId).value = product.product_name;
            document.getElementById('product_id_' + itemId).value = product.id;
            document.getElementById('hsn_' + itemId).value = product.hsn_code;
            document.getElementById('batch_' + itemId).value = product.batch_no || '';
            document.getElementById('rate_' + itemId).value = product.rate;
            document.getElementById('gst_' + itemId).value = product.gst_rate;
            document.getElementById('suggestions_' + itemId).classList.remove('active');

            calculateRow(itemId);
        }

        function updateBatch(itemId) {
            const item = items.find(i => i.id === itemId);
            if (item) item.batch_no = document.getElementById('batch_' + itemId).value;
        }

        function calculateRow(itemId) {
            const item = items.find(i => i.id === itemId);
            if (!item) return;

            item.product_name = document.getElementById('search_' + itemId).value || 'Manual Entry';
            item.hsn_code = document.getElementById('hsn_' + itemId).value;
            item.batch_no = document.getElementById('batch_' + itemId).value;
            item.quantity = parseFloat(document.getElementById('qty_' + itemId).value) || 0;
            item.rate = parseFloat(document.getElementById('rate_' + itemId).value) || 0;
            item.gst_rate = parseFloat(document.getElementById('gst_' + itemId).value) || 0;
            item.amount = item.quantity * item.rate;

            let isInterState;
            if (selectedCustomerState === null) {
                isInterState = document.getElementById('gst_type_selector').value === 'igst';
            } else {
                isInterState = selectedCustomerState.trim().toLowerCase() !== 'uttarakhand';
            }

            if (isInterState) {
                item.igst = (item.amount * item.gst_rate) / 100;
                item.cgst = 0; item.sgst = 0;
            } else {
                item.cgst = (item.amount * item.gst_rate) / 200;
                item.sgst = (item.amount * item.gst_rate) / 200;
                item.igst = 0;
            }

            item.total = item.amount + item.cgst + item.sgst + item.igst;

            document.getElementById('amount_' + itemId).textContent = '₹' + item.amount.toFixed(2);
            document.getElementById('total_' + itemId).textContent = '₹' + item.total.toFixed(2);

            calculateTotals();
        }

        function showGstSelector() { document.getElementById('gst_type_row').style.display = 'flex'; }
        function hideGstSelector() { document.getElementById('gst_type_row').style.display = 'none'; }

        // ─── Same-address checkbox ────────────────────────────────────────────────────
        const shipFields = ['address', 'city', 'state', 'pincode', 'phone', 'gstin'];

        function toggleSameAddress(checked) {
            if (checked) {
                copyBillToShip();
                shipFields.forEach(f => {
                    const el = document.getElementById('ship_' + f);
                    if (el) { el.readOnly = true; el.style.background = '#e9f7ef'; }
                });
                const sn = document.getElementById('ship_name');
                if (sn) { sn.readOnly = true; sn.style.background = '#e9f7ef'; }
            } else {
                shipFields.forEach(f => {
                    const el = document.getElementById('ship_' + f);
                    if (el) { el.readOnly = false; el.style.background = ''; }
                });
                const sn = document.getElementById('ship_name');
                if (sn) { sn.readOnly = false; sn.style.background = ''; }
            }
        }

        function copyBillToShip() {
            shipFields.forEach(f => {
                const b = document.getElementById('bill_' + f);
                const s = document.getElementById('ship_' + f);
                if (b && s) s.value = b.value;
            });
            // also copy customer name to ship_name if empty
            const sn = document.getElementById('ship_name');
            if (sn && !sn.value) {
                sn.value = document.getElementById('customer_search').value || '';
            }
        }

        function syncShip() {
            if (document.getElementById('same_address') && document.getElementById('same_address').checked) {
                copyBillToShip();
            }
        }

        // Auto-fill billing fields when a DB customer is selected
        function fillBillingFromCustomer(c) {
            const fields = {
                bill_address: c.address || '',
                bill_city: c.city || '',
                bill_state: c.state || '',
                bill_pincode: c.pincode || '',
                bill_phone: c.phone || '',
                bill_gstin: c.gstin || ''
            };
            Object.entries(fields).forEach(([id, val]) => {
                const el = document.getElementById(id);
                if (el) el.value = val;
            });
            syncShip();
        }

        function removeItem(itemId) {
            document.getElementById('item_' + itemId).remove();
            items = items.filter(i => i.id !== itemId);
            calculateTotals();
        }

        function calculateTotals() {
            let subtotal = 0, cgst = 0, sgst = 0, igst = 0;
            items.forEach(i => { subtotal += i.amount; cgst += i.cgst; sgst += i.sgst; igst += i.igst; });
            const grandTotal = subtotal + cgst + sgst + igst;

            document.getElementById('subtotal_display').textContent = '₹' + subtotal.toFixed(2);
            document.getElementById('cgst_display').textContent = '₹' + cgst.toFixed(2);
            document.getElementById('sgst_display').textContent = '₹' + sgst.toFixed(2);
            document.getElementById('igst_display').textContent = '₹' + igst.toFixed(2);
            document.getElementById('grand_total_display').textContent = '₹' + grandTotal.toFixed(2);

            document.getElementById('total_amount').value = subtotal.toFixed(2);
            document.getElementById('cgst_total').value = cgst.toFixed(2);
            document.getElementById('sgst_total').value = sgst.toFixed(2);
            document.getElementById('igst_total').value = igst.toFixed(2);
            document.getElementById('grand_total').value = grandTotal.toFixed(2);
        }

        document.getElementById('salesForm').onsubmit = function (e) {
            if (items.length === 0) {
                alert('Please add at least one item');
                e.preventDefault();
                return false;
            }
            document.getElementById('items_json').value = JSON.stringify(items);
        };

        document.getElementById('globalProductSearch').focus();

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.product-search-container') && !e.target.closest('.global-search-container')) {
                document.querySelectorAll('.product-suggestions').forEach(d => d.classList.remove('active'));
            }
            if (!e.target.closest('#customer_search') && !e.target.closest('#cust_drop')) {
                document.getElementById('cust_drop').style.display = 'none';
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.product-suggestions').forEach(d => d.classList.remove('active'));
                document.getElementById('cust_drop').style.display = 'none';
                document.getElementById('globalSuggestions').classList.remove('active');
                document.getElementById('globalProductSearch').value = '';
            }
        });
    </script>
</body>

</html>