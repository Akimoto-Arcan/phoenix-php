import { api } from '../api.js';

export async function DashboardView() {
    const el = document.createElement('div');

    let stats, lines, runs;
    try {
        [stats, lines, runs] = await Promise.all([
            api.get('stats').catch(() => ({ total_output: 0, avg_efficiency: 0, total_downtime: 0, defect_count: 0 })),
            api.get('lines').catch(() => ({ lines: [] })),
            api.get('runs?limit=10').catch(() => ({ runs: [] })),
        ]);
    } catch(e) {
        stats = { total_output: 0, avg_efficiency: 0, total_downtime: 0, defect_count: 0 };
        lines = { lines: [] };
        runs = { runs: [] };
    }

    const s = stats;
    const activeLines = (lines.lines || []).filter(l => l.status === 'running').length;

    el.innerHTML = `
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon green">🏭</div><div class="stat-value">${activeLines} / ${(lines.lines||[]).length}</div><div class="stat-label">Active Lines</div></div>
        <div class="stat-card"><div class="stat-icon blue">📦</div><div class="stat-value">${s.total_output || 0}</div><div class="stat-label">Total Output Today</div></div>
        <div class="stat-card"><div class="stat-icon amber">📊</div><div class="stat-value">${s.avg_efficiency || 0}%</div><div class="stat-label">Avg Efficiency</div></div>
        <div class="stat-card"><div class="stat-icon red">⏸</div><div class="stat-value">${s.total_downtime || 0}m</div><div class="stat-label">Downtime Today</div></div>
    </div>
    <div class="grid-2">
        <div class="card">
            <div class="card-header"><h3>Production Lines</h3></div>
            <div class="card-body">
                ${(lines.lines||[]).length === 0 ? '<p style="color:var(--text-muted)">No production lines configured.</p>' :
                (lines.lines||[]).map(l => `
                    <div class="status-row">
                        <span class="status-label">
                            <span class="status-indicator ${l.status === 'running' ? 'online' : l.status === 'maintenance' ? 'warning' : 'offline'}"></span>
                            ${esc(l.name)} <span style="color:var(--text-muted);font-size:12px">(${esc(l.code)})</span>
                        </span>
                        <span class="badge badge-${l.status === 'running' ? 'success' : l.status === 'idle' ? 'info' : 'danger'}">${esc(l.status)}</span>
                    </div>
                `).join('')}
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h3>Recent Runs</h3></div>
            <div class="card-body">
                ${(runs.runs||[]).length === 0 ? '<p style="color:var(--text-muted)">No production runs recorded.</p>' :
                `<div class="table-wrapper"><table class="phoenix-table"><thead><tr><th>Product</th><th>Output</th><th>Status</th></tr></thead><tbody>
                ${(runs.runs||[]).slice(0,8).map(r => `<tr><td>${esc(r.product_name||'—')}</td><td>${r.good_qty||0}</td><td><span class="badge badge-${r.status==='completed'?'success':'info'}">${esc(r.status)}</span></td></tr>`).join('')}
                </tbody></table></div>`}
            </div>
        </div>
    </div>`;
    return el;
}

function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
