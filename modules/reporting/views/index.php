<?php
require_once __DIR__ . '/../../../bootstrap.php';
\Phoenix\Auth::require('/login');
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($_COOKIE['phoenix_theme'] ?? 'dark') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports — Phoenix ERP</title>
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
                <div class="nav-section-title">Reports</div>
                <a href="#library" class="nav-item active" data-nav="library">📚 Report Library</a>
                <a href="#builder" class="nav-item" data-nav="builder">🔨 Report Builder</a>
                <a href="#scheduled" class="nav-item" data-nav="scheduled">⏰ Scheduled</a>
            </div>
            <div class="nav-section"><div class="nav-section-title">Navigation</div><a href="/dashboard" class="nav-item">← Back to Dashboard</a></div>
        </nav>
    </aside>
    <div class="main-content">
        <header class="topbar"><div style="display:flex;align-items:center;gap:12px"><button class="mobile-menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open')">☰</button><h2>Reports</h2></div></header>
        <div class="content-area"><div id="app">Loading...</div></div>
    </div>
</div>
<script type="module">
const BASE = '/modules/reporting/api/routes.php';
const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

async function request(path, opts = {}) {
    const url = BASE + '?path=' + encodeURIComponent(path);
    const headers = { 'X-CSRF-Token': csrf, ...opts.headers };
    if (opts.body && typeof opts.body === 'object') { headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(opts.body); }
    const r = await fetch(url, { ...opts, headers });
    return r.json();
}

const api = { get: p => request(p), post: (p,b) => request(p,{method:'POST',body:b}) };
const app = document.getElementById('app');
function esc(s) { const d = document.createElement('div'); d.textContent = s||''; return d.innerHTML; }

const views = {
    async library() {
        const d = await api.get('definitions').catch(() => ({reports:[]}));
        const reports = d.reports || d.definitions || [];
        return `
        <div style="display:flex;justify-content:space-between;margin-bottom:24px"><h3>Report Library</h3><button class="btn btn-primary" style="width:auto" onclick="location.hash='builder'">+ New Report</button></div>
        ${reports.length === 0 ? '<div class="card"><div class="card-body" style="text-align:center;color:var(--text-muted);padding:40px"><p>No reports defined yet.</p><p style="margin-top:8px">Use the Report Builder to create your first report.</p></div></div>' :
        `<div class="module-grid">${reports.map(r => `
            <div class="module-card">
                <div class="module-card-header"><div><h3>${esc(r.name)}</h3><span class="module-version">${esc(r.category||'General')}</span></div></div>
                <div class="module-description">${esc(r.description||'No description')}</div>
                <div class="module-footer"><span class="badge badge-info">${esc(r.type||'table')}</span><button class="btn btn-sm btn-outline">Run</button></div>
            </div>
        `).join('')}</div>`}`;
    },
    async builder() {
        return `
        <h3 style="margin-bottom:24px">Report Builder</h3>
        <div class="card"><div class="card-body">
            <div class="form-group"><label>Report Name</label><input type="text" class="form-control" placeholder="Monthly Production Summary"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group"><label>Category</label><select class="form-control"><option>Production</option><option>Maintenance</option><option>Inventory</option><option>Quality</option><option>Custom</option></select></div>
                <div class="form-group"><label>Type</label><select class="form-control"><option>Table</option><option>Chart</option><option>Summary</option></select></div>
            </div>
            <div class="form-group"><label>SQL Query</label><textarea class="form-control" rows="6" placeholder="SELECT * FROM production_runs WHERE run_date >= CURDATE() - INTERVAL 30 DAY" style="font-family:monospace"></textarea></div>
            <button class="btn btn-primary" style="width:auto">Save Report</button>
        </div></div>`;
    },
    async scheduled() {
        const d = await api.get('schedules').catch(() => ({schedules:[]}));
        const items = d.schedules || [];
        return `
        <h3 style="margin-bottom:24px">Scheduled Reports</h3>
        <div class="card"><div class="card-body">
            <div class="table-wrapper"><table class="phoenix-table"><thead><tr><th>Report</th><th>Frequency</th><th>Next Run</th><th>Status</th></tr></thead><tbody>
            ${items.map(s=>`<tr><td>${esc(s.report_name||s.report_id)}</td><td>${esc(s.frequency)}</td><td>${esc(s.next_run||'—')}</td><td><span class="badge badge-${s.is_active?'success':'danger'}">${s.is_active?'Active':'Disabled'}</span></td></tr>`).join('')||'<tr><td colspan="4" style="color:var(--text-muted);text-align:center">No scheduled reports</td></tr>'}
            </tbody></table></div>
        </div></div>`;
    }
};

document.querySelectorAll('[data-nav]').forEach(link => {
    link.addEventListener('click', e => { e.preventDefault(); window.location.hash = link.getAttribute('data-nav'); });
});

async function load() {
    const hash = (window.location.hash || '#library').slice(1);
    document.querySelectorAll('[data-nav]').forEach(n => n.classList.toggle('active', n.getAttribute('data-nav') === hash));
    app.innerHTML = '<div style="text-align:center;padding:60px;color:var(--text-muted)">Loading...</div>';
    try { app.innerHTML = await (views[hash] || views.library)(); } catch(e) { app.innerHTML = '<div style="color:var(--danger);padding:20px">Error: '+e.message+'</div>'; }
}
window.addEventListener('hashchange', load);
load();
</script>
</body>
</html>
