<?php $currentTheme = $theme ?? 'dark'; ?>
<header class="topbar">
    <div style="display:flex;align-items:center;gap:12px">
        <button class="mobile-menu-toggle" onclick="toggleSidebar()" aria-label="Toggle menu">&#9776;</button>
        <h2><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h2>
    </div>
    <div class="topbar-actions">
        <button onclick="toggleTheme()" class="btn btn-sm btn-outline" title="Toggle theme" style="font-size:18px;padding:6px 12px">
            <span id="themeIcon"><?= $currentTheme === 'dark' ? "\u{2600}" : "\u{263D}" ?></span>
        </button>
        <span style="font-size:13px;color:var(--text-muted)"><?= htmlspecialchars($username ?? '') ?></span>
    </div>
</header>
