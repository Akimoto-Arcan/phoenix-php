<?php
/**
 * Point of Sale Module — SPA Shell
 * PhoenixPHP Module — CDAC Programming
 */
require_once __DIR__ . '/../../../bootstrap.php';
\Phoenix\Auth::require('/login');
$username = $_SESSION['username'] ?? 'User';
$userRole = $_SESSION['role'] ?? '';
$csrfToken = \Phoenix\CSRF::getToken();
$theme = $_COOKIE['phoenix_theme'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <title>Point of Sale &mdash; Phoenix</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="/public/css/phoenix.css">
    <script>
    (function(){
        var t = localStorage.getItem('phoenix-theme') || '<?= htmlspecialchars($theme) ?>';
        if (t === 'system') t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', t);
    })();
    </script>
    <style>
        /* Module-scoped overrides */
        .pos-sidebar { width: 240px; background: var(--bg-sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; bottom: 0; z-index: 100; }
        .pos-sidebar-header { padding: 24px 20px; border-bottom: 1px solid var(--border); }
        .pos-sidebar-header a { display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .pos-sidebar-header .brand-icon { font-size: 28px; filter: drop-shadow(0 0 10px rgba(139, 92, 246, 0.5)); }
        .pos-sidebar-header .brand-text { font-size: 18px; font-weight: 700; color: var(--text-primary); }
        .pos-sidebar-header .brand-text span { color: #8b5cf6; }
        .pos-sidebar-header .brand-sub { font-size: 10px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 2px; display: block; margin-top: 2px; }
        .pos-sidebar-nav { flex: 1; padding: 16px 12px; overflow-y: auto; }
        .pos-nav-section { margin-bottom: 24px; }
        .pos-nav-title { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1.5px; padding: 0 12px; margin-bottom: 8px; }
        .pos-nav-item { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: var(--radius-sm); color: var(--text-secondary); text-decoration: none; font-size: 14px; font-weight: 500; transition: var(--transition); margin-bottom: 2px; cursor: pointer; border: none; background: none; width: 100%; text-align: left; }
        .pos-nav-item:hover { background: var(--bg-card); color: var(--text-primary); }
        .pos-nav-item.active { background: rgba(139, 92, 246, 0.12); color: #8b5cf6; border-left: 3px solid #8b5cf6; }
        .pos-nav-item .icon { width: 20px; text-align: center; font-size: 15px; flex-shrink: 0; }
        .pos-sidebar-footer { padding: 16px 20px; border-top: 1px solid var(--border); }
        .pos-sidebar-user { display: flex; align-items: center; gap: 12px; }
        .pos-sidebar-user .avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #8b5cf6, var(--info)); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; color: #fff; }
        .pos-sidebar-user .name { font-size: 14px; font-weight: 600; color: var(--text-primary); }
        .pos-sidebar-user .role { font-size: 12px; color: var(--text-muted); }
        .pos-main { margin-left: 240px; min-height: 100vh; }
        .pos-topbar { padding: 16px 32px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; background: var(--bg-secondary); position: sticky; top: 0; z-index: 50; backdrop-filter: blur(10px); }
        .pos-topbar h2 { font-size: 22px; font-weight: 700; }
        .pos-topbar-actions { display: flex; align-items: center; gap: 12px; }
        .pos-content { padding: 32px; }
        .pos-back-link { color: var(--text-muted); font-size: 13px; text-decoration: none; display: flex; align-items: center; gap: 6px; transition: color 0.2s; }
        .pos-back-link:hover { color: #8b5cf6; }

        /* Modal styles */
        .pos-modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 200; backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .pos-modal-overlay.open { display: flex; }
        .pos-modal { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: var(--radius); width: 100%; max-width: 560px; max-height: 85vh; overflow-y: auto; box-shadow: var(--shadow); }
        .pos-modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .pos-modal-header h3 { font-size: 18px; font-weight: 700; }
        .pos-modal-close { background: none; border: none; color: var(--text-muted); font-size: 22px; cursor: pointer; padding: 4px 8px; border-radius: 4px; }
        .pos-modal-close:hover { background: var(--bg-card); color: var(--text-primary); }
        .pos-modal-body { padding: 24px; }
        .pos-modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; }

        /* Loading spinner */
        .pos-loading { display: flex; align-items: center; justify-content: center; padding: 48px; color: var(--text-muted); font-size: 14px; }
        .pos-spinner { width: 24px; height: 24px; border: 3px solid var(--border); border-top-color: #8b5cf6; border-radius: 50%; animation: pos-spin 0.8s linear infinite; margin-right: 12px; }
        @keyframes pos-spin { to { transform: rotate(360deg); } }

        /* Pagination */
        .pos-pagination { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 20px; }
        .pos-pagination button { padding: 6px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg-card); color: var(--text-secondary); cursor: pointer; font-size: 13px; transition: var(--transition); }
        .pos-pagination button:hover:not(:disabled) { border-color: #8b5cf6; color: #8b5cf6; }
        .pos-pagination button:disabled { opacity: 0.4; cursor: not-allowed; }
        .pos-pagination button.active { background: #8b5cf6; color: #fff; border-color: #8b5cf6; font-weight: 600; }
        .pos-pagination span { color: var(--text-muted); font-size: 13px; }

        /* Empty state */
        .pos-empty { text-align: center; padding: 48px 24px; color: var(--text-muted); }
        .pos-empty .icon { font-size: 48px; margin-bottom: 16px; opacity: 0.4; }
        .pos-empty h3 { font-size: 18px; color: var(--text-secondary); margin-bottom: 8px; }
        .pos-empty p { font-size: 14px; }

        /* Search bar */
        .pos-search-bar { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .pos-search-bar input,
        .pos-search-bar select { padding: 10px 14px; background: var(--bg-input); border: 1px solid var(--border); border-radius: var(--radius-sm); color: var(--text-primary); font-size: 14px; outline: none; transition: var(--transition); }
        .pos-search-bar input:focus,
        .pos-search-bar select:focus { border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15); }
        .pos-search-bar input { flex: 1; min-width: 200px; }

        /* Row danger highlight */
        .pos-row-danger td { background: rgba(239, 68, 68, 0.08) !important; }

        /* Responsive */
        @media (max-width: 1024px) {
            .pos-sidebar { transform: translateX(-100%); z-index: 150; }
            .pos-sidebar.open { transform: translateX(0); }
            .pos-main { margin-left: 0; }
        }
        @media (max-width: 768px) {
            .pos-content { padding: 16px; }
            .pos-topbar { padding: 12px 16px; }
            .pos-topbar h2 { font-size: 18px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <!-- Sidebar -->
        <aside class="pos-sidebar" id="posSidebar">
            <div class="pos-sidebar-header">
                <a href="#register">
                    <span class="brand-icon">&#128176;</span>
                    <div>
                        <span class="brand-text">Point<span>of Sale</span></span>
                        <span class="brand-sub">Sales &amp; Payments</span>
                    </div>
                </a>
            </div>
            <nav class="pos-sidebar-nav">
                <div class="pos-nav-section">
                    <div class="pos-nav-title">Navigation</div>
                    <a href="#register" class="pos-nav-item active" data-route="register">
                        <span class="icon">&#128176;</span> Register
                    </a>
                    <a href="#dashboard" class="pos-nav-item" data-route="dashboard">
                        <span class="icon">&#128202;</span> Dashboard
                    </a>
                    <a href="#orders" class="pos-nav-item" data-route="orders">
                        <span class="icon">&#128203;</span> Orders
                    </a>
                    <a href="#customers" class="pos-nav-item" data-route="customers">
                        <span class="icon">&#128101;</span> Customers
                    </a>
                    <a href="#invoices" class="pos-nav-item" data-route="invoices">
                        <span class="icon">&#128196;</span> Invoices
                    </a>
                    <a href="#purchase-orders" class="pos-nav-item" data-route="purchase-orders">
                        <span class="icon">&#128230;</span> Purchase Orders
                    </a>
                    <a href="#settings" class="pos-nav-item" data-route="settings">
                        <span class="icon">&#9881;</span> Settings
                    </a>
                </div>
                <div class="pos-nav-section">
                    <div class="pos-nav-title">System</div>
                    <a href="/dashboard" class="pos-nav-item pos-back-link">
                        <span class="icon">&#8592;</span> Back to Dashboard
                    </a>
                </div>
            </nav>
            <div class="pos-sidebar-footer">
                <div class="pos-sidebar-user">
                    <div class="avatar"><?= strtoupper(substr($username, 0, 1)) ?></div>
                    <div>
                        <div class="name"><?= htmlspecialchars($username) ?></div>
                        <div class="role"><?= htmlspecialchars($userRole) ?></div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="pos-main">
            <div class="pos-topbar">
                <div style="display:flex;align-items:center;gap:12px">
                    <button class="mobile-menu-toggle" onclick="document.getElementById('posSidebar').classList.toggle('open')">&#9776;</button>
                    <h2 id="pageTitle">Register</h2>
                </div>
                <div class="pos-topbar-actions">
                    <button class="btn btn-sm btn-outline" onclick="toggleTheme()" title="Toggle theme">
                        <span id="themeIcon"><?= $theme === 'dark' ? '&#9788;' : '&#9789;' ?></span>
                    </button>
                </div>
            </div>
            <div class="pos-content" id="appRoot">
                <div class="pos-loading"><div class="pos-spinner"></div> Loading...</div>
            </div>
        </div>
    </div>

    <div class="mobile-overlay" id="mobileOverlay" onclick="document.getElementById('posSidebar').classList.remove('open');this.classList.remove('active')"></div>

    <!-- Modal container -->
    <div class="pos-modal-overlay" id="modalOverlay">
        <div class="pos-modal" id="modalContainer"></div>
    </div>

    <script>
    function toggleTheme() {
        var html = document.documentElement;
        var current = html.getAttribute('data-theme');
        var next = current === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        localStorage.setItem('phoenix-theme', next);
        var icon = document.getElementById('themeIcon');
        if (icon) icon.innerHTML = next === 'dark' ? '&#9788;' : '&#9789;';
    }
    </script>
    <script type="module" src="js/app.js"></script>
</body>
</html>
