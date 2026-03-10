<?php
require_once 'database.php';
$db = new Database();

$purchase_id = $_GET['id'] ?? 0;

$purchase = $db->single("SELECT p.*,
    s.supplier_name, s.address as s_address, s.city as s_city,
    s.state as s_state, s.pincode as s_pincode, s.gstin as s_gstin,
    s.phone as s_phone,
    p.supplier_name_manual
    FROM purchases p
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE p.id = ?", [$purchase_id]);

if (!$purchase) {
    die("Purchase not found");
}

$items = $db->fetchAll("SELECT pi.*, p.unit FROM purchase_items pi LEFT JOIN products p ON pi.product_id = p.id WHERE pi.purchase_id = ?", [$purchase_id]);
$company = $db->single("SELECT * FROM company_settings LIMIT 1");
$is_igst = $purchase['igst_amount'] > 0;

$supp_name = $purchase['supplier_name'] ?? $purchase['supplier_name_manual'] ?? 'N/A';

// Billing: prefer saved bill_ fields, fall back to supplier DB fields
$bill_address = $purchase['bill_address'] ?: ($purchase['s_address'] ?? '');
$bill_city = $purchase['bill_city'] ?: ($purchase['s_city'] ?? '');
$bill_state = $purchase['bill_state'] ?: ($purchase['s_state'] ?? '');
$bill_pincode = $purchase['bill_pincode'] ?: ($purchase['s_pincode'] ?? '');
$bill_phone = $purchase['bill_phone'] ?: ($purchase['s_phone'] ?? '');
$bill_gstin = $purchase['bill_gstin'] ?: ($purchase['s_gstin'] ?? '');

// Shipping
$ship_name = $purchase['ship_name'] ?: $supp_name;
$ship_address = $purchase['ship_address'] ?: $bill_address;
$ship_city = $purchase['ship_city'] ?: $bill_city;
$ship_state = $purchase['ship_state'] ?: $bill_state;
$ship_pincode = $purchase['ship_pincode'] ?: $bill_pincode;
$ship_phone = $purchase['ship_phone'] ?: $bill_phone;
$ship_gstin = $purchase['ship_gstin'] ?: $bill_gstin;

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

$amount_in_words = numberToWords($purchase['grand_total']);

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
$cu_state_code = $state_codes[strtolower(trim($bill_state))] ?? '';
$cu_state_code_ship = $state_codes[strtolower(trim($ship_state))] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order - <?php echo htmlspecialchars($purchase['purchase_no']); ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',Arial,sans-serif; font-size:10px; background:#f5f3ff; padding:14px; color:#1e293b; }

        /* ── Outer wrapper ── */
        .invoice-wrap {
            max-width:960px; margin:0 auto; background:#fff;
            border-radius:8px; overflow:hidden;
            box-shadow:0 4px 24px rgba(124,58,237,0.13);
            border:1.5px solid #ddd6fe;
        }

        .print-btn {
            position:fixed; top:20px; right:20px;
            background:linear-gradient(135deg,#7c3aed,#db2777);
            color:#fff; border:none;
            padding:9px 22px; font-size:13px; border-radius:8px;
            cursor:pointer; z-index:999;
            box-shadow:0 3px 12px rgba(124,58,237,0.4);
            font-weight:600; letter-spacing:0.3px;
            transition:transform 0.15s, box-shadow 0.15s;
        }
        .print-btn:hover {
            transform:translateY(-1px);
            box-shadow:0 5px 18px rgba(124,58,237,0.5);
        }

        /* ── HEADER ── */
        .inv-header {
            display:grid; grid-template-columns:120px 1fr 230px;
            background:linear-gradient(120deg,#1e1b4b 0%,#4c1d95 50%,#7e22ce 100%);
            border-bottom:3px solid #a855f7;
        }
        .hdr-logo {
            padding:10px 8px; border-right:1px solid rgba(255,255,255,0.15);
            display:flex; align-items:center; justify-content:center;
            background:rgba(255,255,255,0.07);
        }
        .hdr-logo img { max-width:100px; max-height:55px; filter:brightness(1.1); }
        .hdr-company { padding:8px 12px; border-right:1px solid rgba(255,255,255,0.15); }
        .hdr-company .co-name { font-size:14px; font-weight:700; margin-bottom:3px; color:#fff; letter-spacing:0.3px; }
        .hdr-company p { line-height:1.55; color:#e9d5ff; font-size:9.5px; }
        .hdr-company p strong { color:#fff; }
        .hdr-title { padding:8px 10px; }
        .hdr-title .title-text {
            font-size:16px; font-weight:700; text-align:center; color:#fff;
            letter-spacing:1px; text-transform:uppercase;
            margin-bottom:5px;
        }
        .hdr-title .orig-badge {
            font-size:7.5px; font-weight:700;
            border:1px solid rgba(255,255,255,0.4);
            padding:2px 6px; display:inline-block; margin-bottom:7px;
            color:#f3e8ff; background:rgba(255,255,255,0.1);
            border-radius:3px; letter-spacing:0.5px;
        }
        .hdr-inv-meta table { width:100%; border-collapse:collapse; }
        .hdr-inv-meta td {
            padding:2px 5px; font-size:9px; vertical-align:middle;
            border:1px solid rgba(255,255,255,0.15); color:#e9d5ff;
        }
        .hdr-inv-meta .lbl {
            font-weight:700; white-space:nowrap;
            background:rgba(255,255,255,0.12); color:#d8b4fe;
        }

        /* ── PARTY ROW ── */
        .party-row {
            display:grid; grid-template-columns:1fr 1fr;
            border-bottom:2px solid #ddd6fe;
            background:#faf5ff;
        }
        .party-box { padding:7px 10px; }
        .party-box:first-child { border-right:2px solid #ddd6fe; }
        .party-box .party-label {
            font-size:8.5px; font-weight:700; text-transform:uppercase;
            letter-spacing:0.8px; margin-bottom:4px;
            color:#7c3aed; border-bottom:1px solid #ede9fe; padding-bottom:2px;
        }
        .party-box .pname { font-size:11px; font-weight:700; margin-bottom:2px; color:#0f172a; }
        .party-box p { line-height:1.55; font-size:9.5px; color:#475569; }

        /* ── ITEMS TABLE ── */
        .items-table { width:100%; border-collapse:collapse; border-bottom:2px solid #ddd6fe; }
        .items-table th {
            background:linear-gradient(135deg,#6d28d9,#db2777);
            color:#fff; padding:5px 4px; text-align:center;
            font-size:9.5px; font-weight:600; border:1px solid #7c3aed;
            print-color-adjust:exact; -webkit-print-color-adjust:exact;
            letter-spacing:0.2px;
        }
        .items-table td {
            padding:4px 5px; border:1px solid #e9d5ff;
            font-size:9.5px; vertical-align:middle; color:#334155;
        }
        .items-table tbody tr:nth-child(odd)  { background:#faf5ff; }
        .items-table tbody tr:nth-child(even) { background:#f3e8ff; }
        .items-table tbody tr:hover { background:#ede9fe; }
        .items-table tfoot tr td {
            border:1px solid #c4b5fd; font-weight:700;
            border-top:2px solid #7c3aed;
            background:linear-gradient(90deg,#f3e8ff,#fce7f3);
            color:#1e293b; font-size:10.5px;
            print-color-adjust:exact; -webkit-print-color-adjust:exact;
        }
        .items-table tbody tr td[colspan="7"] {
            background:#fef3c7; color:#92400e; font-size:9.5px;
        }

        .text-right  { text-align:right  !important; }
        .text-center { text-align:center !important; }

        /* ── AMOUNT ROW ── */
        .amount-row {
            display:flex; justify-content:space-between; align-items:center;
            padding:5px 10px; border-bottom:2px solid #ddd6fe;
            background:linear-gradient(90deg,#fdf4ff,#fce7f3);
        }
        .amount-words { font-size:9.5px; color:#581c87; }
        .amount-words strong { font-size:10.5px; color:#7e22ce; }

        /* ── TAX SECTION ── */
        .tax-section { border-bottom:2px solid #ddd6fe; }
        .tax-section table { width:100%; border-collapse:collapse; }
        .tax-section th {
            background:linear-gradient(135deg,#6d28d9,#be185d);
            color:#fff; padding:5px 5px;
            font-size:9.5px; border:1px solid #7c3aed; text-align:center;
            font-weight:600;
            print-color-adjust:exact; -webkit-print-color-adjust:exact;
        }
        .tax-section td {
            padding:3px 5px; border:1px solid #e9d5ff;
            font-size:9.5px; text-align:center; color:#334155;
        }
        .tax-section tbody tr:nth-child(odd)  { background:#faf5ff; }
        .tax-section tbody tr:nth-child(even) { background:#fdf4ff; }
        .tax-section tfoot td {
            border:1px solid #c4b5fd; font-weight:700;
            border-top:2px solid #7c3aed;
            background:linear-gradient(90deg,#f3e8ff,#fce7f3);
            color:#0f172a;
            print-color-adjust:exact; -webkit-print-color-adjust:exact;
        }

        /* ── TAX WORDS ROW ── */
        .tax-words-row {
            padding:4px 10px; font-size:9.5px; border-bottom:2px solid #ddd6fe;
            background:#fefce8; color:#713f12;
        }
        .tax-words-row strong { color:#92400e; }

        /* ── FOOTER ── */
        .footer-row {
            display:grid; grid-template-columns:1fr 1fr;
            border-bottom:2px solid #ddd6fe;
            background:#faf5ff;
        }
        .footer-left  { padding:8px 10px; border-right:2px solid #ddd6fe; }
        .footer-left h4 {
            font-size:10px; font-weight:700; margin-bottom:5px;
            border-bottom:2px solid #ddd6fe; padding-bottom:3px;
            color:#7c3aed;
        }
        .footer-left p  { line-height:1.8; font-size:9.5px; color:#475569; }
        .footer-right { padding:8px 10px; }
        .footer-right h4 { font-size:10px; font-weight:700; margin-bottom:4px; text-align:right; color:#0f172a; }
        .sig-area { height:52px; }
        .footer-right p { font-size:9.5px; text-align:right; margin-top:3px; color:#475569; }

        /* ── NOTES ── */
        .notes-row {
            padding:5px 10px; font-size:9.5px; border-bottom:2px solid #ddd6fe;
            background:#fffbeb; color:#78350f;
        }
        .notes-row strong { color:#92400e; }

        /* ── BOTTOM BAR ── */
        .bottom-bar {
            text-align:center; padding:6px;
            font-size:8.5px; color:#7c3aed; font-style:italic;
            background:linear-gradient(90deg,#f5f3ff,#fdf4ff,#f5f3ff);
            font-weight:500; letter-spacing:0.3px;
        }

        /* ── PRINT ── */
        @media print {
            body { background:#fff; padding:0; }
            .print-btn { display:none; }
            .invoice-wrap {
                max-width:100%; border-radius:0;
                box-shadow:none; border:1.5px solid #ddd6fe;
            }
            .inv-header {
                background:linear-gradient(120deg,#1e1b4b 0%,#4c1d95 50%,#7e22ce 100%) !important;
                print-color-adjust:exact !important; -webkit-print-color-adjust:exact !important;
            }
            .items-table th, .tax-section th {
                print-color-adjust:exact !important; -webkit-print-color-adjust:exact !important;
            }
            .items-table tfoot tr td, .tax-section tfoot td,
            .party-row, .footer-row, .amount-row, .tax-words-row,
            .notes-row, .bottom-bar {
                print-color-adjust:exact !important; -webkit-print-color-adjust:exact !important;
            }
            .items-table tbody tr:nth-child(odd)  { background:#faf5ff !important; print-color-adjust:exact !important; -webkit-print-color-adjust:exact !important; }
            .items-table tbody tr:nth-child(even) { background:#f3e8ff !important; print-color-adjust:exact !important; -webkit-print-color-adjust:exact !important; }
        }
    </style>
</head>
<body>

<button class="print-btn" onclick="window.print()">🖨️ Print / Save PDF</button>

<div class="invoice-wrap">

    <!-- ── HEADER ── -->
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
            <div class="title-text">Purchase Order</div>
            <div class="orig-badge">ORIGINAL FOR RECORDS</div>
            <div class="hdr-inv-meta">
                <table>
                    <tr><td class="lbl">Purchase No.</td>      <td><?php echo htmlspecialchars($purchase['purchase_no']); ?></td></tr>
                    <tr><td class="lbl">Dated</td>             <td><?php echo date('d-M-Y', strtotime($purchase['purchase_date'])); ?></td></tr>
                    <tr><td class="lbl">Delivery Note</td>     <td><?php echo htmlspecialchars($purchase['delivery_note'] ?? ''); ?></td></tr>
                    <tr><td class="lbl">Supplier's Order No.</td><td><?php echo htmlspecialchars($purchase['buyer_order_no'] ?? ''); ?></td></tr>
                    <tr><td class="lbl">Dispatch Doc No.</td>  <td><?php echo htmlspecialchars($purchase['dispatch_doc_no'] ?? ''); ?></td></tr>
                    <tr><td class="lbl">Dispatched through</td><td><?php echo htmlspecialchars($purchase['dispatched_thru'] ?? ''); ?></td></tr>
                    <tr><td class="lbl">Destination</td>       <td><?php echo htmlspecialchars($purchase['destination'] ?: $bill_city); ?></td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- ── SUPPLIER / SHIP TO ── -->
    <div class="party-row">
        <div class="party-box">
            <div class="party-label">Supplier (Bill to)</div>
            <p class="pname"><?php echo htmlspecialchars($supp_name); ?></p>
            <?php if ($bill_address): ?><p><?php echo htmlspecialchars($bill_address); ?></p><?php
endif; ?>
            <?php if ($bill_city || $bill_state): ?>
            <p><?php echo htmlspecialchars($bill_city); ?><?php if ($bill_city && $bill_state): ?>, <?php
    endif; ?><?php echo htmlspecialchars($bill_state); ?><?php if ($bill_pincode): ?> - <?php echo htmlspecialchars($bill_pincode); ?><?php
    endif; ?></p>
            <?php
endif; ?>
            <?php if ($bill_phone): ?><p>MOB: <?php echo htmlspecialchars($bill_phone); ?></p><?php
endif; ?>
            <?php if ($bill_gstin): ?><p>GSTIN/UIN: <strong><?php echo htmlspecialchars($bill_gstin); ?></strong></p><?php
endif; ?>
            <?php if ($bill_state): ?><p>State: <?php echo htmlspecialchars($bill_state); ?><?php if ($cu_state_code): ?>, Code: <?php echo $cu_state_code; ?><?php
    endif; ?></p><?php
endif; ?>
        </div>
        <div class="party-box">
            <div class="party-label">Consignee (Ship to)</div>
            <p class="pname"><?php echo htmlspecialchars($ship_name); ?></p>
            <?php if ($ship_address): ?><p><?php echo htmlspecialchars($ship_address); ?></p><?php
endif; ?>
            <?php if ($ship_city || $ship_state): ?>
            <p><?php echo htmlspecialchars($ship_city); ?><?php if ($ship_city && $ship_state): ?>, <?php
    endif; ?><?php echo htmlspecialchars($ship_state); ?><?php if ($ship_pincode): ?> - <?php echo htmlspecialchars($ship_pincode); ?><?php
    endif; ?></p>
            <?php
endif; ?>
            <?php if ($ship_phone): ?><p>MOB: <?php echo htmlspecialchars($ship_phone); ?></p><?php
endif; ?>
            <?php if ($ship_gstin): ?><p>GSTIN/UIN: <strong><?php echo htmlspecialchars($ship_gstin); ?></strong></p><?php
endif; ?>
            <?php if ($ship_state): ?><p>State: <?php echo htmlspecialchars($ship_state); ?><?php if ($cu_state_code_ship): ?>, Code: <?php echo $cu_state_code_ship; ?><?php
    endif; ?></p><?php
endif; ?>
        </div>
    </div>

    <!-- ── ITEMS TABLE ── -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:4%">Sl No</th>
                <th style="width:32%">Description of Goods</th>
                <th style="width:11%">Batch No.</th>
                <th style="width:10%">HSN/SAC</th>
                <th style="width:9%">Quantity</th>
                <th style="width:10%">Rate</th>
                <th style="width:7%">Disc %</th>
                <th style="width:13%">Amount</th>
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
                <td class="text-center"><?php echo htmlspecialchars($item['batch_no'] ?? ''); ?></td>
                <td class="text-center"><?php echo htmlspecialchars($item['hsn_code']); ?></td>
                <td class="text-center"><?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['unit'] ?? 'Nos.'); ?></td>
                <td class="text-right">₹<?php echo number_format($item['rate'], 2); ?></td>
                <td class="text-center">-</td>
                <td class="text-right">₹<?php echo number_format($item['amount'], 2); ?></td>
            </tr>
            <?php
endforeach; ?>

            <?php for ($i = 0; $i < max(0, 8 - count($items)); $i++): ?>
            <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
            <?php
endfor; ?>

            <?php if ($is_igst): ?>
            <tr>
                <td colspan="7" style="text-align:right;font-weight:700;border:1px solid #ddd;">IGST</td>
                <td class="text-right" style="font-weight:700;border:1px solid #ddd;">₹<?php echo number_format($purchase['igst_amount'], 2); ?></td>
            </tr>
            <?php
else: ?>
            <tr>
                <td colspan="7" style="text-align:right;font-weight:700;border:1px solid #ddd;">CGST</td>
                <td class="text-right" style="font-weight:700;border:1px solid #ddd;">₹<?php echo number_format($purchase['cgst_amount'], 2); ?></td>
            </tr>
            <tr>
                <td colspan="7" style="text-align:right;font-weight:700;border:1px solid #ddd;">SGST</td>
                <td class="text-right" style="font-weight:700;border:1px solid #ddd;">₹<?php echo number_format($purchase['sgst_amount'], 2); ?></td>
            </tr>
            <?php
endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="text-center">Total</td>
                <td></td>
                <td class="text-center"><?php echo $total_qty; ?> Nos.</td>
                <td></td>
                <td></td>
                <td class="text-right" style="font-size:12px;">₹<?php echo number_format($purchase['grand_total'], 2); ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- ── AMOUNT IN WORDS ── -->
    <div class="amount-row">
        <div class="amount-words">
            Amount Chargeable (in words)<br>
            <strong><?php echo $amount_in_words; ?></strong>
        </div>
        <div style="font-size:9px;color:#555;">E &amp; O.E</div>
    </div>

    <!-- ── TAX BREAKDOWN ── -->
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
                    <td><?php echo $g['igst_rate']; ?>%</td>
                    <td class="text-right">₹<?php echo number_format($g['igst'], 2); ?></td>
                    <?php
    else: ?>
                    <td><?php echo $g['cgst_rate']; ?>%</td>
                    <td class="text-right">₹<?php echo number_format($g['cgst'], 2); ?></td>
                    <td><?php echo $g['sgst_rate']; ?>%</td>
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
                    <td class="text-right"><strong>₹<?php echo number_format($purchase['total_amount'], 2); ?></strong></td>
                    <?php if ($is_igst): ?>
                    <td></td>
                    <td class="text-right"><strong>₹<?php echo number_format($purchase['igst_amount'], 2); ?></strong></td>
                    <?php
else: ?>
                    <td></td>
                    <td class="text-right"><strong>₹<?php echo number_format($purchase['cgst_amount'], 2); ?></strong></td>
                    <td></td>
                    <td class="text-right"><strong>₹<?php echo number_format($purchase['sgst_amount'], 2); ?></strong></td>
                    <?php
endif; ?>
                    <td class="text-right"><strong>₹<?php echo number_format($purchase['cgst_amount'] + $purchase['sgst_amount'] + $purchase['igst_amount'], 2); ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- ── TAX IN WORDS ── -->
    <div class="tax-words-row">
        Tax Amount (in words): <strong><?php echo numberToWords($purchase['cgst_amount'] + $purchase['sgst_amount'] + $purchase['igst_amount']); ?></strong>
    </div>

    <!-- ── FOOTER: Bank + Signature ── -->
    <div class="footer-row">
        <div class="footer-left">
            <h4>Company's Bank Details</h4>
            <p><strong>A/c Name &nbsp;&nbsp;&nbsp;:</strong> <?php echo htmlspecialchars($company['company_name']); ?></p>
            <?php if (!empty($company['bank_account'])): ?>
            <p><strong>A/c No. &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:</strong> <?php echo htmlspecialchars($company['bank_account']); ?></p>
            <?php
endif; ?>
            <?php if (!empty($company['bank_ifsc'])): ?>
            <p><strong>IFSC Code &nbsp;&nbsp;:</strong> <?php echo htmlspecialchars($company['bank_ifsc']); ?></p>
            <?php
endif; ?>
            <?php if (!empty($company['bank_name'])): ?>
            <p><strong>Bank Name &nbsp;&nbsp;:</strong> <?php echo htmlspecialchars($company['bank_name']); ?></p>
            <?php
endif; ?>
            <?php if (!empty($company['bank_branch'])): ?>
            <p><strong>Branch Name :</strong> <?php echo htmlspecialchars($company['bank_branch']); ?></p>
            <?php
endif; ?>
        </div>
        <div class="footer-right">
            <h4>for <?php echo htmlspecialchars($company['company_name']); ?></h4>
            <div class="sig-area"></div>
            <p><strong>Authorised Signatory</strong></p>
            <br>
            <p style="text-align:left; border-top:1px solid #ccc; padding-top:6px; margin-top:10px;">
                Supplier's Seal and Signature
            </p>
        </div>
    </div>

    <?php if ($purchase['notes']): ?>
    <div class="notes-row">
        <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($purchase['notes'])); ?>
    </div>
    <?php
endif; ?>

    <div class="bottom-bar">This is a Computer Generated Purchase Order</div>

</div>
</body>
</html>