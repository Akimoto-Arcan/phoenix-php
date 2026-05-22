import { api } from '../api.js';

export async function WorkOrdersView() {
    const el = document.createElement('div');
    document.getElementById('pageTitle').textContent = 'Work Orders';

    let currentPage = 1;
    let filterStatus = '';
    let filterPriority = '';

    async function render() {
        el.innerHTML = '<div style="text-align:center;padding:60px;color:var(--text-muted)">Loading work orders...</div>';

        try {
            let query = `work-orders?page=${currentPage}&per_page=20`;
            if (filterStatus) query += `&status=${filterStatus}`;
            if (filterPriority) query += `&priority=${filterPriority}`;

            const data = await api.get(query);
            const wos = data.work_orders || [];
            const pag = data.pagination || {};

            el.innerHTML = `
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <select id="filterStatus" class="form-control" style="width:auto;padding:8px 12px;font-size:13px">
                            <option value="">All Statuses</option>
                            <option value="open" ${filterStatus === 'open' ? 'selected' : ''}>Open</option>
                            <option value="in_progress" ${filterStatus === 'in_progress' ? 'selected' : ''}>In Progress</option>
                            <option value="completed" ${filterStatus === 'completed' ? 'selected' : ''}>Completed</option>
                            <option value="cancelled" ${filterStatus === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                        </select>
                        <select id="filterPriority" class="form-control" style="width:auto;padding:8px 12px;font-size:13px">
                            <option value="">All Priorities</option>
                            <option value="emergency" ${filterPriority === 'emergency' ? 'selected' : ''}>Emergency</option>
                            <option value="high" ${filterPriority === 'high' ? 'selected' : ''}>High</option>
                            <option value="medium" ${filterPriority === 'medium' ? 'selected' : ''}>Medium</option>
                            <option value="low" ${filterPriority === 'low' ? 'selected' : ''}>Low</option>
                        </select>
                    </div>
                    <button class="btn btn-primary btn-sm" id="btnCreateWO">+ Create Work Order</button>
                </div>

                <div class="card">
                    <div class="card-body" style="padding:0">
                        <div class="table-wrapper">
                            <table class="phoenix-table">
                                <thead>
                                    <tr>
                                        <th>WO #</th>
                                        <th>Title</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Assigned To</th>
                                        <th>Due Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${wos.length === 0
                                        ? '<tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:32px">No work orders found</td></tr>'
                                        : wos.map(wo => `
                                            <tr>
                                                <td style="font-weight:600">${esc(wo.wo_number)}</td>
                                                <td>${esc(wo.title)}</td>
                                                <td>${priorityBadge(wo.priority)}</td>
                                                <td>${statusBadge(wo.status)}</td>
                                                <td>${esc(wo.assigned_to || '-')}</td>
                                                <td>${wo.due_date ? formatDate(wo.due_date) : '-'}</td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline btn-view-wo" data-id="${wo.id}">View</button>
                                                    ${wo.status === 'open' ? `<button class="btn btn-sm btn-info btn-start-wo" data-id="${wo.id}">Start</button>` : ''}
                                                    ${wo.status === 'in_progress' ? `<button class="btn btn-sm btn-success btn-complete-wo" data-id="${wo.id}">Complete</button>` : ''}
                                                </td>
                                            </tr>
                                        `).join('')
                                    }
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                ${pag.pages > 1 ? `
                    <div style="display:flex;justify-content:center;gap:8px;margin-top:16px">
                        ${currentPage > 1 ? `<button class="btn btn-sm btn-outline btn-page" data-page="${currentPage - 1}">&laquo; Prev</button>` : ''}
                        <span style="padding:8px 16px;font-size:13px;color:var(--text-muted)">Page ${pag.page} of ${pag.pages}</span>
                        ${currentPage < pag.pages ? `<button class="btn btn-sm btn-outline btn-page" data-page="${currentPage + 1}">Next &raquo;</button>` : ''}
                    </div>
                ` : ''}
            `;

            // Filter listeners
            el.querySelector('#filterStatus').addEventListener('change', e => {
                filterStatus = e.target.value;
                currentPage = 1;
                render();
            });
            el.querySelector('#filterPriority').addEventListener('change', e => {
                filterPriority = e.target.value;
                currentPage = 1;
                render();
            });

            // Pagination
            el.querySelectorAll('.btn-page').forEach(btn => {
                btn.addEventListener('click', () => {
                    currentPage = parseInt(btn.dataset.page);
                    render();
                });
            });

            // Create WO
            el.querySelector('#btnCreateWO').addEventListener('click', () => showCreateModal());

            // View WO
            el.querySelectorAll('.btn-view-wo').forEach(btn => {
                btn.addEventListener('click', () => showDetailModal(btn.dataset.id));
            });

            // Start WO
            el.querySelectorAll('.btn-start-wo').forEach(btn => {
                btn.addEventListener('click', async () => {
                    try {
                        await api.put(`work-orders/${btn.dataset.id}`, { status: 'in_progress' });
                        render();
                    } catch (err) { alert('Error: ' + err.message); }
                });
            });

            // Complete WO
            el.querySelectorAll('.btn-complete-wo').forEach(btn => {
                btn.addEventListener('click', async () => {
                    try {
                        await api.put(`work-orders/${btn.dataset.id}`, { status: 'completed' });
                        render();
                    } catch (err) { alert('Error: ' + err.message); }
                });
            });
        } catch (err) {
            el.innerHTML = `<div class="card"><div class="card-body" style="color:var(--danger)">Failed to load work orders: ${esc(err.message)}</div></div>`;
        }
    }

    function showCreateModal() {
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:1000;display:flex;align-items:center;justify-content:center;padding:20px';
        overlay.innerHTML = `
            <div class="card" style="width:100%;max-width:560px;max-height:90vh;overflow-y:auto;margin:0">
                <div class="card-header">
                    <h3>Create Work Order</h3>
                    <button class="btn btn-sm btn-outline" id="modalClose">&times;</button>
                </div>
                <div class="card-body">
                    <form id="woForm">
                        <div class="form-group">
                            <label>Title *</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                            <div class="form-group">
                                <label>Type</label>
                                <select name="type" class="form-control">
                                    <option value="corrective">Corrective</option>
                                    <option value="preventive">Preventive</option>
                                    <option value="emergency">Emergency</option>
                                    <option value="inspection">Inspection</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Priority</label>
                                <select name="priority" class="form-control">
                                    <option value="medium">Medium</option>
                                    <option value="low">Low</option>
                                    <option value="high">High</option>
                                    <option value="emergency">Emergency</option>
                                </select>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                            <div class="form-group">
                                <label>Requested By *</label>
                                <input type="text" name="requested_by" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Assigned To</label>
                                <input type="text" name="assigned_to" class="form-control">
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                            <div class="form-group">
                                <label>Due Date</label>
                                <input type="date" name="due_date" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Estimated Hours</label>
                                <input type="number" name="estimated_hours" class="form-control" step="0.5" min="0">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
                            <button type="button" class="btn btn-sm btn-outline" id="modalCancel">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-primary">Create Work Order</button>
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

        overlay.querySelector('#woForm').addEventListener('submit', async e => {
            e.preventDefault();
            const form = e.target;
            const body = {};
            new FormData(form).forEach((v, k) => { if (v) body[k] = v; });

            try {
                await api.post('work-orders', body);
                close();
                render();
            } catch (err) {
                alert('Error creating work order: ' + err.message);
            }
        });
    }

    async function showDetailModal(id) {
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:1000;display:flex;align-items:center;justify-content:center;padding:20px';
        overlay.innerHTML = '<div class="card" style="width:100%;max-width:600px;margin:0"><div class="card-body" style="text-align:center;color:var(--text-muted);padding:40px">Loading...</div></div>';
        document.body.appendChild(overlay);
        overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });

        try {
            const wo = await api.get(`work-orders/${id}`);
            overlay.querySelector('.card').innerHTML = `
                <div class="card-header">
                    <h3>${esc(wo.wo_number)} - ${esc(wo.title)}</h3>
                    <button class="btn btn-sm btn-outline" onclick="this.closest('[style*=fixed]').remove()">&times;</button>
                </div>
                <div class="card-body" style="max-height:70vh;overflow-y:auto">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px">
                        <div><strong style="color:var(--text-muted);font-size:12px;text-transform:uppercase">Status</strong><br>${statusBadge(wo.status)}</div>
                        <div><strong style="color:var(--text-muted);font-size:12px;text-transform:uppercase">Priority</strong><br>${priorityBadge(wo.priority)}</div>
                        <div><strong style="color:var(--text-muted);font-size:12px;text-transform:uppercase">Type</strong><br>${esc(wo.type || '-')}</div>
                        <div><strong style="color:var(--text-muted);font-size:12px;text-transform:uppercase">Equipment</strong><br>${esc(wo.equipment_name || '-')}</div>
                        <div><strong style="color:var(--text-muted);font-size:12px;text-transform:uppercase">Requested By</strong><br>${esc(wo.requested_by || '-')}</div>
                        <div><strong style="color:var(--text-muted);font-size:12px;text-transform:uppercase">Assigned To</strong><br>${esc(wo.assigned_to || '-')}</div>
                        <div><strong style="color:var(--text-muted);font-size:12px;text-transform:uppercase">Due Date</strong><br>${wo.due_date ? formatDate(wo.due_date) : '-'}</div>
                        <div><strong style="color:var(--text-muted);font-size:12px;text-transform:uppercase">Hours</strong><br>${wo.actual_hours || wo.estimated_hours || '-'} ${wo.actual_hours ? '(actual)' : wo.estimated_hours ? '(est)' : ''}</div>
                    </div>
                    ${wo.description ? `<div style="margin-bottom:16px"><strong style="color:var(--text-muted);font-size:12px;text-transform:uppercase">Description</strong><p style="margin-top:4px">${esc(wo.description)}</p></div>` : ''}
                    ${wo.notes ? `<div style="margin-bottom:16px"><strong style="color:var(--text-muted);font-size:12px;text-transform:uppercase">Notes</strong><p style="margin-top:4px">${esc(wo.notes)}</p></div>` : ''}

                    ${(wo.parts && wo.parts.length > 0) ? `
                        <div style="margin-bottom:16px">
                            <strong style="color:var(--text-muted);font-size:12px;text-transform:uppercase">Parts Used</strong>
                            <table class="phoenix-table" style="margin-top:8px">
                                <thead><tr><th>Part #</th><th>Name</th><th>Qty</th><th>Unit Cost</th></tr></thead>
                                <tbody>${wo.parts.map(p => `<tr><td>${esc(p.part_number)}</td><td>${esc(p.part_name)}</td><td>${p.quantity}</td><td>$${Number(p.unit_cost || 0).toFixed(2)}</td></tr>`).join('')}</tbody>
                            </table>
                        </div>
                    ` : ''}

                    ${(wo.comments && wo.comments.length > 0) ? `
                        <div style="margin-bottom:16px">
                            <strong style="color:var(--text-muted);font-size:12px;text-transform:uppercase">Comments</strong>
                            <ul class="activity-feed" style="margin-top:8px">
                                ${wo.comments.map(c => `
                                    <li class="activity-item">
                                        <span class="activity-dot blue"></span>
                                        <div><div class="activity-text"><strong>${esc(c.user)}</strong>: ${esc(c.comment)}</div><div class="activity-time">${formatDate(c.created_at)}</div></div>
                                    </li>
                                `).join('')}
                            </ul>
                        </div>
                    ` : ''}
                </div>
            `;
        } catch (err) {
            overlay.querySelector('.card').innerHTML = `
                <div class="card-header"><h3>Error</h3><button class="btn btn-sm btn-outline" onclick="this.closest('[style*=fixed]').remove()">&times;</button></div>
                <div class="card-body" style="color:var(--danger)">${esc(err.message)}</div>
            `;
        }
    }

    await render();
    return el;
}

function priorityBadge(priority) {
    const map = { emergency: 'badge-danger', high: 'badge-warning', medium: 'badge-info', low: 'badge-outline' };
    return `<span class="badge ${map[priority] || 'badge-info'}">${esc(priority)}</span>`;
}

function statusBadge(status) {
    const map = { open: 'badge-info', assigned: 'badge-info', in_progress: 'badge-warning', completed: 'badge-success', cancelled: 'badge-danger' };
    return `<span class="badge ${map[status] || 'badge-info'}">${esc((status || '').replace(/_/g, ' '))}</span>`;
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
