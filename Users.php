<?php
require_once 'auth.php';
require_once 'database.php';
requireAdmin(); // Only admin can access this page
$db = new Database();

$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add') {
            if (empty($_POST['username']) || empty($_POST['password']) || empty($_POST['full_name'])) {
                $error = "Username, password and full name are required.";
            } else {
                $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $db->query(
                    "INSERT INTO users (username, password, full_name, email, role, is_active) VALUES (?,?,?,?,?,1)",
                    [$_POST['username'], $hashed, $_POST['full_name'], $_POST['email'], $_POST['role']]
                );
                $_SESSION['success'] = "User '{$_POST['full_name']}' created successfully!";
                header("Location: users.php"); exit;
            }
        } elseif ($_POST['action'] === 'toggle') {
            // Cannot deactivate yourself
            if ($_POST['id'] == $_SESSION['user_id']) {
                $error = "You cannot deactivate your own account!";
            } else {
                $user = $db->single("SELECT is_active FROM users WHERE id=?", [$_POST['id']]);
                $new_status = $user['is_active'] ? 0 : 1;
                $db->query("UPDATE users SET is_active=? WHERE id=?", [$new_status, $_POST['id']]);
                $_SESSION['success'] = "User status updated!";
                header("Location: users.php"); exit;
            }
        } elseif ($_POST['action'] === 'reset_password') {
            if (empty($_POST['new_password'])) {
                $error = "Password cannot be empty.";
            } else {
                $hashed = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $db->query("UPDATE users SET password=? WHERE id=?", [$hashed, $_POST['id']]);
                $_SESSION['success'] = "Password reset successfully!";
                header("Location: users.php"); exit;
            }
        } elseif ($_POST['action'] === 'delete') {
            if ($_POST['id'] == $_SESSION['user_id']) {
                $error = "You cannot delete your own account!";
            } else {
                $db->query("DELETE FROM users WHERE id=?", [$_POST['id']]);
                $_SESSION['success'] = "User deleted!";
                header("Location: users.php"); exit;
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$users = $db->fetchAll("SELECT * FROM users ORDER BY role ASC, full_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MBSBill - User Management</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .role-badge {
            padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .role-admin { background: #dbeafe; color: #1d4ed8; }
        .role-user  { background: #f3f4f6; color: #374151; }
        .status-active   { background: #d1fae5; color: #065f46; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .status-inactive { background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }

        /* Modal */
        .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; }
        .modal.open { display:flex; }
        .modal-box { background:white; border-radius:16px; padding:28px; width:440px; max-width:95vw; box-shadow:0 20px 40px rgba(0,0,0,0.2); animation: fadeIn 0.2s ease; }
        @keyframes fadeIn { from{opacity:0;transform:scale(0.95)} to{opacity:1;transform:scale(1)} }
        .modal-title { font-size:18px; font-weight:700; color:#1e293b; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; }
        .modal-close { background:#f1f5f9; border:none; border-radius:50%; width:30px; height:30px; cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center; }
        .modal-close:hover { background:#e2e8f0; }
        .mform .fg { margin-bottom:14px; }
        .mform label { font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:5px; }
        .mform input, .mform select { width:100%; padding:10px 14px; border:2px solid #e2e8f0; border-radius:8px; font-family:inherit; font-size:14px; }
        .mform input:focus, .mform select:focus { outline:none; border-color:#2563eb; }
        .mform-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:18px; }

        .users-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:1.5rem; }
        .user-card { background:white; border-radius:14px; box-shadow:0 4px 6px rgba(0,0,0,0.07); overflow:hidden; transition:all 0.2s; border: 2px solid transparent; }
        .user-card:hover { transform:translateY(-3px); box-shadow:0 10px 20px rgba(0,0,0,0.1); }
        .user-card.inactive { opacity:0.6; border-color:#fee2e2; }
        .user-card-top { padding:20px; display:flex; align-items:center; gap:14px; border-bottom:1px solid #f1f5f9; }
        .user-avatar-lg { width:52px; height:52px; border-radius:50%; background:linear-gradient(135deg,#60a5fa,#2563eb); color:white; font-size:22px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .user-avatar-admin { background:linear-gradient(135deg,#f59e0b,#d97706); }
        .user-card-name { font-size:16px; font-weight:700; color:#1e293b; }
        .user-card-username { font-size:13px; color:#64748b; margin-top:2px; }
        .user-card-body { padding:14px 20px; display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
        .user-card-footer { padding:12px 20px; border-top:1px solid #f1f5f9; display:flex; gap:8px; flex-wrap:wrap; }
        .act-btn { padding:7px 14px; border:none; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer; font-family:inherit; transition:all 0.2s; }
        .act-btn:hover { transform:translateY(-1px); }
        .btn-reset  { background:#fef3c7; color:#92400e; }
        .btn-toggle-on  { background:#d1fae5; color:#065f46; }
        .btn-toggle-off { background:#fee2e2; color:#991b1b; }
        .btn-del    { background:#fee2e2; color:#991b1b; margin-left:auto; }

        .you-badge { background:#eff6ff; color:#2563eb; padding:3px 8px; border-radius:10px; font-size:10px; font-weight:700; }
        .last-login { font-size:11px; color:#94a3b8; margin-top:4px; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <?php $page_title = '👥 User Management'; include 'topbar.php'; ?>

        <div class="content-wrapper">

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Info box -->
            <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:12px; padding:16px 20px; margin-bottom:1.5rem; font-size:14px; color:#1e40af; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                <div>
                    <strong>🔐 Role Permissions:</strong> &nbsp;
                    <span style="background:#dbeafe;padding:3px 10px;border-radius:10px;font-weight:700;margin-right:8px;">ADMIN</span> Full access to everything &nbsp;|&nbsp;
                    <span style="background:#f3f4f6;padding:3px 10px;border-radius:10px;font-weight:700;margin-right:8px;">USER</span> Can only create sales &amp; view products/customers
                </div>
                <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('open')">
                    ➕ Add New User
                </button>
            </div>

            <!-- Users grid -->
            <div class="users-grid">
                <?php foreach ($users as $u):
                    $isYou = ($u['id'] == $_SESSION['user_id']);
                    $isAdmin = ($u['role'] === 'admin');
                ?>
                <div class="user-card <?php echo !$u['is_active'] ? 'inactive' : ''; ?>">
                    <div class="user-card-top">
                        <div class="user-avatar-lg <?php echo $isAdmin ? 'user-avatar-admin' : ''; ?>">
                            <?php echo strtoupper(substr($u['full_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <div class="user-card-name">
                                <?php echo htmlspecialchars($u['full_name']); ?>
                                <?php if ($isYou): ?><span class="you-badge">YOU</span><?php endif; ?>
                            </div>
                            <div class="user-card-username">@<?php echo htmlspecialchars($u['username']); ?></div>
                            <div class="last-login">
                                Last login: <?php echo $u['last_login'] ? date('d M Y, h:i A', strtotime($u['last_login'])) : 'Never'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="user-card-body">
                        <span class="role-badge <?php echo $isAdmin ? 'role-admin' : 'role-user'; ?>">
                            <?php echo $isAdmin ? '👑 Admin' : '👤 User'; ?>
                        </span>
                        <span class="<?php echo $u['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $u['is_active'] ? '✅ Active' : '❌ Inactive'; ?>
                        </span>
                        <?php if ($u['email']): ?>
                            <span style="font-size:12px;color:#64748b;">📧 <?php echo htmlspecialchars($u['email']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="user-card-footer">
                        <!-- Reset Password -->
                        <button class="act-btn btn-reset"
                                onclick="openResetModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['full_name']); ?>')">
                            🔑 Reset Password
                        </button>

                        <?php if (!$isYou): ?>
                        <!-- Toggle Active -->
                        <form method="POST" style="margin:0">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                            <button type="submit" class="act-btn <?php echo $u['is_active'] ? 'btn-toggle-off' : 'btn-toggle-on'; ?>">
                                <?php echo $u['is_active'] ? '🚫 Deactivate' : '✅ Activate'; ?>
                            </button>
                        </form>
                        <!-- Delete -->
                        <form method="POST" style="margin:0 0 0 auto;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                            <button type="submit" class="act-btn btn-del"
                                    onclick="return confirm('Delete user <?php echo htmlspecialchars($u['full_name']); ?>? This cannot be undone.')">
                                🗑
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        </div>
    </main>
</div>

<!-- Add User Modal -->
<div class="modal" id="addModal">
    <div class="modal-box">
        <div class="modal-title">
            ➕ Add New User
            <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">×</button>
        </div>
        <form method="POST" class="mform">
            <input type="hidden" name="action" value="add">
            <div class="fg">
                <label>Full Name *</label>
                <input type="text" name="full_name" placeholder="e.g. Rahul Sharma" required>
            </div>
            <div class="fg">
                <label>Username *</label>
                <input type="text" name="username" placeholder="e.g. rahulsharma" required>
            </div>
            <div class="fg">
                <label>Email</label>
                <input type="email" name="email" placeholder="e.g. rahul@example.com">
            </div>
            <div class="fg">
                <label>Password *</label>
                <input type="password" name="password" placeholder="Min 6 characters" required minlength="6">
            </div>
            <div class="fg">
                <label>Role *</label>
                <select name="role">
                    <option value="user">👤 User (Limited Access)</option>
                    <option value="admin">👑 Admin (Full Access)</option>
                </select>
            </div>
            <div class="mform-actions">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">✅ Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal" id="resetModal">
    <div class="modal-box">
        <div class="modal-title">
            🔑 Reset Password — <span id="resetName"></span>
            <button class="modal-close" onclick="document.getElementById('resetModal').classList.remove('open')">×</button>
        </div>
        <form method="POST" class="mform">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id" id="resetUserId">
            <div class="fg">
                <label>New Password *</label>
                <input type="password" name="new_password" placeholder="Enter new password" required minlength="6">
            </div>
            <div class="mform-actions">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('resetModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">🔑 Reset Password</button>
            </div>
        </form>
    </div>
</div>

<script src="script.js"></script>
<script>
    function openResetModal(id, name) {
        document.getElementById('resetUserId').value = id;
        document.getElementById('resetName').textContent = name;
        document.getElementById('resetModal').classList.add('open');
    }
    document.querySelectorAll('.modal').forEach(m => {
        m.addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('open');
        });
    });
</script>
</body>
</html>