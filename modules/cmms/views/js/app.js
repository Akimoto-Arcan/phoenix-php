import { DashboardView } from './views/dashboard.js';
import { WorkOrdersView } from './views/work-orders.js';
import { EquipmentView } from './views/equipment.js';
import { PmSchedulesView } from './views/pm-schedules.js';
import { PartsView } from './views/parts.js';

const routes = {
    'dashboard': DashboardView,
    'work-orders': WorkOrdersView,
    'equipment': EquipmentView,
    'pm-schedules': PmSchedulesView,
    'parts': PartsView,
};

const app = document.getElementById('app');
const titleEl = document.getElementById('pageTitle');

// Wire up sidebar nav
document.querySelectorAll('[data-nav]').forEach(link => {
    link.addEventListener('click', e => {
        e.preventDefault();
        window.location.hash = link.getAttribute('data-nav');
    });
});

async function loadView() {
    const hash = (window.location.hash || '#dashboard').slice(1);
    const ViewFn = routes[hash] || routes['dashboard'];

    // Update nav active state
    document.querySelectorAll('[data-nav]').forEach(n => {
        n.classList.toggle('active', n.getAttribute('data-nav') === hash);
    });

    app.innerHTML = '<div style="text-align:center;padding:60px;color:var(--text-muted)">Loading...</div>';

    try {
        const el = await ViewFn();
        app.innerHTML = '';
        app.appendChild(el);
    } catch(e) {
        app.innerHTML = '<div style="color:var(--danger);padding:20px">Error loading view: ' + e.message + '</div>';
        console.error(e);
    }
}

window.addEventListener('hashchange', loadView);
loadView();
