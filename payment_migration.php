<?php
require_once 'auth.php';
require_once 'database.php';
$db = new Database();
$conn = $db->getConnection();

$results = [];
$errors = [];

$queries = [
    "Add payment_status to purchases" => "ALTER TABLE purchases ADD COLUMN IF NOT EXISTS payment_status ENUM('pending','partial','paid') DEFAULT 'pending'",

    "Create payment_receipts table" => "CREATE TABLE IF NOT EXISTS payment_receipts (
        id INT PRIMARY KEY AUTO_INCREMENT,
        sale_id INT NOT NULL,
        receipt_date DATE NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        payment_mode ENUM('Cash','Bank Transfer','UPI','Cheque','NEFT','RTGS','Other') DEFAULT 'Cash',
        reference_no VARCHAR(100) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )",

    "Create payment_disbursements table" => "CREATE TABLE IF NOT EXISTS payment_disbursements (
        id INT PRIMARY KEY AUTO_INCREMENT,
        purchase_id INT NOT NULL,
        payment_date DATE NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        payment_mode ENUM('Cash','Bank Transfer','UPI','Cheque','NEFT','RTGS','Other') DEFAULT 'Cash',
        reference_no VARCHAR(100) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )",
];

foreach ($queries as $label => $sql) {
    try {
        $conn->exec($sql);
        $results[] = "✅ $label — OK";
    } catch (PDOException $e) {
        $errors[] = "❌ $label — " . $e->getMessage();
    }
}

// Also update payment_status on all existing purchases based on disbursements (none yet, so all pending)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Module Migration</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .migration-box { max-width:700px; margin:60px auto; background:white; border-radius:16px; padding:40px; box-shadow:0 4px 20px rgba(0,0,0,0.1); }
        .migration-box h2 { color:#1e293b; margin-bottom:24px; }
        .result-item { padding:10px 16px; border-radius:8px; margin-bottom:10px; font-weight:500; font-family:monospace; }
        .result-ok  { background:#d1fae5; color:#065f46; }
        .result-err { background:#fee2e2; color:#991b1b; }
        .done-msg { background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:20px; margin-top:24px; text-align:center; color:#1e40af; font-size:15px; }
    </style>
</head>
<body>
<div class="migration-box">
    <h2>🗄️ Payment Module — Database Migration</h2>
    <?php foreach ($results as $r): ?>
        <div class="result-item result-ok"><?php echo $r; ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $e): ?>
        <div class="result-item result-err"><?php echo $e; ?></div>
    <?php endforeach; ?>

    <?php if (empty($errors)): ?>
    <div class="done-msg">
        <strong>🎉 Migration Complete!</strong><br>
        All tables are ready. You can now use the Payment Tracking module.<br><br>
        <a href="payments_received.php" class="btn btn-primary" style="margin-right:10px;">💳 Payments Received</a>
        <a href="payments_made.php" class="btn btn-primary" style="margin-right:10px;">💸 Payments Made</a>
        <a href="pnl_report.php" class="btn btn-primary">📉 P&L Report</a>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
