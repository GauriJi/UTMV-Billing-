<?php
require_once 'auth.php';
require_once 'database.php';
$db = new Database();

// ── KPI Stats ──────────────────────────────────────────────────────────────
<<<<<<< HEAD
$total_sales = $db->single("SELECT SUM(grand_total) as total FROM sales")['total'] ?? 0;
=======
$total_sales     = $db->single("SELECT SUM(grand_total) as total FROM sales")['total'] ?? 0;
>>>>>>> 2ccbacb326bbaaafa6cad901234786a0455599fd
$total_purchases = $db->single("SELECT SUM(grand_total) as total FROM purchases")['total'] ?? 0;
$total_customers = $db->single("SELECT COUNT(*) as count FROM customers")['count'] ?? 0;
$total_products = $db->single("SELECT COUNT(*) as count FROM products")['count'] ?? 0;

$today_sales = $db->single("SELECT COALESCE(SUM(grand_total),0) as t FROM sales WHERE sale_date = CURDATE()")['t'] ?? 0;
$month_sales = $db->single("SELECT COALESCE(SUM(grand_total),0) as t FROM sales WHERE MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE())")['t'] ?? 0;
$pending_inv = $db->single("SELECT COUNT(*) as c FROM sales WHERE payment_status='pending'")['c'] ?? 0;

// Outstanding receivable & payable
<<<<<<< HEAD
$outstanding_recv = 0;
$outstanding_pay = 0;
=======
$outstanding_recv = 0; $outstanding_pay = 0;
>>>>>>> 2ccbacb326bbaaafa6cad901234786a0455599fd
try {
    $outstanding_recv = $db->single("
        SELECT COALESCE(SUM(s.grand_total - COALESCE(p.paid,0)),0) AS t
        FROM sales s
        LEFT JOIN (SELECT sale_id, SUM(amount) AS paid FROM payment_receipts GROUP BY sale_id) p ON p.sale_id=s.id
        WHERE s.payment_status != 'paid'
    ")['t'] ?? 0;
    $outstanding_pay = $db->single("
        SELECT COALESCE(SUM(p.grand_total - COALESCE(pd.paid,0)),0) AS t
        FROM purchases p
        LEFT JOIN (SELECT purchase_id, SUM(amount) AS paid FROM payment_disbursements GROUP BY purchase_id) pd ON pd.purchase_id=p.id
        WHERE COALESCE(p.payment_status,'pending') != 'paid'
    ")['t'] ?? 0;
<<<<<<< HEAD
} catch (Exception $e) {
}
=======
} catch(Exception $e) {}
>>>>>>> 2ccbacb326bbaaafa6cad901234786a0455599fd

// ── Chart: Monthly Sales vs Purchases (last 12 months) ─────────────────────
$monthly_data = $db->fetchAll("
    SELECT DATE_FORMAT(sale_date,'%b %Y') AS mo,
           DATE_FORMAT(sale_date,'%Y-%m') AS mo_sort,
           SUM(grand_total) AS total
    FROM sales
    WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
    GROUP BY mo_sort, mo ORDER BY mo_sort ASC
");
$monthly_purch = $db->fetchAll("
    SELECT DATE_FORMAT(purchase_date,'%Y-%m') AS mo_sort,
           SUM(grand_total) AS total
    FROM purchases
    WHERE purchase_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
    GROUP BY mo_sort ORDER BY mo_sort ASC
");
$purch_map = [];
<<<<<<< HEAD
foreach ($monthly_purch as $p)
    $purch_map[$p['mo_sort']] = $p['total'];

$chart_labels = array_column($monthly_data, 'mo');
$chart_sales = array_column($monthly_data, 'total');
$chart_purch = array_map(fn($r) => $purch_map[$r['mo_sort']] ?? 0, $monthly_data);

// ── Chart: Payment Status Donut ─────────────────────────────────────────────
$pay_status = $db->fetchAll("SELECT payment_status, COUNT(*) AS cnt FROM sales GROUP BY payment_status");
$ps_map = ['paid' => 0, 'partial' => 0, 'pending' => 0];
foreach ($pay_status as $r)
    $ps_map[$r['payment_status']] = $r['cnt'];
=======
foreach ($monthly_purch as $p) $purch_map[$p['mo_sort']] = $p['total'];

$chart_labels  = array_column($monthly_data, 'mo');
$chart_sales   = array_column($monthly_data, 'total');
$chart_purch   = array_map(fn($r) => $purch_map[$r['mo_sort']] ?? 0, $monthly_data);

// ── Chart: Payment Status Donut ─────────────────────────────────────────────
$pay_status = $db->fetchAll("SELECT payment_status, COUNT(*) AS cnt FROM sales GROUP BY payment_status");
$ps_map = ['paid'=>0,'partial'=>0,'pending'=>0];
foreach ($pay_status as $r) $ps_map[$r['payment_status']] = $r['cnt'];
>>>>>>> 2ccbacb326bbaaafa6cad901234786a0455599fd

// ── Chart: Top 5 Customers by Revenue ──────────────────────────────────────
$top_customers = $db->fetchAll("
    SELECT COALESCE(c.customer_name, s.customer_name_manual,'Walk-in') AS name,
           SUM(s.grand_total) AS total
    FROM sales s LEFT JOIN customers c ON s.customer_id=c.id
    GROUP BY name ORDER BY total DESC LIMIT 5
");

// ── Chart: Top 5 Products by Quantity ──────────────────────────────────────
$top_products = $db->fetchAll("
    SELECT si.product_name, SUM(si.quantity) AS total_qty
    FROM sales_items si GROUP BY si.product_name ORDER BY total_qty DESC LIMIT 5
");

// ── Chart: Monthly Profit Trend (last 6 months) ─────────────────────────────
$profit_data = $db->fetchAll("
    SELECT DATE_FORMAT(sale_date,'%b %Y') AS mo,
           DATE_FORMAT(sale_date,'%Y-%m') AS ms,
           SUM(total_amount) AS revenue
    FROM sales
    WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY ms, mo ORDER BY ms ASC
");
$profit_purch = $db->fetchAll("
    SELECT DATE_FORMAT(purchase_date,'%Y-%m') AS ms, SUM(total_amount) AS cost
    FROM purchases
    WHERE purchase_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY ms ORDER BY ms ASC
");
$pp_map = [];
<<<<<<< HEAD
foreach ($profit_purch as $p)
    $pp_map[$p['ms']] = $p['cost'];
$profit_labels = array_column($profit_data, 'mo');
$profit_revenue = array_column($profit_data, 'revenue');
$profit_cost = array_map(fn($r) => $pp_map[$r['ms']] ?? 0, $profit_data);
$profit_net = array_map(fn($r, $c) => round($r - $c, 2), $profit_revenue, $profit_cost);
=======
foreach ($profit_purch as $p) $pp_map[$p['ms']] = $p['cost'];
$profit_labels  = array_column($profit_data, 'mo');
$profit_revenue = array_column($profit_data, 'revenue');
$profit_cost    = array_map(fn($r) => $pp_map[$r['ms']] ?? 0, $profit_data);
$profit_net     = array_map(fn($r, $c) => round($r - $c, 2), $profit_revenue, $profit_cost);
>>>>>>> 2ccbacb326bbaaafa6cad901234786a0455599fd

// ── Recent Sales & Reminders ────────────────────────────────────────────────
$recent_sales = $db->fetchAll("
    SELECT s.*, COALESCE(c.customer_name, s.customer_name_manual,'Walk-in') AS customer_name
    FROM sales s LEFT JOIN customers c ON s.customer_id=c.id
    ORDER BY s.created_at DESC LIMIT 7
");
$todays_reminders = [];
$overdue_count = 0;
try {
    $todays_reminders = $db->fetchAll("SELECT * FROM reminders WHERE DATE(remind_date)=CURDATE() AND status='pending' ORDER BY remind_time ASC");
    $overdue_count = $db->single("SELECT COUNT(*) as c FROM reminders WHERE remind_date<CURDATE() AND status='pending'")['c'] ?? 0;
<<<<<<< HEAD
} catch (Exception $e) {
}
=======
} catch(Exception $e) {}
>>>>>>> 2ccbacb326bbaaafa6cad901234786a0455599fd
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> – Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* ── Dashboard-specific ── */
        .dash-hero {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #ec4899 100%);
            border-radius: 20px;
            padding: 28px 32px;
            color: white;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
<<<<<<< HEAD
            box-shadow: 0 8px 40px rgba(99, 102, 241, 0.4);
        }

        .dash-hero::before {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
        }

        .dash-hero::after {
            content: '';
            position: absolute;
            bottom: -60px;
            right: 80px;
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.07);
        }

        .dash-hero h2 {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 6px;
            position: relative;
            z-index: 1;
        }

        .dash-hero p {
            font-size: 14px;
            opacity: 0.85;
            position: relative;
            z-index: 1;
        }

        .dash-hero-strip {
            display: flex;
            gap: 28px;
            margin-top: 18px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        .dash-hero-stat {
            text-align: center;
        }

        .dash-hero-stat .val {
            font-size: 20px;
            font-weight: 800;
            display: block;
        }

        .dash-hero-stat .lbl {
            font-size: 11px;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        /* ── KPI Extras ── */
        .kpi-strip {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .kpi-card {
            border-radius: 16px;
            padding: 20px 22px;
            display: flex;
            align-items: center;
            gap: 16px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.12);
            transition: transform 0.25s, box-shadow 0.25s;
            animation: slideIn 0.5s ease-out backwards;
        }

        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2);
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
        }

        .kpi-card .kpi-icon {
            font-size: 32px;
            flex-shrink: 0;
            position: relative;
            z-index: 1;
        }

        .kpi-card .kpi-info {
            position: relative;
            z-index: 1;
        }

        .kpi-card .kpi-label {
            font-size: 11px;
            font-weight: 700;
            opacity: 0.85;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .kpi-card .kpi-value {
            font-size: 20px;
            font-weight: 800;
            margin-top: 2px;
            font-family: 'JetBrains Mono', monospace;
        }

        .kpi-card:nth-child(1) {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            animation-delay: 0.05s;
        }

        .kpi-card:nth-child(2) {
            background: linear-gradient(135deg, #10b981, #059669);
            animation-delay: 0.10s;
        }

        .kpi-card:nth-child(3) {
            background: linear-gradient(135deg, #f97316, #ea580c);
            animation-delay: 0.15s;
        }

        .kpi-card:nth-child(4) {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            animation-delay: 0.20s;
        }

        /* ── Charts Grid ── */
        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 28px;
        }

        .chart-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 4px 24px rgba(99, 102, 241, 0.08);
            border: 1px solid rgba(99, 102, 241, 0.06);
            animation: slideIn 0.5s ease-out backwards;
        }

        .chart-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .chart-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .chart-card:nth-child(3) {
            animation-delay: 0.3s;
        }

        .chart-card:nth-child(4) {
            animation-delay: 0.4s;
        }

        .chart-card:nth-child(5) {
            animation-delay: 0.5s;
        }

        .chart-title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 18px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .chart-canvas-wrap {
            position: relative;
        }

        .charts-grid2 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 28px;
        }

        /* ── Big stat card area ── */
        .big-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }

        /* ── Table section tighter ── */
        .dash-table-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 20px;
        }

        /* ── Receivable/Payable pills ── */
        .recv-pay-row {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }

        .recv-card {
            flex: 1;
            border-radius: 16px;
            padding: 18px 20px;
            color: white;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
        }

        .recv-card.green {
            background: linear-gradient(135deg, #16a34a, #15803d);
        }

        .recv-card.red {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .recv-card p {
            font-size: 11px;
            font-weight: 700;
            opacity: 0.85;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .recv-card h3 {
            font-size: 22px;
            font-weight: 800;
            margin-top: 4px;
            font-family: 'JetBrains Mono', monospace;
        }

        /* Status chips */
        .chip-paid {
            background: #d1fae5;
            color: #065f46;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .chip-pending {
            background: #fee2e2;
            color: #991b1b;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .chip-partial {
            background: #fef3c7;
            color: #92400e;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        /* ──────────────────────────────────────────
           RESPONSIVE – Tablet (≤900px)
        ────────────────────────────────────────── */
        @media (max-width: 900px) {
            .big-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 14px;
            }

            .kpi-strip {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }

            .charts-grid2 {
                grid-template-columns: 1fr 1fr;
            }

            .dash-table-grid {
                grid-template-columns: 1fr;
            }

            .recv-pay-row {
                flex-direction: column;
                gap: 12px;
            }

            .dash-hero {
                padding: 20px;
            }

            .dash-hero h2 {
                font-size: 18px;
            }

            .dash-hero-strip {
                gap: 16px;
            }

            .dash-hero-stat .val {
                font-size: 16px;
            }
        }

        /* ──────────────────────────────────────────
           RESPONSIVE – Mobile (≤768px)
        ────────────────────────────────────────── */
        @media (max-width: 768px) {

            /* Hero banner */
            .dash-hero {
                padding: 16px;
                border-radius: 14px;
                margin-bottom: 16px;
            }

            .dash-hero h2 {
                font-size: 16px;
                word-break: break-word;
            }

            .dash-hero p {
                font-size: 12px;
            }

            .dash-hero-strip {
                gap: 12px;
                margin-top: 12px;
                flex-wrap: wrap;
            }

            .dash-hero-stat {
                min-width: calc(50% - 6px);
            }

            .dash-hero-stat .val {
                font-size: 15px;
            }

            .dash-hero-stat .lbl {
                font-size: 10px;
            }

            /* KPI / stat card grids */
            .big-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                margin-bottom: 16px;
            }

            .kpi-strip {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                margin-bottom: 16px;
            }

            .kpi-card {
                padding: 14px 14px;
            }

            .kpi-card .kpi-icon {
                font-size: 24px;
            }

            .kpi-card .kpi-value {
                font-size: 15px;
            }

            /* Charts – all single column on mobile */
            .charts-grid {
                grid-template-columns: 1fr;
                gap: 14px;
                margin-bottom: 14px;
            }

            .charts-grid2 {
                grid-template-columns: 1fr;
                gap: 14px;
                margin-bottom: 14px;
            }

            .chart-card {
                padding: 16px;
            }

            .chart-canvas-wrap[style*="height:280px"] {
                height: 220px !important;
            }

            .chart-canvas-wrap[style*="height:240px"] {
                height: 200px !important;
            }

            /* Receivable/Payable row */
            .recv-pay-row {
                flex-direction: column;
                gap: 10px;
                margin-bottom: 16px;
            }

            .recv-card h3 {
                font-size: 18px;
            }

            /* Table */
            .dash-table-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ──────────────────────────────────────────
           RESPONSIVE – Extra Small (≤480px)
        ────────────────────────────────────────── */
        @media (max-width: 480px) {
            .big-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }

            .kpi-strip {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .dash-hero-strip {
                gap: 8px;
            }

            .dash-hero-stat {
                min-width: calc(50% - 4px);
            }
        }
=======
            box-shadow: 0 8px 40px rgba(99,102,241,0.4);
        }
        .dash-hero::before {
            content:'';
            position:absolute; top:-40px; right:-40px;
            width:220px; height:220px; border-radius:50%;
            background:rgba(255,255,255,0.1);
        }
        .dash-hero::after {
            content:'';
            position:absolute; bottom:-60px; right:80px;
            width:160px; height:160px; border-radius:50%;
            background:rgba(255,255,255,0.07);
        }
        .dash-hero h2 { font-size:24px; font-weight:800; margin-bottom:6px; position:relative; z-index:1; }
        .dash-hero p  { font-size:14px; opacity:0.85; position:relative; z-index:1; }
        .dash-hero-strip { display:flex; gap:28px; margin-top:18px; flex-wrap:wrap; position:relative; z-index:1; }
        .dash-hero-stat { text-align:center; }
        .dash-hero-stat .val { font-size:20px; font-weight:800; display:block; }
        .dash-hero-stat .lbl { font-size:11px; opacity:0.8; text-transform:uppercase; letter-spacing:0.6px; }

        /* ── KPI Extras ── */
        .kpi-strip { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:16px; margin-bottom:28px; }
        .kpi-card {
            border-radius:16px; padding:20px 22px;
            display:flex; align-items:center; gap:16px;
            color:white; position:relative; overflow:hidden;
            box-shadow:0 6px 24px rgba(0,0,0,0.12);
            transition:transform 0.25s, box-shadow 0.25s;
            animation: slideIn 0.5s ease-out backwards;
        }
        .kpi-card:hover { transform:translateY(-5px); box-shadow:0 12px 40px rgba(0,0,0,0.2); }
        .kpi-card::before { content:''; position:absolute; top:-20px; right:-20px; width:80px; height:80px; border-radius:50%; background:rgba(255,255,255,0.15); }
        .kpi-card .kpi-icon { font-size:32px; flex-shrink:0; position:relative; z-index:1; }
        .kpi-card .kpi-info { position:relative; z-index:1; }
        .kpi-card .kpi-label { font-size:11px; font-weight:700; opacity:0.85; text-transform:uppercase; letter-spacing:0.5px; }
        .kpi-card .kpi-value { font-size:20px; font-weight:800; margin-top:2px; font-family:'JetBrains Mono',monospace; }
        .kpi-card:nth-child(1) { background:linear-gradient(135deg,#06b6d4,#0891b2); animation-delay:0.05s; }
        .kpi-card:nth-child(2) { background:linear-gradient(135deg,#10b981,#059669); animation-delay:0.10s; }
        .kpi-card:nth-child(3) { background:linear-gradient(135deg,#f97316,#ea580c); animation-delay:0.15s; }
        .kpi-card:nth-child(4) { background:linear-gradient(135deg,#8b5cf6,#7c3aed); animation-delay:0.20s; }

        /* ── Charts Grid ── */
        .charts-grid { display:grid; grid-template-columns:2fr 1fr; gap:20px; margin-bottom:28px; }
        .chart-card {
            background:white; border-radius:20px; padding:24px;
            box-shadow:0 4px 24px rgba(99,102,241,0.08);
            border:1px solid rgba(99,102,241,0.06);
            animation: slideIn 0.5s ease-out backwards;
        }
        .chart-card:nth-child(1) { animation-delay:0.1s; }
        .chart-card:nth-child(2) { animation-delay:0.2s; }
        .chart-card:nth-child(3) { animation-delay:0.3s; }
        .chart-card:nth-child(4) { animation-delay:0.4s; }
        .chart-card:nth-child(5) { animation-delay:0.5s; }

        .chart-title {
            font-size:15px; font-weight:700; margin-bottom:18px;
            background:linear-gradient(135deg,#4f46e5,#7c3aed);
            -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
        }
        .chart-canvas-wrap { position:relative; }

        .charts-grid2 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; margin-bottom:28px; }

        /* ── Big stat card area ── */
        .big-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin-bottom:28px; }

        /* ── Table section tighter ── */
        .dash-table-grid { display:grid; grid-template-columns:1.4fr 1fr; gap:20px; }

        /* ── Receivable/Payable pills ── */
        .recv-pay-row { display:flex; gap:12px; margin-bottom:24px; }
        .recv-card {
            flex:1; border-radius:16px; padding:18px 20px; color:white;
            box-shadow:0 6px 20px rgba(0,0,0,0.12);
        }
        .recv-card.green { background:linear-gradient(135deg,#16a34a,#15803d); }
        .recv-card.red   { background:linear-gradient(135deg,#dc2626,#b91c1c); }
        .recv-card p { font-size:11px; font-weight:700; opacity:0.85; text-transform:uppercase; letter-spacing:0.5px; }
        .recv-card h3 { font-size:22px; font-weight:800; margin-top:4px; font-family:'JetBrains Mono',monospace; }

        /* Status chips */
        .chip-paid    { background:#d1fae5; color:#065f46; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
        .chip-pending { background:#fee2e2; color:#991b1b; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
        .chip-partial { background:#fef3c7; color:#92400e; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }

        @keyframes slideIn { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
>>>>>>> 2ccbacb326bbaaafa6cad901234786a0455599fd
    </style>
</head>

<body>
<<<<<<< HEAD
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        <main class="main-content">
            <?php $page_title = '📊 Dashboard';
            include 'topbar.php'; ?>
            <div class="content-wrapper">

                <!-- Alerts -->
                <?php if (!empty($todays_reminders)): ?>
                    <div class="alert alert-success" style="display:flex;align-items:center;gap:10px;">
                        <span style="font-size:22px;">🔔</span>
                        <div>
                            <strong><?php echo count($todays_reminders); ?> reminder(s) due today!</strong>
                            <?php foreach ($todays_reminders as $r): ?>
                                &nbsp;· <?php echo htmlspecialchars($r['title']); ?>
                            <?php endforeach; ?>
                            &nbsp;<a href="reminders.php?filter=today"
                                style="color:#065f46;font-weight:700;text-decoration:underline;">View all →</a>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($overdue_count > 0): ?>
                    <div class="alert alert-error" style="display:flex;align-items:center;gap:10px;">
                        <span style="font-size:22px;">⚠️</span>
                        <strong><?php echo $overdue_count; ?> overdue reminder(s)!</strong>
                        &nbsp;<a href="reminders.php?filter=overdue"
                            style="color:#991b1b;font-weight:700;text-decoration:underline;">View →</a>
                    </div>
                <?php endif; ?>

                <!-- Hero Banner -->
                <div class="dash-hero">
                    <h2>👋 Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>!</h2>
                    <p>Here's your business overview for <?php echo date('l, d F Y'); ?></p>
                    <div class="dash-hero-strip">
                        <div class="dash-hero-stat">
                            <span class="val">₹<?php echo number_format($today_sales, 0); ?></span>
                            <span class="lbl">Today's Sales</span>
                        </div>
                        <div class="dash-hero-stat">
                            <span class="val">₹<?php echo number_format($month_sales, 0); ?></span>
                            <span class="lbl">This Month</span>
                        </div>
                        <div class="dash-hero-stat">
                            <span class="val"><?php echo $pending_inv; ?></span>
                            <span class="lbl">Pending Invoices</span>
                        </div>
                        <div class="dash-hero-stat">
                            <span class="val">₹<?php echo number_format($total_sales - $total_purchases, 0); ?></span>
                            <span class="lbl">Gross Profit</span>
                        </div>
                    </div>
                </div>

                <!-- Big KPI Cards -->
                <div class="big-stats">
                    <div class="stat-card sales-card">
                        <div class="stat-icon">💰</div>
                        <div class="stat-details">
                            <p class="stat-label">Total Sales</p>
                            <h3 class="stat-value">₹<?php echo number_format($total_sales, 2); ?></h3>
                        </div>
                    </div>
                    <div class="stat-card purchase-card">
                        <div class="stat-icon">🛒</div>
                        <div class="stat-details">
                            <p class="stat-label">Total Purchases</p>
                            <h3 class="stat-value">₹<?php echo number_format($total_purchases, 2); ?></h3>
                        </div>
                    </div>
                    <div class="stat-card customer-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-details">
                            <p class="stat-label">Customers</p>
                            <h3 class="stat-value"><?php echo $total_customers; ?></h3>
                        </div>
                    </div>
                    <div class="stat-card product-card">
                        <div class="stat-icon">📦</div>
                        <div class="stat-details">
                            <p class="stat-label">Products</p>
                            <h3 class="stat-value"><?php echo $total_products; ?></h3>
                        </div>
                    </div>
                </div>

                <!-- KPI Mini Cards -->
                <div class="kpi-strip">
                    <div class="kpi-card">
                        <div class="kpi-icon">📅</div>
                        <div class="kpi-info">
                            <div class="kpi-label">Today's Revenue</div>
                            <div class="kpi-value">₹<?php echo number_format($today_sales, 0); ?></div>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon">📈</div>
                        <div class="kpi-info">
                            <div class="kpi-label">Month Revenue</div>
                            <div class="kpi-value">₹<?php echo number_format($month_sales, 0); ?></div>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon">💳</div>
                        <div class="kpi-info">
                            <div class="kpi-label">Receivable</div>
                            <div class="kpi-value">₹<?php echo number_format($outstanding_recv, 0); ?></div>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon">💸</div>
                        <div class="kpi-info">
                            <div class="kpi-label">Payable</div>
                            <div class="kpi-value">₹<?php echo number_format($outstanding_pay, 0); ?></div>
                        </div>
                    </div>
                </div>

                <!-- ═══ CHARTS ROW 1: Bar + Donut ═════════════════════════════ -->
                <div class="charts-grid" style="margin-bottom:20px;">
                    <div class="chart-card">
                        <div class="chart-title">📊 Monthly Sales vs Purchases</div>
                        <div class="chart-canvas-wrap" style="height:280px;">
                            <canvas id="salesPurchaseChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-card">
                        <div class="chart-title">🎯 Invoice Payment Status</div>
                        <div class="chart-canvas-wrap" style="height:280px;">
                            <canvas id="paymentDonut"></canvas>
                        </div>
                    </div>
                </div>

                <!-- ═══ CHARTS ROW 2: Profit Trend + Top Customers + Top Products -->
                <div class="charts-grid2">
                    <div class="chart-card" style="grid-column:span 1;">
                        <div class="chart-title">📉 Profit Trend (6 Months)</div>
                        <div class="chart-canvas-wrap" style="height:240px;">
                            <canvas id="profitChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-card">
                        <div class="chart-title">🏆 Top 5 Customers</div>
                        <div class="chart-canvas-wrap" style="height:240px;">
                            <canvas id="topCustomersChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-card">
                        <div class="chart-title">📦 Top 5 Products</div>
                        <div class="chart-canvas-wrap" style="height:240px;">
                            <canvas id="topProductsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions" style="margin-bottom:28px;">
                    <h3 class="section-title" style="margin-bottom:14px;">⚡ Quick Actions</h3>
                    <div class="action-grid">
                        <a href="sales.php" class="action-card">
                            <div class="action-icon">💰</div>
                            <div class="action-text">New Sale</div>
                        </a>
                        <a href="purchase.php" class="action-card">
                            <div class="action-icon">🛒</div>
                            <div class="action-text">New Purchase</div>
                        </a>
                        <a href="payments_received.php" class="action-card">
                            <div class="action-icon">💳</div>
                            <div class="action-text">Record Receipt</div>
                        </a>
                        <a href="payments_made.php" class="action-card">
                            <div class="action-icon">💸</div>
                            <div class="action-text">Record Payment</div>
                        </a>
                        <a href="pnl_report.php" class="action-card">
                            <div class="action-icon">📉</div>
                            <div class="action-text">P&amp;L Report</div>
                        </a>
                        <a href="reminders.php" class="action-card">
                            <div class="action-icon">🔔</div>
                            <div class="action-text">Reminders</div>
                        </a>
                        <a href="reports.php" class="action-card">
                            <div class="action-icon">📈</div>
                            <div class="action-text">Reports</div>
                        </a>
                        <a href="invoices.php" class="action-card">
                            <div class="action-icon">🧾</div>
                            <div class="action-text">All Invoices</div>
                        </a>
                    </div>
                </div>

                <!-- Recent Sales Table -->
                <div class="table-section">
                    <div class="section-header">
                        <h3 class="section-title">🧾 Recent Sales</h3>
                        <a href="sales.php" class="btn btn-primary">+ New Sale</a>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Invoice No</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>GST</th>
                                    <th>Grand Total</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_sales)): ?>
                                    <tr>
                                        <td colspan="8" class="no-data">No sales found. <a href="sales.php">Create your
                                                first sale!</a></td>
                                    </tr>
                                <?php else:
                                    foreach ($recent_sales as $sale):
                                        $gst = $sale['cgst_amount'] + $sale['sgst_amount'] + $sale['igst_amount'];
                                        $chip = ['paid' => 'chip-paid', 'pending' => 'chip-pending', 'partial' => 'chip-partial'][$sale['payment_status']] ?? 'chip-pending';
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($sale['invoice_no']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($sale['sale_date'])); ?></td>
                                            <td>₹<?php echo number_format($sale['total_amount'], 2); ?></td>
                                            <td>₹<?php echo number_format($gst, 2); ?></td>
                                            <td><strong>₹<?php echo number_format($sale['grand_total'], 2); ?></strong></td>
                                            <td><span
                                                    class="<?php echo $chip; ?>"><?php echo strtoupper($sale['payment_status']); ?></span>
                                            </td>
                                            <td>
                                                <a href="invoice_print.php?id=<?php echo $sale['id']; ?>" target="_blank"
                                                    class="btn-icon" title="Print">🖨️</a>
                                                <a href="edit_sale.php?id=<?php echo $sale['id']; ?>" class="btn-icon"
                                                    title="Edit">✏️</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div><!-- /content-wrapper -->
        </main>
    </div>

    <script src="script.js"></script>
    <script>
        // ═══════════════════════════════════════════════════════
        // Chart.js Global Defaults — clean, colorful look
        // ═══════════════════════════════════════════════════════
        Chart.defaults.font.family = "'Outfit', sans-serif";
        Chart.defaults.color = '#64748b';
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.pointStyleWidth = 10;
        Chart.defaults.plugins.legend.labels.padding = 16;

        const PALETTE = ['#6366f1', '#10b981', '#f97316', '#ec4899', '#06b6d4', '#8b5cf6', '#f59e0b', '#14b8a6'];

        // ── 1. Monthly Sales vs Purchases Bar Chart ────────────────────────────────
        const salesLabels = <?php echo json_encode($chart_labels); ?>;
        const salesData = <?php echo json_encode(array_map('floatval', $chart_sales)); ?>;
        const purchData = <?php echo json_encode(array_map('floatval', $chart_purch)); ?>;

        new Chart(document.getElementById('salesPurchaseChart'), {
            type: 'bar',
            data: {
                labels: salesLabels.length ? salesLabels : ['No Data'],
                datasets: [
                    {
                        label: 'Sales ₹',
                        data: salesData.length ? salesData : [0],
                        backgroundColor: 'rgba(99,102,241,0.75)',
                        borderColor: '#6366f1',
                        borderWidth: 2,
                        borderRadius: 8,
                        borderSkipped: false,
                    },
                    {
                        label: 'Purchases ₹',
                        data: purchData.length ? purchData : [0],
                        backgroundColor: 'rgba(249,115,22,0.7)',
                        borderColor: '#f97316',
                        borderWidth: 2,
                        borderRadius: 8,
                        borderSkipped: false,
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' ₹' + ctx.parsed.y.toLocaleString('en-IN', { minimumFractionDigits: 2 })
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false } },
                    y: {
                        grid: { color: 'rgba(99,102,241,0.07)', lineWidth: 1 },
                        border: { display: false },
                        ticks: { callback: v => '₹' + (v >= 1000 ? (v / 1000).toFixed(0) + 'K' : v) }
                    }
                }
            }
        });

        // ── 2. Payment Status Donut ────────────────────────────────────────────────
        const psData = [
            <?php echo (int) $ps_map['paid']; ?>,
            <?php echo (int) $ps_map['partial']; ?>,
            <?php echo (int) $ps_map['pending']; ?>
        ];

        new Chart(document.getElementById('paymentDonut'), {
            type: 'doughnut',
            data: {
                labels: ['Paid', 'Partial', 'Pending'],
                datasets: [{
                    data: psData,
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                    borderColor: ['#fff', '#fff', '#fff'],
                    borderWidth: 3,
                    hoverOffset: 10,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: { callbacks: { label: ctx => ' ' + ctx.parsed + ' Invoices' } }
                }
            }
        });

        // ── 3. Profit Trend Line Chart ─────────────────────────────────────────────
        const profitLabels = <?php echo json_encode($profit_labels); ?>;
        const profitRevenue = <?php echo json_encode(array_map('floatval', $profit_revenue)); ?>;
        const profitCost = <?php echo json_encode(array_map('floatval', $profit_cost)); ?>;
        const profitNet = <?php echo json_encode(array_map('floatval', $profit_net)); ?>;

        const profCvs = document.getElementById('profitChart').getContext('2d');
        const gradGreen = profCvs.createLinearGradient(0, 0, 0, 200);
        gradGreen.addColorStop(0, 'rgba(16,185,129,0.3)');
        gradGreen.addColorStop(1, 'rgba(16,185,129,0)');

        new Chart(document.getElementById('profitChart'), {
            type: 'line',
            data: {
                labels: profitLabels.length ? profitLabels : ['No Data'],
                datasets: [
                    {
                        label: 'Revenue ₹',
                        data: profitRevenue.length ? profitRevenue : [0],
                        borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.1)',
                        borderWidth: 2.5, pointRadius: 5, pointBackgroundColor: '#6366f1',
                        fill: true, tension: 0.4,
                    },
                    {
                        label: 'Cost ₹',
                        data: profitCost.length ? profitCost : [0],
                        borderColor: '#f97316', backgroundColor: 'transparent',
                        borderWidth: 2.5, pointRadius: 5, pointBackgroundColor: '#f97316',
                        borderDash: [5, 4], fill: false, tension: 0.4,
                    },
                    {
                        label: 'Profit ₹',
                        data: profitNet.length ? profitNet : [0],
                        borderColor: '#10b981', backgroundColor: gradGreen,
                        borderWidth: 3, pointRadius: 6, pointBackgroundColor: '#10b981',
                        fill: true, tension: 0.4,
                    },
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { callbacks: { label: ctx => ' ₹' + ctx.parsed.y.toLocaleString('en-IN', { minimumFractionDigits: 2 }) } }
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false } },
                    y: {
                        grid: { color: 'rgba(99,102,241,0.07)' }, border: { display: false },
                        ticks: { callback: v => '₹' + (v >= 1000 ? (v / 1000).toFixed(0) + 'K' : v) }
                    }
                }
            }
        });

        // ── 4. Top Customers Horizontal Bar ─────────────────────────────────────────
        const custNames = <?php echo json_encode(array_column($top_customers, 'name')); ?>;
        const custTotals = <?php echo json_encode(array_map('floatval', array_column($top_customers, 'total'))); ?>;

        new Chart(document.getElementById('topCustomersChart'), {
            type: 'bar',
            data: {
                labels: custNames.length ? custNames : ['No Data'],
                datasets: [{
                    label: 'Revenue ₹',
                    data: custTotals.length ? custTotals : [0],
                    backgroundColor: ['#6366f1', '#8b5cf6', '#ec4899', '#06b6d4', '#10b981'],
                    borderRadius: 8, borderSkipped: false,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => ' ₹' + ctx.parsed.x.toLocaleString('en-IN', { minimumFractionDigits: 2 }) } }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(99,102,241,0.07)' }, border: { display: false },
                        ticks: { callback: v => '₹' + (v >= 1000 ? (v / 1000).toFixed(0) + 'K' : v) }
                    },
                    y: { grid: { display: false }, border: { display: false } }
                }
            }
        });

        // ── 5. Top Products Polar Area ──────────────────────────────────────────────
        const prodNames = <?php echo json_encode(array_column($top_products, 'product_name')); ?>;
        const prodQty = <?php echo json_encode(array_map('intval', array_column($top_products, 'total_qty'))); ?>;

        new Chart(document.getElementById('topProductsChart'), {
            type: 'polarArea',
            data: {
                labels: prodNames.length ? prodNames : ['No Data'],
                datasets: [{
                    data: prodQty.length ? prodQty : [1],
                    backgroundColor: ['rgba(99,102,241,0.75)', 'rgba(16,185,129,0.75)', 'rgba(249,115,22,0.75)', 'rgba(236,72,153,0.75)', 'rgba(6,182,212,0.75)'],
                    borderColor: '#fff', borderWidth: 2,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 12 } },
                    tooltip: { callbacks: { label: ctx => ' Qty: ' + ctx.parsed.r } }
                },
                scales: { r: { ticks: { display: false }, grid: { color: 'rgba(99,102,241,0.1)' } } }
            }
        });
    </script>
=======
<div class="app-container">
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <?php $page_title = '📊 Dashboard'; include 'topbar.php'; ?>
        <div class="content-wrapper">

            <!-- Alerts -->
            <?php if (!empty($todays_reminders)): ?>
            <div class="alert alert-success" style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:22px;">🔔</span>
                <div>
                    <strong><?php echo count($todays_reminders); ?> reminder(s) due today!</strong>
                    <?php foreach($todays_reminders as $r): ?>
                        &nbsp;· <?php echo htmlspecialchars($r['title']); ?>
                    <?php endforeach; ?>
                    &nbsp;<a href="reminders.php?filter=today" style="color:#065f46;font-weight:700;text-decoration:underline;">View all →</a>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($overdue_count > 0): ?>
            <div class="alert alert-error" style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:22px;">⚠️</span>
                <strong><?php echo $overdue_count; ?> overdue reminder(s)!</strong>
                &nbsp;<a href="reminders.php?filter=overdue" style="color:#991b1b;font-weight:700;text-decoration:underline;">View →</a>
            </div>
            <?php endif; ?>

            <!-- Hero Banner -->
            <div class="dash-hero">
                <h2>👋 Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>!</h2>
                <p>Here's your business overview for <?php echo date('l, d F Y'); ?></p>
                <div class="dash-hero-strip">
                    <div class="dash-hero-stat">
                        <span class="val">₹<?php echo number_format($today_sales, 0); ?></span>
                        <span class="lbl">Today's Sales</span>
                    </div>
                    <div class="dash-hero-stat">
                        <span class="val">₹<?php echo number_format($month_sales, 0); ?></span>
                        <span class="lbl">This Month</span>
                    </div>
                    <div class="dash-hero-stat">
                        <span class="val"><?php echo $pending_inv; ?></span>
                        <span class="lbl">Pending Invoices</span>
                    </div>
                    <div class="dash-hero-stat">
                        <span class="val">₹<?php echo number_format($total_sales - $total_purchases, 0); ?></span>
                        <span class="lbl">Gross Profit</span>
                    </div>
                </div>
            </div>

            <!-- Big KPI Cards -->
            <div class="big-stats">
                <div class="stat-card sales-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-details">
                        <p class="stat-label">Total Sales</p>
                        <h3 class="stat-value">₹<?php echo number_format($total_sales, 2); ?></h3>
                    </div>
                </div>
                <div class="stat-card purchase-card">
                    <div class="stat-icon">🛒</div>
                    <div class="stat-details">
                        <p class="stat-label">Total Purchases</p>
                        <h3 class="stat-value">₹<?php echo number_format($total_purchases, 2); ?></h3>
                    </div>
                </div>
                <div class="stat-card customer-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-details">
                        <p class="stat-label">Customers</p>
                        <h3 class="stat-value"><?php echo $total_customers; ?></h3>
                    </div>
                </div>
                <div class="stat-card product-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-details">
                        <p class="stat-label">Products</p>
                        <h3 class="stat-value"><?php echo $total_products; ?></h3>
                    </div>
                </div>
            </div>

            <!-- KPI Mini Cards -->
            <div class="kpi-strip">
                <div class="kpi-card">
                    <div class="kpi-icon">📅</div>
                    <div class="kpi-info">
                        <div class="kpi-label">Today's Revenue</div>
                        <div class="kpi-value">₹<?php echo number_format($today_sales, 0); ?></div>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon">📈</div>
                    <div class="kpi-info">
                        <div class="kpi-label">Month Revenue</div>
                        <div class="kpi-value">₹<?php echo number_format($month_sales, 0); ?></div>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon">💳</div>
                    <div class="kpi-info">
                        <div class="kpi-label">Receivable</div>
                        <div class="kpi-value">₹<?php echo number_format($outstanding_recv, 0); ?></div>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon">💸</div>
                    <div class="kpi-info">
                        <div class="kpi-label">Payable</div>
                        <div class="kpi-value">₹<?php echo number_format($outstanding_pay, 0); ?></div>
                    </div>
                </div>
            </div>

            <!-- ═══ CHARTS ROW 1: Bar + Donut ═════════════════════════════ -->
            <div class="charts-grid" style="margin-bottom:20px;">
                <div class="chart-card">
                    <div class="chart-title">📊 Monthly Sales vs Purchases</div>
                    <div class="chart-canvas-wrap" style="height:280px;">
                        <canvas id="salesPurchaseChart"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-title">🎯 Invoice Payment Status</div>
                    <div class="chart-canvas-wrap" style="height:280px;">
                        <canvas id="paymentDonut"></canvas>
                    </div>
                </div>
            </div>

            <!-- ═══ CHARTS ROW 2: Profit Trend + Top Customers + Top Products -->
            <div class="charts-grid2">
                <div class="chart-card" style="grid-column:span 1;">
                    <div class="chart-title">📉 Profit Trend (6 Months)</div>
                    <div class="chart-canvas-wrap" style="height:240px;">
                        <canvas id="profitChart"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-title">🏆 Top 5 Customers</div>
                    <div class="chart-canvas-wrap" style="height:240px;">
                        <canvas id="topCustomersChart"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-title">📦 Top 5 Products</div>
                    <div class="chart-canvas-wrap" style="height:240px;">
                        <canvas id="topProductsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions" style="margin-bottom:28px;">
                <h3 class="section-title" style="margin-bottom:14px;">⚡ Quick Actions</h3>
                <div class="action-grid">
                    <a href="sales.php"              class="action-card"><div class="action-icon">💰</div><div class="action-text">New Sale</div></a>
                    <a href="purchase.php"           class="action-card"><div class="action-icon">🛒</div><div class="action-text">New Purchase</div></a>
                    <a href="payments_received.php"  class="action-card"><div class="action-icon">💳</div><div class="action-text">Record Receipt</div></a>
                    <a href="payments_made.php"      class="action-card"><div class="action-icon">💸</div><div class="action-text">Record Payment</div></a>
                    <a href="pnl_report.php"         class="action-card"><div class="action-icon">📉</div><div class="action-text">P&amp;L Report</div></a>
                    <a href="reminders.php"          class="action-card"><div class="action-icon">🔔</div><div class="action-text">Reminders</div></a>
                    <a href="reports.php"            class="action-card"><div class="action-icon">📈</div><div class="action-text">Reports</div></a>
                    <a href="invoices.php"           class="action-card"><div class="action-icon">🧾</div><div class="action-text">All Invoices</div></a>
                </div>
            </div>

            <!-- Recent Sales Table -->
            <div class="table-section">
                <div class="section-header">
                    <h3 class="section-title">🧾 Recent Sales</h3>
                    <a href="sales.php" class="btn btn-primary">+ New Sale</a>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Invoice No</th><th>Customer</th><th>Date</th>
                                <th>Amount</th><th>GST</th><th>Grand Total</th><th>Status</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($recent_sales)): ?>
                            <tr><td colspan="8" class="no-data">No sales found. <a href="sales.php">Create your first sale!</a></td></tr>
                            <?php else: foreach($recent_sales as $sale):
                                $gst = $sale['cgst_amount'] + $sale['sgst_amount'] + $sale['igst_amount'];
                                $chip = ['paid'=>'chip-paid','pending'=>'chip-pending','partial'=>'chip-partial'][$sale['payment_status']] ?? 'chip-pending';
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($sale['invoice_no']); ?></strong></td>
                                <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                                <td><?php echo date('d M Y', strtotime($sale['sale_date'])); ?></td>
                                <td>₹<?php echo number_format($sale['total_amount'], 2); ?></td>
                                <td>₹<?php echo number_format($gst, 2); ?></td>
                                <td><strong>₹<?php echo number_format($sale['grand_total'], 2); ?></strong></td>
                                <td><span class="<?php echo $chip; ?>"><?php echo strtoupper($sale['payment_status']); ?></span></td>
                                <td>
                                    <a href="invoice_print.php?id=<?php echo $sale['id']; ?>" target="_blank" class="btn-icon" title="Print">🖨️</a>
                                    <a href="edit_sale.php?id=<?php echo $sale['id']; ?>" class="btn-icon" title="Edit">✏️</a>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /content-wrapper -->
    </main>
</div>

<script src="script.js"></script>
<script>
// ═══════════════════════════════════════════════════════
// Chart.js Global Defaults — clean, colorful look
// ═══════════════════════════════════════════════════════
Chart.defaults.font.family = "'Outfit', sans-serif";
Chart.defaults.color = '#64748b';
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.pointStyleWidth = 10;
Chart.defaults.plugins.legend.labels.padding = 16;

const PALETTE = ['#6366f1','#10b981','#f97316','#ec4899','#06b6d4','#8b5cf6','#f59e0b','#14b8a6'];

// ── 1. Monthly Sales vs Purchases Bar Chart ────────────────────────────────
const salesLabels  = <?php echo json_encode($chart_labels); ?>;
const salesData    = <?php echo json_encode(array_map('floatval',$chart_sales)); ?>;
const purchData    = <?php echo json_encode(array_map('floatval',$chart_purch)); ?>;

new Chart(document.getElementById('salesPurchaseChart'), {
    type: 'bar',
    data: {
        labels: salesLabels.length ? salesLabels : ['No Data'],
        datasets: [
            {
                label: 'Sales ₹',
                data: salesData.length ? salesData : [0],
                backgroundColor: 'rgba(99,102,241,0.75)',
                borderColor: '#6366f1',
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false,
            },
            {
                label: 'Purchases ₹',
                data: purchData.length ? purchData : [0],
                backgroundColor: 'rgba(249,115,22,0.7)',
                borderColor: '#f97316',
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false,
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' },
            tooltip: {
                callbacks: {
                    label: ctx => ' ₹' + ctx.parsed.y.toLocaleString('en-IN', {minimumFractionDigits:2})
                }
            }
        },
        scales: {
            x: { grid: { display: false }, border: { display: false } },
            y: {
                grid: { color: 'rgba(99,102,241,0.07)', lineWidth: 1 },
                border: { display: false },
                ticks: { callback: v => '₹' + (v >= 1000 ? (v/1000).toFixed(0)+'K' : v) }
            }
        }
    }
});

// ── 2. Payment Status Donut ────────────────────────────────────────────────
const psData = [
    <?php echo (int)$ps_map['paid']; ?>,
    <?php echo (int)$ps_map['partial']; ?>,
    <?php echo (int)$ps_map['pending']; ?>
];

new Chart(document.getElementById('paymentDonut'), {
    type: 'doughnut',
    data: {
        labels: ['Paid', 'Partial', 'Pending'],
        datasets: [{
            data: psData,
            backgroundColor: ['#10b981','#f59e0b','#ef4444'],
            borderColor: ['#fff','#fff','#fff'],
            borderWidth: 3,
            hoverOffset: 10,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        cutout: '68%',
        plugins: {
            legend: { position: 'bottom' },
            tooltip: { callbacks: { label: ctx => ' ' + ctx.parsed + ' Invoices' } }
        }
    }
});

// ── 3. Profit Trend Line Chart ─────────────────────────────────────────────
const profitLabels  = <?php echo json_encode($profit_labels); ?>;
const profitRevenue = <?php echo json_encode(array_map('floatval',$profit_revenue)); ?>;
const profitCost    = <?php echo json_encode(array_map('floatval',$profit_cost)); ?>;
const profitNet     = <?php echo json_encode(array_map('floatval',$profit_net)); ?>;

const profCvs = document.getElementById('profitChart').getContext('2d');
const gradGreen = profCvs.createLinearGradient(0,0,0,200);
gradGreen.addColorStop(0,'rgba(16,185,129,0.3)');
gradGreen.addColorStop(1,'rgba(16,185,129,0)');

new Chart(document.getElementById('profitChart'), {
    type: 'line',
    data: {
        labels: profitLabels.length ? profitLabels : ['No Data'],
        datasets: [
            {
                label: 'Revenue ₹',
                data: profitRevenue.length ? profitRevenue : [0],
                borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.1)',
                borderWidth: 2.5, pointRadius: 5, pointBackgroundColor: '#6366f1',
                fill: true, tension: 0.4,
            },
            {
                label: 'Cost ₹',
                data: profitCost.length ? profitCost : [0],
                borderColor: '#f97316', backgroundColor: 'transparent',
                borderWidth: 2.5, pointRadius: 5, pointBackgroundColor: '#f97316',
                borderDash: [5,4], fill: false, tension: 0.4,
            },
            {
                label: 'Profit ₹',
                data: profitNet.length ? profitNet : [0],
                borderColor: '#10b981', backgroundColor: gradGreen,
                borderWidth: 3, pointRadius: 6, pointBackgroundColor: '#10b981',
                fill: true, tension: 0.4,
            },
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' },
            tooltip: { callbacks: { label: ctx => ' ₹' + ctx.parsed.y.toLocaleString('en-IN', {minimumFractionDigits:2}) } }
        },
        scales: {
            x: { grid: { display: false }, border: { display: false } },
            y: { grid: { color: 'rgba(99,102,241,0.07)' }, border: { display: false },
                 ticks: { callback: v => '₹' + (v >= 1000 ? (v/1000).toFixed(0)+'K' : v) } }
        }
    }
});

// ── 4. Top Customers Horizontal Bar ─────────────────────────────────────────
const custNames  = <?php echo json_encode(array_column($top_customers,'name')); ?>;
const custTotals = <?php echo json_encode(array_map('floatval',array_column($top_customers,'total'))); ?>;

new Chart(document.getElementById('topCustomersChart'), {
    type: 'bar',
    data: {
        labels: custNames.length ? custNames : ['No Data'],
        datasets: [{
            label: 'Revenue ₹',
            data: custTotals.length ? custTotals : [0],
            backgroundColor: ['#6366f1','#8b5cf6','#ec4899','#06b6d4','#10b981'],
            borderRadius: 8, borderSkipped: false,
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ' ₹' + ctx.parsed.x.toLocaleString('en-IN', {minimumFractionDigits:2}) } }
        },
        scales: {
            x: { grid: { color: 'rgba(99,102,241,0.07)' }, border: { display:false },
                 ticks: { callback: v => '₹' + (v >= 1000 ? (v/1000).toFixed(0)+'K' : v) } },
            y: { grid: { display: false }, border: { display: false } }
        }
    }
});

// ── 5. Top Products Polar Area ──────────────────────────────────────────────
const prodNames = <?php echo json_encode(array_column($top_products,'product_name')); ?>;
const prodQty   = <?php echo json_encode(array_map('intval',array_column($top_products,'total_qty'))); ?>;

new Chart(document.getElementById('topProductsChart'), {
    type: 'polarArea',
    data: {
        labels: prodNames.length ? prodNames : ['No Data'],
        datasets: [{
            data: prodQty.length ? prodQty : [1],
            backgroundColor: ['rgba(99,102,241,0.75)','rgba(16,185,129,0.75)','rgba(249,115,22,0.75)','rgba(236,72,153,0.75)','rgba(6,182,212,0.75)'],
            borderColor: '#fff', borderWidth: 2,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { position: 'right', labels: { boxWidth: 12 } },
            tooltip: { callbacks: { label: ctx => ' Qty: ' + ctx.parsed.r } }
        },
        scales: { r: { ticks: { display: false }, grid: { color: 'rgba(99,102,241,0.1)' } } }
    }
});
</script>
>>>>>>> 2ccbacb326bbaaafa6cad901234786a0455599fd
</body>

</html>