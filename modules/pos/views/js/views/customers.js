/**
 * Point of Sale — Customers View
 * PhoenixPHP Module — CDAC Programming
 *
 * Customer directory with search and add/edit forms.
 */
window.PosViews = window.PosViews || {};

PosViews.customers = function (root) {
    'use strict';

    var currentPage = 1;
    var perPage = 20;
    var searchTerm = '';

    function esc(s) {
        if (s === null || s === undefined) return '';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function loadCustomers() {
        var tbody = document.getElementById('posCustBody');
        var pagEl = document.getElementById('posCustPagination');
        if (tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:32px"><div class="pos-loading"><div class="pos-spinner"></div> Loading...</div></td></tr>';

        var params = { page: currentPage, per_page: perPage };
        if (searchTerm) params.search = searchTerm;

        PosAPI.get('customers', params).then(function (res) {
            var data = res.data || {};
            var customers = data.customers || [];
            var pagination = data.pagination || {};

            if (!tbody) return;

            if (customers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">No customers found.</td></tr>';
                if (pagEl) pagEl.innerHTML = '';
                return;
            }

            var html = '';
            for (var i = 0; i < customers.length; i++) {
                var c = customers[i];
                html +=
                    '<tr>' +
                        '<td><strong>' + esc(c.name) + '</strong></td>' +
                        '<td>' + esc(c.email || '-') + '</td>' +
                        '<td>' + esc(c.phone || '-') + '</td>' +
                        '<td>' + esc(c.company || '-') + '</td>' +
                        '<td style="text-align:center">' +
                            (c.tax_exempt ? '<span class="badge badge-warning">Exempt</span>' : '<span class="badge badge-outline">No</span>') +
                        '</td>' +
                        '<td style="text-align:center">' + (c.order_count || 0) + '</td>' +
                        '<td>' +
                            '<button class="btn btn-sm btn-outline" onclick="PosViews._custEdit(' + c.id + ')" style="padding:4px 10px;font-size:12px">Edit</button>' +
                        '</td>' +
                    '</tr>';
            }
            tbody.innerHTML = html;

            if (pagEl && pagination.pages > 1) {
                var pagHtml = '<button ' + (currentPage <= 1 ? 'disabled' : '') + ' onclick="PosViews._custPage(' + (currentPage - 1) + ')">&laquo; Prev</button>';
                pagHtml += '<span>Page ' + pagination.page + ' of ' + pagination.pages + '</span>';
                pagHtml += '<button ' + (currentPage >= pagination.pages ? 'disabled' : '') + ' onclick="PosViews._custPage(' + (currentPage + 1) + ')">Next &raquo;</button>';
                pagEl.innerHTML = pagHtml;
            } else if (pagEl) {
                pagEl.innerHTML = pagination.total ? '<span>' + pagination.total + ' customers</span>' : '';
            }

        }).catch(function (err) {
            if (tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--danger)">' + esc(err.message) + '</td></tr>';
        });
    }

    PosViews._custPage = function (p) { currentPage = p; loadCustomers(); };

    PosViews._custSearch = function () {
        var input = document.getElementById('posCustSearchInput');
        searchTerm = input ? input.value.trim() : '';
        currentPage = 1;
        loadCustomers();
    };

    function customerFormHtml(title, cust) {
        cust = cust || {};
        return '' +
            '<div class="pos-modal-header">' +
                '<h3>' + esc(title) + '</h3>' +
                '<button class="pos-modal-close" onclick="closeModal()">&times;</button>' +
            '</div>' +
            '<div class="pos-modal-body">' +
                '<div class="form-group">' +
                    '<label>Name *</label>' +
                    '<input type="text" class="form-control" id="custName" value="' + esc(cust.name || '') + '" placeholder="Full name">' +
                '</div>' +
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">' +
                    '<div class="form-group">' +
                        '<label>Email</label>' +
                        '<input type="email" class="form-control" id="custEmail" value="' + esc(cust.email || '') + '" placeholder="email@example.com">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>Phone</label>' +
                        '<input type="tel" class="form-control" id="custPhone" value="' + esc(cust.phone || '') + '" placeholder="(555) 123-4567">' +
                    '</div>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Company</label>' +
                    '<input type="text" class="form-control" id="custCompany" value="' + esc(cust.company || '') + '" placeholder="Company name">' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Address</label>' +
                    '<input type="text" class="form-control" id="custAddress" value="' + esc(cust.address || '') + '" placeholder="Street address">' +
                '</div>' +
                '<div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:16px">' +
                    '<div class="form-group">' +
                        '<label>City</label>' +
                        '<input type="text" class="form-control" id="custCity" value="' + esc(cust.city || '') + '">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>State</label>' +
                        '<input type="text" class="form-control" id="custState" value="' + esc(cust.state || '') + '">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>ZIP</label>' +
                        '<input type="text" class="form-control" id="custZip" value="' + esc(cust.zip || '') + '">' +
                    '</div>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label style="display:flex;align-items:center;gap:8px;cursor:pointer">' +
                        '<input type="checkbox" id="custTaxExempt" ' + (cust.tax_exempt ? 'checked' : '') + '>' +
                        ' Tax Exempt' +
                    '</label>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Notes</label>' +
                    '<textarea class="form-control" id="custNotes" rows="2" placeholder="Optional notes...">' + esc(cust.notes || '') + '</textarea>' +
                '</div>' +
                '<div id="custFormError" style="color:var(--danger);font-size:13px;margin-top:8px;display:none"></div>' +
            '</div>' +
            '<div class="pos-modal-footer">' +
                '<button class="btn btn-sm btn-outline" onclick="closeModal()">Cancel</button>' +
                '<button class="btn btn-sm btn-success" id="custFormSubmit" onclick="PosViews._custSubmit(' + (cust.id || 0) + ')">' + (cust.id ? 'Update' : 'Create') + '</button>' +
            '</div>';
    }

    PosViews._custAdd = function () {
        openModal(customerFormHtml('Add Customer'));
    };

    PosViews._custEdit = function (id) {
        PosAPI.get('customers/' + id).then(function (res) {
            var cust = res.data && res.data.customer ? res.data.customer : res.data;
            openModal(customerFormHtml('Edit Customer', cust));
        }).catch(function (err) {
            console.error('Customer load error:', err);
        });
    };

    PosViews._custSubmit = function (id) {
        var name = (document.getElementById('custName').value || '').trim();
        var errEl = document.getElementById('custFormError');
        var btn = document.getElementById('custFormSubmit');

        if (!name) {
            errEl.textContent = 'Name is required.';
            errEl.style.display = 'block';
            return;
        }

        errEl.style.display = 'none';
        btn.disabled = true;
        btn.textContent = 'Saving...';

        var body = {
            name: name,
            email: (document.getElementById('custEmail').value || '').trim() || null,
            phone: (document.getElementById('custPhone').value || '').trim() || null,
            company: (document.getElementById('custCompany').value || '').trim() || null,
            address: (document.getElementById('custAddress').value || '').trim() || null,
            city: (document.getElementById('custCity').value || '').trim() || null,
            state: (document.getElementById('custState').value || '').trim() || null,
            zip: (document.getElementById('custZip').value || '').trim() || null,
            tax_exempt: document.getElementById('custTaxExempt').checked ? 1 : 0,
            notes: (document.getElementById('custNotes').value || '').trim() || null
        };

        var promise = id ? PosAPI.put('customers/' + id, body) : PosAPI.post('customers', body);

        promise.then(function () {
            closeModal();
            loadCustomers();
        }).catch(function (err) {
            errEl.textContent = err.message;
            errEl.style.display = 'block';
            btn.disabled = false;
            btn.textContent = id ? 'Update' : 'Create';
        });
    };

    // Render shell
    root.innerHTML = '' +
        '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">' +
            '<div>' +
                '<h3 style="font-size:18px;font-weight:700">Customer Directory</h3>' +
                '<p style="color:var(--text-muted);font-size:13px;margin-top:4px">Manage customer accounts and contact info</p>' +
            '</div>' +
            '<button class="btn btn-sm btn-success" onclick="PosViews._custAdd()">+ Add Customer</button>' +
        '</div>' +
        '<div class="pos-search-bar">' +
            '<input type="text" id="posCustSearchInput" placeholder="Search by name, email, or phone..." onkeyup="if(event.key===\'Enter\')PosViews._custSearch()">' +
            '<button class="btn btn-sm btn-outline" onclick="PosViews._custSearch()">Search</button>' +
        '</div>' +
        '<div class="card">' +
            '<div class="table-wrapper">' +
                '<table class="phoenix-table">' +
                    '<thead><tr>' +
                        '<th>Name</th>' +
                        '<th>Email</th>' +
                        '<th>Phone</th>' +
                        '<th>Company</th>' +
                        '<th style="text-align:center">Tax Exempt</th>' +
                        '<th style="text-align:center">Orders</th>' +
                        '<th></th>' +
                    '</tr></thead>' +
                    '<tbody id="posCustBody"></tbody>' +
                '</table>' +
            '</div>' +
            '<div class="pos-pagination" id="posCustPagination"></div>' +
        '</div>';

    loadCustomers();
};
