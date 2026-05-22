import { api } from '../api.js';

export async function EquipmentView() {
    const el = document.createElement('div');
    document.getElementById('pageTitle').textContent = 'Equipment';

    async function render() {
        el.innerHTML = '<div style="text-align:center;padding:60px;color:var(--text-muted)">Loading equipment...</div>';

        try {
            const data = await api.get('equipment');
            const items = data.equipment || [];

            el.innerHTML = `
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
                    <div style="font-size:13px;color:var(--text-muted)">${items.length} asset${items.length !== 1 ? 's' : ''} registered</div>
                    <button class="btn btn-primary btn-sm" id="btnAddEquipment">+ Add Equipment</button>
                </div>

                <div class="card">
                    <div class="card-body" style="padding:0">
                        <div class="table-wrapper">
                            <table class="phoenix-table">
                                <thead>
                                    <tr>
                                        <th>Asset Tag</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Criticality</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${items.length === 0
                                        ? '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:32px">No equipment registered</td></tr>'
                                        : items.map(eq => `
                                            <tr>
                                                <td style="font-weight:600;font-family:monospace">${esc(eq.asset_tag)}</td>
                                                <td>${esc(eq.name)}</td>
                                                <td>${esc(eq.category || '-')}</td>
                                                <td>${esc(eq.location || '-')}</td>
                                                <td>${equipmentStatusBadge(eq.status)}</td>
                                                <td>${esc(eq.criticality || '-')}</td>
                                            </tr>
                                        `).join('')
                                    }
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;

            el.querySelector('#btnAddEquipment').addEventListener('click', () => showCreateModal());
        } catch (err) {
            el.innerHTML = `<div class="card"><div class="card-body" style="color:var(--danger)">Failed to load equipment: ${esc(err.message)}</div></div>`;
        }
    }

    function showCreateModal() {
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:1000;display:flex;align-items:center;justify-content:center;padding:20px';
        overlay.innerHTML = `
            <div class="card" style="width:100%;max-width:560px;max-height:90vh;overflow-y:auto;margin:0">
                <div class="card-header">
                    <h3>Add Equipment</h3>
                    <button class="btn btn-sm btn-outline" id="modalClose">&times;</button>
                </div>
                <div class="card-body">
                    <form id="eqForm">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                            <div class="form-group">
                                <label>Asset Tag *</label>
                                <input type="text" name="asset_tag" class="form-control" required placeholder="e.g. EQ-001">
                            </div>
                            <div class="form-group">
                                <label>Name *</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                            <div class="form-group">
                                <label>Category</label>
                                <input type="text" name="category" class="form-control" placeholder="e.g. HVAC, Electrical">
                            </div>
                            <div class="form-group">
                                <label>Location</label>
                                <input type="text" name="location" class="form-control" placeholder="e.g. Building A, Floor 2">
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                            <div class="form-group">
                                <label>Manufacturer</label>
                                <input type="text" name="manufacturer" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Model</label>
                                <input type="text" name="model" class="form-control">
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                            <div class="form-group">
                                <label>Serial Number</label>
                                <input type="text" name="serial_number" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Department</label>
                                <input type="text" name="department" class="form-control">
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                            <div class="form-group">
                                <label>Install Date</label>
                                <input type="date" name="install_date" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Warranty Expiry</label>
                                <input type="date" name="warranty_expiry" class="form-control">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Criticality</label>
                            <select name="criticality" class="form-control">
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
                            <button type="button" class="btn btn-sm btn-outline" id="modalCancel">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-primary">Add Equipment</button>
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

        overlay.querySelector('#eqForm').addEventListener('submit', async e => {
            e.preventDefault();
            const body = {};
            new FormData(e.target).forEach((v, k) => { if (v) body[k] = v; });

            try {
                await api.post('equipment', body);
                close();
                render();
            } catch (err) {
                alert('Error adding equipment: ' + err.message);
            }
        });
    }

    await render();
    return el;
}

function equipmentStatusBadge(status) {
    const map = {
        operational: 'badge-success',
        maintenance: 'badge-warning',
        down: 'badge-danger',
        retired: 'badge-outline'
    };
    return `<span class="badge ${map[status] || 'badge-info'}">${esc(status || 'unknown')}</span>`;
}

function esc(str) {
    if (str == null) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}
