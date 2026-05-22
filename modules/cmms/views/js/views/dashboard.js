import { api } from '../api.js';

export async function DashboardView() {
    const el = document.createElement('div');
    el.innerHTML = '<div style="text-align:center;padding:60px;color:var(--text-muted)">Loading dashboard...</div>';

    try {
        const [stats, woData] = await Promise.all([
            api.get('stats'),
            api.get('work-orders?status=open&per_page=5')
        ]);

        const recentWOs = woData.work_orders || [];

        el.innerHTML = `
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon amber">&#x1f4cb;</div>
                    <div class="stat-value">${stats.open_work_orders ?? 0}</div>
                    <div class="stat-label">Open Work Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red">&#x23f0;</div>
                    <div class="stat-value">${stats.overdue_pm ?? 0}</div>
                    <div class="stat-label">Overdue PMs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red">&#x26a0;</div>
                    <div class="stat-value">${stats.equipment_down ?? 0}</div>
                    <div class="stat-label">Down Equipment</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">&#x2705;</div>
                    <div class="stat-value">${stats.completed_this_month ?? 0}</div>
                    <div class="stat-label">Completed This Month</div>
                </div>
            </div>

            <div class="grid-3">
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Open Work Orders</h3>
                        <a href="#work-orders" class="btn btn-sm btn-outline">View All</a>
                    </div>
                    <div class="card-body" style="padding:0">
                        <div class="table-wrapper">
                            <table class="phoenix-table">
                                <thead>
                                    <tr>
                                        <th>WO #</th>
                                        <th>Title</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Equipment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${recentWOs.length === 0
                                        ? '<tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:32px">No open work orders</td></tr>'
                                        : recentWOs.map(wo => `
                                            <tr>
                                                <td style="font-weight:600">${esc(wo.wo_number)}</td>
                                                <td>${esc(wo.title)}</td>
                                                <td>${priorityBadge(wo.priority)}</td>
                                                <td>${statusBadge(wo.status)}</td>
                                                <td>${esc(wo.equipment_name || '-')}</td>
                                            </tr>
                                        `).join('')
                                    }
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Summary</h3>
                    </div>
                    <div class="card-body">
                        <div class="status-row">
                            <span class="status-label">Avg Completion Time</span>
                            <span class="status-value">${stats.avg_completion_hours ?? 0} hrs</span>
                        </div>
                        <div class="status-row">
                            <span class="status-label">Cost This Month</span>
                            <span class="status-value">$${Number(stats.total_cost_this_month ?? 0).toLocaleString()}</span>
                        </div>
                        <div class="status-row">
                            <span class="status-label">Open WOs</span>
                            <span class="status-value">${stats.open_work_orders ?? 0}</span>
                        </div>
                        <div class="status-row">
                            <span class="status-label">Overdue PMs</span>
                            <span class="status-value" style="color:${(stats.overdue_pm ?? 0) > 0 ? 'var(--danger)' : 'inherit'}">${stats.overdue_pm ?? 0}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    } catch (err) {
        el.innerHTML = `<div class="card"><div class="card-body" style="color:var(--danger)">Failed to load dashboard: ${esc(err.message)}</div></div>`;
    }

    return el;
}

function priorityBadge(priority) {
    const map = {
        emergency: 'badge-danger',
        high: 'badge-warning',
        medium: 'badge-info',
        low: 'badge-outline'
    };
    const cls = map[priority] || 'badge-info';
    return `<span class="badge ${cls}">${esc(priority)}</span>`;
}

function statusBadge(status) {
    const map = {
        open: 'badge-info',
        assigned: 'badge-info',
        in_progress: 'badge-warning',
        completed: 'badge-success',
        cancelled: 'badge-danger'
    };
    const cls = map[status] || 'badge-info';
    const label = (status || '').replace(/_/g, ' ');
    return `<span class="badge ${cls}">${esc(label)}</span>`;
}

function esc(str) {
    if (str == null) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}
