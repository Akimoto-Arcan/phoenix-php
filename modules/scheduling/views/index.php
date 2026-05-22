<?php
require_once __DIR__ . '/../../../bootstrap.php';
\Phoenix\Auth::require('/login');
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($_COOKIE['phoenix_theme'] ?? 'dark') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheduling — Phoenix ERP</title>
    <?= \Phoenix\CSRF::meta() ?>
    <link rel="stylesheet" href="/public/css/phoenix.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <script>(function(){var t=localStorage.getItem('phoenix-theme')||'dark';if(t==='system')t=matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light';document.documentElement.setAttribute('data-theme',t)})()</script>
</head>
<body>
<div class="app-wrapper">
    <aside class="sidebar">
        <div class="sidebar-header"><a href="/dashboard" class="brand"><span class="brand-icon">🔥</span><div><span class="brand-text">Phoenix<span>PHP</span></span></div></a></div>
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Scheduling</div>
                <a href="#dashboard" class="nav-item active" data-nav="dashboard">📊 Overview</a>
                <a href="#calendar" class="nav-item" data-nav="calendar">📅 Calendar</a>
                <a href="#time-off" class="nav-item" data-nav="time-off">🏖 Time Off</a>
                <a href="#swaps" class="nav-item" data-nav="swaps">🔄 Shift Swaps</a>
            </div>
            <div class="nav-section"><div class="nav-section-title">Navigation</div><a href="/dashboard" class="nav-item">← Back to Dashboard</a></div>
        </nav>
    </aside>
    <div class="main-content">
        <header class="topbar"><div style="display:flex;align-items:center;gap:12px"><button class="mobile-menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open')">☰</button><h2>Scheduling</h2></div></header>
        <div class="content-area"><div id="app">Loading...</div></div>
    </div>
</div>
<script type="module">
const BASE = '/modules/scheduling/api/routes.php';
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
    async dashboard() {
        const [shifts, depts] = await Promise.all([
            api.get('shifts').catch(() => ({shifts:[]})),
            api.get('departments').catch(() => ({departments:[]})),
        ]);
        return `
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon blue">📅</div><div class="stat-value">${(shifts.shifts||[]).length}</div><div class="stat-label">Shift Types</div></div>
            <div class="stat-card"><div class="stat-icon green">🏢</div><div class="stat-value">${(depts.departments||[]).length}</div><div class="stat-label">Departments</div></div>
        </div>
        <div class="card"><div class="card-header"><h3>Shift Definitions</h3></div><div class="card-body">
            <div class="table-wrapper"><table class="phoenix-table"><thead><tr><th>Shift</th><th>Start</th><th>End</th></tr></thead><tbody>
            ${(shifts.shifts||[]).map(s=>`<tr><td>${esc(s.name)}</td><td>${esc(s.start_time)}</td><td>${esc(s.end_time)}</td></tr>`).join('')||'<tr><td colspan="3" style="color:var(--text-muted);text-align:center">No shifts defined</td></tr>'}
            </tbody></table></div>
        </div></div>`;
    },
    async calendar() {
        const d = await api.get('assignments').catch(() => ({assignments:[]}));
        const items = d.assignments || [];
        return `
        <div style="margin-bottom:24px"><h3>Schedule Assignments</h3></div>
        <div class="card"><div class="card-body">
            <div class="table-wrapper"><table class="phoenix-table"><thead><tr><th>Employee</th><th>Date</th><th>Shift</th><th>Department</th><th>Status</th></tr></thead><tbody>
            ${items.map(a=>`<tr><td>${esc(a.username)}</td><td>${esc(a.schedule_date)}</td><td>${esc(a.shift_name||a.shift_id)}</td><td>${esc(a.department_name||a.department_id||'—')}</td><td><span class="badge badge-${a.status==='confirmed'?'success':'info'}">${esc(a.status)}</span></td></tr>`).join('')||'<tr><td colspan="5" style="color:var(--text-muted);text-align:center">No assignments</td></tr>'}
            </tbody></table></div>
        </div></div>`;
    },
    async 'time-off'() {
        const d = await api.get('time-off').catch(() => ({requests:[]}));
        const items = d.requests || d.time_off || [];
        return `
        <div style="display:flex;justify-content:space-between;margin-bottom:24px"><h3>Time Off Requests</h3><button class="btn btn-primary" style="width:auto">+ Request Time Off</button></div>
        <div class="card"><div class="card-body">
            <div class="table-wrapper"><table class="phoenix-table"><thead><tr><th>Employee</th><th>Type</th><th>Start</th><th>End</th><th>Status</th></tr></thead><tbody>
            ${items.map(r=>`<tr><td>${esc(r.username)}</td><td>${esc(r.type)}</td><td>${esc(r.start_date)}</td><td>${esc(r.end_date)}</td><td><span class="badge badge-${r.status==='approved'?'success':r.status==='denied'?'danger':'warning'}">${esc(r.status)}</span></td></tr>`).join('')||'<tr><td colspan="5" style="color:var(--text-muted);text-align:center">No requests</td></tr>'}
            </tbody></table></div>
        </div></div>`;
    },
    async swaps() {
        const d = await api.get('swaps').catch(() => ({swaps:[]}));
        const items = d.swaps || [];
        return `
        <div style="margin-bottom:24px"><h3>Shift Swap Requests</h3></div>
        <div class="card"><div class="card-body">
            <div class="table-wrapper"><table class="phoenix-table"><thead><tr><th>Requester</th><th>Target</th><th>Status</th></tr></thead><tbody>
            ${items.map(s=>`<tr><td>${esc(s.requester)}</td><td>${esc(s.target_user)}</td><td><span class="badge badge-${s.status==='approved'?'success':s.status==='denied'?'danger':'warning'}">${esc(s.status)}</span></td></tr>`).join('')||'<tr><td colspan="3" style="color:var(--text-muted);text-align:center">No swap requests</td></tr>'}
            </tbody></table></div>
        </div></div>`;
    }
};

document.querySelectorAll('[data-nav]').forEach(link => {
    link.addEventListener('click', e => { e.preventDefault(); window.location.hash = link.getAttribute('data-nav'); });
});

async function load() {
    const hash = (window.location.hash || '#dashboard').slice(1);
    document.querySelectorAll('[data-nav]').forEach(n => n.classList.toggle('active', n.getAttribute('data-nav') === hash));
    app.innerHTML = '<div style="text-align:center;padding:60px;color:var(--text-muted)">Loading...</div>';
    try { app.innerHTML = await (views[hash] || views.dashboard)(); } catch(e) { app.innerHTML = '<div style="color:var(--danger);padding:20px">Error: '+e.message+'</div>'; }
}
window.addEventListener('hashchange', load);
load();
</script>
</body>
</html>
