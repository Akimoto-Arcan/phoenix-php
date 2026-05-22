<?php
require_once __DIR__ . '/../../../bootstrap.php';
\Phoenix\Auth::require('/login');

$pageTitle = 'CMMS - Maintenance';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($_COOKIE['phoenix_theme'] ?? 'dark') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> — Phoenix ERP</title>
    <?= \Phoenix\CSRF::meta() ?>
    <link rel="stylesheet" href="/public/css/phoenix.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script>/* theme init */ (function(){var t=localStorage.getItem('phoenix-theme')||'dark';if(t==='system')t=matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light';document.documentElement.setAttribute('data-theme',t)})();</script>
</head>
<body>
    <div class="app-wrapper">
        <!-- Minimal sidebar for module -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="/dashboard" class="brand">
                    <span class="brand-icon">&#x1f525;</span>
                    <div><span class="brand-text">Phoenix<span>PHP</span></span></div>
                </a>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">CMMS</div>
                    <a href="#dashboard" class="nav-item active" data-nav="dashboard">&#x229e; Dashboard</a>
                    <a href="#work-orders" class="nav-item" data-nav="work-orders">&#x1f4cb; Work Orders</a>
                    <a href="#equipment" class="nav-item" data-nav="equipment">&#x2699; Equipment</a>
                    <a href="#pm-schedules" class="nav-item" data-nav="pm-schedules">&#x1f4c5; PM Schedules</a>
                    <a href="#parts" class="nav-item" data-nav="parts">&#x1f529; Parts</a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">Navigation</div>
                    <a href="/dashboard" class="nav-item">&larr; Back to Dashboard</a>
                </div>
            </nav>
        </aside>
        <div class="main-content">
            <header class="topbar">
                <div style="display:flex;align-items:center;gap:12px">
                    <button class="mobile-menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open')">&#x2630;</button>
                    <h2 id="pageTitle">CMMS Dashboard</h2>
                </div>
            </header>
            <div class="content-area">
                <div id="app">Loading...</div>
            </div>
        </div>
    </div>
    <script type="module" src="/modules/cmms/views/js/app.js"></script>
</body>
</html>
