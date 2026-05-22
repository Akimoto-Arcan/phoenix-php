import { api } from '../api.js';

export async function DowntimeView() {
    const el = document.createElement('div');
    const data = await api.get('downtime').catch(() => ({ downtime: [] }));
    const items = data.downtime || [];

    el.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
        <h3>Downtime Log</h3>
        <button class="btn btn-primary" style="width:auto" id="logDowntimeBtn">+ Log Downtime</button>
    </div>
    <div class="card"><div class="card-body">
        <div class="table-wrapper"><table class="phoenix-table"><thead><tr>
            <th>Line</th><th>Category</th><th>Reason</th><th>Duration</th><th>Started</th><th>Reported By</th>
        </tr></thead><tbody>
        ${items.length === 0 ? '<tr><td colspan="6" style="text-align:center;color:var(--text-muted)">No downtime recorded</td></tr>' :
        items.map(d => `<tr>
            <td>${esc(d.line_name || d.line_id)}</td>
            <td><span class="badge badge-warning">${esc(d.category||'—')}</span></td>
            <td>${esc(d.reason_detail||d.reason_code||'—')}</td>
            <td><strong>${d.duration_minutes||0} min</strong></td>
            <td>${esc(d.started_at||'')}</td>
            <td>${esc(d.reported_by||'—')}</td>
        </tr>`).join('')}
        </tbody></table></div>
    </div></div>`;
    return el;
}

function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
