<?php
require_once 'database.php';
$db = new Database();

$sale_id = $_GET['id'] ?? 0;

$sale = $db->single("SELECT s.*, 
    c.customer_name, c.address as c_address, c.city as c_city, 
    c.state as c_state, c.pincode as c_pincode, c.gstin as c_gstin, 
    c.phone as c_phone, c.contact_person as c_contact,
    s.customer_name_manual
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    WHERE s.id = ?", [$sale_id]);

if (!$sale) {
    die("Invoice not found");
}

$items = $db->fetchAll("SELECT si.*, p.unit FROM sales_items si LEFT JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?", [$sale_id]);
$company = $db->single("SELECT * FROM company_settings LIMIT 1");
$is_igst = $sale['igst_amount'] > 0;

$cust_name = $sale['customer_name'] ?? $sale['customer_name_manual'] ?? 'Walk-in Customer';

function numberToWords($num)
{
    $num = round($num, 2);
    $parts = explode('.', number_format($num, 2, '.', ''));
    $rupees = (int)str_replace(',', '', $parts[0]);
    $paise = (int)$parts[1];
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $cg = function ($n) use ($ones, $tens) {
        $out = '';
        if ($n >= 100) {
            $out .= $ones[intval($n / 100)] . ' Hundred ';
            $n %= 100;
        }
        if ($n >= 20) {
            $out .= $tens[intval($n / 10)] . ' ';
            $n %= 10;
        }
        if ($n > 0) {
            $out .= $ones[$n] . ' ';
        }
        return $out;
    };
    $words = '';
    if ($rupees >= 10000000) {
        $words .= $cg(intval($rupees / 10000000)) . 'Crore ';
        $rupees %= 10000000;
    }
    if ($rupees >= 100000) {
        $words .= $cg(intval($rupees / 100000)) . 'Lakh ';
        $rupees %= 100000;
    }
    if ($rupees >= 1000) {
        $words .= $cg(intval($rupees / 1000)) . 'Thousand ';
        $rupees %= 1000;
    }
    if ($rupees > 0) {
        $words .= $cg($rupees);
    }
    $result = 'INR ' . trim($words) . ' ';
    if ($paise > 0)
        $result .= 'and ' . $cg($paise) . 'Paise ';
    return trim($result) . ' Only';
}

$amount_in_words = numberToWords($sale['grand_total']);

$hsn_groups = [];
foreach ($items as $item) {
    $hsn = $item['hsn_code'] ?: '-';
    if (!isset($hsn_groups[$hsn])) {
        $hsn_groups[$hsn] = ['taxable' => 0, 'cgst_rate' => 0, 'cgst' => 0, 'sgst_rate' => 0, 'sgst' => 0, 'igst_rate' => 0, 'igst' => 0, 'total_tax' => 0];
    }
    $hsn_groups[$hsn]['taxable'] += $item['amount'];
    $hsn_groups[$hsn]['cgst_rate'] = $item['gst_rate'] / 2;
    $hsn_groups[$hsn]['cgst'] += $item['cgst'];
    $hsn_groups[$hsn]['sgst_rate'] = $item['gst_rate'] / 2;
    $hsn_groups[$hsn]['sgst'] += $item['sgst'];
    $hsn_groups[$hsn]['igst_rate'] = $item['gst_rate'];
    $hsn_groups[$hsn]['igst'] += $item['igst'];
    $hsn_groups[$hsn]['total_tax'] += $item['cgst'] + $item['sgst'] + $item['igst'];
}

$state_codes = [
    'jammu and kashmir' => '01', 'himachal pradesh' => '02', 'punjab' => '03', 'chandigarh' => '04',
    'uttarakhand' => '05', 'haryana' => '06', 'delhi' => '07', 'rajasthan' => '08', 'uttar pradesh' => '09',
    'bihar' => '10', 'sikkim' => '11', 'arunachal pradesh' => '12', 'nagaland' => '13', 'manipur' => '14',
    'mizoram' => '15', 'tripura' => '16', 'meghalaya' => '17', 'assam' => '18', 'west bengal' => '19',
    'jharkhand' => '20', 'odisha' => '21', 'chhattisgarh' => '22', 'madhya pradesh' => '23',
    'gujarat' => '24', 'dadra and nagar haveli' => '26', 'maharashtra' => '27', 'andhra pradesh' => '28',
    'karnataka' => '29', 'goa' => '30', 'lakshadweep' => '31', 'kerala' => '32', 'tamil nadu' => '33',
    'puducherry' => '34', 'andaman and nicobar islands' => '35', 'telangana' => '36',
];
$co_state_code = $state_codes[strtolower(trim($company['state'] ?? ''))] ?? '05';
$cu_state_code = $state_codes[strtolower(trim($sale['c_state'] ?? ''))] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice - <?php echo $sale['invoice_no']; ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: Arial, sans-serif; font-size:11px; background:#f0f0f0; padding:10px; color:#000; }

        .invoice-wrap { max-width:980px; margin:0 auto; background:#fff; border:2px solid #000; }

        .print-btn {
            position:fixed; top:20px; right:20px;
            background:#2563eb; color:#fff; border:none;
            padding:10px 20px; font-size:14px; border-radius:6px;
            cursor:pointer; z-index:999; box-shadow:0 2px 8px rgba(0,0,0,0.2);
        }
        .print-btn:hover { background:#1d4ed8; }

        /* Header */
        .inv-header { display:grid; grid-template-columns:130px 1fr 240px; border-bottom:1.5px solid #000; }
        .hdr-logo { padding:10px; border-right:1px solid #000; display:flex; align-items:center; justify-content:center; }
        .hdr-logo img { max-width:110px; max-height:60px; }
        .hdr-company { padding:8px 12px; border-right:1px solid #000; }
        .hdr-company .co-name { font-size:14px; font-weight:700; margin-bottom:3px; }
        .hdr-company p { line-height:1.5; color:#111; }
        .hdr-title { padding:8px; text-align:center; }
        .hdr-title .title-text { font-size:16px; font-weight:700; margin-bottom:6px; }
        .hdr-title .orig-badge { font-size:9px; font-weight:700; border:1px solid #000; padding:2px 6px; display:inline-block; margin-bottom:8px; }
        .hdr-inv-meta table { width:100%; border-collapse:collapse; }
        .hdr-inv-meta td { padding:2px 4px; vertical-align:middle; font-size:10px; text-align:left; }
        .hdr-inv-meta .lbl { font-weight:700; white-space:nowrap; width:1%; }
        .hdr-inv-meta .val { text-align:left; }

        /* Parties */
        .party-row { display:grid; grid-template-columns:1fr 1fr; border-bottom:1.5px solid #000; }
        .party-box { padding:8px 10px; }
        .party-box:first-child { border-right:1px solid #000; }
        .party-box .party-label { font-size:10px; font-weight:700; text-decoration:underline; margin-bottom:4px; }
        .party-box p { line-height:1.6; }
        .party-box .pname { font-size:12px; font-weight:700; }

        /* Items table */
        .items-table { width:100%; border-collapse:collapse; border-bottom:1.5px solid #000; }
        .items-table th {
            background:#000; color:#fff; padding:6px 5px; text-align:center;
            font-size:10px; font-weight:700; border:1px solid #000;
            print-color-adjust:exact; -webkit-print-color-adjust:exact;
        }
        .items-table td { padding:5px; border:1px solid #ccc; font-size:10px; vertical-align:top; }
        .items-table tbody tr:nth-child(even) { background:#fafafa; }
        .items-table tfoot td { font-weight:700; border-top:1.5px solid #000; border:1px solid #000; padding:5px; }
        .text-right  { text-align:right !important; }
        .text-center { text-align:center !important; }

        /* Editable fields — type before printing */
        .meta-input, .batch-input {
            border:none; border-bottom:1px dashed #aaa; background:transparent;
            font-size:10px; font-family:Arial,sans-serif; outline:none;
            color:#000; width:100%; text-align:left;
        }
        .batch-input { width:80px; text-align:center; font-style:italic; }
        .meta-input:focus, .batch-input:focus { border-bottom:1px solid #2563eb; }
        .meta-input::placeholder { color:#bbb; font-style:italic; }
        .batch-input::placeholder { color:#bbb; font-style:italic; }

        /* Amount row */
        .amount-row {
            display:flex; justify-content:space-between; align-items:center;
            padding:6px 10px; border-bottom:1.5px solid #000; background:#f8f8f8;
        }
        .amount-words { font-size:10px; }
        .amount-words strong { font-size:11px; }

        /* Tax table */
        .tax-section { border-bottom:1.5px solid #000; }
        .tax-section table { width:100%; border-collapse:collapse; }
        .tax-section th {
            background:#000; color:#fff; padding:5px 6px;
            font-size:10px; border:1px solid #000; text-align:center;
            print-color-adjust:exact; -webkit-print-color-adjust:exact;
        }
        .tax-section td { padding:5px 6px; border:1px solid #ccc; font-size:10px; text-align:center; }
        .tax-section tfoot td { font-weight:700; border-top:1.5px solid #000; border:1px solid #000; }

        /* Footer */
        .footer-row { display:grid; grid-template-columns:1fr 1fr; }
        .footer-left { padding:10px; border-right:1px solid #000; }
        .footer-left h4 { font-size:11px; font-weight:700; margin-bottom:6px; border-bottom:1px solid #ccc; padding-bottom:3px; }
        .footer-left p { line-height:1.8; font-size:10px; }
        .footer-right { padding:10px; }
        .footer-right h4 { font-size:11px; font-weight:700; margin-bottom:5px; text-align:right; }
        .sig-area { height:60px; }
        .footer-right p { font-size:10px; text-align:right; margin-top:4px; }

        .bottom-bar { text-align:center; padding:4px; font-size:9px; color:#555; font-style:italic; }

        @media print {
            body { background:#fff; padding:0; }
            .print-btn { display:none; }
            .invoice-wrap { max-width:100%; border:2px solid #000; box-shadow:none; }
            .items-table th, .tax-section th {
                background:#000 !important; color:#fff !important;
                print-color-adjust:exact !important; -webkit-print-color-adjust:exact !important;
            }
            /* On print, all inputs look like plain text — no border, no placeholder */
            .batch-input, .meta-input { border:none; }
            .meta-input::placeholder, .batch-input::placeholder { color:transparent; }
        }
    </style>
</head>
<body>

<button class="print-btn" onclick="window.print()">🖨️ Print / Save PDF</button>

<div class="invoice-wrap">

    <!-- HEADER -->
    <div class="inv-header">
        <div class="hdr-logo">
            <?php if (!empty($company['logo_path']) && file_exists($company['logo_path'])): ?>
                <img src="<?php echo htmlspecialchars($company['logo_path']); ?>" alt="Logo">
            <?php
else: ?>
                <img src="UTMV-LOGO.png" alt="Logo" onerror="this.style.display='none'">
            <?php
endif; ?>
        </div>

        <div class="hdr-company">
            <div class="co-name"><?php echo htmlspecialchars($company['company_name']); ?></div>
            <p><?php echo htmlspecialchars($company['address']); ?></p>
            <p><?php echo htmlspecialchars($company['city']); ?><?php if ($company['pincode']): ?> - <?php echo htmlspecialchars($company['pincode']); ?><?php
endif; ?></p>
            <p>Mob: <?php echo htmlspecialchars($company['phone']); ?></p>
            <?php if ($company['email']): ?><p>E-Mail: <?php echo htmlspecialchars($company['email']); ?></p><?php
endif; ?>
            <p>GSTIN/UIN: <strong><?php echo htmlspecialchars($company['gstin']); ?></strong></p>
            <p>State Name: <?php echo htmlspecialchars($company['state']); ?>, Code: <?php echo $co_state_code; ?></p>
        </div>

        <div class="hdr-title">
            <div class="title-text">Tax Invoice</div>
            <div class="orig-badge">ORIGINAL FOR RECIPIENT</div>
            <div class="hdr-inv-meta">
                <table>
                    <tr>
                        <td class="lbl">Invoice No.</td>
                        <td><?php echo htmlspecialchars($sale['invoice_no']); ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Dated</td>
                        <td><?php echo date('d-M-y', strtotime($sale['sale_date'])); ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Delivery Note</td>
                        <td><input type="text" class="meta-input" placeholder="Enter delivery note..."></td>
                    </tr>
                    <tr>
                        <td class="lbl">Buyer's Order No.</td>
                        <td><input type="text" class="meta-input" placeholder="Enter order no..."></td>
                    </tr>
                    <tr>
                        <td class="lbl">Dispatch Doc No.</td>
                        <td><input type="text" class="meta-input" placeholder="Enter doc no..."></td>
                    </tr>
                    <tr>
                        <td class="lbl">Dispatched through</td>
                        <td><input type="text" class="meta-input" placeholder="e.g. DTDC, By Hand..."></td>
                    </tr>
                    <tr>
                        <td class="lbl">Destination</td>
                        <td><input type="text" class="meta-input" value="<?php echo htmlspecialchars($sale['c_city'] ?? ''); ?>" placeholder="Destination city..."></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- CONSIGNEE / BUYER -->
    <div class="party-row">
        <div class="party-box">
            <div class="party-label">Consignee (Ship to)</div>
            <p class="pname"><?php echo htmlspecialchars($cust_name); ?></p>
            <?php if ($sale['c_address']): ?><p><?php echo htmlspecialchars($sale['c_address']); ?></p><?php
endif; ?>
            <?php if ($sale['c_city'] || $sale['c_state']): ?>
            <p><?php echo htmlspecialchars($sale['c_city']); ?><?php if ($sale['c_city'] && $sale['c_state']): ?>, <?php
    endif; ?><?php echo htmlspecialchars($sale['c_state'] ?? ''); ?></p>
            <?php
endif; ?>
            <?php if ($sale['c_phone']): ?><p>MOB: <?php echo htmlspecialchars($sale['c_phone']); ?></p><?php
endif; ?>
            <?php if ($sale['c_gstin']): ?><p>GSTIN/UIN: <strong><?php echo htmlspecialchars($sale['c_gstin']); ?></strong></p><?php
endif; ?>
            <?php if ($sale['c_state']): ?><p>State Name: <?php echo htmlspecialchars($sale['c_state']); ?><?php if ($cu_state_code): ?>, Code: <?php echo $cu_state_code; ?><?php
    endif; ?></p><?php
endif; ?>
        </div>
        <div class="party-box">
            <div class="party-label">Buyer (Bill to)</div>
            <p class="pname"><?php echo htmlspecialchars($cust_name); ?></p>
            <?php if ($sale['c_address']): ?><p><?php echo htmlspecialchars($sale['c_address']); ?></p><?php
endif; ?>
            <?php if ($sale['c_city'] || $sale['c_state']): ?>
            <p><?php echo htmlspecialchars($sale['c_city']); ?><?php if ($sale['c_city'] && $sale['c_state']): ?>, <?php
    endif; ?><?php echo htmlspecialchars($sale['c_state'] ?? ''); ?></p>
            <?php
endif; ?>
            <?php if ($sale['c_phone']): ?><p>MOB: <?php echo htmlspecialchars($sale['c_phone']); ?></p><?php
endif; ?>
            <?php if ($sale['c_gstin']): ?><p>GSTIN/UIN: <strong><?php echo htmlspecialchars($sale['c_gstin']); ?></strong></p><?php
endif; ?>
            <?php if ($sale['c_state']): ?><p>State Name: <?php echo htmlspecialchars($sale['c_state']); ?><?php if ($cu_state_code): ?>, Code: <?php echo $cu_state_code; ?><?php
    endif; ?></p><?php
endif; ?>
        </div>
    </div>

    <!-- ITEMS TABLE — includes Batch No column (manually editable before print) -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:4%">Sl No</th>
                <th style="width:30%">Description of Goods</th>
                <th style="width:10%">HSN/SAC</th>
                <th style="width:10%">Batch No.</th>
                <th style="width:8%">Quantity</th>
                <th style="width:5%">per</th>
                <th style="width:10%">Rate</th>
                <th style="width:5%">Disc %</th>
                <th style="width:12%">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php $sr = 1;
$total_qty = 0;
foreach ($items as $item):
    $total_qty += $item['quantity']; ?>
            <tr>
                <td class="text-center"><?php echo $sr++; ?></td>
                <td><strong><?php echo htmlspecialchars($item['product_name']); ?></strong></td>
                <td class="text-center"><?php echo htmlspecialchars($item['hsn_code']); ?></td>
                <td class="text-center">
                    <!-- Editable before printing — value is typed manually -->
                    <input type="text" class="batch-input" placeholder="Batch No." title="Type batch number before printing">
                </td>
                <td class="text-center"><?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['unit'] ?? 'Nos.'); ?></td>
                <td class="text-center"><?php echo htmlspecialchars($item['unit'] ?? 'Nos.'); ?></td>
                <td class="text-right">₹<?php echo number_format($item['rate'], 2); ?></td>
                <td class="text-center">-</td>
                <td class="text-right">₹<?php echo number_format($item['amount'], 2); ?></td>
            </tr>
            <?php
endforeach; ?>

            <?php for ($i = 0; $i < max(0, 8 - count($items)); $i++): ?>
            <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
            <?php
endfor; ?>

            <?php if ($is_igst): ?>
            <tr>
                <td colspan="7" style="text-align:right;font-weight:700;border:none;">IGST</td>
                <td></td>
                <td class="text-right" style="font-weight:700;">₹<?php echo number_format($sale['igst_amount'], 2); ?></td>
            </tr>
            <?php
else: ?>
            <tr>
                <td colspan="7" style="text-align:right;font-weight:700;border:none;">CGST</td>
                <td></td>
                <td class="text-right" style="font-weight:700;">₹<?php echo number_format($sale['cgst_amount'], 2); ?></td>
            </tr>
            <tr>
                <td colspan="7" style="text-align:right;font-weight:700;border:none;">SGST</td>
                <td></td>
                <td class="text-right" style="font-weight:700;">₹<?php echo number_format($sale['sgst_amount'], 2); ?></td>
            </tr>
            <?php
endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align:center;font-weight:700;">Total</td>
                <td></td>
                <td class="text-center" style="font-weight:700;"><?php echo $total_qty; ?> Nos.</td>
                <td></td><td></td><td></td>
                <td class="text-right" style="font-size:12px;">₹<?php echo number_format($sale['grand_total'], 2); ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- AMOUNT IN WORDS -->
    <div class="amount-row">
        <div class="amount-words">
            Amount Chargeable (in words)<br>
            <strong><?php echo $amount_in_words; ?></strong>
        </div>
        <div style="font-size:9px;color:#555;">E &amp; O.E</div>
    </div>

    <!-- TAX BREAKDOWN -->
    <div class="tax-section">
        <table>
            <thead>
                <tr>
                    <th rowspan="2">HSN/SAC</th>
                    <th rowspan="2">Taxable Value</th>
                    <?php if ($is_igst): ?>
                    <th colspan="2">IGST</th>
                    <?php
else: ?>
                    <th colspan="2">CGST</th>
                    <th colspan="2">SGST/UTGST</th>
                    <?php
endif; ?>
                    <th rowspan="2">Total Tax Amount</th>
                </tr>
                <tr>
                    <?php if ($is_igst): ?>
                    <th>Rate</th><th>Amount</th>
                    <?php
else: ?>
                    <th>Rate</th><th>Amount</th>
                    <th>Rate</th><th>Amount</th>
                    <?php
endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($hsn_groups as $hsn => $g): ?>
                <tr>
                    <td><?php echo htmlspecialchars($hsn); ?></td>
                    <td class="text-right">₹<?php echo number_format($g['taxable'], 2); ?></td>
                    <?php if ($is_igst): ?>
                    <td class="text-center"><?php echo $g['igst_rate']; ?>%</td>
                    <td class="text-right">₹<?php echo number_format($g['igst'], 2); ?></td>
                    <?php
    else: ?>
                    <td class="text-center"><?php echo $g['cgst_rate']; ?>%</td>
                    <td class="text-right">₹<?php echo number_format($g['cgst'], 2); ?></td>
                    <td class="text-center"><?php echo $g['sgst_rate']; ?>%</td>
                    <td class="text-right">₹<?php echo number_format($g['sgst'], 2); ?></td>
                    <?php
    endif; ?>
                    <td class="text-right">₹<?php echo number_format($g['total_tax'], 2); ?></td>
                </tr>
                <?php
endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td><strong>Total</strong></td>
                    <td class="text-right"><strong>₹<?php echo number_format($sale['total_amount'], 2); ?></strong></td>
                    <?php if ($is_igst): ?>
                    <td></td>
                    <td class="text-right"><strong>₹<?php echo number_format($sale['igst_amount'], 2); ?></strong></td>
                    <?php
else: ?>
                    <td></td>
                    <td class="text-right"><strong>₹<?php echo number_format($sale['cgst_amount'], 2); ?></strong></td>
                    <td></td>
                    <td class="text-right"><strong>₹<?php echo number_format($sale['sgst_amount'], 2); ?></strong></td>
                    <?php
endif; ?>
                    <td class="text-right"><strong>₹<?php echo number_format($sale['cgst_amount'] + $sale['sgst_amount'] + $sale['igst_amount'], 2); ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Tax amount in words -->
    <div style="padding:4px 10px;font-size:10px;border-bottom:1px solid #ccc;">
        Tax Amount (in words): <strong><?php echo numberToWords($sale['cgst_amount'] + $sale['sgst_amount'] + $sale['igst_amount']); ?></strong>
    </div>

    <!-- FOOTER: Bank Details + Signature -->
    <div class="footer-row">
        <div class="footer-left">
            <h4>Company's Bank Details</h4>
            <p><strong>A/c Name &nbsp;&nbsp;&nbsp;:</strong> UT MEDIA VENTURES</p>
            <p><strong>A/c No. &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:</strong> 024005002580</p>
            <p><strong>IFSC Code &nbsp;&nbsp;:</strong> ICIC0000240</p>
            <p><strong>Type &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:</strong> Current</p>
            <p><strong>Bank Name &nbsp;&nbsp;:</strong> ICICI Bank</p>
            <p><strong>Branch Name :</strong> Haldwani</p>
        </div>
        <div class="footer-right">
            <h4>for <?php echo htmlspecialchars($company['company_name']); ?></h4>
            <div class="sig-area"></div>
            <p><strong>Authorised Signatory</strong></p>
            <br><br>
            <p style="text-align:left;border-top:1px solid #ccc;padding-top:6px;margin-top:8px;">Customer's Seal and Signature</p>
        </div>
    </div>

    <?php if ($sale['notes']): ?>
    <div style="padding:6px 10px;font-size:10px;border-top:1px solid #ccc;">
        <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($sale['notes'])); ?>
    </div>
    <?php
endif; ?>

    <div class="bottom-bar">This is a Computer Generated Invoice</div>

</div>
</body>
</html>