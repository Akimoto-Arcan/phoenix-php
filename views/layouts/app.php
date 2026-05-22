<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($theme ?? 'dark') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> &mdash; <?= htmlspecialchars($appName ?? 'Phoenix') ?></title>
    <?= $csrfMeta ?? '' ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="/public/css/phoenix.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script>
    (function(){
        var t = localStorage.getItem('phoenix-theme') || '<?= htmlspecialchars($theme ?? 'dark') ?>';
        if (t === 'system') t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', t);
    })();
    </script>
    <?= \Phoenix\View::yieldSection('head') ?>
</head>
<body>
    <div class="app-wrapper">
        <?php \Phoenix\View::partial('sidebar'); ?>
        <div class="main-content">
            <?php \Phoenix\View::partial('topbar'); ?>
            <div class="content-area">
                <?php \Phoenix\View::partial('flash'); ?>
                <?= $content ?>
            </div>
        </div>
    </div>
    <div class="mobile-overlay" id="mobileOverlay" onclick="toggleSidebar()"></div>
    <script>
    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('open');
        document.getElementById('mobileOverlay').classList.toggle('active');
    }
    function toggleTheme() {
        var html = document.documentElement;
        var current = html.getAttribute('data-theme');
        var next = current === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        localStorage.setItem('phoenix-theme', next);
        var icon = document.getElementById('themeIcon');
        if (icon) icon.textContent = next === 'dark' ? '☀' : '☽';
        fetch('/api/v1/me', {
            method: 'POST',
            headers: {'Content-Type':'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''},
            body: JSON.stringify({theme_preference: next})
        }).catch(function(){});
    }
    </script>
    <?= \Phoenix\View::yieldSection('scripts') ?>
</body>
</html>
