/**
 * Point of Sale — Orders View
 * PhoenixPHP Module — CDAC Programming
 *
 * Order history table with status filters and order detail modal.
 */
window.PosViews = window.PosViews || {};

PosViews.orders = function (root) {
    'use strict';

    var currentPage = 1;
    var perPage = 20;
    var filterStatus = '';
    var filterDateFrom = '';
    var filterDateTo = '';

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

    function fmtDateTime(dateStr) {
        if (!dateStr) return '-';
        var d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) +
               ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }

    function statusBadge(status) {
        var map = { open: 'info', paid: 'success', refunded: 'warning', voided: 'danger' };
        var cls = map[status] || 'info';
        return '<span class="badge badge-' + cls + '">' + esc(status) + '</span>';
    }

    function loadOrders() {
        var tbody = document.getElementById('posOrdersBody');
        var pagEl = document.getElementById('posOrdersPagination');
        if (tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:32px"><div class="pos-loading"><div class="pos-spinner"></div> Loading...</div></td></tr>';

        var params = { page: currentPage, per_page: perPage };
        if (filterStatus) params.status = filterStatus;
        if (filterDateFrom) params.date_from = filterDateFrom;
        if (filterDateTo) params.date_to = filterDateTo;

        PosAPI.get('orders', params).then(function (res) {
            var data = res.data || {};
            var orders = data.orders || [];
            var pagination = data.pagination || {};

            if (!tbody) return;

            if (orders.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">No orders found.</td></tr>';
                if (pagEl) pagEl.innerHTML = '';
                return;
            }

            var html = '';
            for (var i = 0; i < orders.length; i++) {
                var o = orders[i];
                var itemCount = o.item_count || o.items_count || '-';
                html +=
                    '<tr style="cursor:pointer" onclick="PosViews._ordersDetail(' + o.id + ')">' +
                        '<td><strong>' + esc(o.order_number) + '</strong></td>' +
                        '<td>' + fmtDateTime(o.created_at) + '</td>' +
                        '<td>' + esc(o.customer_name || '-') + '</td>' +
                        '<td style="text-align:center">' + esc(itemCount) + '</td>' +
                        '<td style="text-align:right">' + money(o.total) + '</td>' +
                        '<td>' + esc(o.payment_method || '-') + '</td>' +
                        '<td>' + statusBadge(o.status) + '</td>' +
                    '</tr>';
            }
            tbody.innerHTML = html;

            // Pagination
            if (pagEl && pagination.pages > 1) {
                var pagHtml = '<button ' + (currentPage <= 1 ? 'disabled' : '') + ' onclick="PosViews._ordersPage(' + (currentPage - 1) + ')">&laquo; Prev</button>';
                pagHtml += '<span>Page ' + pagination.page + ' of ' + pagination.pages + '</span>';
                pagHtml += '<button ' + (currentPage >= pagination.pages ? 'disabled' : '') + ' onclick="PosViews._ordersPage(' + (currentPage + 1) + ')">Next &raquo;</button>';
                pagEl.innerHTML = pagHtml;
            } else if (pagEl) {
                pagEl.innerHTML = pagination.total ? '<span>' + pagination.total + ' orders</span>' : '';
            }

        }).catch(function (err) {
            if (tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--danger)">' + esc(err.message) + '</td></tr>';
        });
    }

    PosViews._ordersPage = function (p) { currentPage = p; loadOrders(); };

    PosViews._ordersFilter = function () {
        filterStatus = (document.getElementById('posOrderStatusFilter') || {}).value || '';
        filterDateFrom = (document.getElementById('posOrderDateFrom') || {}).value || '';
        filterDateTo = (document.getElementById('posOrderDateTo') || {}).value || '';
        currentPage = 1;
        loadOrders();
    };

    PosViews._ordersDetail = function (orderId) {
        PosAPI.get('orders/' + orderId).then(function (res) {
            var o = res.data && res.data.order ? res.data.order : res.data;
            if (!o) return;

            var items = o.items || [];
            var payments = o.payments || [];

            var itemsHtml = '';
            for (var i = 0; i < items.length; i++) {
                var it = items[i];
                itemsHtml +=
                    '<tr>' +
                        '<td style="padding:8px">' + esc(it.name) + '</td>' +
                        '<td style="padding:8px;font-family:monospace;font-size:12px">' + esc(it.sku || '-') + '</td>' +
                        '<td style="padding:8px;text-align:center">' + it.quantity + '</td>' +
                        '<td style="padding:8px;text-align:right">' + money(it.unit_price) + '</td>' +
                        '<td style="padding:8px;text-align:right;font-weight:600">' + money(it.line_total) + '</td>' +
                    '</tr>';
            }

            var paymentsHtml = '';
            for (var j = 0; j < payments.length; j++) {
                var p = payments[j];
                paymentsHtml +=
                    '<div style="display:flex;justify-content:space-between;padding:6px 0;font-size:13px;border-bottom:1px solid var(--border)">' +
                        '<span>' + esc(p.method) + '</span>' +
                        '<span>' + money(p.amount) + ' &mdash; <span class="badge badge-' + (p.status === 'completed' ? 'success' : p.status === 'failed' ? 'danger' : 'warning') + '">' + esc(p.status) + '</span></span>' +
                    '</div>';
            }

            openModal(
                '<div class="pos-modal-header">' +
                    '<h3>Order ' + esc(o.order_number) + '</h3>' +
                    '<button class="pos-modal-close" onclick="closeModal()">&times;</button>' +
                '</div>' +
                '<div class="pos-modal-body">' +
                    '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">' +
                        '<div><div style="font-size:12px;color:var(--text-muted)">Date</div><div style="font-size:14px;font-weight:600">' + fmtDateTime(o.created_at) + '</div></div>' +
                        '<div><div style="font-size:12px;color:var(--text-muted)">Status</div><div>' + statusBadge(o.status) + '</div></div>' +
                        '<div><div style="font-size:12px;color:var(--text-muted)">Customer</div><div style="font-size:14px;font-weight:600">' + esc(o.customer_name || 'Walk-in') + '</div></div>' +
                        '<div><div style="font-size:12px;color:var(--text-muted)">Operator</div><div style="font-size:14px;font-weight:600">' + esc(o.operator || '-') + '</div></div>' +
                    '</div>' +

                    '<h4 style="font-size:14px;font-weight:600;margin-bottom:8px">Items</h4>' +
                    '<div style="overflow-x:auto;margin-bottom:16px">' +
                        '<table class="phoenix-table" style="font-size:13px">' +
                            '<thead><tr>' +
                                '<th>Item</th><th>SKU</th><th style="text-align:center">Qty</th><th style="text-align:right">Price</th><th style="text-align:right">Total</th>' +
                            '</tr></thead>' +
                            '<tbody>' + itemsHtml + '</tbody>' +
                        '</table>' +
                    '</div>' +

                    '<div style="text-align:right;margin-bottom:16px">' +
                        '<div style="font-size:13px;color:var(--text-secondary)">Subtotal: ' + money(o.subtotal) + '</div>' +
                        (o.discount_amount > 0 ? '<div style="font-size:13px;color:var(--danger)">Discount: -' + money(o.discount_amount) + '</div>' : '') +
                        '<div style="font-size:13px;color:var(--text-secondary)">Tax (' + (o.tax_rate || 0) + '%): ' + money(o.tax_amount) + '</div>' +
                        '<div style="font-size:18px;font-weight:800;margin-top:4px">Total: ' + money(o.total) + '</div>' +
                    '</div>' +

                    (payments.length > 0 ?
                        '<h4 style="font-size:14px;font-weight:600;margin-bottom:8px">Payments</h4>' + paymentsHtml
                        : '') +

                    (o.notes ? '<div style="margin-top:16px;padding:12px;background:var(--bg-card);border-radius:var(--radius-sm);font-size:13px;color:var(--text-secondary)"><strong>Notes:</strong> ' + esc(o.notes) + '</div>' : '') +
                '</div>' +
                '<div class="pos-modal-footer">' +
                    '<button class="btn btn-sm btn-outline" onclick="closeModal()">Close</button>' +
                '</div>'
            );
        }).catch(function (err) {
            console.error('Order detail error:', err);
        });
    };

    // Render shell
    root.innerHTML = '' +
        '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">' +
            '<div>' +
                '<h3 style="font-size:18px;font-weight:700">Order History</h3>' +
                '<p style="color:var(--text-muted);font-size:13px;margin-top:4px">View and manage all sales orders</p>' +
            '</div>' +
        '</div>' +
        '<div class="pos-search-bar">' +
            '<select class="form-control" id="posOrderStatusFilter" onchange="PosViews._ordersFilter()" style="max-width:160px">' +
                '<option value="">All Statuses</option>' +
                '<option value="open">Open</option>' +
                '<option value="paid">Paid</option>' +
                '<option value="refunded">Refunded</option>' +
                '<option value="voided">Voided</option>' +
            '</select>' +
            '<input type="date" class="form-control" id="posOrderDateFrom" onchange="PosViews._ordersFilter()" style="max-width:160px" placeholder="From">' +
            '<input type="date" class="form-control" id="posOrderDateTo" onchange="PosViews._ordersFilter()" style="max-width:160px" placeholder="To">' +
        '</div>' +
        '<div class="card">' +
            '<div class="table-wrapper">' +
                '<table class="phoenix-table">' +
                    '<thead><tr>' +
                        '<th>Order #</th>' +
                        '<th>Date</th>' +
                        '<th>Customer</th>' +
                        '<th style="text-align:center">Items</th>' +
                        '<th style="text-align:right">Total</th>' +
                        '<th>Payment</th>' +
                        '<th>Status</th>' +
                    '</tr></thead>' +
                    '<tbody id="posOrdersBody"></tbody>' +
                '</table>' +
            '</div>' +
            '<div class="pos-pagination" id="posOrdersPagination"></div>' +
        '</div>';

    loadOrders();
};
