import { api } from '../api.js';

export async function RunsView() {
    const el = document.createElement('div');
    const data = await api.get('runs').catch(() => ({ runs: [] }));
    const runs = data.runs || [];

    el.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
        <h3>Production Runs</h3>
        <button class="btn btn-primary" style="width:auto" id="addRunBtn">+ New Run</button>
    </div>
    <div class="card"><div class="card-body">
        <div class="table-wrapper"><table class="phoenix-table"><thead><tr>
            <th>Line</th><th>Product</th><th>Target</th><th>Good</th><th>Reject</th><th>Efficiency</th><th>Status</th><th>Date</th>
        </tr></thead><tbody>
        ${runs.length === 0 ? '<tr><td colspan="8" style="text-align:center;color:var(--text-muted)">No runs recorded</td></tr>' :
        runs.map(r => `<tr>
            <td>${esc(r.line_name || r.line_id)}</td>
            <td><strong>${esc(r.product_name||'—')}</strong></td>
            <td>${r.target_qty||0}</td>
            <td style="color:var(--success)">${r.good_qty||0}</td>
            <td style="color:${(r.reject_qty||0) > 0 ? 'var(--danger)' : 'inherit'}">${r.reject_qty||0}</td>
            <td>${r.efficiency||0}%</td>
            <td><span class="badge badge-${r.status==='completed'?'success':r.status==='in_progress'?'warning':'info'}">${esc(r.status)}</span></td>
            <td>${esc(r.run_date||'')}</td>
        </tr>`).join('')}
        </tbody></table></div>
    </div></div>`;
    return el;
}

function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
