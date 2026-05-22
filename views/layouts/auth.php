<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($theme ?? 'dark') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Login') ?> &mdash; <?= htmlspecialchars($appName ?? 'Phoenix') ?></title>
    <?= $csrfMeta ?? '' ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="/public/css/phoenix.css">
    <script>
    (function(){
        var t = localStorage.getItem('phoenix-theme') || 'dark';
        if (t === 'system') t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', t);
    })();
    </script>
</head>
<body>
    <div class="login-wrapper">
        <?= $content ?>
    </div>
</body>
</html>
