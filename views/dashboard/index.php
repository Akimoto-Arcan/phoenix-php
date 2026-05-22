<?php $pageTitle = 'Dashboard'; ?>

<div style="margin-bottom:32px">
    <h1 style="font-size:28px;font-weight:800;margin-bottom:4px">Welcome back, <?= htmlspecialchars($username ?? 'User') ?></h1>
    <p style="color:var(--text-muted);font-size:14px"><?= date('l, F j, Y') ?></p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon amber">&#9879;</div>
        <div class="stat-value" id="stat-modules">0</div>
        <div class="stat-label">Installed Modules</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">&#128100;</div>
        <div class="stat-value" id="stat-role"><?= htmlspecialchars($userRole ?? 'N/A') ?></div>
        <div class="stat-label">Your Role</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">&#x2714;</div>
        <div class="stat-value">Active</div>
        <div class="stat-label">System Status</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">&#128196;</div>
        <div class="stat-value"><?= phpversion() ?></div>
        <div class="stat-label">PHP Version</div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h3>System Information</h3>
        </div>
        <div class="card-body">
            <div class="status-row">
                <span class="status-label">
                    <span class="status-indicator online"></span>
                    Application
                </span>
                <span class="status-value">Phoenix ERP v1.0.0</span>
            </div>
            <div class="status-row">
                <span class="status-label">
                    <span class="status-indicator online"></span>
                    PHP Version
                </span>
                <span class="status-value"><?= phpversion() ?></span>
            </div>
            <div class="status-row">
                <span class="status-label">
                    <span class="status-indicator online"></span>
                    Server
                </span>
                <span class="status-value"><?= htmlspecialchars(php_uname('s') . ' ' . php_uname('r')) ?></span>
            </div>
            <div class="status-row">
                <span class="status-label">
                    <span class="status-indicator online"></span>
                    Database
                </span>
                <span class="status-value">MySQL</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Installed Modules</h3>
        </div>
        <div class="card-body" id="modules-list">
            <p style="color:var(--text-muted);font-size:13px">Loading modules...</p>
        </div>
    </div>
</div>

<?php \Phoenix\View::section('scripts') ?>
<script>
(function() {
    var modules = <?= json_encode(array_values($installedModules ?? [])) ?>;
    document.getElementById('stat-modules').textContent = modules.length;

    var list = document.getElementById('modules-list');
    if (modules.length === 0) {
        list.innerHTML = '<p style="color:var(--text-muted);font-size:13px">No modules installed. Visit the install wizard to enable modules.</p>';
        return;
    }

    var html = '';
    modules.forEach(function(m) {
        html += '<div class="status-row">';
        html += '<span class="status-label"><span class="status-indicator online"></span>' + (m.name || m.id) + '</span>';
        html += '<span class="status-value">v' + (m.version || '1.0') + '</span>';
        html += '</div>';
    });
    list.innerHTML = html;
})();
</script>
<?php \Phoenix\View::endSection() ?>
