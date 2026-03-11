<?php
// topbar.php — include this at the top of <main class="main-content"> on every page
// It renders the sticky top bar with page title + logout button
// Set $page_title before including, or pass nothing for default

$_topbar_title = $page_title ?? basename($_SERVER['PHP_SELF'], '.php');
?>
<!-- Dark overlay backdrop for mobile sidebar -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>

<header class="top-bar">
    <!-- Hamburger button – visible only on tablet/mobile via CSS -->
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Open menu">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <h2 class="page-title" style="flex-shrink:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
        <?php echo htmlspecialchars($_topbar_title); ?>
    </h2>

    <div style="display:flex;align-items:center;gap:10px;margin-left:auto;flex-shrink:0;">
        <span class="date-display" style="color:#64748b;font-size:14px;font-weight:500;white-space:nowrap;">
            <?php echo date('D, d M Y'); ?>
        </span>
        <span class="topbar-username" style="color:#94a3b8;font-size:13px;font-weight:600;white-space:nowrap;">
            👤 <?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>
        </span>
        <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')" class="top-bar-logout" style="display:inline-flex;align-items:center;gap:6px;
                  padding:8px 14px;background:#fee2e2;color:#991b1b;
                  border-radius:8px;font-size:13px;font-weight:700;
                  text-decoration:none;border:2px solid #fecaca;transition:all 0.2s;white-space:nowrap;"
            onmouseover="this.style.background='#fecaca';this.style.transform='translateY(-1px)'"
            onmouseout="this.style.background='#fee2e2';this.style.transform='none'">
            🚪 <span class="logout-text">Logout</span>
        </a>
    </div>
</header>

<script>
    function toggleSidebar() {
        var sidebar = document.querySelector('.sidebar');
        var btn = document.getElementById('hamburgerBtn');
        var backdrop = document.getElementById('sidebarBackdrop');
        if (sidebar.classList.contains('open')) {
            closeSidebar();
        } else {
            sidebar.classList.add('open');
            if (btn) btn.classList.add('open');
            if (backdrop) backdrop.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
    function closeSidebar() {
        var sidebar = document.querySelector('.sidebar');
        var btn = document.getElementById('hamburgerBtn');
        var backdrop = document.getElementById('sidebarBackdrop');
        sidebar.classList.remove('open');
        if (btn) btn.classList.remove('open');
        if (backdrop) backdrop.classList.remove('active');
        document.body.style.overflow = '';
    }
    // Close on ESC key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeSidebar();
    });
    // Close sidebar when a nav link is clicked (mobile navigation)
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.nav-item a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 900) { closeSidebar(); }
            });
        });
    });
</script>