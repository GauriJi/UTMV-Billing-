<?php
require_once 'database.php';
$db = new Database();

$sale_id = $_GET['id'] ?? 0;

// Get sale details
$sale = $db->single("SELECT s.*, c.customer_name, c.address as c_address, c.city as c_city, c.state as c_state, c.pincode as c_pincode, c.gstin as c_gstin, c.phone as c_phone 
                     FROM sales s 
                     LEFT JOIN customers c ON s.customer_id = c.id 
                     WHERE s.id = ?", [$sale_id]);

if (!$sale) {
    die("Invoice not found");
}

// Get sale items
$items = $db->fetchAll("SELECT * FROM sales_items WHERE sale_id = ?", [$sale_id]);

// Get company details
$company = $db->single("SELECT * FROM company_settings LIMIT 1");

// Calculate if inter-state or intra-state
$is_igst = $sale['igst_amount'] > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice - <?php echo $sale['invoice_no']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .invoice-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .invoice-header {
            text-align: center;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .invoice-header h1 {
            color: #2563eb;
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .invoice-header .tax-invoice {
    font-size: 40px;
    font-weight: 900;
    color: #000;
    text-align: center;
    letter-spacing: 3px;
    margin-top: 10px;
}
        
        .company-details {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .company-details h2 {
            color: #1e293b;
            font-size: 22px;
            margin-bottom: 10px;
        }
        
        .company-details p {
            color: #475569;
            line-height: 1.6;
            margin: 3px 0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-box {
            border: 1px solid #e2e8f0;
            padding: 15px;
            border-radius: 6px;
        }
        
        .info-box h3 {
            color: #2563eb;
            font-size: 14px;
            text-transform: uppercase;
            margin-bottom: 10px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 5px;
        }
        
        .info-box p {
            color: #334155;
            margin: 5px 0;
            font-size: 14px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .items-table thead {
            background: #2563eb;
            color: white;
        }
        
        .items-table th,
        .items-table td {
            padding: 12px 8px;
            text-align: left;
            border: 1px solid #e2e8f0;
            font-size: 13px;
        }
        
        .items-table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
        }
        
        .items-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }
        
        .items-table tbody tr:hover {
            background: #f1f5f9;
        }
        
        .text-right {
            text-align: right !important;
        }
        
        .text-center {
            text-align: center !important;
        }
        
        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 30px;
        }
        
        .totals-table {
            width: 350px;
            border-collapse: collapse;
        }
        
        .totals-table td {
            padding: 10px 15px;
            border: 1px solid #e2e8f0;
        }
        
        .totals-table tr.grand-total {
            background: #2563eb;
            color: white;
            font-weight: 700;
            font-size: 16px;
        }
        
        .totals-table tr.gst-row {
            background: #f1f5f9;
        }
        
        .footer-notes {
            border-top: 2px solid #e2e8f0;
            padding-top: 20px;
            margin-top: 40px;
        }
        
        .footer-notes h4 {
            color: #1e293b;
            margin-bottom: 10px;
        }
        
        .footer-notes p {
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
        }
        
        .signature-box {
            text-align: center;
        }
        
        .signature-box .line {
            width: 200px;
            border-top: 1px solid #333;
            margin-top: 50px;
            margin-bottom: 5px;
        }
        
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #2563eb;
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 16px;
            border-radius: 6px;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .print-btn:hover {
            background: #1d4ed8;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .invoice-container {
                box-shadow: none;
                padding: 20px;
            }
            
            .print-btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨️ Print Invoice</button>
    
    <div class="invoice-container">
        <div class="invoice-header">
    <div style="display:flex; align-items:center; justify-content:space-between;">
        <div>
            <img src="UTMV-LOGO.png" alt="Company Logo" style="height:70px;">
        </div>
        <div style="text-align:center; flex:1;">
            
            <div class="tax-invoice">TAX INVOICE</div>
        </div>
    </div>
</div>
        
        <div class="info-grid">

    <!-- Seller Details -->
    <div class="info-box">
        <h3>Seller Details</h3>
        <p><strong><?php echo htmlspecialchars($company['company_name']); ?></strong></p>
        <p><strong>Address:</strong> <?php echo htmlspecialchars($company['address']); ?></p>
        <p>
            <strong>City:</strong> <?php echo htmlspecialchars($company['city']); ?>, 
            <strong>State:</strong> <?php echo htmlspecialchars($company['state']); ?> - 
            <?php echo htmlspecialchars($company['pincode']); ?>
        </p>
        <p>
            <strong>Phone:</strong> <?php echo htmlspecialchars($company['phone']); ?> | 
            <strong>Email:</strong> <?php echo htmlspecialchars($company['email']); ?>
        </p>
        <p><strong>GSTIN:</strong> <?php echo htmlspecialchars($company['gstin']); ?></p>
    </div>

    <!-- Invoice Details -->
    <div class="info-box">
        <h3>Invoice Details</h3>
        <p><strong>Invoice No:</strong> <?php echo htmlspecialchars($sale['invoice_no']); ?></p>
        <p><strong>Invoice Date:</strong> <?php echo date('d-M-Y', strtotime($sale['sale_date'])); ?></p>
        <p>
            <strong>Payment Status:</strong> 
            <span style="color: <?php echo $sale['payment_status'] == 'paid' ? 'green' : 'orange'; ?>; font-weight: bold;">
                <?php echo strtoupper($sale['payment_status']); ?>
            </span>
        </p>
    </div>

    <!-- Bill To -->
    <div class="info-box">
        <h3>Bill To</h3>
        <p><strong><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer'); ?></strong></p>

        <?php if ($sale['c_address']): ?>
        <p><?php echo htmlspecialchars($sale['c_address']); ?></p>
        <p>
            <?php echo htmlspecialchars($sale['c_city']); ?>, 
            <?php echo htmlspecialchars($sale['c_state']); ?> - 
            <?php echo htmlspecialchars($sale['c_pincode']); ?>
        </p>
        <?php endif; ?>

        <?php if ($sale['c_phone']): ?>
        <p><strong>Phone:</strong> <?php echo htmlspecialchars($sale['c_phone']); ?></p>
        <?php endif; ?>

        <?php if ($sale['c_gstin']): ?>
        <p><strong>GSTIN:</strong> <?php echo htmlspecialchars($sale['c_gstin']); ?></p>
        <?php endif; ?>
    </div>

</div>
        
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%">S.No</th>
                    <th style="width: 35%">Product/Service</th>
                    <th style="width: 12%">HSN/SAC</th>
                    <th style="width: 8%" class="text-center">Qty</th>
                    <th style="width: 12%" class="text-right">Rate</th>
                    <th style="width: 8%" class="text-center">GST %</th>
                    <th style="width: 12%" class="text-right">Amount</th>
                    <th style="width: 8%" class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $sr = 1; foreach ($items as $item): ?>
                <tr>
                    <td class="text-center"><?php echo $sr++; ?></td>
                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td><?php echo htmlspecialchars($item['hsn_code']); ?></td>
                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                    <td class="text-right">₹<?php echo number_format($item['rate'], 2); ?></td>
                    <td class="text-center"><?php echo $item['gst_rate']; ?>%</td>
                    <td class="text-right">₹<?php echo number_format($item['amount'], 2); ?></td>
                    <td class="text-right"><strong>₹<?php echo number_format($item['total'], 2); ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td><strong>Taxable Amount</strong></td>
                    <td class="text-right"><strong>₹<?php echo number_format($sale['total_amount'], 2); ?></strong></td>
                </tr>
                <?php if ($is_igst): ?>
                <tr class="gst-row">
                    <td>IGST</td>
                    <td class="text-right">₹<?php echo number_format($sale['igst_amount'], 2); ?></td>
                </tr>
                <?php else: ?>
                <tr class="gst-row">
                    <td>CGST</td>
                    <td class="text-right">₹<?php echo number_format($sale['cgst_amount'], 2); ?></td>
                </tr>
                <tr class="gst-row">
                    <td>SGST</td>
                    <td class="text-right">₹<?php echo number_format($sale['sgst_amount'], 2); ?></td>
                </tr>
                <?php endif; ?>
                <tr class="grand-total">
                    <td>GRAND TOTAL</td>
                    <td class="text-right">₹<?php echo number_format($sale['grand_total'], 2); ?></td>
                </tr>
            </table>
        </div>
        
        <?php if ($sale['notes']): ?>
        <div class="footer-notes">
            <h4>Notes:</h4>
            <p><?php echo nl2br(htmlspecialchars($sale['notes'])); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="footer-notes">
            <h4>Terms & Conditions:</h4>
            <p>1. Goods once sold will not be taken back or exchanged</p>
            <p>2. All disputes are subject to local jurisdiction only</p>
            <p>3. Payment due within 30 days from the date of invoice</p>
        </div>
        
        <div class="signature-section">
            <div class="signature-box">
                <p><strong>Customer Signature</strong></p>
                <div class="line"></div>
            </div>
            <div class="signature-box">
                <p><strong>Authorized Signatory</strong></p>
                <div class="line"></div>
                <p style="margin-top: 10px;">For <?php echo htmlspecialchars($company['company_name']); ?></p>
            </div>
        </div>
    </div>
</body>
</html>
