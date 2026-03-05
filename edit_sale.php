<?php
require_once 'auth.php'; // Authentication check
require_once 'database.php';
$db = new Database();

$sale_id = $_GET['id'] ?? 0;

// Get sale data
$sale = $db->single("SELECT * FROM sales WHERE id = ?", [$sale_id]);
if (!$sale) {
    header("Location: index.php");
    exit;
}

// Get sale items
$sale_items = $db->fetchAll("SELECT * FROM sales_items WHERE sale_id = ?", [$sale_id]);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_sale') {
    try {
        $db->beginTransaction();
        
        // Restore stock from old items first
        foreach ($sale_items as $old_item) {
            if ($old_item['product_id']) {
                $db->query("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?", 
                          [$old_item['quantity'], $old_item['product_id']]);
            }
        }
        
        // Delete old sale items
        $db->query("DELETE FROM sales_items WHERE sale_id = ?", [$sale_id]);
        
        // Update sale
        $sale_sql = "UPDATE sales SET 
                     customer_id = ?,
                     sale_date = ?,
                     total_amount = ?,
                     cgst_amount = ?,
                     sgst_amount = ?,
                     igst_amount = ?,
                     grand_total = ?,
                     payment_status = ?,
                     notes = ?
                     WHERE id = ?";
        
        $db->query($sale_sql, [
            $_POST['customer_id'] ?: null,
            $_POST['sale_date'],
            $_POST['total_amount'],
            $_POST['cgst_total'],
            $_POST['sgst_total'],
            $_POST['igst_total'],
            $_POST['grand_total'],
            $_POST['payment_status'],
            $_POST['notes'],
            $sale_id
        ]);
        
        // Insert new sale items
        $items = json_decode($_POST['items'], true);
        foreach ($items as $item) {
            $item_sql = "INSERT INTO sales_items (sale_id, product_id, product_name, hsn_code, quantity, rate, amount, gst_rate, cgst, sgst, igst, total)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $db->query($item_sql, [
                $sale_id,
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
            
            // Update stock with new quantities
            if ($item['product_id']) {
                $db->query("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?", 
                          [$item['quantity'], $item['product_id']]);
            }
        }
        
        $db->commit();
        
        header("Location: invoice_print.php?id=" . $sale_id);
        exit;
        
    } catch (Exception $e) {
        $db->rollback();
        $error = "Error updating sale: " . $e->getMessage();
    }
}

// Get customers and products
$customers = $db->fetchAll("SELECT * FROM customers ORDER BY customer_name");
$products = $db->fetchAll("SELECT * FROM products ORDER BY product_name");
$company = $db->single("SELECT * FROM company_settings LIMIT 1");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Sales Invoice - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Autocomplete Styles */
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
            min-width: 400px;
        }

        .edit-header {
            background: #fef3c7;
            border: 2px solid #fbbf24;
            border-radius: 8px;
            padding: 12px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .edit-header-icon {
            font-size: 24px;
        }

        .edit-header-text h3 {
            margin: 0;
            color: #92400e;
            font-size: 16px;
        }

        .edit-header-text p {
            margin: 4px 0 0 0;
            color: #78350f;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <main class="main-content">
            <header class="top-bar">
                <h2 class="page-title">Edit Sales Invoice</h2>
                <div style="display: flex; gap: 12px;">
                    <a href="invoice_print.php?id=<?php echo $sale_id; ?>" class="btn btn-secondary" target="_blank">🖨️ Print</a>
                    <a href="index.php" class="btn btn-secondary">← Back</a>
                </div>
            </header>

            <div class="content-wrapper">
                <div class="edit-header">
                    <div class="edit-header-icon">⚠️</div>
                    <div class="edit-header-text">
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
                            <label for="customer_id">Customer *</label>
                            <select name="customer_id" id="customer_id" class="form-control">
                                <option value="">Walk-in Customer</option>
                                <?php foreach($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>" 
                                            data-gstin="<?php echo $customer['gstin']; ?>"
                                            data-state="<?php echo $customer['state']; ?>"
                                            <?php echo ($sale['customer_id'] == $customer['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['customer_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="sale_date">Invoice Date *</label>
                            <input type="date" name="sale_date" id="sale_date" class="form-control" value="<?php echo $sale['sale_date']; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="payment_status">Payment Status *</label>
                            <select name="payment_status" id="payment_status" class="form-control" required>
                                <option value="pending" <?php echo ($sale['payment_status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="partial" <?php echo ($sale['payment_status'] == 'partial') ? 'selected' : ''; ?>>Partial</option>
                                <option value="paid" <?php echo ($sale['payment_status'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                            </select>
                        </div>
                    </div>

                    <!-- Items Section -->
                    <div class="items-section">
                        <div class="section-header">
                            <h3>Invoice Items</h3>
                            <div style="display: flex; gap: 12px; align-items: center;">
                                <div class="global-search-container" style="position: relative; width: 350px;">
                                    <input type="text" 
                                           id="globalProductSearch" 
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
                                    <!-- Items will be loaded from database -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Totals Section -->
                    <div class="totals-section">
                        <div class="form-group full-width">
                            <label for="notes">Notes</label>
                            <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="Additional notes..."><?php echo htmlspecialchars($sale['notes']); ?></textarea>
                        </div>

                        <div class="totals-grid">
                            <div class="total-item">
                                <span>Subtotal:</span>
                                <strong id="subtotal_display">₹0.00</strong>
                            </div>
                            <div class="total-item">
                                <span>CGST:</span>
                                <strong id="cgst_display">₹0.00</strong>
                            </div>
                            <div class="total-item">
                                <span>SGST:</span>
                                <strong id="sgst_display">₹0.00</strong>
                            </div>
                            <div class="total-item">
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
                        <button type="submit" class="btn btn-success btn-lg">💾 Update & Print Invoice</button>
                        <button type="button" class="btn btn-secondary btn-lg" onclick="window.location.href='index.php'">Cancel</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        const products = <?php echo json_encode($products); ?>;
        const companyState = "<?php echo $company['state'] ?? 'Maharashtra'; ?>";
        const existingItems = <?php echo json_encode($sale_items); ?>;
        let itemCounter = 0;
        let items = [];

        // Global product search functionality
        function globalSearchProduct(query) {
            const suggestionsDiv = document.getElementById('globalSuggestions');
            
            if (!query || query.length < 1) {
                suggestionsDiv.classList.remove('active');
                return;
            }
            
            const filteredProducts = products.filter(p => {
                const searchTerm = query.toLowerCase();
                return p.product_name.toLowerCase().includes(searchTerm) || 
                       (p.hsn_code && p.hsn_code.toLowerCase().includes(searchTerm)) ||
                       (p.sku && p.sku.toLowerCase().includes(searchTerm));
            });
            
            if (filteredProducts.length === 0) {
                suggestionsDiv.innerHTML = '<div class="no-results">No products found</div>';
                suggestionsDiv.classList.add('active');
                return;
            }
            
            let html = '';
            filteredProducts.slice(0, 10).forEach(product => {
                html += `
                    <div class="product-suggestion-item" onclick="addProductFromGlobalSearch(${product.id})">
                        <div class="product-name">${product.product_name}</div>
                        <div class="product-details">
                            <span>HSN: ${product.hsn_code || 'N/A'}</span>
                            <span>Rate: ₹${parseFloat(product.rate).toFixed(2)}</span>
                            <span>GST: ${product.gst_rate}%</span>
                            <span>Stock: ${product.stock_quantity || 0}</span>
                        </div>
                    </div>
                `;
            });
            
            suggestionsDiv.innerHTML = html;
            suggestionsDiv.classList.add('active');
        }

        function showGlobalSuggestions() {
            const input = document.getElementById('globalProductSearch');
            if (input.value.length >= 1) {
                globalSearchProduct(input.value);
            }
        }

        function addProductFromGlobalSearch(productId) {
            const product = products.find(p => p.id == productId);
            
            if (product) {
                itemCounter++;
                const row = document.createElement('tr');
                row.id = 'item_' + itemCounter;
                row.innerHTML = `
                    <td>
                        <div class="product-search-container" id="search_container_${itemCounter}">
                            <input type="text" 
                                   class="product-search-input" 
                                   id="search_${itemCounter}"
                                   value="${product.product_name}"
                                   placeholder="🔍 Search product by name or HSN..."
                                   autocomplete="off"
                                   oninput="searchProduct(${itemCounter}, this.value)"
                                   onfocus="showSuggestions(${itemCounter})">
                            <div class="product-suggestions" id="suggestions_${itemCounter}"></div>
                            <input type="hidden" id="product_id_${itemCounter}" value="${product.id}">
                        </div>
                    </td>
                    <td><input type="text" class="form-control hsn-input" id="hsn_${itemCounter}" value="${product.hsn_code}" placeholder="HSN"></td>
                    <td><input type="number" class="form-control qty-input" id="qty_${itemCounter}" value="1" min="1" onchange="calculateRow(${itemCounter})"></td>
                    <td><input type="number" class="form-control rate-input" id="rate_${itemCounter}" value="${product.rate}" step="0.01" onchange="calculateRow(${itemCounter})"></td>
                    <td><input type="number" class="form-control gst-input" id="gst_${itemCounter}" value="${product.gst_rate}" step="0.01" onchange="calculateRow(${itemCounter})"></td>
                    <td><strong id="amount_${itemCounter}">₹0.00</strong></td>
                    <td><strong id="total_${itemCounter}">₹0.00</strong></td>
                    <td><button type="button" class="btn-remove" onclick="removeItem(${itemCounter})">✕</button></td>
                `;
                document.getElementById('itemsBody').appendChild(row);
                
                items.push({
                    id: itemCounter,
                    product_id: product.id,
                    product_name: product.product_name,
                    hsn_code: product.hsn_code,
                    quantity: 1,
                    rate: parseFloat(product.rate),
                    amount: 0,
                    gst_rate: parseFloat(product.gst_rate),
                    cgst: 0,
                    sgst: 0,
                    igst: 0,
                    total: 0
                });

                calculateRow(itemCounter);

                document.getElementById('globalProductSearch').value = '';
                document.getElementById('globalSuggestions').classList.remove('active');

                document.getElementById('qty_' + itemCounter).focus();
                document.getElementById('qty_' + itemCounter).select();
            }
        }

        function addItem() {
            itemCounter++;
            const row = document.createElement('tr');
            row.id = 'item_' + itemCounter;
            row.innerHTML = `
                <td>
                    <div class="product-search-container" id="search_container_${itemCounter}">
                        <input type="text" 
                               class="product-search-input" 
                               id="search_${itemCounter}"
                               placeholder="🔍 Search product by name or HSN..."
                               autocomplete="off"
                               oninput="searchProduct(${itemCounter}, this.value)"
                               onfocus="showSuggestions(${itemCounter})">
                        <div class="product-suggestions" id="suggestions_${itemCounter}"></div>
                        <input type="hidden" id="product_id_${itemCounter}">
                    </div>
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

            document.getElementById('search_' + itemCounter).focus();
        }

        function searchProduct(itemId, query) {
            const suggestionsDiv = document.getElementById('suggestions_' + itemId);
            
            if (!query || query.length < 1) {
                suggestionsDiv.classList.remove('active');
                return;
            }
            
            const filteredProducts = products.filter(p => {
                const searchTerm = query.toLowerCase();
                return p.product_name.toLowerCase().includes(searchTerm) || 
                       (p.hsn_code && p.hsn_code.toLowerCase().includes(searchTerm)) ||
                       (p.sku && p.sku.toLowerCase().includes(searchTerm));
            });
            
            if (filteredProducts.length === 0) {
                suggestionsDiv.innerHTML = '<div class="no-results">No products found</div>';
                suggestionsDiv.classList.add('active');
                return;
            }
            
            let html = '';
            filteredProducts.slice(0, 10).forEach(product => {
                html += `
                    <div class="product-suggestion-item" onclick="selectSearchedProduct(${itemId}, ${product.id})">
                        <div class="product-name">${product.product_name}</div>
                        <div class="product-details">
                            <span>HSN: ${product.hsn_code || 'N/A'}</span>
                            <span>Rate: ₹${parseFloat(product.rate).toFixed(2)}</span>
                            <span>GST: ${product.gst_rate}%</span>
                            <span>Stock: ${product.stock_quantity || 0}</span>
                        </div>
                    </div>
                `;
            });
            
            suggestionsDiv.innerHTML = html;
            suggestionsDiv.classList.add('active');
        }

        function showSuggestions(itemId) {
            const input = document.getElementById('search_' + itemId);
            if (input.value.length >= 1) {
                searchProduct(itemId, input.value);
            }
        }

        function selectSearchedProduct(itemId, productId) {
            const product = products.find(p => p.id == productId);
            const item = items.find(i => i.id === itemId);
            
            if (product && item) {
                item.product_id = product.id;
                item.product_name = product.product_name;
                item.hsn_code = product.hsn_code;
                item.rate = parseFloat(product.rate);
                item.gst_rate = parseFloat(product.gst_rate);
                
                document.getElementById('search_' + itemId).value = product.product_name;
                document.getElementById('product_id_' + itemId).value = product.id;
                document.getElementById('hsn_' + itemId).value = product.hsn_code;
                document.getElementById('rate_' + itemId).value = product.rate;
                document.getElementById('gst_' + itemId).value = product.gst_rate;
                
                document.getElementById('suggestions_' + itemId).classList.remove('active');
                
                calculateRow(itemId);
            }
        }

        function calculateRow(itemId) {
            const item = items.find(i => i.id === itemId);
            
            item.product_name = document.getElementById('search_' + itemId).value || 'Manual Entry';
            item.hsn_code = document.getElementById('hsn_' + itemId).value;
            item.quantity = parseFloat(document.getElementById('qty_' + itemId).value) || 0;
            item.rate = parseFloat(document.getElementById('rate_' + itemId).value) || 0;
            item.gst_rate = parseFloat(document.getElementById('gst_' + itemId).value) || 0;
            
            item.amount = item.quantity * item.rate;
            
            const customerSelect = document.getElementById('customer_id');
            const selectedCustomer = customerSelect.options[customerSelect.selectedIndex];
            const customerState = selectedCustomer ? selectedCustomer.dataset.state : companyState;
            const isInterState = customerState && customerState !== companyState;
            
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

        document.getElementById('salesForm').onsubmit = function(e) {
            if (items.length === 0) {
                alert('Please add at least one item');
                e.preventDefault();
                return false;
            }
            
            if (!confirm('Are you sure you want to update this invoice? Stock quantities will be adjusted.')) {
                e.preventDefault();
                return false;
            }
            
            document.getElementById('items_json').value = JSON.stringify(items);
        };

        // Load existing items
        existingItems.forEach(existingItem => {
            itemCounter++;
            const row = document.createElement('tr');
            row.id = 'item_' + itemCounter;
            row.innerHTML = `
                <td>
                    <div class="product-search-container" id="search_container_${itemCounter}">
                        <input type="text" 
                               class="product-search-input" 
                               id="search_${itemCounter}"
                               value="${existingItem.product_name}"
                               placeholder="🔍 Search product by name or HSN..."
                               autocomplete="off"
                               oninput="searchProduct(${itemCounter}, this.value)"
                               onfocus="showSuggestions(${itemCounter})">
                        <div class="product-suggestions" id="suggestions_${itemCounter}"></div>
                        <input type="hidden" id="product_id_${itemCounter}" value="${existingItem.product_id || ''}">
                    </div>
                </td>
                <td><input type="text" class="form-control hsn-input" id="hsn_${itemCounter}" value="${existingItem.hsn_code}" placeholder="HSN"></td>
                <td><input type="number" class="form-control qty-input" id="qty_${itemCounter}" value="${existingItem.quantity}" min="1" onchange="calculateRow(${itemCounter})"></td>
                <td><input type="number" class="form-control rate-input" id="rate_${itemCounter}" value="${existingItem.rate}" step="0.01" onchange="calculateRow(${itemCounter})"></td>
                <td><input type="number" class="form-control gst-input" id="gst_${itemCounter}" value="${existingItem.gst_rate}" step="0.01" onchange="calculateRow(${itemCounter})"></td>
                <td><strong id="amount_${itemCounter}">₹0.00</strong></td>
                <td><strong id="total_${itemCounter}">₹0.00</strong></td>
                <td><button type="button" class="btn-remove" onclick="removeItem(${itemCounter})">✕</button></td>
            `;
            document.getElementById('itemsBody').appendChild(row);
            
            items.push({
                id: itemCounter,
                product_id: existingItem.product_id || '',
                product_name: existingItem.product_name,
                hsn_code: existingItem.hsn_code,
                quantity: parseFloat(existingItem.quantity),
                rate: parseFloat(existingItem.rate),
                amount: 0,
                gst_rate: parseFloat(existingItem.gst_rate),
                cgst: 0,
                sgst: 0,
                igst: 0,
                total: 0
            });
            
            calculateRow(itemCounter);
        });

        document.getElementById('customer_id').onchange = function() {
            items.forEach(item => calculateRow(item.id));
        };

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.product-search-container') && !e.target.closest('.global-search-container')) {
                document.querySelectorAll('.product-suggestions').forEach(div => {
                    div.classList.remove('active');
                });
            }
        });

        document.addEventListener('keydown', function(e) {
            const activeInput = document.activeElement;
            
            if (activeInput && activeInput.classList.contains('product-search-input') && activeInput.id !== 'globalProductSearch') {
                const itemId = parseInt(activeInput.id.replace('search_', ''));
                const suggestionsDiv = document.getElementById('suggestions_' + itemId);
                
                if (e.key === 'Escape') {
                    suggestionsDiv.classList.remove('active');
                }
            }
            
            if (activeInput && activeInput.id === 'globalProductSearch') {
                const suggestionsDiv = document.getElementById('globalSuggestions');
                
                if (e.key === 'Escape') {
                    suggestionsDiv.classList.remove('active');
                    activeInput.value = '';
                }
            }
        });
    </script>
</body>
</html>
