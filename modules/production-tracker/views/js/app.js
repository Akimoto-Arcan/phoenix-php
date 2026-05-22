import { DashboardView } from './views/dashboard.js';
import { LinesView } from './views/lines.js';
import { RunsView } from './views/runs.js';
import { DowntimeView } from './views/downtime.js';
import { DefectsView } from './views/defects.js';

const routes = {
    'dashboard': DashboardView,
    'lines': LinesView,
    'runs': RunsView,
    'downtime': DowntimeView,
    'defects': DefectsView,
};

const app = document.getElementById('app');

document.querySelectorAll('[data-nav]').forEach(link => {
    link.addEventListener('click', e => {
        e.preventDefault();
        window.location.hash = link.getAttribute('data-nav');
    });
});

async function loadView() {
    const hash = (window.location.hash || '#dashboard').slice(1);
    const ViewFn = routes[hash] || routes['dashboard'];
    document.querySelectorAll('[data-nav]').forEach(n => n.classList.toggle('active', n.getAttribute('data-nav') === hash));
    app.innerHTML = '<div style="text-align:center;padding:60px;color:var(--text-muted)">Loading...</div>';
    try {
        const el = await ViewFn();
        app.innerHTML = '';
        app.appendChild(el);
    } catch(e) {
        app.innerHTML = '<div style="color:var(--danger);padding:20px">Error: ' + e.message + '</div>';
    }
}

window.addEventListener('hashchange', loadView);
loadView();
