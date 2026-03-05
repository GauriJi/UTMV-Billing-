<?php
require_once 'auth.php';
require_once 'database.php';
$db = new Database();

// Create calendar_events table if it doesn't exist
$db->query("CREATE TABLE IF NOT EXISTS calendar_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    event_date DATE NOT NULL,
    event_type ENUM('meeting', 'payment', 'delivery', 'reminder', 'other') DEFAULT 'other',
    description TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Handle Add Event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add') {
            $db->query(
                "INSERT INTO calendar_events (title, event_date, event_type, description, created_by) VALUES (?, ?, ?, ?, ?)",
                [$_POST['title'], $_POST['event_date'], $_POST['event_type'], $_POST['description'], $_SESSION['user_id']]
            );
            $_SESSION['success'] = "Event added successfully!";
        } elseif ($_POST['action'] === 'delete') {
            $db->query("DELETE FROM calendar_events WHERE id = ?", [$_POST['event_id']]);
            $_SESSION['success'] = "Event deleted!";
        }
        header("Location: Calendar.php?month=" . ($_POST['month'] ?? date('m')) . "&year=" . ($_POST['year'] ?? date('Y')));
        exit;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Current month/year
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');

// Clamp
if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 13) { $nextMonth = 1; $nextYear++; }

// Days in month / first day
$daysInMonth  = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$firstDayOfWeek = (int)date('w', mktime(0,0,0,$month,1,$year)); // 0=Sun

// Fetch all events this month
$events_raw = $db->fetchAll(
    "SELECT * FROM calendar_events WHERE MONTH(event_date) = ? AND YEAR(event_date) = ? ORDER BY event_date ASC",
    [$month, $year]
);

// Group events by day
$events = [];
foreach ($events_raw as $ev) {
    $day = (int)date('j', strtotime($ev['event_date']));
    $events[$day][] = $ev;
}

// Upcoming events (next 5 from today)
$upcoming = $db->fetchAll(
    "SELECT * FROM calendar_events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 5"
);

$monthName = date('F', mktime(0,0,0,$month,1,$year));
$today_day   = (int)date('j');
$today_month = (int)date('m');
$today_year  = (int)date('Y');

$type_colors = [
    'meeting'  => '#2563eb',
    'payment'  => '#10b981',
    'delivery' => '#f59e0b',
    'reminder' => '#ef4444',
    'other'    => '#8b5cf6',
];
$type_icons = [
    'meeting'  => '🤝',
    'payment'  => '💰',
    'delivery' => '📦',
    'reminder' => '🔔',
    'other'    => '📌',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MBSBill - Calendar</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ── Calendar Page Styles ── */
        .calendar-layout {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 1.5rem;
            align-items: start;
        }

        /* Month navigation */
        .cal-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
            padding: 1.25rem 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            margin-bottom: 1.5rem;
        }
        .cal-nav h2 {
            font-size: 22px;
            font-weight: 700;
            color: #1e293b;
        }
        .cal-nav-btn {
            background: #f1f5f9;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 18px;
            cursor: pointer;
            text-decoration: none;
            color: #334155;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
        }
        .cal-nav-btn:hover { background: #e2e8f0; transform: translateY(-1px); }
        .today-btn {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 18px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .today-btn:hover { opacity: 0.9; transform: translateY(-1px); }

        /* Calendar grid */
        .calendar-grid {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            overflow: hidden;
        }
        .cal-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
        }
        .cal-weekday {
            padding: 14px 0;
            text-align: center;
            color: #94a3b8;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .cal-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
        }
        .cal-cell {
            min-height: 110px;
            border-right: 1px solid #f1f5f9;
            border-bottom: 1px solid #f1f5f9;
            padding: 8px;
            position: relative;
            transition: background 0.2s;
            cursor: pointer;
        }
        .cal-cell:hover { background: #f8fafc; }
        .cal-cell.empty { background: #fafafa; cursor: default; }
        .cal-cell.today {
            background: #eff6ff;
            border: 2px solid #2563eb;
        }
        .day-number {
            font-size: 14px;
            font-weight: 700;
            color: #475569;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-bottom: 4px;
        }
        .cal-cell.today .day-number {
            background: #2563eb;
            color: white;
        }
        .cal-cell.has-events .day-number { color: #1e293b; }

        /* Event chips in cells */
        .event-chip {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 2px;
            cursor: pointer;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            color: white;
            transition: opacity 0.2s;
        }
        .event-chip:hover { opacity: 0.85; }
        .more-events {
            font-size: 10px;
            color: #64748b;
            font-weight: 600;
            padding: 1px 4px;
        }

        /* Add event button in cell */
        .add-event-btn {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 20px;
            height: 20px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 14px;
            line-height: 1;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .cal-cell:hover .add-event-btn { display: flex; }
        .add-event-btn:hover { background: #1e40af; transform: scale(1.1); }

        /* Right sidebar */
        .sidebar-panel {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        .panel-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            overflow: hidden;
        }
        .panel-header {
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            color: white;
            padding: 14px 18px;
            font-weight: 700;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .panel-body { padding: 16px; }

        /* Add event form panel */
        .event-form .form-group { margin-bottom: 12px; }
        .event-form label {
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 5px;
        }
        .event-form input,
        .event-form select,
        .event-form textarea {
            width: 100%;
            padding: 9px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
            font-family: inherit;
            transition: border-color 0.2s;
            background: white;
        }
        .event-form input:focus,
        .event-form select:focus,
        .event-form textarea:focus {
            outline: none;
            border-color: #2563eb;
        }
        .event-form textarea { resize: vertical; min-height: 60px; }
        .event-form .btn-add {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
        }
        .event-form .btn-add:hover { opacity: 0.9; transform: translateY(-1px); }

        /* Upcoming events list */
        .upcoming-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .upcoming-item:last-child { border-bottom: none; }
        .upcoming-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-top: 5px;
            flex-shrink: 0;
        }
        .upcoming-title {
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
        }
        .upcoming-date {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
        }
        .upcoming-delete {
            margin-left: auto;
            background: none;
            border: none;
            color: #ef4444;
            cursor: pointer;
            font-size: 14px;
            padding: 2px 6px;
            border-radius: 4px;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .upcoming-delete:hover { background: #fee2e2; }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: white;
            border-radius: 16px;
            padding: 28px;
            width: 420px;
            max-width: 95vw;
            box-shadow: 0 20px 25px rgba(0,0,0,0.2);
            animation: modalIn 0.25s ease;
        }
        @keyframes modalIn {
            from { opacity:0; transform: scale(0.95) translateY(-10px); }
            to   { opacity:1; transform: scale(1) translateY(0); }
        }
        .modal-title {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-close {
            background: #f1f5f9;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        .modal-close:hover { background: #e2e8f0; }

        /* Legend */
        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: #475569;
            font-weight: 500;
        }
        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        @media (max-width: 1100px) {
            .calendar-layout { grid-template-columns: 1fr; }
            .sidebar-panel { display: grid; grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 700px) {
            .sidebar-panel { grid-template-columns: 1fr; }
            .cal-cell { min-height: 70px; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <header class="top-bar">
            <h2 class="page-title">📅 Calendar</h2>
            <div class="user-info">
                <span class="date-time"><?php echo date('l, F j, Y'); ?></span>
            </div>
        </header>

        <div class="content-wrapper">

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Month Navigation -->
            <div class="cal-nav">
                <a href="Calendar.php?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="cal-nav-btn">‹ <?php echo date('M', mktime(0,0,0,$prevMonth,1,$prevYear)); ?></a>
                <div style="text-align:center;">
                    <h2><?php echo $monthName . ' ' . $year; ?></h2>
                    <div class="legend" style="justify-content:center; margin-top:6px;">
                        <?php foreach ($type_colors as $type => $color): ?>
                            <div class="legend-item">
                                <div class="legend-dot" style="background:<?php echo $color; ?>"></div>
                                <?php echo ucfirst($type); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <a href="Calendar.php" class="today-btn">Today</a>
                    <a href="Calendar.php?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="cal-nav-btn"><?php echo date('M', mktime(0,0,0,$nextMonth,1,$nextYear)); ?> ›</a>
                </div>
            </div>

            <!-- Two-column layout -->
            <div class="calendar-layout">

                <!-- Calendar -->
                <div>
                    <div class="calendar-grid">
                        <!-- Weekday headers -->
                        <div class="cal-weekdays">
                            <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
                                <div class="cal-weekday"><?php echo $d; ?></div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Day cells -->
                        <div class="cal-days">
                            <!-- Empty cells before month starts -->
                            <?php for ($i = 0; $i < $firstDayOfWeek; $i++): ?>
                                <div class="cal-cell empty"></div>
                            <?php endfor; ?>

                            <!-- Day cells -->
                            <?php for ($day = 1; $day <= $daysInMonth; $day++):
                                $isToday = ($day === $today_day && $month === $today_month && $year === $today_year);
                                $dayEvents = $events[$day] ?? [];
                                $hasEvents = count($dayEvents) > 0;
                                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                            ?>
                                <div class="cal-cell <?php echo $isToday ? 'today' : ''; ?> <?php echo $hasEvents ? 'has-events' : ''; ?>"
                                     onclick="openAddModal('<?php echo $dateStr; ?>')">
                                    <div class="day-number"><?php echo $day; ?></div>

                                    <?php
                                    $shown = 0;
                                    foreach ($dayEvents as $ev):
                                        if ($shown >= 2) break;
                                        $color = $type_colors[$ev['event_type']] ?? '#8b5cf6';
                                        $icon  = $type_icons[$ev['event_type']]  ?? '📌';
                                    ?>
                                        <div class="event-chip"
                                             style="background:<?php echo $color; ?>"
                                             title="<?php echo htmlspecialchars($ev['title']); ?>"
                                             onclick="event.stopPropagation(); showEventDetail(<?php echo htmlspecialchars(json_encode($ev)); ?>)">
                                            <?php echo $icon; ?> <?php echo htmlspecialchars(mb_substr($ev['title'], 0, 14)); ?>
                                        </div>
                                    <?php $shown++; endforeach; ?>

                                    <?php if (count($dayEvents) > 2): ?>
                                        <div class="more-events">+<?php echo count($dayEvents) - 2; ?> more</div>
                                    <?php endif; ?>

                                    <button class="add-event-btn" onclick="event.stopPropagation(); openAddModal('<?php echo $dateStr; ?>')">+</button>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <!-- Right sidebar panels -->
                <div class="sidebar-panel">

                    <!-- Add Event Panel -->
                    <div class="panel-card">
                        <div class="panel-header">➕ Add Event</div>
                        <div class="panel-body">
                            <form method="POST" class="event-form">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="month" value="<?php echo $month; ?>">
                                <input type="hidden" name="year" value="<?php echo $year; ?>">

                                <div class="form-group">
                                    <label>Event Title *</label>
                                    <input type="text" name="title" placeholder="e.g. Client meeting" required>
                                </div>
                                <div class="form-group">
                                    <label>Date *</label>
                                    <input type="date" name="event_date" id="quick_date"
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Type</label>
                                    <select name="event_type">
                                        <option value="meeting">🤝 Meeting</option>
                                        <option value="payment">💰 Payment</option>
                                        <option value="delivery">📦 Delivery</option>
                                        <option value="reminder">🔔 Reminder</option>
                                        <option value="other">📌 Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Notes</label>
                                    <textarea name="description" placeholder="Optional notes..."></textarea>
                                </div>
                                <button type="submit" class="btn-add">+ Add Event</button>
                            </form>
                        </div>
                    </div>

                    <!-- Upcoming Events -->
                    <div class="panel-card">
                        <div class="panel-header">🗓️ Upcoming Events</div>
                        <div class="panel-body">
                            <?php if (empty($upcoming)): ?>
                                <p style="color:#94a3b8; font-size:13px; text-align:center; padding:16px 0;">No upcoming events</p>
                            <?php else: ?>
                                <?php foreach ($upcoming as $ev):
                                    $color = $type_colors[$ev['event_type']] ?? '#8b5cf6';
                                    $icon  = $type_icons[$ev['event_type']]  ?? '📌';
                                    $evDate = new DateTime($ev['event_date']);
                                    $todayDt = new DateTime(date('Y-m-d'));
                                    $diff = $todayDt->diff($evDate);
                                    $daysLeft = $diff->days;
                                    $daysLabel = $daysLeft === 0 ? 'Today' : ($daysLeft === 1 ? 'Tomorrow' : 'In ' . $daysLeft . ' days');
                                ?>
                                <div class="upcoming-item">
                                    <div class="upcoming-dot" style="background:<?php echo $color; ?>"></div>
                                    <div style="flex:1; min-width:0;">
                                        <div class="upcoming-title"><?php echo $icon; ?> <?php echo htmlspecialchars($ev['title']); ?></div>
                                        <div class="upcoming-date">
                                            <?php echo date('d M Y', strtotime($ev['event_date'])); ?>
                                            &bull; <span style="color:<?php echo $color; ?>; font-weight:700;"><?php echo $daysLabel; ?></span>
                                        </div>
                                    </div>
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
                                        <input type="hidden" name="month" value="<?php echo $month; ?>">
                                        <input type="hidden" name="year" value="<?php echo $year; ?>">
                                        <button type="submit" class="upcoming-delete"
                                                onclick="return confirm('Delete this event?')">✕</button>
                                    </form>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div><!-- /sidebar-panel -->
            </div><!-- /calendar-layout -->
        </div>
    </main>
</div>

<!-- Event Detail Modal -->
<div class="modal-overlay" id="detailModal">
    <div class="modal-box">
        <div class="modal-title">
            <span id="modalTitle">Event Details</span>
            <button class="modal-close" onclick="closeModal('detailModal')">×</button>
        </div>
        <div id="modalBody"></div>
        <div style="margin-top:18px; display:flex; gap:10px; justify-content:flex-end;">
            <form method="POST" id="deleteEventForm" style="margin:0;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="event_id" id="deleteEventId">
                <input type="hidden" name="month" value="<?php echo $month; ?>">
                <input type="hidden" name="year" value="<?php echo $year; ?>">
                <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this event?')">🗑 Delete</button>
            </form>
            <button class="btn btn-secondary" onclick="closeModal('detailModal')">Close</button>
        </div>
    </div>
</div>

<!-- Add Event Modal (triggered by clicking day) -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <div class="modal-title">
            <span>➕ New Event</span>
            <button class="modal-close" onclick="closeModal('addModal')">×</button>
        </div>
        <form method="POST" class="event-form">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="month" value="<?php echo $month; ?>">
            <input type="hidden" name="year" value="<?php echo $year; ?>">
            <div class="form-group">
                <label>Event Title *</label>
                <input type="text" name="title" placeholder="e.g. Client meeting" required>
            </div>
            <div class="form-group">
                <label>Date *</label>
                <input type="date" name="event_date" id="modal_date" required>
            </div>
            <div class="form-group">
                <label>Type</label>
                <select name="event_type">
                    <option value="meeting">🤝 Meeting</option>
                    <option value="payment">💰 Payment</option>
                    <option value="delivery">📦 Delivery</option>
                    <option value="reminder">🔔 Reminder</option>
                    <option value="other">📌 Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="description" placeholder="Optional notes..."></textarea>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:8px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">+ Add Event</button>
            </div>
        </form>
    </div>
</div>

<script src="script.js"></script>
<script>
    const typeColors = <?php echo json_encode($type_colors); ?>;
    const typeIcons  = <?php echo json_encode($type_icons);  ?>;

    function openAddModal(dateStr) {
        document.getElementById('modal_date').value = dateStr;
        document.getElementById('addModal').classList.add('open');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    function showEventDetail(ev) {
        const color = typeColors[ev.event_type] || '#8b5cf6';
        const icon  = typeIcons[ev.event_type]  || '📌';
        document.getElementById('modalTitle').innerHTML = icon + ' ' + ev.title;
        document.getElementById('deleteEventId').value = ev.id;
        document.getElementById('modalBody').innerHTML = `
            <table style="width:100%; font-size:14px; border-collapse:collapse;">
                <tr><td style="padding:6px 0; color:#64748b; font-weight:600; width:90px;">Date</td>
                    <td style="padding:6px 0;">${ev.event_date}</td></tr>
                <tr><td style="padding:6px 0; color:#64748b; font-weight:600;">Type</td>
                    <td style="padding:6px 0;"><span style="background:${color};color:white;padding:2px 10px;border-radius:4px;font-size:12px;font-weight:700;">${ev.event_type.toUpperCase()}</span></td></tr>
                <tr><td style="padding:6px 0; color:#64748b; font-weight:600; vertical-align:top;">Notes</td>
                    <td style="padding:6px 0;">${ev.description || '<em style="color:#94a3b8">No notes</em>'}</td></tr>
            </table>`;
        document.getElementById('detailModal').classList.add('open');
    }

    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(el => {
        el.addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('open');
        });
    });
</script>
</body>
</html>
