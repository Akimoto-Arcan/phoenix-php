import { api } from '../api.js';

export async function PmSchedulesView() {
    const el = document.createElement('div');
    document.getElementById('pageTitle').textContent = 'PM Schedules';

    async function render() {
        el.innerHTML = '<div style="text-align:center;padding:60px;color:var(--text-muted)">Loading PM schedules...</div>';

        try {
            const [schedData, overdueData] = await Promise.all([
                api.get('pm-schedules'),
                api.get('pm-schedules/overdue')
            ]);

            const schedules = schedData.schedules || [];
            const overdueIds = new Set((overdueData.overdue || []).map(o => o.id));

            el.innerHTML = `
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
                    <div style="display:flex;gap:16px;align-items:center">
                        <span style="font-size:13px;color:var(--text-muted)">${schedules.length} active schedule${schedules.length !== 1 ? 's' : ''}</span>
                        ${overdueIds.size > 0 ? `<span class="badge badge-danger">${overdueIds.size} overdue</span>` : ''}
                    </div>
                    <button class="btn btn-primary btn-sm" id="btnAddPM">+ Add PM Schedule</button>
                </div>

                <div class="card">
                    <div class="card-body" style="padding:0">
                        <div class="table-wrapper">
                            <table class="phoenix-table">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Equipment</th>
                                        <th>Frequency</th>
                                        <th>Last Performed</th>
                                        <th>Next Due</th>
                                        <th>Assigned To</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${schedules.length === 0
                                        ? '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:32px">No PM schedules configured</td></tr>'
                                        : schedules.map(pm => {
                                            const isOverdue = overdueIds.has(pm.id);
                                            const rowStyle = isOverdue ? 'background:rgba(239,68,68,0.08)' : '';
                                            return `
                                                <tr style="${rowStyle}">
                                                    <td>
                                                        ${esc(pm.title)}
                                                        ${isOverdue ? '<span class="badge badge-danger" style="margin-left:8px">Overdue</span>' : ''}
                                                    </td>
                                                    <td>
                                                        <span style="font-weight:600">${esc(pm.equipment_name || '-')}</span>
                                                        ${pm.asset_tag ? `<br><span style="font-size:11px;color:var(--text-muted);font-family:monospace">${esc(pm.asset_tag)}</span>` : ''}
                                                    </td>
                                                    <td>${frequencyBadge(pm.frequency)}</td>
                                                    <td>${pm.last_performed ? formatDate(pm.last_performed) : '<span style="color:var(--text-muted)">Never</span>'}</td>
                                                    <td style="${isOverdue ? 'color:var(--danger);font-weight:600' : ''}">${pm.next_due ? formatDate(pm.next_due) : '-'}</td>
                                                    <td>${esc(pm.assigned_to || '-')}</td>
                                                </tr>
                                            `;
                                        }).join('')
                                    }
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;

            el.querySelector('#btnAddPM').addEventListener('click', () => showCreateModal());
        } catch (err) {
            el.innerHTML = `<div class="card"><div class="card-body" style="color:var(--danger)">Failed to load PM schedules: ${esc(err.message)}</div></div>`;
        }
    }

    function showCreateModal() {
        // Need equipment list for the dropdown
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:1000;display:flex;align-items:center;justify-content:center;padding:20px';
        overlay.innerHTML = `
            <div class="card" style="width:100%;max-width:520px;max-height:90vh;overflow-y:auto;margin:0">
                <div class="card-header">
                    <h3>Add PM Schedule</h3>
                    <button class="btn btn-sm btn-outline" id="modalClose">&times;</button>
                </div>
                <div class="card-body">
                    <form id="pmForm">
                        <div class="form-group">
                            <label>Title *</label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g. Monthly belt inspection">
                        </div>
                        <div class="form-group">
                            <label>Equipment *</label>
                            <select name="equipment_id" class="form-control" id="pmEquipmentSelect" required>
                                <option value="">Loading equipment...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                            <div class="form-group">
                                <label>Frequency *</label>
                                <select name="frequency" class="form-control" required>
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="biweekly">Bi-Weekly</option>
                                    <option value="monthly" selected>Monthly</option>
                                    <option value="quarterly">Quarterly</option>
                                    <option value="semiannual">Semi-Annual</option>
                                    <option value="annual">Annual</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Next Due *</label>
                                <input type="date" name="next_due" class="form-control" required>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                            <div class="form-group">
                                <label>Assigned To</label>
                                <input type="text" name="assigned_to" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Estimated Hours</label>
                                <input type="number" name="estimated_hours" class="form-control" step="0.5" min="0">
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
                            <button type="button" class="btn btn-sm btn-outline" id="modalCancel">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-primary">Create Schedule</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);

        const close = () => overlay.remove();
        overlay.querySelector('#modalClose').addEventListener('click', close);
        overlay.querySelector('#modalCancel').addEventListener('click', close);
        overlay.addEventListener('click', e => { if (e.target === overlay) close(); });

        // Load equipment options
        api.get('equipment').then(data => {
            const select = overlay.querySelector('#pmEquipmentSelect');
            const items = data.equipment || [];
            select.innerHTML = '<option value="">-- Select Equipment --</option>' +
                items.map(eq => `<option value="${eq.id}">${esc(eq.asset_tag)} - ${esc(eq.name)}</option>`).join('');
        }).catch(() => {
            overlay.querySelector('#pmEquipmentSelect').innerHTML = '<option value="">Failed to load equipment</option>';
        });

        overlay.querySelector('#pmForm').addEventListener('submit', async e => {
            e.preventDefault();
            const body = {};
            new FormData(e.target).forEach((v, k) => { if (v) body[k] = v; });
            if (body.equipment_id) body.equipment_id = parseInt(body.equipment_id);
            if (body.estimated_hours) body.estimated_hours = parseFloat(body.estimated_hours);

            try {
                await api.post('pm-schedules', body);
                close();
                render();
            } catch (err) {
                alert('Error creating PM schedule: ' + err.message);
            }
        });
    }

    await render();
    return el;
}

function frequencyBadge(freq) {
    const colors = {
        daily: 'badge-danger',
        weekly: 'badge-warning',
        biweekly: 'badge-warning',
        monthly: 'badge-info',
        quarterly: 'badge-info',
        semiannual: 'badge-success',
        annual: 'badge-success'
    };
    return `<span class="badge ${colors[freq] || 'badge-info'}">${esc(freq)}</span>`;
}

function formatDate(d) {
    if (!d) return '-';
    try { return new Date(d).toLocaleDateString(); } catch { return d; }
}

function esc(str) {
    if (str == null) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}
