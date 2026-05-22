import { api } from '../api.js';

export async function DefectsView() {
    const el = document.createElement('div');
    const data = await api.get('defects').catch(() => ({ defects: [] }));
    const items = data.defects || [];

    el.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
        <h3>Defect Tracker</h3>
        <button class="btn btn-primary" style="width:auto">+ Log Defect</button>
    </div>
    <div class="card"><div class="card-body">
        <div class="table-wrapper"><table class="phoenix-table"><thead><tr>
            <th>Type</th><th>Severity</th><th>Qty</th><th>Disposition</th><th>Description</th><th>Reported By</th>
        </tr></thead><tbody>
        ${items.length === 0 ? '<tr><td colspan="6" style="text-align:center;color:var(--text-muted)">No defects recorded</td></tr>' :
        items.map(d => `<tr>
            <td><strong>${esc(d.defect_type||'—')}</strong></td>
            <td><span class="badge badge-${d.severity==='critical'?'danger':d.severity==='major'?'warning':'info'}">${esc(d.severity)}</span></td>
            <td>${d.quantity||0}</td>
            <td><span class="badge badge-${d.disposition==='scrap'?'danger':d.disposition==='rework'?'warning':'success'}">${esc(d.disposition||'—')}</span></td>
            <td>${esc(d.description||'—')}</td>
            <td>${esc(d.reported_by||'—')}</td>
        </tr>`).join('')}
        </tbody></table></div>
    </div></div>`;
    return el;
}

function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
