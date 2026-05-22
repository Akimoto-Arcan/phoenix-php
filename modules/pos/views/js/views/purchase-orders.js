/**
 * Point of Sale — Purchase Orders View
 * PhoenixPHP Module — CDAC Programming
 *
 * PO list with status badges, receive action, and link to PO form.
 */
window.PosViews = window.PosViews || {};

PosViews.purchaseOrders = function (root) {
    'use strict';

    var currentPage = 1;
    var perPage = 20;
    var filterStatus = '';

    function esc(s) {
        if (s === null || s === undefined) return '';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function money(n) {
        if (n === null || n === undefined) return '$0.00';
        return '$' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtDate(dateStr) {
        if (!dateStr) return '-';
        var d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function statusBadge(status) {
        var map = { draft: 'info', submitted: 'warning', partial: 'warning', received: 'success', cancelled: 'danger' };
        var cls = map[status] || 'info';
        return '<span class="badge badge-' + cls + '">' + esc(status) + '</span>';
    }

    function loadPurchaseOrders() {
        var tbody = document.getElementById('posPOBody');
        var pagEl = document.getElementById('posPOPagination');
        if (tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:32px"><div class="pos-loading"><div class="pos-spinner"></div> Loading...</div></td></tr>';

        var params = { page: currentPage, per_page: perPage };
        if (filterStatus) params.status = filterStatus;

        PosAPI.get('purchase-orders', params).then(function (res) {
            var data = res.data || {};
            var pos = data.purchase_orders || [];
            var pagination = data.pagination || {};

            if (!tbody) return;

            if (pos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">No purchase orders found.</td></tr>';
                if (pagEl) pagEl.innerHTML = '';
                return;
            }

            var html = '';
            for (var i = 0; i < pos.length; i++) {
                var po = pos[i];
                var canReceive = po.status === 'submitted' || po.status === 'partial';
                html +=
                    '<tr>' +
                        '<td><strong>' + esc(po.po_number) + '</strong></td>' +
                        '<td>' + esc(po.supplier_name || '-') + '</td>' +
                        '<td>' + fmtDate(po.created_at || po.order_date) + '</td>' +
                        '<td>' + fmtDate(po.expected_date) + '</td>' +
                        '<td style="text-align:right">' + money(po.total) + '</td>' +
                        '<td>' + statusBadge(po.status) + '</td>' +
                        '<td>' +
                            (canReceive ?
                                '<button class="btn btn-sm btn-outline" onclick="PosViews._poReceive(' + po.id + ')" style="padding:4px 10px;font-size:12px">Receive</button>'
                                : '') +
                        '</td>' +
                    '</tr>';
            }
            tbody.innerHTML = html;

            if (pagEl && pagination.pages > 1) {
                var pagHtml = '<button ' + (currentPage <= 1 ? 'disabled' : '') + ' onclick="PosViews._poPage(' + (currentPage - 1) + ')">&laquo; Prev</button>';
                pagHtml += '<span>Page ' + pagination.page + ' of ' + pagination.pages + '</span>';
                pagHtml += '<button ' + (currentPage >= pagination.pages ? 'disabled' : '') + ' onclick="PosViews._poPage(' + (currentPage + 1) + ')">Next &raquo;</button>';
                pagEl.innerHTML = pagHtml;
            } else if (pagEl) {
                pagEl.innerHTML = pagination.total ? '<span>' + pagination.total + ' POs</span>' : '';
            }

        }).catch(function (err) {
            if (tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--danger)">' + esc(err.message) + '</td></tr>';
        });
    }

    PosViews._poPage = function (p) { currentPage = p; loadPurchaseOrders(); };

    PosViews._poFilter = function () {
        filterStatus = (document.getElementById('posPOStatusFilter') || {}).value || '';
        currentPage = 1;
        loadPurchaseOrders();
    };

    PosViews._poReceive = function (poId) {
        PosAPI.get('purchase-orders/' + poId).then(function (res) {
            var po = res.data && res.data.purchase_order ? res.data.purchase_order : res.data;
            if (!po) return;
            var items = po.items || [];

            var itemsHtml = '';
            for (var i = 0; i < items.length; i++) {
                var it = items[i];
                var ordered = parseFloat(it.quantity) || 0;
                var received = parseFloat(it.received_qty || 0);
                var remaining = Math.max(0, ordered - received);
                itemsHtml +=
                    '<tr>' +
                        '<td style="padding:8px;font-size:13px">' + esc(it.name || it.description) + '</td>' +
                        '<td style="padding:8px;text-align:center;font-size:13px">' + ordered + '</td>' +
                        '<td style="padding:8px;text-align:center;font-size:13px">' + received + '</td>' +
                        '<td style="padding:8px;width:80px">' +
                            '<input type="number" class="form-control" id="poRecv_' + (it.id || i) + '" value="' + remaining + '" min="0" max="' + remaining + '" step="1" style="padding:6px;font-size:13px;text-align:center">' +
                        '</td>' +
                    '</tr>';
            }

            openModal(
                '<div class="pos-modal-header">' +
                    '<h3>Receive PO ' + esc(po.po_number) + '</h3>' +
                    '<button class="pos-modal-close" onclick="closeModal()">&times;</button>' +
                '</div>' +
                '<div class="pos-modal-body">' +
                    '<div style="overflow-x:auto">' +
                        '<table class="phoenix-table" style="font-size:13px">' +
                            '<thead><tr>' +
                                '<th>Item</th>' +
                                '<th style="text-align:center">Ordered</th>' +
                                '<th style="text-align:center">Received</th>' +
                                '<th style="text-align:center">Receiving</th>' +
                            '</tr></thead>' +
                            '<tbody>' + itemsHtml + '</tbody>' +
                        '</table>' +
                    '</div>' +
                    '<div id="poRecvError" style="color:var(--danger);font-size:13px;margin-top:12px;display:none"></div>' +
                '</div>' +
                '<div class="pos-modal-footer">' +
                    '<button class="btn btn-sm btn-outline" onclick="closeModal()">Cancel</button>' +
                    '<button class="btn btn-sm btn-success" id="poRecvSubmit" onclick="PosViews._poSubmitReceive(' + poId + ')">Confirm Receipt</button>' +
                '</div>'
            );
        }).catch(function (err) {
            console.error('PO detail error:', err);
        });
    };

    PosViews._poSubmitReceive = function (poId) {
        var btn = document.getElementById('poRecvSubmit');
        var errEl = document.getElementById('poRecvError');

        // Collect receiving quantities from all inputs starting with poRecv_
        var receivedItems = [];
        var inputs = document.querySelectorAll('[id^="poRecv_"]');
        inputs.forEach(function (input) {
            var itemIdStr = input.id.replace('poRecv_', '');
            var qty = parseFloat(input.value) || 0;
            if (qty > 0) {
                receivedItems.push({ item_id: parseInt(itemIdStr, 10), quantity: qty });
            }
        });

        if (receivedItems.length === 0) {
            errEl.textContent = 'Enter at least one quantity to receive.';
            errEl.style.display = 'block';
            return;
        }

        errEl.style.display = 'none';
        btn.disabled = true;
        btn.textContent = 'Processing...';

        PosAPI.post('purchase-orders/' + poId + '/receive', { items: receivedItems })
            .then(function () {
                closeModal();
                loadPurchaseOrders();
            })
            .catch(function (err) {
                errEl.textContent = err.message;
                errEl.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Confirm Receipt';
            });
    };

    // Render shell
    root.innerHTML = '' +
        '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">' +
            '<div>' +
                '<h3 style="font-size:18px;font-weight:700">Purchase Orders</h3>' +
                '<p style="color:var(--text-muted);font-size:13px;margin-top:4px">Create and track purchase orders from suppliers</p>' +
            '</div>' +
            '<a href="#po-form" class="btn btn-sm btn-success">+ Create PO</a>' +
        '</div>' +
        '<div class="pos-search-bar">' +
            '<select class="form-control" id="posPOStatusFilter" onchange="PosViews._poFilter()" style="max-width:160px">' +
                '<option value="">All Statuses</option>' +
                '<option value="draft">Draft</option>' +
                '<option value="submitted">Submitted</option>' +
                '<option value="partial">Partial</option>' +
                '<option value="received">Received</option>' +
                '<option value="cancelled">Cancelled</option>' +
            '</select>' +
        '</div>' +
        '<div class="card">' +
            '<div class="table-wrapper">' +
                '<table class="phoenix-table">' +
                    '<thead><tr>' +
                        '<th>PO #</th>' +
                        '<th>Supplier</th>' +
                        '<th>Date</th>' +
                        '<th>Expected</th>' +
                        '<th style="text-align:right">Total</th>' +
                        '<th>Status</th>' +
                        '<th></th>' +
                    '</tr></thead>' +
                    '<tbody id="posPOBody"></tbody>' +
                '</table>' +
            '</div>' +
            '<div class="pos-pagination" id="posPOPagination"></div>' +
        '</div>';

    loadPurchaseOrders();
};
