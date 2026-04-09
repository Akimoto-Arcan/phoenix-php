/**
 * PhoenixPHP Dashboard — CDAC Programming
 * Demo interaction layer
 */

// Page navigation
function showPage(pageId, el) {
    document.querySelectorAll('.page-content').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    document.getElementById('page-' + pageId).style.display = 'block';
    if (el) el.classList.add('active');

    const titles = {
        dashboard: 'Dashboard',
        analytics: 'Analytics',
        users: 'Users & RBAC',
        api: 'API Explorer',
        activity: 'Activity Log',
        inventory: 'Inventory Management',
        modules: 'Module Marketplace',
        cache: 'Cache Manager',
        security: 'Security',
        settings: 'Settings'
    };
    document.getElementById('pageTitle').textContent = titles[pageId] || 'Dashboard';

    if (pageId === 'analytics') initPerfChart();
}

// Animated number counter
function animateValue(id, start, end, duration) {
    const el = document.getElementById(id);
    if (!el) return;
    const range = end - start;
    const startTime = performance.now();
    function step(timestamp) {
        const progress = Math.min((timestamp - startTime) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        const current = Math.floor(start + range * eased);
        el.textContent = current.toLocaleString();
        if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
}

// Traffic chart
const ctx = document.getElementById('trafficChart');
if (ctx) {
    const gradient = ctx.getContext('2d');
    const grad = gradient.createLinearGradient(0, 0, 0, 280);
    grad.addColorStop(0, 'rgba(245, 158, 11, 0.3)');
    grad.addColorStop(1, 'rgba(245, 158, 11, 0.0)');

    const grad2 = gradient.createLinearGradient(0, 0, 0, 280);
    grad2.addColorStop(0, 'rgba(59, 130, 246, 0.2)');
    grad2.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

    window.trafficChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'API Requests',
                data: [3200, 4100, 3800, 5200, 4800, 2100, 2600],
                borderColor: '#f59e0b',
                backgroundColor: grad,
                borderWidth: 2.5,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#f59e0b',
                pointBorderColor: '#0f0f1a',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 7
            }, {
                label: 'Cache Hits',
                data: [2900, 3700, 3500, 4800, 4400, 1900, 2300],
                borderColor: '#3b82f6',
                backgroundColor: grad2,
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#3b82f6',
                pointBorderColor: '#0f0f1a',
                pointBorderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: '#8892b0', font: { family: 'Inter', size: 12 } }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(42, 42, 74, 0.3)' },
                    ticks: { color: '#5a6380', font: { family: 'Inter' } }
                },
                y: {
                    grid: { color: 'rgba(42, 42, 74, 0.3)' },
                    ticks: { color: '#5a6380', font: { family: 'Inter' } }
                }
            },
            interaction: { intersect: false, mode: 'index' }
        }
    });
}

function updateChart(range) {
    if (!window.trafficChart) return;
    const data = range === 'month'
        ? { labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'], data1: [22400, 25800, 24100, 28300], data2: [20100, 23500, 22000, 26100] }
        : { labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], data1: [3200, 4100, 3800, 5200, 4800, 2100, 2600], data2: [2900, 3700, 3500, 4800, 4400, 1900, 2300] };
    window.trafficChart.data.labels = data.labels;
    window.trafficChart.data.datasets[0].data = data.data1;
    window.trafficChart.data.datasets[1].data = data.data2;
    window.trafficChart.update();
}

// Performance chart (analytics page)
function initPerfChart() {
    const el = document.getElementById('perfChart');
    if (!el || el.dataset.init) return;
    el.dataset.init = 'true';

    const g = el.getContext('2d');
    const grad = g.createLinearGradient(0, 0, 0, 280);
    grad.addColorStop(0, 'rgba(16, 185, 129, 0.3)');
    grad.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

    new Chart(el, {
        type: 'line',
        data: {
            labels: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00', '24:00'],
            datasets: [{
                label: 'Avg Response Time (ms)',
                data: [42, 38, 55, 72, 65, 48, 41],
                borderColor: '#10b981',
                backgroundColor: grad,
                borderWidth: 2.5,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#0f0f1a',
                pointBorderWidth: 2,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: '#8892b0', font: { family: 'Inter', size: 12 } } } },
            scales: {
                x: { grid: { color: 'rgba(42, 42, 74, 0.3)' }, ticks: { color: '#5a6380' } },
                y: { grid: { color: 'rgba(42, 42, 74, 0.3)' }, ticks: { color: '#5a6380' } }
            }
        }
    });
}

// Animate stats on load
window.addEventListener('DOMContentLoaded', () => {
    animateValue('stat-users', 0, 128, 1500);
    setTimeout(() => {
        const apiEl = document.getElementById('stat-api');
        if (apiEl) {
            let start = 0;
            const end = 24.8;
            const duration = 1500;
            const startTime = performance.now();
            function step(ts) {
                const p = Math.min((ts - startTime) / duration, 1);
                const e = 1 - Math.pow(1 - p, 3);
                apiEl.textContent = (start + (end - start) * e).toFixed(1) + 'K';
                if (p < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        }
    }, 300);
});

// Simulate live activity (adds new items periodically)
const activities = [
    { dot: 'green', text: '<strong>operator_12</strong> logged in successfully', time: 'Just now' },
    { dot: 'blue', text: 'API <strong>/v1/modules/data</strong> called — 200 OK (12ms)', time: 'Just now' },
    { dot: 'amber', text: 'Cache key <strong>user_prefs_44</strong> expired and refreshed', time: 'Just now' },
    { dot: 'green', text: 'Database backup shard 3 — <strong>completed</strong>', time: 'Just now' },
    { dot: 'red', text: 'Failed login attempt for <strong>unknown@test.com</strong>', time: 'Just now' },
    { dot: 'blue', text: 'Query optimized: <strong>248ms → 18ms</strong> (index applied)', time: 'Just now' },
];

let actIdx = 0;
setInterval(() => {
    const feed = document.getElementById('activityFeed');
    if (!feed || document.getElementById('page-dashboard').style.display === 'none') return;

    const act = activities[actIdx % activities.length];
    const li = document.createElement('li');
    li.className = 'activity-item';
    li.style.opacity = '0';
    li.style.transform = 'translateY(-10px)';
    li.innerHTML = `<span class="activity-dot ${act.dot}"></span><div><div class="activity-text">${act.text}</div><div class="activity-time">${act.time}</div></div>`;

    feed.insertBefore(li, feed.firstChild);
    requestAnimationFrame(() => {
        li.style.transition = 'all 0.4s ease';
        li.style.opacity = '1';
        li.style.transform = 'translateY(0)';
    });

    if (feed.children.length > 8) feed.removeChild(feed.lastChild);
    actIdx++;
}, 5000);
