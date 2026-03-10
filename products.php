<?php
require_once 'auth.php';
require_once 'database.php';
$db = new Database();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add') {
            $db->query("INSERT INTO products (product_name, hsn_code, unit, rate, gst_rate, stock_quantity, batch_no) VALUES (?,?,?,?,?,?,?)",
            [$_POST['product_name'], $_POST['hsn_code'], $_POST['unit'], $_POST['rate'], $_POST['gst_rate'], $_POST['stock_quantity'], $_POST['batch_no'] ?? '']);
            $_SESSION['success'] = "Product added successfully!";
        }
        elseif ($_POST['action'] === 'edit') {
            $db->query("UPDATE products SET product_name=?, hsn_code=?, unit=?, rate=?, gst_rate=?, stock_quantity=?, batch_no=? WHERE id=?",
            [$_POST['product_name'], $_POST['hsn_code'], $_POST['unit'], $_POST['rate'], $_POST['gst_rate'], $_POST['stock_quantity'], $_POST['batch_no'] ?? '', $_POST['id']]);
            $_SESSION['success'] = "Product updated successfully!";
        }
        elseif ($_POST['action'] === 'delete') {
            $db->query("DELETE FROM products WHERE id=?", [$_POST['id']]);
            $_SESSION['success'] = "Product deleted successfully!";
        }
        header("Location: products.php");
        exit;
    }
    catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$products = $db->fetchAll("SELECT * FROM products ORDER BY product_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-container">
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <?php $page_title = 'Product Management';
include 'topbar.php'; ?>
        <div class="content-wrapper">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success'];
    unset($_SESSION['success']); ?></div>
            <?php
endif; ?>
            <?php if (isset($error)): ?><div class="alert alert-error"><?php echo $error; ?></div><?php
endif; ?>

            <div class="table-section">
                <div class="section-header">
                    <h3 class="section-title">All Products</h3>
                    <?php if (isAdmin()): ?>
                    <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='flex'">+ Add Product</button>
                    <?php
endif; ?>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>HSN Code</th>
                                <th>Batch No.</th>
                                <th>Unit</th>
                                <th>Rate (₹)</th>
                                <th>GST %</th>
                                <th>Stock</th>
                                <?php if (isAdmin()): ?><th>Actions</th><?php
endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($p['product_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($p['hsn_code']); ?></td>
                                <td><?php echo htmlspecialchars($p['batch_no'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($p['unit']); ?></td>
                                <td>₹<?php echo number_format($p['rate'], 2); ?></td>
                                <td><?php echo $p['gst_rate']; ?>%</td>
                                <td style="color:<?php echo $p['stock_quantity'] < 10 ? '#ef4444' : '#10b981'; ?>;font-weight:700;">
                                    <?php echo $p['stock_quantity']; ?>
                                </td>
                                <?php if (isAdmin()): ?>
                                <td style="display:flex;gap:6px;">
                                    <button class="btn-icon" title="Edit"
                                        onclick="openEdit(<?php echo htmlspecialchars(json_encode($p)); ?>)">✏️</button>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" class="btn-icon" onclick="return confirm('Delete product?')" title="Delete">🗑️</button>
                                    </form>
                                </td>
                                <?php
    endif; ?>
                            </tr>
                            <?php
endforeach; ?>
                            <?php if (empty($products)): ?>
                            <tr><td colspan="8" class="no-data">No products found.</td></tr>
                            <?php
endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php if (isAdmin()): ?>
<!-- Add Modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:16px;padding:28px;width:520px;max-width:95vw;">
        <h3 style="margin-bottom:20px;">➕ Add New Product</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div class="form-group full-width">
                    <label>Product Name *</label>
                    <input type="text" name="product_name" class="form-control" maxlength="500" required>
                </div>
                <div class="form-group">
                    <label>HSN Code</label>
                    <input type="text" name="hsn_code" class="form-control">
                </div>
                <div class="form-group">
                    <label>Batch No.</label>
                    <input type="text" name="batch_no" class="form-control" placeholder="e.g. BT-2024-001">
                </div>
                <div class="form-group">
                    <label>Unit</label>
                    <select name="unit" class="form-control">
                        <option>PCS</option><option>KG</option><option>LTR</option>
                        <option>MTR</option><option>BOX</option><option>Service</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Rate (₹) *</label>
                    <input type="number" name="rate" step="0.01" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>GST Rate %</label>
                    <select name="gst_rate" class="form-control">
                        <option value="0">0%</option><option value="5">5%</option>
                        <option value="12">12%</option><option value="18" selected>18%</option>
                        <option value="28">28%</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Stock Quantity</label>
                    <input type="number" name="stock_quantity" class="form-control" value="0">
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">✅ Add Product</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:16px;padding:28px;width:520px;max-width:95vw;">
        <h3 style="margin-bottom:20px;">✏️ Edit Product</h3>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-grid">
                <div class="form-group full-width">
                    <label>Product Name *</label>
                    <input type="text" name="product_name" id="edit_name" class="form-control" maxlength="500" required>
                </div>
                <div class="form-group">
                    <label>HSN Code</label>
                    <input type="text" name="hsn_code" id="edit_hsn" class="form-control">
                </div>
                <div class="form-group">
                    <label>Batch No.</label>
                    <input type="text" name="batch_no" id="edit_batch" class="form-control" placeholder="e.g. BT-2024-001">
                </div>
                <div class="form-group">
                    <label>Unit</label>
                    <select name="unit" id="edit_unit" class="form-control">
                        <option>PCS</option><option>KG</option><option>LTR</option>
                        <option>MTR</option><option>BOX</option><option>Service</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Rate (₹)</label>
                    <input type="number" name="rate" id="edit_rate" step="0.01" class="form-control">
                </div>
                <div class="form-group">
                    <label>GST Rate %</label>
                    <select name="gst_rate" id="edit_gst" class="form-control">
                        <option value="0">0%</option><option value="5">5%</option>
                        <option value="12">12%</option><option value="18">18%</option>
                        <option value="28">28%</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Stock Quantity</label>
                    <input type="number" name="stock_quantity" id="edit_stock" class="form-control">
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php
endif; ?>

<script src="script.js"></script>
<script>
function openEdit(p) {
    document.getElementById('edit_id').value    = p.id;
    document.getElementById('edit_name').value  = p.product_name;
    document.getElementById('edit_hsn').value   = p.hsn_code  || '';
    document.getElementById('edit_batch').value = p.batch_no  || '';
    document.getElementById('edit_unit').value  = p.unit;
    document.getElementById('edit_rate').value  = p.rate;
    document.getElementById('edit_gst').value   = p.gst_rate;
    document.getElementById('edit_stock').value = p.stock_quantity;
    document.getElementById('editModal').style.display = 'flex';
}
</script>
</body>
</html>