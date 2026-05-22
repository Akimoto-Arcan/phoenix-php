import { api } from '../api.js';

export async function PartsView() {
    const el = document.createElement('div');
    document.getElementById('pageTitle').textContent = 'Parts Inventory';

    async function render() {
        el.innerHTML = '<div style="text-align:center;padding:60px;color:var(--text-muted)">Loading parts inventory...</div>';

        try {
            const data = await api.get('parts');
            const parts = data.parts || [];

            const lowStock = parts.filter(p => p.quantity_on_hand < p.reorder_point).length;

            el.innerHTML = `
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
                    <div style="display:flex;gap:16px;align-items:center">
                        <span style="font-size:13px;color:var(--text-muted)">${parts.length} part${parts.length !== 1 ? 's' : ''} in inventory</span>
                        ${lowStock > 0 ? `<span class="badge badge-danger">${lowStock} below reorder point</span>` : ''}
                    </div>
                    <button class="btn btn-primary btn-sm" id="btnAddPart">+ Add Part</button>
                </div>

                <div class="card">
                    <div class="card-body" style="padding:0">
                        <div class="table-wrapper">
                            <table class="phoenix-table">
                                <thead>
                                    <tr>
                                        <th>Part Number</th>
                                        <th>Name</th>
                                        <th>Qty On Hand</th>
                                        <th>Reorder Point</th>
                                        <th>Unit Cost</th>
                                        <th>Location</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${parts.length === 0
                                        ? '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:32px">No parts in inventory</td></tr>'
                                        : parts.map(p => {
                                            const isLow = p.quantity_on_hand < p.reorder_point;
                                            const rowStyle = isLow ? 'background:rgba(239,68,68,0.08)' : '';
                                            return `
                                                <tr style="${rowStyle}">
                                                    <td style="font-weight:600;font-family:monospace">${esc(p.part_number)}</td>
                                                    <td>${esc(p.name)}</td>
                                                    <td>
                                                        <span style="${isLow ? 'color:var(--danger);font-weight:700' : ''}">${p.quantity_on_hand}</span>
                                                        ${isLow ? ' <span class="badge badge-danger" style="font-size:10px">Low</span>' : ''}
                                                    </td>
                                                    <td>${p.reorder_point}</td>
                                                    <td>$${Number(p.unit_cost || 0).toFixed(2)}</td>
                                                    <td>${esc(p.location || '-')}</td>
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

            el.querySelector('#btnAddPart').addEventListener('click', () => showCreateModal());
        } catch (err) {
            el.innerHTML = `<div class="card"><div class="card-body" style="color:var(--danger)">Failed to load parts: ${esc(err.message)}</div></div>`;
        }
    }

    function showCreateModal() {
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:1000;display:flex;align-items:center;justify-content:center;padding:20px';
        overlay.innerHTML = `
            <div class="card" style="width:100%;max-width:520px;max-height:90vh;overflow-y:auto;margin:0">
                <div class="card-header">
                    <h3>Add Part</h3>
                    <button class="btn btn-sm btn-outline" id="modalClose">&times;</button>
                </div>
                <div class="card-body">
                    <form id="partForm">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                            <div class="form-group">
                                <label>Part Number *</label>
                                <input type="text" name="part_number" class="form-control" required placeholder="e.g. BLT-2024-001">
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
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
                            <div class="form-group">
                                <label>Qty On Hand</label>
                                <input type="number" name="quantity_on_hand" class="form-control" value="0" min="0">
                            </div>
                            <div class="form-group">
                                <label>Reorder Point</label>
                                <input type="number" name="reorder_point" class="form-control" value="0" min="0">
                            </div>
                            <div class="form-group">
                                <label>Unit Cost ($)</label>
                                <input type="number" name="unit_cost" class="form-control" step="0.01" min="0" value="0">
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                            <div class="form-group">
                                <label>Supplier</label>
                                <input type="text" name="supplier" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Location</label>
                                <input type="text" name="location" class="form-control" placeholder="e.g. Shelf A3, Bin 12">
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
                            <button type="button" class="btn btn-sm btn-outline" id="modalCancel">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-primary">Add Part</button>
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

        overlay.querySelector('#partForm').addEventListener('submit', async e => {
            e.preventDefault();
            const body = {};
            new FormData(e.target).forEach((v, k) => { if (v) body[k] = v; });
            // Coerce numeric fields
            if (body.quantity_on_hand) body.quantity_on_hand = parseInt(body.quantity_on_hand);
            if (body.reorder_point) body.reorder_point = parseInt(body.reorder_point);
            if (body.unit_cost) body.unit_cost = parseFloat(body.unit_cost);

            try {
                await api.post('parts', body);
                close();
                render();
            } catch (err) {
                alert('Error adding part: ' + err.message);
            }
        });
    }

    await render();
    return el;
}

function esc(str) {
    if (str == null) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}
