<?php
require_once 'auth.php';
require_once 'database.php';
$db = new Database();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_purchase') {
    try {
        $db->beginTransaction();
        
        $settings = $db->single("SELECT * FROM company_settings LIMIT 1");
        $prefix = $settings['purchase_prefix'] ?? 'PUR';
        
        $last_purchase = $db->single("SELECT purchase_no FROM purchases ORDER BY id DESC LIMIT 1");
        if ($last_purchase) {
            $last_num = intval(substr($last_purchase['purchase_no'], strlen($prefix)));
            $purchase_no = $prefix . str_pad($last_num + 1, 5, '0', STR_PAD_LEFT);
        } else {
            $purchase_no = $prefix . '00001';
        }

        // Resolve supplier — saved ID or manual typed name
        $supp_id     = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        $supp_manual = (!$supp_id && !empty($_POST['supplier_name_manual']))
                       ? trim($_POST['supplier_name_manual']) : null;
        
        $purchase_sql = "INSERT INTO purchases (purchase_no, supplier_id, supplier_name_manual, purchase_date, total_amount, cgst_amount, sgst_amount, igst_amount, grand_total, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $db->query($purchase_sql, [
            $purchase_no,
            $supp_id,
            $supp_manual,
            $_POST['purchase_date'],
            $_POST['total_amount'],
            $_POST['cgst_total'],
            $_POST['sgst_total'],
            $_POST['igst_total'],
            $_POST['grand_total'],
            $_POST['notes']
        ]);
        
        $purchase_id = $db->lastInsertId();
        
        $items = json_decode($_POST['items'], true);
        foreach ($items as $item) {
            $item_sql = "INSERT INTO purchase_items (purchase_id, product_id, product_name, hsn_code, quantity, rate, amount, gst_rate, cgst, sgst, igst, total)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $db->query($item_sql, [
                $purchase_id,
                $item['product_id'] ?: null,
                $item['product_name'],
                $item['hsn_code'],
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
                $db->query("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?", 
                          [$item['quantity'], $item['product_id']]);
            }
        }
        
        $db->commit();
        $_SESSION['success'] = "Purchase entry saved successfully! Purchase No: " . $purchase_no;
        header("Location: purchase.php");
        exit;
        
    } catch (Exception $e) {
        $db->rollback();
        $error = "Error saving purchase: " . $e->getMessage();
    }
}

$suppliers        = $db->fetchAll("SELECT * FROM suppliers ORDER BY supplier_name");
$products         = $db->fetchAll("SELECT * FROM products ORDER BY product_name");
$company          = $db->single("SELECT * FROM company_settings LIMIT 1");
$recent_purchases = $db->fetchAll("
    SELECT p.*, 
           COALESCE(s.supplier_name, p.supplier_name_manual, 'N/A') AS display_supplier
    FROM purchases p 
    LEFT JOIN suppliers s ON p.supplier_id = s.id 
    ORDER BY p.created_at DESC LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Entry - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .product-search-container { position: relative; width: 100%; }
        .product-search-input {
            width: 100%; padding: 8px 12px;
            border: 2px solid #e2e8f0; border-radius: 6px;
            font-size: 14px; transition: all 0.3s ease;
        }
        .product-search-input:focus {
            outline: none; border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .product-suggestions {
            position: absolute; top: 100%; left: 0; right: 0;
            background: white; border: 1px solid #e2e8f0; border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15); max-height: 300px;
            overflow-y: auto; z-index: 1000; margin-top: 4px; display: none;
        }
        .product-suggestions.active { display: block; }
        .product-suggestion-item {
            padding: 12px 16px; cursor: pointer;
            border-bottom: 1px solid #f1f5f9; transition: background 0.2s ease;
        }
        .product-suggestion-item:hover { background: #f8fafc; }
        .product-suggestion-item:last-child { border-bottom: none; }
        .product-name { font-weight: 600; color: #1e293b; margin-bottom: 4px; }
        .product-details { font-size: 12px; color: #64748b; }
        .product-details span { margin-right: 12px; }
        .no-results { padding: 16px; text-align: center; color: #94a3b8; font-size: 14px; }

        /* Supplier search dropdown */
        #supp_drop {
            position: absolute; top: 100%; left: 0; right: 0;
            background: white; border: 1px solid #e2e8f0; border-radius: 8px;
            box-shadow: 0 6px 16px rgba(0,0,0,0.12); max-height: 220px;
            overflow-y: auto; z-index: 999; margin-top: 3px; display: none;
        }
        .supp-item {
            padding: 10px 14px; cursor: pointer; font-size: 13px;
            border-bottom: 1px solid #f1f5f9; transition: background 0.15s;
        }
        .supp-item:hover { background: #f8fafc; }
        .supp-item:last-child { border-bottom: none; }
        .supp-manual { color: #64748b; }
        .supp-badge {
            display: none; margin-top: 5px; padding: 4px 12px;
            background: #eff6ff; border-radius: 6px; font-size: 12px;
            color: #1d4ed8; font-weight: 600;
        }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <?php $page_title = 'Purchase Entry'; include 'topbar.php'; ?>

        <div class="content-wrapper">
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if(isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form id="purchaseForm" method="POST" class="invoice-form">
                <input type="hidden" name="action" value="save_purchase">
                
                <div class="form-grid">

                    <!-- ── Supplier field with search + manual entry ── -->
                    <div class="form-group">
                        <label>Supplier</label>
                        <div style="position:relative;">
                            <input type="text" id="supplier_search" class="form-control"
                                   placeholder="🔍 Search or type any name manually..."
                                   autocomplete="off"
                                   oninput="searchSupplier(this.value)"
                                   onblur="setTimeout(()=>{document.getElementById('supp_drop').style.display='none'},200)">
                            <div id="supp_drop"></div>
                        </div>
                        <input type="hidden" name="supplier_id"          id="supplier_id">
                        <input type="hidden" name="supplier_name_manual" id="supplier_name_manual">
                        <div id="supp_badge" class="supp-badge"></div>
                    </div>

                    <div class="form-group">
                        <label for="purchase_date">Purchase Date *</label>
                        <input type="date" name="purchase_date" id="purchase_date"
                               class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>

                <!-- GST Type selector — only shown for manual suppliers -->
                <div id="gst_type_row" style="display:none;align-items:center;gap:12px;
                     background:#fef9c3;border:1px solid #fbbf24;border-radius:8px;
                     padding:12px 16px;margin-bottom:20px;">
                    <span style="font-size:18px;">⚠️</span>
                    <span style="font-weight:600;color:#92400e;">Manual supplier — choose GST type:</span>
                    <select id="gst_type_selector" class="form-control" style="width:auto;"
                            onchange="items.forEach(i=>calculateRow(i.id))">
                        <option value="cgst_sgst">CGST + SGST (Uttarakhand — Intra-state)</option>
                        <option value="igst">IGST (Outside Uttarakhand — Inter-state)</option>
                    </select>
                </div>

                <!-- Items Section -->
                <div class="items-section">
                    <div class="section-header">
                        <h3>Purchase Items</h3>
                        <div style="display:flex;gap:12px;align-items:center;">
                            <div class="global-search-container" style="position:relative;width:350px;">
                                <input type="text" id="globalProductSearch"
                                       class="product-search-input"
                                       placeholder="🔍 Search products to add..."
                                       autocomplete="off"
                                       oninput="globalSearchProduct(this.value)"
                                       onfocus="showGlobalSuggestions()">
                                <div class="product-suggestions" id="globalSuggestions"></div>
                            </div>
                            <button type="button" class="btn btn-primary" onclick="addItem()">+ Add Manually</button>
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="items-table" id="itemsTable">
                            <thead>
                                <tr>
                                    <th style="width:30%">Product</th>
                                    <th style="width:12%">HSN Code</th>
                                    <th style="width:10%">Qty</th>
                                    <th style="width:12%">Rate</th>
                                    <th style="width:8%">GST %</th>
                                    <th style="width:12%">Amount</th>
                                    <th style="width:12%">Total</th>
                                    <th style="width:4%">Action</th>
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
                        <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="Additional notes..."></textarea>
                    </div>
                    <div class="totals-grid">
                        <div class="total-item"><span>Subtotal:</span><strong id="subtotal_display">₹0.00</strong></div>
                        <div class="total-item gst-item"><span>CGST:</span><strong id="cgst_display">₹0.00</strong></div>
                        <div class="total-item gst-item"><span>SGST:</span><strong id="sgst_display">₹0.00</strong></div>
                        <div class="total-item gst-item"><span>IGST:</span><strong id="igst_display">₹0.00</strong></div>
                        <div class="total-item grand-total"><span>Grand Total:</span><strong id="grand_total_display">₹0.00</strong></div>
                    </div>
                </div>

                <input type="hidden" name="total_amount" id="total_amount">
                <input type="hidden" name="cgst_total"   id="cgst_total">
                <input type="hidden" name="sgst_total"   id="sgst_total">
                <input type="hidden" name="igst_total"   id="igst_total">
                <input type="hidden" name="grand_total"  id="grand_total">
                <input type="hidden" name="items"        id="items_json">

                <div class="form-actions">
                    <button type="submit" class="btn btn-success btn-lg">💾 Save Purchase Entry</button>
                    <button type="button" class="btn btn-secondary btn-lg" onclick="window.location.href='index.php'">Cancel</button>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($recent_purchases)): ?>
                            <tr><td colspan="6" class="no-data">No purchases found</td></tr>
                            <?php else: ?>
                                <?php foreach($recent_purchases as $p): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($p['purchase_no']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($p['display_supplier']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($p['purchase_date'])); ?></td>
                                    <td>₹<?php echo number_format($p['total_amount'], 2); ?></td>
                                    <td>₹<?php echo number_format($p['cgst_amount'] + $p['sgst_amount'] + $p['igst_amount'], 2); ?></td>
                                    <td><strong>₹<?php echo number_format($p['grand_total'], 2); ?></strong></td>
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

<script>
const products     = <?php echo json_encode($products); ?>;
const companyState = 'Uttarakhand'; // Your company is in Uttarakhand
let itemCounter = 0;
let items = [];

// ─── Supplier search with manual entry ───────────────────────────────────────
const supplierList = <?php echo json_encode(array_map(function($s){
    return ['id'=>$s['id'],'name'=>$s['supplier_name'],'gstin'=>$s['gstin'],'state'=>$s['state']];
}, $suppliers)); ?>;

// null = manual (unknown state), string = known state
let selectedSupplierState = null;

function searchSupplier(val) {
    const drop    = document.getElementById('supp_drop');
    const hidId   = document.getElementById('supplier_id');
    const hidName = document.getElementById('supplier_name_manual');
    const badge   = document.getElementById('supp_badge');

    hidId.value           = '';
    hidName.value         = val.trim();
    selectedSupplierState = null;
    badge.style.display   = 'none';
    showGstSelector();

    if (!val.trim()) { drop.style.display = 'none'; return; }

    const matches = supplierList.filter(s =>
        s.name.toLowerCase().includes(val.toLowerCase())
    );

    drop.innerHTML = '';

    const manualDiv = document.createElement('div');
    manualDiv.className = 'supp-item supp-manual';
    manualDiv.innerHTML = `✏️ Use <b>"${val}"</b> as manual supplier name`;
    manualDiv.onmousedown = () => {
        hidId.value           = '';
        hidName.value         = val.trim();
        selectedSupplierState = null;
        document.getElementById('supplier_search').value = val.trim();
        badge.textContent   = '✏️ Manual: ' + val.trim();
        badge.style.display = 'block';
        drop.style.display  = 'none';
        showGstSelector();
        items.forEach(i => calculateRow(i.id));
    };
    drop.appendChild(manualDiv);

    matches.forEach(s => {
        const d = document.createElement('div');
        d.className = 'supp-item';
        const stateLabel = s.state ? ` — ${s.state}` : '';
        d.innerHTML = `🏭 <strong>${s.name}</strong><span style="color:#94a3b8;font-size:11px;">${stateLabel}${s.gstin ? ' | '+s.gstin : ''}</span>`;
        d.onmousedown = () => {
            hidId.value           = s.id;
            hidName.value         = '';
            selectedSupplierState = s.state || '';
            document.getElementById('supplier_search').value = s.name;
            badge.textContent   = '✅ ' + s.name + (s.state ? ' (' + s.state + ')' : '');
            badge.style.display = 'block';
            drop.style.display  = 'none';
            hideGstSelector();
            items.forEach(i => calculateRow(i.id));
        };
        drop.appendChild(d);
    });

    drop.style.display = 'block';
}
// ─────────────────────────────────────────────────────────────────────────────

// ─── Global product search ────────────────────────────────────────────────────
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

    suggestionsDiv.innerHTML = filtered.slice(0,10).map(p => `
        <div class="product-suggestion-item" onclick="addProductFromGlobalSearch(${p.id})">
            <div class="product-name">${p.product_name}</div>
            <div class="product-details">
                <span>HSN: ${p.hsn_code||'N/A'}</span>
                <span>Rate: ₹${parseFloat(p.rate).toFixed(2)}</span>
                <span>GST: ${p.gst_rate}%</span>
                <span>Stock: ${p.stock_quantity||0}</span>
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
    row.innerHTML = itemRowHTML(itemCounter, product.product_name, product.hsn_code, product.rate, product.gst_rate, product.id);
    document.getElementById('itemsBody').appendChild(row);

    items.push({ id:itemCounter, product_id:product.id, product_name:product.product_name,
        hsn_code:product.hsn_code, quantity:1, rate:parseFloat(product.rate),
        amount:0, gst_rate:parseFloat(product.gst_rate), cgst:0, sgst:0, igst:0, total:0 });

    calculateRow(itemCounter);
    document.getElementById('globalProductSearch').value = '';
    document.getElementById('globalSuggestions').classList.remove('active');
    document.getElementById('qty_' + itemCounter).focus();
    document.getElementById('qty_' + itemCounter).select();
}

function itemRowHTML(id, name='', hsn='', rate=0, gst=18, pid='') {
    return `
        <td>
            <div class="product-search-container" id="search_container_${id}">
                <input type="text" class="product-search-input" id="search_${id}"
                       value="${name}" placeholder="🔍 Search product..."
                       autocomplete="off"
                       oninput="searchProduct(${id}, this.value)"
                       onfocus="showSuggestions(${id})">
                <div class="product-suggestions" id="suggestions_${id}"></div>
                <input type="hidden" id="product_id_${id}" value="${pid}">
            </div>
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
    items.push({ id:itemCounter, product_id:'', product_name:'', hsn_code:'',
        quantity:1, rate:0, amount:0, gst_rate:18, cgst:0, sgst:0, igst:0, total:0 });
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

    suggestionsDiv.innerHTML = filtered.slice(0,10).map(p => `
        <div class="product-suggestion-item" onclick="selectSearchedProduct(${itemId}, ${p.id})">
            <div class="product-name">${p.product_name}</div>
            <div class="product-details">
                <span>HSN: ${p.hsn_code||'N/A'}</span>
                <span>Rate: ₹${parseFloat(p.rate).toFixed(2)}</span>
                <span>GST: ${p.gst_rate}%</span>
                <span>Stock: ${p.stock_quantity||0}</span>
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
    const item    = items.find(i => i.id === itemId);
    if (!product || !item) return;

    item.product_id   = product.id;
    item.product_name = product.product_name;
    item.hsn_code     = product.hsn_code;
    item.rate         = parseFloat(product.rate);
    item.gst_rate     = parseFloat(product.gst_rate);

    document.getElementById('search_'     + itemId).value = product.product_name;
    document.getElementById('product_id_' + itemId).value = product.id;
    document.getElementById('hsn_'        + itemId).value = product.hsn_code;
    document.getElementById('rate_'       + itemId).value = product.rate;
    document.getElementById('gst_'        + itemId).value = product.gst_rate;
    document.getElementById('suggestions_'+ itemId).classList.remove('active');

    calculateRow(itemId);
}

function calculateRow(itemId) {
    const item = items.find(i => i.id === itemId);
    if (!item) return;

    item.product_name = document.getElementById('search_' + itemId).value || 'Manual Entry';
    item.hsn_code     = document.getElementById('hsn_'    + itemId).value;
    item.quantity     = parseFloat(document.getElementById('qty_'  + itemId).value) || 0;
    item.rate         = parseFloat(document.getElementById('rate_' + itemId).value) || 0;
    item.gst_rate     = parseFloat(document.getElementById('gst_'  + itemId).value) || 0;
    item.amount       = item.quantity * item.rate;

    let isInterState;
    if (selectedSupplierState === null) {
        // Manual supplier — read from user's GST type selector
        isInterState = document.getElementById('gst_type_selector').value === 'igst';
    } else {
        // Known supplier state — auto detect
        isInterState = selectedSupplierState.trim().toLowerCase() !== 'uttarakhand';
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
    document.getElementById('total_'  + itemId).textContent = '₹' + item.total.toFixed(2);

    calculateTotals();
}

function showGstSelector() {
    document.getElementById('gst_type_row').style.display = 'flex';
}
function hideGstSelector() {
    document.getElementById('gst_type_row').style.display = 'none';
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

    document.getElementById('subtotal_display').textContent    = '₹' + subtotal.toFixed(2);
    document.getElementById('cgst_display').textContent        = '₹' + cgst.toFixed(2);
    document.getElementById('sgst_display').textContent        = '₹' + sgst.toFixed(2);
    document.getElementById('igst_display').textContent        = '₹' + igst.toFixed(2);
    document.getElementById('grand_total_display').textContent = '₹' + grandTotal.toFixed(2);

    document.getElementById('total_amount').value = subtotal.toFixed(2);
    document.getElementById('cgst_total').value   = cgst.toFixed(2);
    document.getElementById('sgst_total').value   = sgst.toFixed(2);
    document.getElementById('igst_total').value   = igst.toFixed(2);
    document.getElementById('grand_total').value  = grandTotal.toFixed(2);
}

document.getElementById('purchaseForm').onsubmit = function(e) {
    if (items.length === 0) {
        alert('Please add at least one item');
        e.preventDefault();
        return false;
    }
    document.getElementById('items_json').value = JSON.stringify(items);
};

// Close dropdowns on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('.product-search-container') && !e.target.closest('.global-search-container')) {
        document.querySelectorAll('.product-suggestions').forEach(d => d.classList.remove('active'));
    }
    if (!e.target.closest('#supplier_search') && !e.target.closest('#supp_drop')) {
        document.getElementById('supp_drop').style.display = 'none';
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.product-suggestions').forEach(d => d.classList.remove('active'));
        document.getElementById('supp_drop').style.display = 'none';
    }
});

// Add one empty row on load
addItem();
</script>
</body>
</html>