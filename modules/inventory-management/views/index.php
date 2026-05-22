<?php
/**
 * Inventory Management Module — SPA Shell
 * PhoenixPHP Module — CDAC Programming
 */
require_once __DIR__ . '/../../../bootstrap.php';
\Phoenix\Auth::require('/login.php');
$username = $_SESSION['username'] ?? 'User';
$userRole = $_SESSION['role'] ?? '';
$csrfToken = \Phoenix\CSRF::token();
$theme = $_SESSION['theme_preference'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <title>Inventory Management &mdash; Phoenix</title>
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
        .inv-sidebar { width: 240px; background: var(--bg-sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; bottom: 0; z-index: 100; }
        .inv-sidebar-header { padding: 24px 20px; border-bottom: 1px solid var(--border); }
        .inv-sidebar-header a { display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .inv-sidebar-header .brand-icon { font-size: 28px; filter: drop-shadow(0 0 10px rgba(16, 185, 129, 0.5)); }
        .inv-sidebar-header .brand-text { font-size: 18px; font-weight: 700; color: var(--text-primary); }
        .inv-sidebar-header .brand-text span { color: #10b981; }
        .inv-sidebar-header .brand-sub { font-size: 10px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 2px; display: block; margin-top: 2px; }
        .inv-sidebar-nav { flex: 1; padding: 16px 12px; overflow-y: auto; }
        .inv-nav-section { margin-bottom: 24px; }
        .inv-nav-title { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1.5px; padding: 0 12px; margin-bottom: 8px; }
        .inv-nav-item { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: var(--radius-sm); color: var(--text-secondary); text-decoration: none; font-size: 14px; font-weight: 500; transition: var(--transition); margin-bottom: 2px; cursor: pointer; border: none; background: none; width: 100%; text-align: left; }
        .inv-nav-item:hover { background: var(--bg-card); color: var(--text-primary); }
        .inv-nav-item.active { background: rgba(16, 185, 129, 0.12); color: #10b981; border-left: 3px solid #10b981; }
        .inv-nav-item .icon { width: 20px; text-align: center; font-size: 15px; flex-shrink: 0; }
        .inv-sidebar-footer { padding: 16px 20px; border-top: 1px solid var(--border); }
        .inv-sidebar-user { display: flex; align-items: center; gap: 12px; }
        .inv-sidebar-user .avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #10b981, var(--info)); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; color: #000; }
        .inv-sidebar-user .name { font-size: 14px; font-weight: 600; color: var(--text-primary); }
        .inv-sidebar-user .role { font-size: 12px; color: var(--text-muted); }
        .inv-main { margin-left: 240px; min-height: 100vh; }
        .inv-topbar { padding: 16px 32px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; background: var(--bg-secondary); position: sticky; top: 0; z-index: 50; backdrop-filter: blur(10px); }
        .inv-topbar h2 { font-size: 22px; font-weight: 700; }
        .inv-topbar-actions { display: flex; align-items: center; gap: 12px; }
        .inv-content { padding: 32px; }
        .inv-back-link { color: var(--text-muted); font-size: 13px; text-decoration: none; display: flex; align-items: center; gap: 6px; transition: color 0.2s; }
        .inv-back-link:hover { color: #10b981; }

        /* Modal styles */
        .inv-modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 200; backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .inv-modal-overlay.open { display: flex; }
        .inv-modal { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: var(--radius); width: 100%; max-width: 560px; max-height: 85vh; overflow-y: auto; box-shadow: var(--shadow); }
        .inv-modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .inv-modal-header h3 { font-size: 18px; font-weight: 700; }
        .inv-modal-close { background: none; border: none; color: var(--text-muted); font-size: 22px; cursor: pointer; padding: 4px 8px; border-radius: 4px; }
        .inv-modal-close:hover { background: var(--bg-card); color: var(--text-primary); }
        .inv-modal-body { padding: 24px; }
        .inv-modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; }

        /* Loading spinner */
        .inv-loading { display: flex; align-items: center; justify-content: center; padding: 48px; color: var(--text-muted); font-size: 14px; }
        .inv-spinner { width: 24px; height: 24px; border: 3px solid var(--border); border-top-color: #10b981; border-radius: 50%; animation: inv-spin 0.8s linear infinite; margin-right: 12px; }
        @keyframes inv-spin { to { transform: rotate(360deg); } }

        /* Pagination */
        .inv-pagination { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 20px; }
        .inv-pagination button { padding: 6px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg-card); color: var(--text-secondary); cursor: pointer; font-size: 13px; transition: var(--transition); }
        .inv-pagination button:hover:not(:disabled) { border-color: #10b981; color: #10b981; }
        .inv-pagination button:disabled { opacity: 0.4; cursor: not-allowed; }
        .inv-pagination button.active { background: #10b981; color: #000; border-color: #10b981; font-weight: 600; }
        .inv-pagination span { color: var(--text-muted); font-size: 13px; }

        /* Empty state */
        .inv-empty { text-align: center; padding: 48px 24px; color: var(--text-muted); }
        .inv-empty .icon { font-size: 48px; margin-bottom: 16px; opacity: 0.4; }
        .inv-empty h3 { font-size: 18px; color: var(--text-secondary); margin-bottom: 8px; }
        .inv-empty p { font-size: 14px; }

        /* Coming soon placeholder */
        .inv-coming-soon { text-align: center; padding: 80px 24px; }
        .inv-coming-soon .icon { font-size: 56px; margin-bottom: 20px; opacity: 0.3; }
        .inv-coming-soon h3 { font-size: 22px; font-weight: 700; margin-bottom: 8px; color: var(--text-secondary); }
        .inv-coming-soon p { font-size: 14px; color: var(--text-muted); max-width: 400px; margin: 0 auto; }

        /* Row danger highlight */
        .inv-row-danger td { background: rgba(239, 68, 68, 0.08) !important; }

        /* Search bar */
        .inv-search-bar { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .inv-search-bar input,
        .inv-search-bar select { padding: 10px 14px; background: var(--bg-input); border: 1px solid var(--border); border-radius: var(--radius-sm); color: var(--text-primary); font-size: 14px; outline: none; transition: var(--transition); }
        .inv-search-bar input:focus,
        .inv-search-bar select:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15); }
        .inv-search-bar input { flex: 1; min-width: 200px; }

        /* Responsive */
        @media (max-width: 1024px) {
            .inv-sidebar { transform: translateX(-100%); z-index: 150; }
            .inv-sidebar.open { transform: translateX(0); }
            .inv-main { margin-left: 0; }
        }
        @media (max-width: 768px) {
            .inv-content { padding: 16px; }
            .inv-topbar { padding: 12px 16px; }
            .inv-topbar h2 { font-size: 18px; }
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
        <aside class="inv-sidebar" id="invSidebar">
            <div class="inv-sidebar-header">
                <a href="#dashboard">
                    <span class="brand-icon">&#128230;</span>
                    <div>
                        <span class="brand-text">Inventory<span>Mgmt</span></span>
                        <span class="brand-sub">Stock &amp; Fulfillment</span>
                    </div>
                </a>
            </div>
            <nav class="inv-sidebar-nav">
                <div class="inv-nav-section">
                    <div class="inv-nav-title">Navigation</div>
                    <a href="#dashboard" class="inv-nav-item active" data-route="dashboard">
                        <span class="icon">&#9632;</span> Dashboard
                    </a>
                    <a href="#items" class="inv-nav-item" data-route="items">
                        <span class="icon">&#128220;</span> Items
                    </a>
                    <a href="#low-stock" class="inv-nav-item" data-route="low-stock">
                        <span class="icon">&#9888;</span> Low Stock
                    </a>
                    <a href="#transactions" class="inv-nav-item" data-route="transactions">
                        <span class="icon">&#128259;</span> Transactions
                    </a>
                    <a href="#purchase-orders" class="inv-nav-item" data-route="purchase-orders">
                        <span class="icon">&#128196;</span> Purchase Orders
                    </a>
                    <a href="#suppliers" class="inv-nav-item" data-route="suppliers">
                        <span class="icon">&#129309;</span> Suppliers
                    </a>
                </div>
                <div class="inv-nav-section">
                    <div class="inv-nav-title">System</div>
                    <a href="/dashboard" class="inv-nav-item inv-back-link">
                        <span class="icon">&#8592;</span> Back to Dashboard
                    </a>
                </div>
            </nav>
            <div class="inv-sidebar-footer">
                <div class="inv-sidebar-user">
                    <div class="avatar"><?= strtoupper(substr($username, 0, 1)) ?></div>
                    <div>
                        <div class="name"><?= htmlspecialchars($username) ?></div>
                        <div class="role"><?= htmlspecialchars($userRole) ?></div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="inv-main">
            <div class="inv-topbar">
                <div style="display:flex;align-items:center;gap:12px">
                    <button class="mobile-menu-toggle" onclick="document.getElementById('invSidebar').classList.toggle('open')">&#9776;</button>
                    <h2 id="pageTitle">Dashboard</h2>
                </div>
                <div class="inv-topbar-actions">
                    <button class="btn btn-sm btn-outline" onclick="toggleTheme()" title="Toggle theme">
                        <span id="themeIcon"><?= $theme === 'dark' ? '&#9788;' : '&#9789;' ?></span>
                    </button>
                </div>
            </div>
            <div class="inv-content" id="appRoot">
                <div class="inv-loading"><div class="inv-spinner"></div> Loading...</div>
            </div>
        </div>
    </div>

    <div class="mobile-overlay" id="mobileOverlay" onclick="document.getElementById('invSidebar').classList.remove('open');this.classList.remove('active')"></div>

    <!-- Modal container -->
    <div class="inv-modal-overlay" id="modalOverlay">
        <div class="inv-modal" id="modalContainer"></div>
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
    <script src="js/api.js"></script>
    <script src="js/views/dashboard.js"></script>
    <script src="js/views/items.js"></script>
    <script src="js/views/low-stock.js"></script>
    <script src="js/views/transactions.js"></script>
    <script src="js/views/purchase-orders.js"></script>
    <script src="js/views/suppliers.js"></script>
    <script src="js/app.js"></script>
</body>
</html>
