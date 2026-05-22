<?php
$currentUri = $_SERVER['REQUEST_URI'] ?? '/';
$groups = $userGroups ?? [];
if (is_string($groups)) $groups = array_map('trim', explode(',', $groups));

$isAdmin = in_array('SuperAdmin', $groups) || in_array('Admin', $groups);

$coreNav = [
    ['label' => 'Dashboard', 'icon' => "\u{25A6}", 'href' => '/dashboard'],
];

$moduleItems = $moduleNavItems ?? [];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="/dashboard" class="brand">
            <span class="brand-icon">\u{1F525}</span>
            <div>
                <span class="brand-text">Phoenix<span>PHP</span></span>
                <span class="brand-sub">ERP &amp; CMMS</span>
            </div>
        </a>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Main</div>
            <?php foreach ($coreNav as $item): ?>
            <a href="<?= htmlspecialchars($item['href']) ?>"
               class="nav-item <?= ($currentUri === $item['href'] || strpos($currentUri, $item['href'] . '/') === 0) ? 'active' : '' ?>">
                <span style="width:20px;text-align:center;font-size:16px"><?= $item['icon'] ?></span>
                <?= htmlspecialchars($item['label']) ?>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($moduleItems)): ?>
        <div class="nav-section">
            <div class="nav-section-title">Modules</div>
            <?php foreach ($moduleItems as $item):
                if (isset($item['permission']) && !\Phoenix\Auth::can($item['permission'])) continue;
                $modHref = $item['href'] ?? '#';
                $modActive = strpos($currentUri, $modHref) === 0;
            ?>
            <a href="<?= htmlspecialchars($modHref) ?>"
               class="nav-item <?= $modActive ? 'active' : '' ?>">
                <span style="width:20px;text-align:center;font-size:16px"><?= $item['icon'] ?? "\u{25CF}" ?></span>
                <?= htmlspecialchars($item['label'] ?? $item['name'] ?? '') ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <div class="nav-section">
            <div class="nav-section-title">Administration</div>
            <a href="/admin/users" class="nav-item <?= strpos($currentUri, '/admin/users') === 0 ? 'active' : '' ?>">
                <span style="width:20px;text-align:center;font-size:16px">\u{1F465}</span>
                Users
            </a>
            <a href="/admin/roles" class="nav-item <?= strpos($currentUri, '/admin/roles') === 0 ? 'active' : '' ?>">
                <span style="width:20px;text-align:center;font-size:16px">\u{1F512}</span>
                Roles &amp; Permissions
            </a>
            <a href="/settings" class="nav-item <?= $currentUri === '/settings' ? 'active' : '' ?>">
                <span style="width:20px;text-align:center;font-size:16px">\u{2699}</span>
                Settings
            </a>
        </div>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar"><?= strtoupper(substr($username ?? 'U', 0, 1)) ?></div>
            <div class="user-info">
                <div class="name"><?= htmlspecialchars($username ?? 'User') ?></div>
                <div class="role"><?= htmlspecialchars($userRole ?? '') ?></div>
            </div>
        </div>
        <a href="/logout" class="nav-item" style="margin-top:8px;color:var(--danger)">
            <span style="width:20px;text-align:center;font-size:14px">\u{2192}</span>
            Sign Out
        </a>
    </div>
</aside>
