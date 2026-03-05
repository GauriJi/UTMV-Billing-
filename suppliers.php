<?php
require_once 'auth.php'; // Authentication check
require_once 'database.php';
$db = new Database();

// Handle supplier operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add') {
            $sql = "INSERT INTO suppliers (supplier_name, contact_person, phone, email, address, city, state, pincode, gstin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $db->query($sql, [$_POST['supplier_name'], $_POST['contact_person'], $_POST['phone'], $_POST['email'], $_POST['address'], $_POST['city'], $_POST['state'], $_POST['pincode'], $_POST['gstin']]);
            $_SESSION['success'] = "Supplier added successfully!";
        } elseif ($_POST['action'] === 'delete') {
            $db->query("DELETE FROM suppliers WHERE id=?", [$_POST['id']]);
            $_SESSION['success'] = "Supplier deleted successfully!";
        }
        header("Location: suppliers.php");
        exit;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$suppliers = $db->fetchAll("SELECT * FROM suppliers ORDER BY supplier_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <main class="main-content">
            <header class="top-bar">
                <h2 class="page-title">Supplier Management</h2>
                <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='block'">+ Add Supplier</button>
            </header>

            <div class="content-wrapper">
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>

                <div class="table-section">
                    <h3 class="section-title">All Suppliers</h3>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Supplier Name</th>
                                    <th>Contact Person</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th>City, State</th>
                                    <th>GSTIN</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($suppliers as $supplier): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($supplier['supplier_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($supplier['contact_person']); ?></td>
                                    <td><?php echo htmlspecialchars($supplier['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($supplier['email']); ?></td>
                                    <td><?php echo htmlspecialchars($supplier['city'] . ', ' . $supplier['state']); ?></td>
                                    <td><?php echo htmlspecialchars($supplier['gstin']); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this supplier?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $supplier['id']; ?>">
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

    <!-- Add Supplier Modal -->
    <div id="addModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; overflow:auto;">
        <div style="background:white; margin:50px auto; padding:30px; max-width:700px; border-radius:12px;">
            <h2 style="margin-bottom:20px;">Add New Supplier</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <input type="text" name="supplier_name" class="form-control" placeholder="Supplier Name *" required style="grid-column:1/-1;">
                    <input type="text" name="contact_person" class="form-control" placeholder="Contact Person">
                    <input type="tel" name="phone" class="form-control" placeholder="Phone">
                    <input type="email" name="email" class="form-control" placeholder="Email" style="grid-column:1/-1;">
                    <input type="text" name="address" class="form-control" placeholder="Address" style="grid-column:1/-1;">
                    <input type="text" name="city" class="form-control" placeholder="City">
                    <input type="text" name="state" class="form-control" placeholder="State">
                    <input type="text" name="pincode" class="form-control" placeholder="Pincode">
                    <input type="text" name="gstin" class="form-control" placeholder="GSTIN (Optional)">
                </div>
                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button type="submit" class="btn btn-primary">Add Supplier</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        window.onclick = function(event) {
            const modal = document.getElementById('addModal');
            if (event.target == modal) modal.style.display = 'none';
        }
    </script>
</body>
</html>
