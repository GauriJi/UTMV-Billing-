<?php
require_once 'auth.php';
require_once 'database.php';
$db = new Database();

$sale_id = $_GET['id'] ?? 0;

$sale = $db->single("SELECT * FROM sales WHERE id = ?", [$sale_id]);
if (!$sale) { header("Location: index.php"); exit; }

$sale_items = $db->fetchAll("SELECT * FROM sales_items WHERE sale_id = ?", [$sale_id]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_sale') {
    try {
        $db->beginTransaction();

        foreach ($sale_items as $old_item) {
            if ($old_item['product_id']) {
                $db->query("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?",
                           [$old_item['quantity'], $old_item['product_id']]);
            }
        }

        $db->query("DELETE FROM sales_items WHERE sale_id = ?", [$sale_id]);

        $cust_id     = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $cust_manual = (!$cust_id && !empty($_POST['customer_name_manual']))
                       ? trim($_POST['customer_name_manual']) : null;

        $db->query("UPDATE sales SET
                     customer_id = ?, customer_name_manual = ?,
                     sale_date = ?, total_amount = ?,
                     cgst_amount = ?, sgst_amount = ?, igst_amount = ?,
                     grand_total = ?, payment_status = ?, notes = ?
                     WHERE id = ?",
            [$cust_id, $cust_manual,
             $_POST['sale_date'], $_POST['total_amount'],
             $_POST['cgst_total'], $_POST['sgst_total'], $_POST['igst_total'],
             $_POST['grand_total'], $_POST['payment_status'], $_POST['notes'],
             $sale_id]);

        $items = json_decode($_POST['items'], true);
        foreach ($items as $item) {
            $db->query("INSERT INTO sales_items (sale_id, product_id, product_name, hsn_code, quantity, rate, amount, gst_rate, cgst, sgst, igst, total) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
                [$sale_id, $item['product_id'] ?: null, $item['product_name'],
                 $item['hsn_code'], $item['quantity'], $item['rate'], $item['amount'],
                 $item['gst_rate'], $item['cgst'], $item['sgst'], $item['igst'], $item['total']]);

            if ($item['product_id']) {
                $db->query("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?",
                           [$item['quantity'], $item['product_id']]);
            }
        }

        $db->commit();
        header("Location: invoice_print.php?id=" . $sale_id); exit;

    } catch (Exception $e) {
        $db->rollback();
        $error = "Error updating sale: " . $e->getMessage();
    }
}

$customers = $db->fetchAll("SELECT * FROM customers ORDER BY customer_name");
$products  = $db->fetchAll("SELECT * FROM products ORDER BY product_name");
$company   = $db->single("SELECT * FROM company_settings LIMIT 1");

// Resolve existing customer display name
$existing_customer_name = '';
$existing_customer_id   = $sale['customer_id'] ?? '';
if ($existing_customer_id) {
    $ec = $db->single("SELECT customer_name, state FROM customers WHERE id=?", [$existing_customer_id]);
    $existing_customer_name = $ec['customer_name'] ?? '';
} elseif (!empty($sale['customer_name_manual'])) {
    $existing_customer_name = $sale['customer_name_manual'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Sales Invoice - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .cust-wrap { position:relative; }
        #cust_drop {
            display:none; position:absolute; top:100%; left:0; right:0;
            background:#fff; border:1px solid #e2e8f0; border-radius:8px;
            box-shadow:0 4px 16px rgba(0,0,0,0.12); max-height:240px;
            overflow-y:auto; z-index:999; margin-top:4px;
        }
        .cust-item { padding:10px 14px; cursor:pointer; font-size:14px; }
        .cust-item:hover { background:#f1f5f9; }
        .cust-manual { color:#64748b; font-style:italic; }
        #cust_badge {
            display:none; margin-top:6px; background:#dbeafe; color:#1e40af;
            padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600;
        }
        #gst_type_row {
            display:none; align-items:center; gap:12px;
            background:#fef9c3; border:1px solid #fbbf24; border-radius:8px;
            padding:12px 16px; margin-bottom:20px;
        }
        .product-search-container { position:relative; width:100%; }
        .product-search-input { width:100%; padding:8px 12px; border:2px solid #e2e8f0; border-radius:6px; font-size:14px; }
        .product-search-input:focus { outline:none; border-color:#2563eb; }
        .product-suggestions {
            position:absolute; top:100%; left:0; right:0; background:#fff;
            border:1px solid #e2e8f0; border-radius:6px;
            box-shadow:0 4px 12px rgba(0,0,0,0.15); max-height:300px;
            overflow-y:auto; z-index:1000; margin-top:4px; display:none;
        }
        .product-suggestions.active { display:block; }
        .suggestion-item { padding:10px 14px; cursor:pointer; border-bottom:1px solid #f1f5f9; }
        .suggestion-item:hover { background:#f8fafc; }
        .suggestion-name { font-weight:600; color:#1e293b; font-size:14px; display:block; }
        .suggestion-meta { font-size:11px; color:#94a3b8; display:block; }
        .edit-header {
            background:#fef3c7; border:2px solid #fbbf24; border-radius:8px;
            padding:12px 20px; margin-bottom:20px; display:flex; align-items:center; gap:12px;
        }
        .edit-header h3 { margin:0; color:#92400e; font-size:16px; }
        .edit-header p  { margin:4px 0 0; color:#78350f; font-size:13px; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <header class="top-bar">
            <h2 class="page-title">Edit Sales Invoice</h2>
            <div style="display:flex;gap:12px;">
                <a href="invoice_print.php?id=<?php echo $sale_id; ?>" class="btn btn-secondary" target="_blank">🖨️ Print</a>
                <a href="index.php" class="btn btn-secondary">← Back</a>
            </div>
        </header>

        <div class="content-wrapper">
            <div class="edit-header">
                <div style="font-size:24px;">⚠️</div>
                <div>
                    <h3>Editing Invoice: <?php echo htmlspecialchars($sale['invoice_no']); ?></h3>
                    <p>Changes will update stock quantities and invoice details. Original invoice created on <?php echo date('d M Y', strtotime($sale['created_at'])); ?></p>
                </div>
            </div>

            <?php if(isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form id="salesForm" method="POST" class="invoice-form">
                <input type="hidden" name="action" value="update_sale">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Customer *</label>
                        <div class="cust-wrap">
                            <input type="text" id="customer_search" class="form-control"
                                   placeholder="🔍 Search or type any name manually..."
                                   autocomplete="off"
                                   value="<?php echo htmlspecialchars($existing_customer_name); ?>"
                                   oninput="searchCustomer(this.value)"
                                   onblur="setTimeout(()=>{document.getElementById('cust_drop').style.display='none'},200)"
                                   onfocus="if(this.value) searchCustomer(this.value)">
                            <input type="hidden" name="customer_id" id="customer_id"
                                   value="<?php echo htmlspecialchars($existing_customer_id); ?>">
                            <input type="hidden" name="customer_name_manual" id="customer_name_manual"
                                   value="<?php echo htmlspecialchars($sale['customer_name_manual'] ?? ''); ?>">
                            <div id="cust_drop"></div>
                            <div id="cust_badge"><?php
                                if ($existing_customer_id) echo '✅ ' . htmlspecialchars($existing_customer_name);
                                elseif (!empty($sale['customer_name_manual'])) echo '✏️ Manual: ' . htmlspecialchars($existing_customer_name);
                            ?></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="sale_date">Invoice Date *</label>
                        <input type="date" name="sale_date" id="sale_date" class="form-control"
                               value="<?php echo $sale['sale_date']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="payment_status">Payment Status *</label>
                        <select name="payment_status" id="payment_status" class="form-control" required>
                            <option value="pending" <?php echo $sale['payment_status']==='pending' ?'selected':''; ?>>Pending</option>
                            <option value="partial" <?php echo $sale['payment_status']==='partial' ?'selected':''; ?>>Partial</option>
                            <option value="paid"    <?php echo $sale['payment_status']==='paid'    ?'selected':''; ?>>Paid</option>
                        </select>
                    </div>
                </div>

                <div id="gst_type_row">
                    <span style="font-size:18px;">⚠️</span>
                    <span style="font-weight:600;color:#92400e;">Manual customer — choose GST type:</span>
                    <select id="gst_type_selector" class="form-control" style="width:auto;"
                            onchange="items.forEach(i=>calculateRow(i.id))">
                        <option value="cgst_sgst">CGST + SGST (Uttarakhand — Intra-state)</option>
                        <option value="igst">IGST (Outside Uttarakhand — Inter-state)</option>
                    </select>
                </div>

                <div class="items-section">
                    <div class="section-header">
                        <h3>Invoice Items</h3>
                        <div style="display:flex;gap:12px;align-items:center;">
                            <div class="global-search-container" style="position:relative;width:350px;">
                                <input type="text" id="globalProductSearch" class="product-search-input"
                                       placeholder="🔍 Search products to add..." autocomplete="off"
                                       oninput="globalSearchProduct(this.value)">
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
                                    <th style="width:8%">QTY</th>
                                    <th style="width:12%">Rate</th>
                                    <th style="width:8%">GST %</th>
                                    <th style="width:10%">Amount</th>
                                    <th style="width:10%">Total</th>
                                    <th style="width:5%">Action</th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody"></tbody>
                        </table>
                    </div>

                    <div class="totals-section">
                        <div class="totals-card">
                            <div class="total-item"><span>Subtotal:</span><strong id="subtotal_display">₹0.00</strong></div>
                            <div class="total-item"><span>CGST:</span><strong id="cgst_display">₹0.00</strong></div>
                            <div class="total-item"><span>SGST:</span><strong id="sgst_display">₹0.00</strong></div>
                            <div class="total-item"><span>IGST:</span><strong id="igst_display">₹0.00</strong></div>
                            <div class="total-item grand-total"><span>Grand Total:</span><strong id="grand_total_display">₹0.00</strong></div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea name="notes" id="notes" class="form-control" rows="2"
                              placeholder="Additional notes..."><?php echo htmlspecialchars($sale['notes'] ?? ''); ?></textarea>
                </div>

                <input type="hidden" name="total_amount" id="total_amount">
                <input type="hidden" name="cgst_total"   id="cgst_total">
                <input type="hidden" name="sgst_total"   id="sgst_total">
                <input type="hidden" name="igst_total"   id="igst_total">
                <input type="hidden" name="grand_total"  id="grand_total">
                <input type="hidden" name="items"        id="items_json">

                <div class="form-actions">
                    <button type="submit" class="btn btn-success btn-lg">💾 Update & Print Invoice</button>
                    <button type="button" class="btn btn-secondary btn-lg" onclick="window.location.href='index.php'">Cancel</button>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
const companyState = 'Uttarakhand';
const customerList = <?php echo json_encode(array_map(fn($c)=>['id'=>$c['id'],'name'=>$c['customer_name'],'gstin'=>$c['gstin'],'state'=>$c['state']], $customers)); ?>;
const products     = <?php echo json_encode($products); ?>;
let itemCounter = 0, items = [];

// Set initial state: null = manual, string = known state
let selectedCustomerState = <?php
    if (!empty($sale['customer_id'])) {
        $ec2 = $db->single("SELECT state FROM customers WHERE id=?", [$sale['customer_id']]);
        echo json_encode($ec2['state'] ?? '');
    } else { echo 'null'; }
?>;

// Show badge and GST selector on load if needed
<?php if ($existing_customer_name): ?>
document.getElementById('cust_badge').style.display = 'block';
<?php endif; ?>
<?php if (empty($sale['customer_id']) && !empty($sale['customer_name_manual'])): ?>
document.getElementById('gst_type_row').style.display = 'flex';
<?php endif; ?>

function searchCustomer(val) {
    const drop=document.getElementById('cust_drop'), hidId=document.getElementById('customer_id'),
          hidName=document.getElementById('customer_name_manual'), badge=document.getElementById('cust_badge');
    hidId.value=''; hidName.value=val.trim(); selectedCustomerState=null;
    badge.style.display='none';
    document.getElementById('gst_type_row').style.display='flex';
    if(!val.trim()){drop.style.display='none';return;}
    const matches=customerList.filter(c=>c.name.toLowerCase().includes(val.toLowerCase()));
    drop.innerHTML='';
    const md=document.createElement('div'); md.className='cust-item cust-manual';
    md.innerHTML=`✏️ Use <b>"${val}"</b> as manual customer name`;
    md.onmousedown=()=>{
        hidId.value=''; hidName.value=val.trim(); selectedCustomerState=null;
        document.getElementById('customer_search').value=val.trim();
        badge.textContent='✏️ Manual: '+val.trim(); badge.style.display='block';
        drop.style.display='none';
        document.getElementById('gst_type_row').style.display='flex';
        items.forEach(i=>calculateRow(i.id));
    };
    drop.appendChild(md);
    matches.forEach(c=>{
        const d=document.createElement('div'); d.className='cust-item';
        d.innerHTML=`👤 <strong>${c.name}</strong><span style="color:#94a3b8;font-size:11px;">${c.state?' — '+c.state:''}${c.gstin?' | '+c.gstin:''}</span>`;
        d.onmousedown=()=>{
            hidId.value=c.id; hidName.value=''; selectedCustomerState=c.state||'';
            document.getElementById('customer_search').value=c.name;
            badge.textContent='✅ '+c.name+(c.state?' ('+c.state+')':''); badge.style.display='block';
            drop.style.display='none';
            document.getElementById('gst_type_row').style.display='none';
            items.forEach(i=>calculateRow(i.id));
        };
        drop.appendChild(d);
    });
    drop.style.display='block';
}

function itemRowHTML(id,name,hsn,rate,gst,pid){
    return `<td><div class="product-search-container">
        <input type="text" class="product-search-input" id="search_${id}" value="${name}"
               placeholder="Search product or type manually..."
               oninput="searchProduct(${id},this.value)"
               onblur="setTimeout(()=>document.getElementById('suggestions_${id}').classList.remove('active'),200)"
               onfocus="if(this.value.length>=1)searchProduct(${id},this.value)">
        <input type="hidden" id="product_id_${id}" value="${pid}">
        <div class="product-suggestions" id="suggestions_${id}"></div>
    </div></td>
    <td><input type="text"   class="form-control" id="hsn_${id}"  value="${hsn}" placeholder="HSN"></td>
    <td><input type="number" class="form-control qty-input"  id="qty_${id}"  value="1" min="1" onchange="calculateRow(${id})"></td>
    <td><input type="number" class="form-control rate-input" id="rate_${id}" value="${rate}" step="0.01" onchange="calculateRow(${id})"></td>
    <td><input type="number" class="form-control gst-input"  id="gst_${id}"  value="${gst}"  step="0.01" onchange="calculateRow(${id})"></td>
    <td><strong id="amount_${id}">₹0.00</strong></td>
    <td><strong id="total_${id}">₹0.00</strong></td>
    <td><button type="button" class="btn-remove" onclick="removeItem(${id})">✕</button></td>`;
}

function searchProduct(itemId,val){
    const sd=document.getElementById('suggestions_'+itemId);
    if(!val.trim()){sd.classList.remove('active');return;}
    const m=products.filter(p=>p.product_name.toLowerCase().includes(val.toLowerCase())).slice(0,8);
    if(!m.length){sd.classList.remove('active');return;}
    sd.innerHTML=m.map(p=>`<div class="suggestion-item" onmousedown="selectSearchedProduct(${itemId},${p.id})">
        <span class="suggestion-name">${p.product_name}</span>
        <span class="suggestion-meta">HSN:${p.hsn_code} | ₹${p.rate} | GST:${p.gst_rate}% | Stock:${p.stock_quantity}</span>
    </div>`).join('');
    sd.classList.add('active');
}

function selectSearchedProduct(itemId,productId){
    const p=products.find(x=>x.id==productId), item=items.find(i=>i.id===itemId);
    if(!p||!item)return;
    item.product_id=p.id; item.product_name=p.product_name; item.hsn_code=p.hsn_code;
    item.rate=parseFloat(p.rate); item.gst_rate=parseFloat(p.gst_rate);
    document.getElementById('search_'+itemId).value=p.product_name;
    document.getElementById('product_id_'+itemId).value=p.id;
    document.getElementById('hsn_'+itemId).value=p.hsn_code;
    document.getElementById('rate_'+itemId).value=p.rate;
    document.getElementById('gst_'+itemId).value=p.gst_rate;
    document.getElementById('suggestions_'+itemId).classList.remove('active');
    calculateRow(itemId);
}

function globalSearchProduct(val){
    const sd=document.getElementById('globalSuggestions');
    if(!val.trim()){sd.classList.remove('active');return;}
    const m=products.filter(p=>p.product_name.toLowerCase().includes(val.toLowerCase())).slice(0,8);
    if(!m.length){sd.classList.remove('active');return;}
    sd.innerHTML=m.map(p=>`<div class="suggestion-item" onmousedown="addProductFromGlobalSearch(${p.id})">
        <span class="suggestion-name">${p.product_name}</span>
        <span class="suggestion-meta">HSN:${p.hsn_code} | ₹${p.rate} | GST:${p.gst_rate}%</span>
    </div>`).join('');
    sd.classList.add('active');
}

function addProductFromGlobalSearch(productId){
    const p=products.find(x=>x.id==productId); if(!p)return;
    itemCounter++;
    const row=document.createElement('tr'); row.id='item_'+itemCounter;
    row.innerHTML=itemRowHTML(itemCounter,p.product_name,p.hsn_code,p.rate,p.gst_rate,p.id);
    document.getElementById('itemsBody').appendChild(row);
    items.push({id:itemCounter,product_id:p.id,product_name:p.product_name,hsn_code:p.hsn_code,
        quantity:1,rate:parseFloat(p.rate),amount:0,gst_rate:parseFloat(p.gst_rate),cgst:0,sgst:0,igst:0,total:0});
    document.getElementById('globalProductSearch').value='';
    document.getElementById('globalSuggestions').classList.remove('active');
    calculateRow(itemCounter);
}

function addItem(){
    itemCounter++;
    const row=document.createElement('tr'); row.id='item_'+itemCounter;
    row.innerHTML=itemRowHTML(itemCounter,'','',0,18,'');
    document.getElementById('itemsBody').appendChild(row);
    items.push({id:itemCounter,product_id:'',product_name:'',hsn_code:'',quantity:1,rate:0,amount:0,gst_rate:18,cgst:0,sgst:0,igst:0,total:0});
}

function calculateRow(itemId){
    const item=items.find(i=>i.id===itemId); if(!item)return;
    item.product_name=document.getElementById('search_'+itemId).value||'Manual Entry';
    item.hsn_code=document.getElementById('hsn_'+itemId).value;
    item.quantity=parseFloat(document.getElementById('qty_'+itemId).value)||0;
    item.rate=parseFloat(document.getElementById('rate_'+itemId).value)||0;
    item.gst_rate=parseFloat(document.getElementById('gst_'+itemId).value)||0;
    item.amount=item.quantity*item.rate;

    let isInterState;
    if(selectedCustomerState===null){
        isInterState=document.getElementById('gst_type_selector').value==='igst';
    } else {
        isInterState=selectedCustomerState.trim().toLowerCase()!=='uttarakhand';
    }

    if(isInterState){item.igst=(item.amount*item.gst_rate)/100;item.cgst=0;item.sgst=0;}
    else{item.cgst=(item.amount*item.gst_rate)/200;item.sgst=(item.amount*item.gst_rate)/200;item.igst=0;}
    item.total=item.amount+item.cgst+item.sgst+item.igst;

    document.getElementById('amount_'+itemId).textContent='₹'+item.amount.toFixed(2);
    document.getElementById('total_'+itemId).textContent='₹'+item.total.toFixed(2);
    calculateTotals();
}

function removeItem(itemId){
    document.getElementById('item_'+itemId).remove();
    items=items.filter(i=>i.id!==itemId);
    calculateTotals();
}

function calculateTotals(){
    let subtotal=0,cgst=0,sgst=0,igst=0;
    items.forEach(i=>{subtotal+=i.amount;cgst+=i.cgst;sgst+=i.sgst;igst+=i.igst;});
    const gt=subtotal+cgst+sgst+igst;
    document.getElementById('subtotal_display').textContent='₹'+subtotal.toFixed(2);
    document.getElementById('cgst_display').textContent='₹'+cgst.toFixed(2);
    document.getElementById('sgst_display').textContent='₹'+sgst.toFixed(2);
    document.getElementById('igst_display').textContent='₹'+igst.toFixed(2);
    document.getElementById('grand_total_display').textContent='₹'+gt.toFixed(2);
    document.getElementById('total_amount').value=subtotal.toFixed(2);
    document.getElementById('cgst_total').value=cgst.toFixed(2);
    document.getElementById('sgst_total').value=sgst.toFixed(2);
    document.getElementById('igst_total').value=igst.toFixed(2);
    document.getElementById('grand_total').value=gt.toFixed(2);
}

// Load existing items
const existingItems=<?php echo json_encode($sale_items); ?>;
existingItems.forEach(ei=>{
    itemCounter++;
    const row=document.createElement('tr'); row.id='item_'+itemCounter;
    row.innerHTML=itemRowHTML(itemCounter,ei.product_name,ei.hsn_code,ei.rate,ei.gst_rate,ei.product_id||'');
    document.getElementById('itemsBody').appendChild(row);
    items.push({id:itemCounter,product_id:ei.product_id||'',product_name:ei.product_name,
        hsn_code:ei.hsn_code,quantity:parseFloat(ei.quantity),rate:parseFloat(ei.rate),
        amount:0,gst_rate:parseFloat(ei.gst_rate),cgst:0,sgst:0,igst:0,total:0});
    document.getElementById('qty_'+itemCounter).value=ei.quantity;
    calculateRow(itemCounter);
});

document.getElementById('salesForm').onsubmit=function(e){
    if(items.length===0){alert('Please add at least one item');e.preventDefault();return false;}
    if(!confirm('Update this invoice? Stock quantities will be adjusted.')){e.preventDefault();return false;}
    document.getElementById('items_json').value=JSON.stringify(items);
};

document.addEventListener('click',function(e){
    if(!e.target.closest('.product-search-container')&&!e.target.closest('.global-search-container')){
        document.querySelectorAll('.product-suggestions').forEach(d=>d.classList.remove('active'));
    }
});
</script>
</body>
</html>