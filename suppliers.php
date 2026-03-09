<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'india_states.php';
requireAdmin();
$db = new Database();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add') {
            $sql = "INSERT INTO suppliers (supplier_name,contact_person,phone,email,address,city,state,pincode,gstin) VALUES (?,?,?,?,?,?,?,?,?)";
            $db->query($sql, [
                $_POST['supplier_name'], $_POST['contact_person'], $_POST['phone'],
                $_POST['email'], $_POST['address'],
                $_POST['district'],  // city field stores district
                $_POST['state'], $_POST['pincode'], $_POST['gstin']
            ]);
            $_SESSION['success'] = "Supplier added successfully!";
        } elseif ($_POST['action'] === 'delete') {
            $db->query("DELETE FROM suppliers WHERE id=?", [$_POST['id']]);
            $_SESSION['success'] = "Supplier deleted successfully!";
        }
        header("Location: suppliers.php"); exit;
    } catch (Exception $e) { $error = "Error: " . $e->getMessage(); }
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
        <?php $page_title = '🏭 Supplier Management'; include 'topbar.php'; ?>
        <div class="content-wrapper">
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if(isset($error)): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

            <div class="table-section">
                <div class="section-header">
                    <h3 class="section-title">All Suppliers</h3>
                    <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='flex'">+ Add Supplier</button>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead><tr>
                            <th>Supplier Name</th><th>Contact Person</th><th>Phone</th>
                            <th>Email</th><th>District, State</th><th>GSTIN</th><th>Actions</th>
                        </tr></thead>
                        <tbody>
                            <?php foreach($suppliers as $s): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($s['supplier_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($s['contact_person']); ?></td>
                                <td><?php echo htmlspecialchars($s['phone']); ?></td>
                                <td><?php echo htmlspecialchars($s['email']); ?></td>
                                <td><?php echo htmlspecialchars($s['city'].', '.$s['state']); ?></td>
                                <td><code><?php echo htmlspecialchars($s['gstin']); ?></code></td>
                                <td>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                        <button type="submit" class="btn-icon" onclick="return confirm('Delete this supplier?')" title="Delete">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($suppliers)): ?><tr><td colspan="7" class="no-data">No suppliers found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Supplier Modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(4px);">
    <div style="background:white;border-radius:20px;padding:32px;width:600px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(99,102,241,0.25);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
            <h3 style="font-size:20px;font-weight:700;background:linear-gradient(135deg,#f97316,#f59e0b);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">➕ Add New Supplier</h3>
            <button onclick="document.getElementById('addModal').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;" >✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div class="form-group"><label>Supplier Name *</label><input type="text" name="supplier_name" class="form-control" required placeholder="Full business name"></div>
                <div class="form-group"><label>Contact Person</label><input type="text" name="contact_person" class="form-control" placeholder="Primary contact name"></div>
                <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control" placeholder="+91 98765 43210"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" placeholder="email@supplier.com"></div>
                <div class="form-group"><label>District</label><input type="text" name="district" class="form-control" placeholder="e.g. Nainital"></div>
                <div class="form-group"><label>State</label><?php echo india_states_select('state', 'Uttarakhand', 'form-control'); ?></div>
                <div class="form-group"><label>Pincode</label><input type="text" name="pincode" class="form-control" placeholder="263001" maxlength="6"></div>
                <div class="form-group"><label>GSTIN</label><input type="text" name="gstin" class="form-control" placeholder="22AAAAA0000A1Z5" style="text-transform:uppercase"></div>
                <div class="form-group full-width"><label>Address</label><textarea name="address" class="form-control" rows="2" placeholder="Street / Area / Colony"></textarea></div>
            </div>
            <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid rgba(99,102,241,0.1);">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">✅ Add Supplier</button>
            </div>
        </form>
    </div>
</div>
<script src="script.js"></script>
</body>
</html>