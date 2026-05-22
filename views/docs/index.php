<?php
$pageTitle = 'Help & Documentation';

$docsDir = dirname(dirname(__DIR__)) . '/docs';
$requestedPage = $page ?? 'getting-started';
$requestedPage = preg_replace('/[^a-zA-Z0-9\/_-]/', '', $requestedPage);

$filePath = $docsDir . '/' . $requestedPage . '.md';
if (!file_exists($filePath)) {
    $filePath = $docsDir . '/getting-started.md';
    $requestedPage = 'getting-started';
}

$content = file_get_contents($filePath);
$title = \Phoenix\Markdown::extractTitle($content);
$html = \Phoenix\Markdown::toHtml($content);

$tocSections = [
    'Getting Started' => [
        'getting-started' => 'First Steps',
        'navigation' => 'Using the Dashboard',
        'user-account' => 'Your Account',
    ],
    'Point of Sale' => [
        'pos/overview' => 'Overview',
        'pos/opening-register' => 'Opening the Register',
        'pos/making-a-sale' => 'Making a Sale',
        'pos/cash-payments' => 'Cash Payments',
        'pos/card-payments' => 'Card Payments',
        'pos/invoicing' => 'Invoicing',
        'pos/purchase-orders' => 'Purchase Orders',
        'pos/refunds' => 'Refunds',
        'pos/customers' => 'Customers',
        'pos/end-of-day' => 'End of Day',
        'pos/troubleshooting' => 'Troubleshooting',
    ],
    'Inventory' => [
        'inventory/overview' => 'Overview',
        'inventory/adding-items' => 'Adding Items',
        'inventory/receiving-stock' => 'Receiving Stock',
        'inventory/shipping-stock' => 'Shipping Stock',
        'inventory/low-stock-alerts' => 'Low Stock Alerts',
        'inventory/troubleshooting' => 'Troubleshooting',
    ],
    'Maintenance (CMMS)' => [
        'cmms/overview' => 'Overview',
        'cmms/creating-work-orders' => 'Work Orders',
        'cmms/equipment' => 'Equipment',
        'cmms/pm-schedules' => 'PM Schedules',
        'cmms/parts' => 'Parts',
        'cmms/troubleshooting' => 'Troubleshooting',
    ],
    'Production' => [
        'production/overview' => 'Overview',
        'production/production-lines' => 'Production Lines',
        'production/logging-runs' => 'Logging Runs',
        'production/downtime' => 'Downtime',
        'production/defects' => 'Defects',
    ],
    'Scheduling' => [
        'scheduling/overview' => 'Overview',
        'scheduling/viewing-schedule' => 'Your Schedule',
        'scheduling/requesting-time-off' => 'Time Off',
        'scheduling/shift-swaps' => 'Shift Swaps',
    ],
    'Chat' => [
        'chat/overview' => 'Overview',
        'chat/using-chat' => 'Using Chat',
    ],
    'Reports' => [
        'reports/overview' => 'Overview',
        'reports/running-reports' => 'Running Reports',
        'reports/creating-reports' => 'Creating Reports',
    ],
    'Administration' => [
        'admin/managing-users' => 'Managing Users',
        'admin/roles-permissions' => 'Roles & Permissions',
        'admin/system-settings' => 'System Settings',
        'admin/installing-modules' => 'Installing Modules',
        'admin/backup-restore' => 'Backup & Restore',
    ],
    'Reference' => [
        'faq' => 'FAQ',
        'glossary' => 'Glossary',
    ],
];
?>

<?php \Phoenix\View::section('head') ?>
<style>
.docs-layout { display: grid; grid-template-columns: 260px 1fr; gap: 0; min-height: calc(100vh - 140px); }
.docs-toc { border-right: 1px solid var(--border); padding: 24px 0; overflow-y: auto; max-height: calc(100vh - 140px); position: sticky; top: 80px; }
.docs-toc-section { margin-bottom: 20px; }
.docs-toc-title { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1.5px; padding: 0 20px; margin-bottom: 6px; }
.docs-toc-link { display: block; padding: 5px 20px; font-size: 13px; color: var(--text-secondary); text-decoration: none; border-left: 2px solid transparent; }
.docs-toc-link:hover { color: var(--text-primary); background: var(--bg-card); }
.docs-toc-link.active { color: var(--accent); border-left-color: var(--accent); background: var(--accent-glow); font-weight: 600; }
.docs-content { padding: 32px 40px; max-width: 800px; }
.docs-search { padding: 12px 20px; border-bottom: 1px solid var(--border); }
.docs-search input { width: 100%; padding: 8px 12px; background: var(--bg-input); border: 1px solid var(--border); border-radius: var(--radius-sm); color: var(--text-primary); font-size: 13px; outline: none; }
.docs-search input:focus { border-color: var(--accent); }
.docs-breadcrumb { font-size: 13px; color: var(--text-muted); margin-bottom: 8px; }
.docs-breadcrumb a { color: var(--accent); text-decoration: none; }
@media (max-width: 768px) {
    .docs-layout { grid-template-columns: 1fr; }
    .docs-toc { display: none; }
    .docs-content { padding: 16px; }
}
</style>
<?php \Phoenix\View::endSection() ?>

<div class="docs-layout">
    <div class="docs-toc">
        <div class="docs-search">
            <input type="text" id="docsSearch" placeholder="Search documentation..." oninput="filterDocs(this.value)">
        </div>
        <?php foreach ($tocSections as $sectionTitle => $pages): ?>
        <div class="docs-toc-section" data-section>
            <div class="docs-toc-title"><?= htmlspecialchars($sectionTitle) ?></div>
            <?php foreach ($pages as $slug => $label): ?>
            <a href="/docs/<?= htmlspecialchars($slug) ?>"
               class="docs-toc-link <?= $requestedPage === $slug ? 'active' : '' ?>"
               data-label="<?= htmlspecialchars(strtolower($label)) ?>">
                <?= htmlspecialchars($label) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="docs-content">
        <?php
        $parts = explode('/', $requestedPage);
        if (count($parts) > 1) {
            $sectionName = ucfirst($parts[0]);
            echo '<div class="docs-breadcrumb"><a href="/docs/getting-started">Docs</a> / <a href="/docs/' . htmlspecialchars($parts[0]) . '/overview">' . htmlspecialchars($sectionName) . '</a> / ' . htmlspecialchars($title) . '</div>';
        }
        ?>
        <?= $html ?>
    </div>
</div>

<?php \Phoenix\View::section('scripts') ?>
<script>
function filterDocs(query) {
    query = query.toLowerCase();
    document.querySelectorAll('.docs-toc-link').forEach(function(link) {
        var label = link.getAttribute('data-label') || '';
        link.style.display = (!query || label.indexOf(query) !== -1) ? '' : 'none';
    });
    document.querySelectorAll('.docs-toc-section').forEach(function(section) {
        var visibleLinks = section.querySelectorAll('.docs-toc-link[style=""], .docs-toc-link:not([style])');
        section.style.display = visibleLinks.length > 0 || !query ? '' : 'none';
    });
}
</script>
<?php \Phoenix\View::endSection() ?>
