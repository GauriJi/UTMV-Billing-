<?php
require_once 'auth.php';
require_once 'database.php';
$db = new Database();

// Create reminders table if not exists
$db->query("CREATE TABLE IF NOT EXISTS reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    remind_date DATE NOT NULL,
    remind_time TIME DEFAULT '09:00:00',
    priority ENUM('low','medium','high') DEFAULT 'medium',
    category ENUM('payment','meeting','followup','tax','delivery','other') DEFAULT 'other',
    status ENUM('pending','done') DEFAULT 'pending',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add') {
            $db->query(
                "INSERT INTO reminders (title, description, remind_date, remind_time, priority, category, created_by) VALUES (?,?,?,?,?,?,?)",
                [$_POST['title'], $_POST['description'], $_POST['remind_date'], $_POST['remind_time'], $_POST['priority'], $_POST['category'], $_SESSION['user_id']]
            );
            $_SESSION['success'] = "Reminder added successfully!";
        } elseif ($_POST['action'] === 'done') {
            $db->query("UPDATE reminders SET status='done' WHERE id=?", [$_POST['id']]);
            $_SESSION['success'] = "Reminder marked as done!";
        } elseif ($_POST['action'] === 'pending') {
            $db->query("UPDATE reminders SET status='pending' WHERE id=?", [$_POST['id']]);
            $_SESSION['success'] = "Reminder marked as pending!";
        } elseif ($_POST['action'] === 'delete') {
            $db->query("DELETE FROM reminders WHERE id=?", [$_POST['id']]);
            $_SESSION['success'] = "Reminder deleted!";
        }
        header("Location: reminders.php?filter=" . ($_POST['filter'] ?? 'all'));
        exit;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Filter
$filter = $_GET['filter'] ?? 'all';

$where = "";
if ($filter === 'today')   $where = "WHERE DATE(r.remind_date) = CURDATE()";
if ($filter === 'pending') $where = "WHERE r.status = 'pending'";
if ($filter === 'done')    $where = "WHERE r.status = 'done'";
if ($filter === 'overdue') $where = "WHERE r.remind_date < CURDATE() AND r.status = 'pending'";

$reminders = $db->fetchAll("SELECT * FROM reminders $where ORDER BY remind_date ASC, remind_time ASC");

// Stats
$stats = [
    'total'   => $db->single("SELECT COUNT(*) as c FROM reminders")['c'] ?? 0,
    'pending' => $db->single("SELECT COUNT(*) as c FROM reminders WHERE status='pending'")['c'] ?? 0,
    'done'    => $db->single("SELECT COUNT(*) as c FROM reminders WHERE status='done'")['c'] ?? 0,
    'overdue' => $db->single("SELECT COUNT(*) as c FROM reminders WHERE remind_date < CURDATE() AND status='pending'")['c'] ?? 0,
    'today'   => $db->single("SELECT COUNT(*) as c FROM reminders WHERE DATE(remind_date) = CURDATE() AND status='pending'")['c'] ?? 0,
];

$priority_colors = ['low' => '#10b981', 'medium' => '#f59e0b', 'high' => '#ef4444'];
$priority_bg    = ['low' => '#d1fae5', 'medium' => '#fef3c7', 'high' => '#fee2e2'];
$category_icons = ['payment'=>'💰','meeting'=>'🤝','followup'=>'📞','tax'=>'📋','delivery'=>'📦','other'=>'📌'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MBSBill - Reminders</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Stats */
        .reminder-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .r-stat {
            background: white;
            border-radius: 12px;
            padding: 18px 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            display: flex;
            align-items: center;
            gap: 14px;
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid transparent;
            text-decoration: none;
        }
        .r-stat:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
        .r-stat.active { border-color: #2563eb; }
        .r-stat-icon { font-size: 32px; }
        .r-stat-num { font-size: 24px; font-weight: 700; color: #1e293b; line-height: 1; }
        .r-stat-label { font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }

        /* Toolbar */
        .toolbar {
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 12px;
        }
        .filter-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
        .filter-tab {
            padding: 7px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            color: #64748b;
            background: #f1f5f9;
            transition: all 0.2s;
            border: 2px solid transparent;
        }
        .filter-tab:hover { background: #e2e8f0; color: #1e293b; }
        .filter-tab.active { background: #eff6ff; color: #2563eb; border-color: #2563eb; }

        /* Reminder cards */
        .reminders-list { display: flex; flex-direction: column; gap: 12px; }
        .reminder-card {
            background: white;
            border-radius: 12px;
            padding: 18px 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.2s;
            border-left: 5px solid #e2e8f0;
            position: relative;
        }
        .reminder-card:hover { box-shadow: 0 6px 15px rgba(0,0,0,0.1); transform: translateX(3px); }
        .reminder-card.done { opacity: 0.6; background: #f8fafc; }
        .reminder-card.overdue { border-left-color: #ef4444 !important; }
        .reminder-card.today  { border-left-color: #2563eb !important; }

        .cat-icon {
            font-size: 28px;
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .reminder-info { flex: 1; min-width: 0; }
        .reminder-title {
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .reminder-card.done .reminder-title { text-decoration: line-through; color: #94a3b8; }
        .reminder-desc { font-size: 13px; color: #64748b; margin-bottom: 6px; }
        .reminder-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .meta-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .date-badge {
            font-size: 12px;
            color: #475569;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .overdue-badge {
            background: #fee2e2;
            color: #b91c1c;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 20px;
        }
        .today-badge {
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 20px;
        }

        /* Actions */
        .reminder-actions { display: flex; gap: 8px; flex-shrink: 0; }
        .action-btn {
            padding: 7px 14px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
        }
        .action-btn:hover { transform: translateY(-1px); }
        .btn-done    { background: #d1fae5; color: #065f46; }
        .btn-done:hover { background: #a7f3d0; }
        .btn-undo    { background: #fef3c7; color: #92400e; }
        .btn-undo:hover { background: #fde68a; }
        .btn-del     { background: #fee2e2; color: #991b1b; }
        .btn-del:hover { background: #fecaca; }

        /* Add form */
        .add-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .add-card-header {
            background: linear-gradient(135deg, #0f172a, #1e40af);
            color: white;
            padding: 14px 20px;
            font-weight: 700;
            font-size: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            user-select: none;
        }
        .add-card-body { padding: 20px; display: none; }
        .add-card-body.open { display: block; }
        .add-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
            margin-bottom: 14px;
        }
        .add-form-grid .full { grid-column: 1 / -1; }
        .add-form-grid label {
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 5px;
        }
        .add-form-grid input,
        .add-form-grid select,
        .add-form-grid textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        .add-form-grid input:focus,
        .add-form-grid select:focus,
        .add-form-grid textarea:focus { outline: none; border-color: #2563eb; }
        .add-form-grid textarea { resize: vertical; min-height: 60px; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        }
        .empty-state .empty-icon { font-size: 64px; margin-bottom: 16px; }
        .empty-state h3 { font-size: 20px; color: #1e293b; margin-bottom: 8px; }
        .empty-state p { color: #64748b; font-size: 14px; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="top-bar">
            <h2 class="page-title">🔔 Reminders</h2>
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

            <!-- Stats -->
            <div class="reminder-stats">
                <a href="reminders.php?filter=all" class="r-stat <?php echo $filter==='all'?'active':''; ?>">
                    <div class="r-stat-icon">📋</div>
                    <div><div class="r-stat-num"><?php echo $stats['total']; ?></div><div class="r-stat-label">Total</div></div>
                </a>
                <a href="reminders.php?filter=pending" class="r-stat <?php echo $filter==='pending'?'active':''; ?>">
                    <div class="r-stat-icon">⏳</div>
                    <div><div class="r-stat-num"><?php echo $stats['pending']; ?></div><div class="r-stat-label">Pending</div></div>
                </a>
                <a href="reminders.php?filter=today" class="r-stat <?php echo $filter==='today'?'active':''; ?>">
                    <div class="r-stat-icon">📅</div>
                    <div><div class="r-stat-num"><?php echo $stats['today']; ?></div><div class="r-stat-label">Today</div></div>
                </a>
                <a href="reminders.php?filter=overdue" class="r-stat <?php echo $filter==='overdue'?'active':''; ?>">
                    <div class="r-stat-icon">🚨</div>
                    <div><div class="r-stat-num" style="color:#ef4444"><?php echo $stats['overdue']; ?></div><div class="r-stat-label">Overdue</div></div>
                </a>
                <a href="reminders.php?filter=done" class="r-stat <?php echo $filter==='done'?'active':''; ?>">
                    <div class="r-stat-icon">✅</div>
                    <div><div class="r-stat-num" style="color:#10b981"><?php echo $stats['done']; ?></div><div class="r-stat-label">Done</div></div>
                </a>
            </div>

            <!-- Add Reminder (collapsible) -->
            <div class="add-card">
                <div class="add-card-header" onclick="toggleForm()">
                    <span>➕ Add New Reminder</span>
                    <span id="toggle-icon">▼</span>
                </div>
                <div class="add-card-body" id="addForm">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                        <div class="add-form-grid">
                            <div class="full">
                                <label>Title *</label>
                                <input type="text" name="title" placeholder="e.g. Follow up with client ABC" required>
                            </div>
                            <div>
                                <label>Date *</label>
                                <input type="date" name="remind_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div>
                                <label>Time</label>
                                <input type="time" name="remind_time" value="09:00">
                            </div>
                            <div>
                                <label>Category</label>
                                <select name="category">
                                    <option value="payment">💰 Payment</option>
                                    <option value="meeting">🤝 Meeting</option>
                                    <option value="followup">📞 Follow Up</option>
                                    <option value="tax">📋 Tax / GST</option>
                                    <option value="delivery">📦 Delivery</option>
                                    <option value="other" selected>📌 Other</option>
                                </select>
                            </div>
                            <div>
                                <label>Priority</label>
                                <select name="priority">
                                    <option value="low">🟢 Low</option>
                                    <option value="medium" selected>🟡 Medium</option>
                                    <option value="high">🔴 High</option>
                                </select>
                            </div>
                            <div class="full">
                                <label>Notes</label>
                                <textarea name="description" placeholder="Optional details..."></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">🔔 Add Reminder</button>
                    </form>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="toolbar">
                <div class="filter-tabs">
                    <a href="reminders.php?filter=all"     class="filter-tab <?php echo $filter==='all'?'active':''; ?>">All</a>
                    <a href="reminders.php?filter=pending" class="filter-tab <?php echo $filter==='pending'?'active':''; ?>">⏳ Pending</a>
                    <a href="reminders.php?filter=today"   class="filter-tab <?php echo $filter==='today'?'active':''; ?>">📅 Today</a>
                    <a href="reminders.php?filter=overdue" class="filter-tab <?php echo $filter==='overdue'?'active':''; ?>">🚨 Overdue</a>
                    <a href="reminders.php?filter=done"    class="filter-tab <?php echo $filter==='done'?'active':''; ?>">✅ Done</a>
                </div>
                <span style="font-size:13px; color:#64748b; font-weight:600;">
                    <?php echo count($reminders); ?> reminder<?php echo count($reminders)!=1?'s':''; ?>
                </span>
            </div>

            <!-- Reminders List -->
            <?php if (empty($reminders)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🔔</div>
                    <h3>No reminders found</h3>
                    <p>Click "Add New Reminder" above to create one.</p>
                </div>
            <?php else: ?>
                <div class="reminders-list">
                    <?php foreach ($reminders as $r):
                        $isOverdue = ($r['remind_date'] < date('Y-m-d') && $r['status'] === 'pending');
                        $isToday   = ($r['remind_date'] === date('Y-m-d') && $r['status'] === 'pending');
                        $pColor    = $priority_colors[$r['priority']] ?? '#64748b';
                        $pBg       = $priority_bg[$r['priority']]    ?? '#f1f5f9';
                        $catIcon   = $category_icons[$r['category']] ?? '📌';
                        $cardClass = $r['status']==='done' ? 'done' : ($isOverdue ? 'overdue' : ($isToday ? 'today' : ''));
                        $borderColor = $isOverdue ? '#ef4444' : ($isToday ? '#2563eb' : $pColor);
                    ?>
                    <div class="reminder-card <?php echo $cardClass; ?>" style="border-left-color:<?php echo $borderColor; ?>">
                        <div class="cat-icon"><?php echo $catIcon; ?></div>

                        <div class="reminder-info">
                            <div class="reminder-title"><?php echo htmlspecialchars($r['title']); ?></div>
                            <?php if ($r['description']): ?>
                                <div class="reminder-desc"><?php echo htmlspecialchars($r['description']); ?></div>
                            <?php endif; ?>
                            <div class="reminder-meta">
                                <span class="date-badge">
                                    🗓 <?php echo date('d M Y', strtotime($r['remind_date'])); ?>
                                    &nbsp;⏰ <?php echo date('h:i A', strtotime($r['remind_time'])); ?>
                                </span>
                                <span class="meta-badge" style="background:<?php echo $pBg; ?>; color:<?php echo $pColor; ?>">
                                    <?php echo strtoupper($r['priority']); ?>
                                </span>
                                <span class="meta-badge" style="background:#f1f5f9; color:#475569;">
                                    <?php echo ucfirst($r['category']); ?>
                                </span>
                                <?php if ($isOverdue): ?>
                                    <span class="overdue-badge">⚠ OVERDUE</span>
                                <?php elseif ($isToday): ?>
                                    <span class="today-badge">📅 TODAY</span>
                                <?php endif; ?>
                                <?php if ($r['status']==='done'): ?>
                                    <span class="meta-badge" style="background:#d1fae5;color:#065f46;">✅ DONE</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="reminder-actions">
                            <?php if ($r['status'] === 'pending'): ?>
                                <form method="POST" style="margin:0">
                                    <input type="hidden" name="action" value="done">
                                    <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                    <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                                    <button type="submit" class="action-btn btn-done">✔ Done</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" style="margin:0">
                                    <input type="hidden" name="action" value="pending">
                                    <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                    <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                                    <button type="submit" class="action-btn btn-undo">↩ Undo</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" style="margin:0">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                                <button type="submit" class="action-btn btn-del"
                                        onclick="return confirm('Delete this reminder?')">🗑</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<script src="script.js"></script>
<script>
    function toggleForm() {
        const body = document.getElementById('addForm');
        const icon = document.getElementById('toggle-icon');
        body.classList.toggle('open');
        icon.textContent = body.classList.contains('open') ? '▲' : '▼';
    }

    // Auto-open form if no reminders
    <?php if (empty($reminders)): ?>
    toggleForm();
    <?php endif; ?>
</script>
</body>
</html>