<?php
require_once __DIR__ . '/../../../bootstrap.php';
\Phoenix\Auth::require('/login');
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($_COOKIE['phoenix_theme'] ?? 'dark') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Tracker — Phoenix ERP</title>
    <?= \Phoenix\CSRF::meta() ?>
    <link rel="stylesheet" href="/public/css/phoenix.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script>(function(){var t=localStorage.getItem('phoenix-theme')||'dark';if(t==='system')t=matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light';document.documentElement.setAttribute('data-theme',t)})()</script>
</head>
<body>
<div class="app-wrapper">
    <aside class="sidebar">
        <div class="sidebar-header"><a href="/dashboard" class="brand"><span class="brand-icon">🔥</span><div><span class="brand-text">Phoenix<span>PHP</span></span></div></a></div>
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Production</div>
                <a href="#dashboard" class="nav-item active" data-nav="dashboard">📊 Dashboard</a>
                <a href="#lines" class="nav-item" data-nav="lines">🏭 Lines</a>
                <a href="#runs" class="nav-item" data-nav="runs">▶ Production Runs</a>
                <a href="#downtime" class="nav-item" data-nav="downtime">⏸ Downtime</a>
                <a href="#defects" class="nav-item" data-nav="defects">⚠ Defects</a>
            </div>
            <div class="nav-section"><div class="nav-section-title">Navigation</div><a href="/dashboard" class="nav-item">← Back to Dashboard</a></div>
        </nav>
    </aside>
    <div class="main-content">
        <header class="topbar"><div style="display:flex;align-items:center;gap:12px"><button class="mobile-menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open')">☰</button><h2 id="pageTitle">Production Dashboard</h2></div></header>
        <div class="content-area"><div id="app">Loading...</div></div>
    </div>
</div>
<script type="module" src="/modules/production-tracker/views/js/app.js"></script>
</body>
</html>
