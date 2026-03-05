<?php
require_once 'auth.php'; // Authentication check
require_once 'database.php';
$db = new Database();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_purchase') {
    try {
        $db->beginTransaction();
        
        $settings = $db->single("SELECT * FROM company_settings LIMIT 1");
        $prefix = $settings['purchase_prefix'] ?? 'PUR';
        
        // Generate purchase number
        $last_purchase = $db->single("SELECT purchase_no FROM purchases ORDER BY id DESC LIMIT 1");
        if ($last_purchase) {
            $last_num = intval(substr($last_purchase['purchase_no'], strlen($prefix)));
            $purchase_no = $prefix . str_pad($last_num + 1, 5, '0', STR_PAD_LEFT);
        } else {
            $purchase_no = $prefix . '00001';
        }
        
        // Insert purchase
        $purchase_sql = "INSERT INTO purchases (purchase_no, supplier_id, purchase_date, total_amount, cgst_amount, sgst_amount, igst_amount, grand_total, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $db->query($purchase_sql, [
            $purchase_no,
            $_POST['supplier_id'] ?: null,
            $_POST['purchase_date'],
            $_POST['total_amount'],
            $_POST['cgst_total'],
            $_POST['sgst_total'],
            $_POST['igst_total'],
            $_POST['grand_total'],
            $_POST['notes']
        ]);
        
        $purchase_id = $db->lastInsertId();
        
        // Insert purchase items
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
            
            // Update stock
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

// Get suppliers and products
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
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <main class="main-content">
            <header class="top-bar">
                <h2 class="page-title">Purchase Entry</h2>
                <a href="index.php" class="btn btn-secondary">← Back to Dashboard</a>
            </header>

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
                        <div class="form-group">
                            <label for="supplier_id">Supplier *</label>
                            <select name="supplier_id" id="supplier_id" class="form-control" required>
                                <option value="">Select Supplier</option>
                                <?php foreach($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>" 
                                            data-gstin="<?php echo $supplier['gstin']; ?>"
                                            data-state="<?php echo $supplier['state']; ?>">
                                        <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="purchase_date">Purchase Date *</label>
                            <input type="date" name="purchase_date" id="purchase_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <!-- Items Section -->
                    <div class="items-section">
                        <div class="section-header">
                            <h3>Purchase Items</h3>
                            <button type="button" class="btn btn-primary" onclick="addItem()">+ Add Item</button>
                        </div>

                        <div class="table-container">
                            <table class="items-table" id="itemsTable">
                                <thead>
                                    <tr>
                                        <th style="width: 30%">Product</th>
                                        <th style="width: 12%">HSN Code</th>
                                        <th style="width: 10%">Qty</th>
                                        <th style="width: 12%">Rate</th>
                                        <th style="width: 8%">GST %</th>
                                        <th style="width: 12%">Amount</th>
                                        <th style="width: 12%">Total</th>
                                        <th style="width: 4%">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="itemsBody">
                                    <!-- Items will be added dynamically -->
                                </tbody>
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
                            <div class="total-item">
                                <span>Subtotal:</span>
                                <strong id="subtotal_display">₹0.00</strong>
                            </div>
                            <div class="total-item gst-item">
                                <span>CGST:</span>
                                <strong id="cgst_display">₹0.00</strong>
                            </div>
                            <div class="total-item gst-item">
                                <span>SGST:</span>
                                <strong id="sgst_display">₹0.00</strong>
                            </div>
                            <div class="total-item gst-item">
                                <span>IGST:</span>
                                <strong id="igst_display">₹0.00</strong>
                            </div>
                            <div class="total-item grand-total">
                                <span>Grand Total:</span>
                                <strong id="grand_total_display">₹0.00</strong>
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
                        <button type="submit" class="btn btn-success btn-lg">💾 Save Purchase Entry</button>
                        <button type="button" class="btn btn-secondary btn-lg" onclick="window.location.href='index.php'">Cancel</button>
                    </div>
                </form>

                <!-- Recent Purchases Table -->
                <div class="table-section" style="margin-top: 3rem;">
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
                                <tr>
                                    <td colspan="6" class="no-data">No purchases found</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach($recent_purchases as $purchase): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($purchase['purchase_no']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($purchase['supplier_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('d M Y', strtotime($purchase['purchase_date'])); ?></td>
                                        <td>₹<?php echo number_format($purchase['total_amount'], 2); ?></td>
                                        <td>₹<?php echo number_format($purchase['cgst_amount'] + $purchase['sgst_amount'] + $purchase['igst_amount'], 2); ?></td>
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

    <script>
        const products = <?php echo json_encode($products); ?>;
        const companyState = "<?php echo $company['state'] ?? 'Maharashtra'; ?>";
        let itemCounter = 0;
        let items = [];

        function addItem() {
            itemCounter++;
            const row = document.createElement('tr');
            row.id = 'item_' + itemCounter;
            row.innerHTML = `
                <td>
                    <select class="form-control product-select" onchange="selectProduct(this, ${itemCounter})" data-item="${itemCounter}">
                        <option value="">Select Product</option>
                        ${products.map(p => `<option value="${p.id}" data-hsn="${p.hsn_code}" data-rate="${p.rate}" data-gst="${p.gst_rate}">${p.product_name}</option>`).join('')}
                    </select>
                </td>
                <td><input type="text" class="form-control hsn-input" id="hsn_${itemCounter}" placeholder="HSN"></td>
                <td><input type="number" class="form-control qty-input" id="qty_${itemCounter}" value="1" min="1" onchange="calculateRow(${itemCounter})"></td>
                <td><input type="number" class="form-control rate-input" id="rate_${itemCounter}" value="0" step="0.01" onchange="calculateRow(${itemCounter})"></td>
                <td><input type="number" class="form-control gst-input" id="gst_${itemCounter}" value="18" step="0.01" onchange="calculateRow(${itemCounter})"></td>
                <td><strong id="amount_${itemCounter}">₹0.00</strong></td>
                <td><strong id="total_${itemCounter}">₹0.00</strong></td>
                <td><button type="button" class="btn-remove" onclick="removeItem(${itemCounter})">✕</button></td>
            `;
            document.getElementById('itemsBody').appendChild(row);
            
            items.push({
                id: itemCounter,
                product_id: '',
                product_name: '',
                hsn_code: '',
                quantity: 1,
                rate: 0,
                amount: 0,
                gst_rate: 18,
                cgst: 0,
                sgst: 0,
                igst: 0,
                total: 0
            });
        }

        function selectProduct(select, itemId) {
            const option = select.options[select.selectedIndex];
            const item = items.find(i => i.id === itemId);
            
            if (option.value) {
                item.product_id = option.value;
                item.product_name = option.text;
                item.hsn_code = option.dataset.hsn;
                item.rate = parseFloat(option.dataset.rate);
                item.gst_rate = parseFloat(option.dataset.gst);
                
                document.getElementById('hsn_' + itemId).value = item.hsn_code;
                document.getElementById('rate_' + itemId).value = item.rate;
                document.getElementById('gst_' + itemId).value = item.gst_rate;
                
                calculateRow(itemId);
            }
        }

        function calculateRow(itemId) {
            const item = items.find(i => i.id === itemId);
            const productSelect = document.querySelector(`select[data-item="${itemId}"]`);
            
            item.product_name = productSelect.options[productSelect.selectedIndex].text;
            item.hsn_code = document.getElementById('hsn_' + itemId).value;
            item.quantity = parseFloat(document.getElementById('qty_' + itemId).value) || 0;
            item.rate = parseFloat(document.getElementById('rate_' + itemId).value) || 0;
            item.gst_rate = parseFloat(document.getElementById('gst_' + itemId).value) || 0;
            
            item.amount = item.quantity * item.rate;
            
            const supplierSelect = document.getElementById('supplier_id');
            const selectedSupplier = supplierSelect.options[supplierSelect.selectedIndex];
            const supplierState = selectedSupplier ? selectedSupplier.dataset.state : companyState;
            const isInterState = supplierState && supplierState !== companyState;
            
            if (isInterState) {
                item.igst = (item.amount * item.gst_rate) / 100;
                item.cgst = 0;
                item.sgst = 0;
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

        function removeItem(itemId) {
            document.getElementById('item_' + itemId).remove();
            items = items.filter(i => i.id !== itemId);
            calculateTotals();
        }

        function calculateTotals() {
            let subtotal = 0;
            let cgst = 0;
            let sgst = 0;
            let igst = 0;
            
            items.forEach(item => {
                subtotal += item.amount;
                cgst += item.cgst;
                sgst += item.sgst;
                igst += item.igst;
            });
            
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

        document.getElementById('purchaseForm').onsubmit = function(e) {
            if (items.length === 0) {
                alert('Please add at least one item');
                e.preventDefault();
                return false;
            }
            document.getElementById('items_json').value = JSON.stringify(items);
        };

        addItem();

        document.getElementById('supplier_id').onchange = function() {
            items.forEach(item => calculateRow(item.id));
        };
    </script>
</body>
</html>
