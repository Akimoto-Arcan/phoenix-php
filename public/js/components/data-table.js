export class DataTable {
    constructor(options = {}) {
        this.columns = options.columns || [];
        this.fetchData = options.fetchData;
        this.pageSize = options.pageSize || 25;
        this.actions = options.actions || [];
        this.emptyMessage = options.emptyMessage || 'No data found';
        this.selectable = options.selectable || false;

        this.page = 1;
        this.total = 0;
        this.rows = [];
        this.sort = null;
        this.sortDir = 'asc';
        this.search = '';
        this.selected = new Set();
        this._searchTimer = null;

        this.el = this._build();
        this.refresh();
    }

    render() {
        return this.el;
    }

    async refresh() {
        this._setLoading(true);
        try {
            const result = await this.fetchData({
                page: this.page,
                pageSize: this.pageSize,
                sort: this.sort,
                sortDir: this.sortDir,
                search: this.search
            });
            this.rows = result.rows || [];
            this.total = result.total || 0;
            this._renderBody();
            this._renderPagination();
        } catch (e) {
            this._tbody.innerHTML = `<tr><td colspan="${this._colSpan()}" style="text-align:center;padding:32px;color:var(--danger)">Error loading data</td></tr>`;
        } finally {
            this._setLoading(false);
        }
    }

    getSelected() {
        return [...this.selected];
    }

    _colSpan() {
        return this.columns.length + (this.actions.length ? 1 : 0) + (this.selectable ? 1 : 0);
    }

    _build() {
        const wrapper = document.createElement('div');
        wrapper.className = 'card';

        // Toolbar
        const toolbar = document.createElement('div');
        toolbar.className = 'card-header';
        toolbar.innerHTML = `
            <div style="position:relative;flex:1;max-width:320px">
                <input type="text" class="form-control" placeholder="Search..." style="padding-left:36px;padding-top:10px;padding-bottom:10px;font-size:13px">
                <svg style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--text-muted)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35" stroke-linecap="round"/>
                </svg>
            </div>
            <div class="dt-info" style="font-size:13px;color:var(--text-muted)"></div>
        `;
        wrapper.appendChild(toolbar);

        this._searchInput = toolbar.querySelector('input');
        this._infoEl = toolbar.querySelector('.dt-info');
        this._searchInput.addEventListener('input', () => {
            clearTimeout(this._searchTimer);
            this._searchTimer = setTimeout(() => {
                this.search = this._searchInput.value;
                this.page = 1;
                this.refresh();
            }, 300);
        });

        // Table container
        const tableContainer = document.createElement('div');
        tableContainer.style.position = 'relative';

        // Loading overlay
        this._loader = document.createElement('div');
        Object.assign(this._loader.style, {
            position: 'absolute', inset: '0', background: 'rgba(15,15,26,0.6)',
            display: 'none', alignItems: 'center', justifyContent: 'center', zIndex: '5',
            backdropFilter: 'blur(2px)', borderRadius: 'var(--radius)'
        });
        this._loader.innerHTML = `<div style="width:32px;height:32px;border:3px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:dt-spin 0.8s linear infinite"></div>`;
        tableContainer.appendChild(this._loader);

        // Inject spinner keyframe if not present
        if (!document.getElementById('dt-spin-style')) {
            const style = document.createElement('style');
            style.id = 'dt-spin-style';
            style.textContent = '@keyframes dt-spin{to{transform:rotate(360deg)}}';
            document.head.appendChild(style);
        }

        // Table
        const table = document.createElement('table');
        table.className = 'phoenix-table';

        // Head
        const thead = document.createElement('thead');
        const headRow = document.createElement('tr');

        if (this.selectable) {
            const th = document.createElement('th');
            th.style.width = '40px';
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.addEventListener('change', () => {
                this.rows.forEach(r => {
                    if (cb.checked) this.selected.add(r.id);
                    else this.selected.delete(r.id);
                });
                this._renderBody();
            });
            th.appendChild(cb);
            headRow.appendChild(th);
            this._selectAllCb = cb;
        }

        this.columns.forEach(col => {
            const th = document.createElement('th');
            th.textContent = col.label;
            if (col.sortable) {
                th.style.cursor = 'pointer';
                th.style.userSelect = 'none';
                th.addEventListener('click', () => {
                    if (this.sort === col.key) {
                        this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        this.sort = col.key;
                        this.sortDir = 'asc';
                    }
                    this._updateSortIndicators();
                    this.page = 1;
                    this.refresh();
                });
            }
            headRow.appendChild(th);
            col._th = th;
        });

        if (this.actions.length) {
            const th = document.createElement('th');
            th.textContent = 'Actions';
            th.style.textAlign = 'right';
            headRow.appendChild(th);
        }

        thead.appendChild(headRow);
        table.appendChild(thead);

        this._tbody = document.createElement('tbody');
        table.appendChild(this._tbody);
        tableContainer.appendChild(table);
        wrapper.appendChild(tableContainer);

        // Pagination
        this._paginationEl = document.createElement('div');
        Object.assign(this._paginationEl.style, {
            padding: '16px 24px', borderTop: '1px solid var(--border)',
            display: 'flex', alignItems: 'center', justifyContent: 'flex-end', gap: '8px'
        });
        wrapper.appendChild(this._paginationEl);

        return wrapper;
    }

    _setLoading(on) {
        this._loader.style.display = on ? 'flex' : 'none';
    }

    _updateSortIndicators() {
        this.columns.forEach(col => {
            if (!col.sortable) return;
            const arrow = this.sort === col.key ? (this.sortDir === 'asc' ? ' ▲' : ' ▼') : '';
            col._th.textContent = col.label + arrow;
        });
    }

    _renderBody() {
        this._tbody.innerHTML = '';

        if (!this.rows.length) {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td colspan="${this._colSpan()}" style="text-align:center;padding:40px;color:var(--text-muted)">${this.emptyMessage}</td>`;
            this._tbody.appendChild(tr);
            return;
        }

        this.rows.forEach(row => {
            const tr = document.createElement('tr');

            if (this.selectable) {
                const td = document.createElement('td');
                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.checked = this.selected.has(row.id);
                cb.addEventListener('change', () => {
                    if (cb.checked) this.selected.add(row.id);
                    else this.selected.delete(row.id);
                });
                td.appendChild(cb);
                tr.appendChild(td);
            }

            this.columns.forEach(col => {
                const td = document.createElement('td');
                const val = row[col.key];
                if (col.render) {
                    const rendered = col.render(val, row);
                    if (rendered instanceof HTMLElement) td.appendChild(rendered);
                    else td.innerHTML = rendered;
                } else {
                    td.textContent = val ?? '';
                }
                tr.appendChild(td);
            });

            if (this.actions.length) {
                const td = document.createElement('td');
                td.style.textAlign = 'right';
                td.style.whiteSpace = 'nowrap';
                this.actions.forEach(action => {
                    const btn = document.createElement('button');
                    btn.className = `btn btn-sm ${action.variant ? 'btn-' + action.variant : 'btn-outline'}`;
                    btn.style.marginLeft = '6px';
                    btn.innerHTML = (action.icon ? action.icon + ' ' : '') + (action.label || '');
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        action.onClick(row);
                    });
                    td.appendChild(btn);
                });
                tr.appendChild(td);
            }

            this._tbody.appendChild(tr);
        });
    }

    _renderPagination() {
        const totalPages = Math.max(1, Math.ceil(this.total / this.pageSize));
        const start = this.total ? (this.page - 1) * this.pageSize + 1 : 0;
        const end = Math.min(this.page * this.pageSize, this.total);

        this._infoEl.textContent = this.total ? `Showing ${start}–${end} of ${this.total}` : '';

        this._paginationEl.innerHTML = '';

        const prevBtn = document.createElement('button');
        prevBtn.className = 'btn btn-sm btn-outline';
        prevBtn.textContent = 'Prev';
        prevBtn.disabled = this.page <= 1;
        prevBtn.addEventListener('click', () => { this.page--; this.refresh(); });

        const pageInfo = document.createElement('span');
        pageInfo.style.cssText = 'font-size:13px;color:var(--text-secondary);padding:0 8px';
        pageInfo.textContent = `${this.page} / ${totalPages}`;

        const nextBtn = document.createElement('button');
        nextBtn.className = 'btn btn-sm btn-outline';
        nextBtn.textContent = 'Next';
        nextBtn.disabled = this.page >= totalPages;
        nextBtn.addEventListener('click', () => { this.page++; this.refresh(); });

        this._paginationEl.append(prevBtn, pageInfo, nextBtn);
    }
}
