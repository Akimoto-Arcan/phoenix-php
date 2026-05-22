import { api } from '../api.js';

export async function LinesView() {
    const el = document.createElement('div');
    const data = await api.get('lines').catch(() => ({ lines: [] }));
    const lines = data.lines || [];

    el.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
        <h3>Production Lines (${lines.length})</h3>
        <button class="btn btn-primary" style="width:auto" id="addLineBtn">+ Add Line</button>
    </div>
    <div class="card"><div class="card-body">
        <div class="table-wrapper"><table class="phoenix-table"><thead><tr>
            <th>Name</th><th>Code</th><th>Type</th><th>Location</th><th>Status</th>
        </tr></thead><tbody>
        ${lines.length === 0 ? '<tr><td colspan="5" style="text-align:center;color:var(--text-muted)">No lines configured</td></tr>' :
        lines.map(l => `<tr>
            <td><strong>${esc(l.name)}</strong></td>
            <td>${esc(l.code)}</td>
            <td>${esc(l.type || '—')}</td>
            <td>${esc(l.location || '—')}</td>
            <td><span class="badge badge-${l.status==='running'?'success':l.status==='idle'?'info':l.status==='maintenance'?'warning':'danger'}">${esc(l.status)}</span></td>
        </tr>`).join('')}
        </tbody></table></div>
    </div></div>`;

    el.querySelector('#addLineBtn')?.addEventListener('click', () => showAddForm(el));
    return el;
}

async function showAddForm(container) {
    const form = document.createElement('div');
    form.className = 'card';
    form.style.marginBottom = '24px';
    form.innerHTML = `
    <div class="card-header"><h3>Add Production Line</h3></div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group"><label>Name</label><input type="text" class="form-control" id="lineName" required></div>
            <div class="form-group"><label>Code</label><input type="text" class="form-control" id="lineCode" required></div>
            <div class="form-group"><label>Type</label><input type="text" class="form-control" id="lineType"></div>
            <div class="form-group"><label>Location</label><input type="text" class="form-control" id="lineLocation"></div>
        </div>
        <div style="display:flex;gap:8px;margin-top:16px">
            <button class="btn btn-primary" style="width:auto" id="saveLineBtn">Save</button>
            <button class="btn btn-outline" style="width:auto" id="cancelLineBtn">Cancel</button>
        </div>
        <div id="lineFormStatus" style="margin-top:8px"></div>
    </div>`;

    container.prepend(form);
    form.querySelector('#cancelLineBtn').addEventListener('click', () => form.remove());
    form.querySelector('#saveLineBtn').addEventListener('click', async () => {
        try {
            await api.post('lines', {
                name: form.querySelector('#lineName').value,
                code: form.querySelector('#lineCode').value,
                type: form.querySelector('#lineType').value,
                location: form.querySelector('#lineLocation').value,
            });
            window.location.hash = '';
            window.location.hash = 'lines';
        } catch(e) {
            form.querySelector('#lineFormStatus').innerHTML = `<span style="color:var(--danger)">${e.message}</span>`;
        }
    });
}

function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
