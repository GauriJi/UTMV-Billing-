<?php
require_once 'auth.php'; // Authentication check
require_once 'database.php';
$db = new Database();

// Handle product operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add') {
                $sql = "INSERT INTO products (product_name, hsn_code, unit, rate, gst_rate, stock_quantity) VALUES (?, ?, ?, ?, ?, ?)";
                $db->query($sql, [$_POST['product_name'], $_POST['hsn_code'], $_POST['unit'], $_POST['rate'], $_POST['gst_rate'], $_POST['stock_quantity']]);
                $_SESSION['success'] = "Product added successfully!";
            } elseif ($_POST['action'] === 'edit') {
                $sql = "UPDATE products SET product_name=?, hsn_code=?, unit=?, rate=?, gst_rate=?, stock_quantity=? WHERE id=?";
                $db->query($sql, [$_POST['product_name'], $_POST['hsn_code'], $_POST['unit'], $_POST['rate'], $_POST['gst_rate'], $_POST['stock_quantity'], $_POST['id']]);
                $_SESSION['success'] = "Product updated successfully!";
            } elseif ($_POST['action'] === 'delete') {
                $db->query("DELETE FROM products WHERE id=?", [$_POST['id']]);
                $_SESSION['success'] = "Product deleted successfully!";
            }
            header("Location: products.php");
            exit;
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
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
            <header class="top-bar">
                <h2 class="page-title">Product Management</h2>
                <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='block'">+ Add Product</button>
            </header>

            <div class="content-wrapper">
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>

                <div class="table-section">
                    <h3 class="section-title">All Products</h3>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>HSN Code</th>
                                    <th>Unit</th>
                                    <th>Rate (₹)</th>
                                    <th>GST %</th>
                                    <th>Stock</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($products as $product): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($product['product_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($product['hsn_code']); ?></td>
                                    <td><?php echo htmlspecialchars($product['unit']); ?></td>
                                    <td>₹<?php echo number_format($product['rate'], 2); ?></td>
                                    <td><?php echo $product['gst_rate']; ?>%</td>
                                    <td><span style="color: <?php echo $product['stock_quantity'] < 10 ? 'red' : 'green'; ?>; font-weight: bold;"><?php echo $product['stock_quantity']; ?></span></td>
                                    <td>
                                        <button class="btn-icon" onclick='editProduct(<?php echo json_encode($product); ?>)' title="Edit">✏️</button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this product?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                            <button type="submit" class="btn-icon" title="Delete">🗑️</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Product Modal -->
    <div id="addModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; overflow:auto;">
        <div style="background:white; margin:50px auto; padding:30px; max-width:600px; border-radius:12px;">
            <h2 style="margin-bottom:20px;">Add New Product</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div style="display:grid; gap:15px;">
                    <input type="text" name="product_name" class="form-control" placeholder="Product Name *" required>
                    <input type="text" name="hsn_code" class="form-control" placeholder="HSN Code">
                    <input type="text" name="unit" class="form-control" placeholder="Unit (PCS/KG/Service)" value="PCS">
                    <input type="number" name="rate" class="form-control" placeholder="Rate *" step="0.01" required>
                    <input type="number" name="gst_rate" class="form-control" placeholder="GST Rate %" step="0.01" value="18">
                    <input type="number" name="stock_quantity" class="form-control" placeholder="Stock Quantity" value="0">
                    <div style="display:flex; gap:10px;">
                        <button type="submit" class="btn btn-primary">Add Product</button>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; overflow:auto;">
        <div style="background:white; margin:50px auto; padding:30px; max-width:600px; border-radius:12px;">
            <h2 style="margin-bottom:20px;">Edit Product</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div style="display:grid; gap:15px;">
                    <input type="text" name="product_name" id="edit_name" class="form-control" placeholder="Product Name *" required>
                    <input type="text" name="hsn_code" id="edit_hsn" class="form-control" placeholder="HSN Code">
                    <input type="text" name="unit" id="edit_unit" class="form-control" placeholder="Unit">
                    <input type="number" name="rate" id="edit_rate" class="form-control" placeholder="Rate *" step="0.01" required>
                    <input type="number" name="gst_rate" id="edit_gst" class="form-control" placeholder="GST Rate %" step="0.01">
                    <input type="number" name="stock_quantity" id="edit_stock" class="form-control" placeholder="Stock Quantity">
                    <div style="display:flex; gap:10px;">
                        <button type="submit" class="btn btn-primary">Update Product</button>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        function editProduct(product) {
            document.getElementById('edit_id').value = product.id;
            document.getElementById('edit_name').value = product.product_name;
            document.getElementById('edit_hsn').value = product.hsn_code;
            document.getElementById('edit_unit').value = product.unit;
            document.getElementById('edit_rate').value = product.rate;
            document.getElementById('edit_gst').value = product.gst_rate;
            document.getElementById('edit_stock').value = product.stock_quantity;
            document.getElementById('editModal').style.display = 'block';
        }

        // Close modals on outside click
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            if (event.target == addModal) addModal.style.display = 'none';
            if (event.target == editModal) editModal.style.display = 'none';
        }
    </script>
</body>
</html>
