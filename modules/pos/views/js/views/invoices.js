/**
 * Point of Sale — Invoices View
 * PhoenixPHP Module — CDAC Programming
 *
 * Invoice list with status badges, record payment, and link to invoice form.
 */
window.PosViews = window.PosViews || {};

PosViews.invoices = function (root) {
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
        var map = { draft: 'info', sent: 'warning', paid: 'success', partial: 'warning', overdue: 'danger', cancelled: 'danger' };
        var cls = map[status] || 'info';
        return '<span class="badge badge-' + cls + '">' + esc(status) + '</span>';
    }

    function loadInvoices() {
        var tbody = document.getElementById('posInvBody');
        var pagEl = document.getElementById('posInvPagination');
        if (tbody) tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:32px"><div class="pos-loading"><div class="pos-spinner"></div> Loading...</div></td></tr>';

        var params = { page: currentPage, per_page: perPage };
        if (filterStatus) params.status = filterStatus;

        PosAPI.get('invoices', params).then(function (res) {
            var data = res.data || {};
            var invoices = data.invoices || [];
            var pagination = data.pagination || {};

            if (!tbody) return;

            if (invoices.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted)">No invoices found.</td></tr>';
                if (pagEl) pagEl.innerHTML = '';
                return;
            }

            var html = '';
            for (var i = 0; i < invoices.length; i++) {
                var inv = invoices[i];
                var isUnpaid = inv.status !== 'paid' && inv.status !== 'cancelled';
                html +=
                    '<tr>' +
                        '<td><strong>' + esc(inv.invoice_number) + '</strong></td>' +
                        '<td>' + esc(inv.customer_name || '-') + '</td>' +
                        '<td>' + fmtDate(inv.issue_date) + '</td>' +
                        '<td>' + fmtDate(inv.due_date) + '</td>' +
                        '<td style="text-align:right">' + money(inv.total) + '</td>' +
                        '<td style="text-align:right">' + money(inv.amount_paid) + '</td>' +
                        '<td>' + statusBadge(inv.status) + '</td>' +
                        '<td>' +
                            (isUnpaid ?
                                '<button class="btn btn-sm btn-outline" onclick="PosViews._invRecordPayment(' + inv.id + ')" style="padding:4px 10px;font-size:12px">Record Payment</button>'
                                : '') +
                        '</td>' +
                    '</tr>';
            }
            tbody.innerHTML = html;

            if (pagEl && pagination.pages > 1) {
                var pagHtml = '<button ' + (currentPage <= 1 ? 'disabled' : '') + ' onclick="PosViews._invPage(' + (currentPage - 1) + ')">&laquo; Prev</button>';
                pagHtml += '<span>Page ' + pagination.page + ' of ' + pagination.pages + '</span>';
                pagHtml += '<button ' + (currentPage >= pagination.pages ? 'disabled' : '') + ' onclick="PosViews._invPage(' + (currentPage + 1) + ')">Next &raquo;</button>';
                pagEl.innerHTML = pagHtml;
            } else if (pagEl) {
                pagEl.innerHTML = pagination.total ? '<span>' + pagination.total + ' invoices</span>' : '';
            }

        }).catch(function (err) {
            if (tbody) tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:32px;color:var(--danger)">' + esc(err.message) + '</td></tr>';
        });
    }

    PosViews._invPage = function (p) { currentPage = p; loadInvoices(); };

    PosViews._invFilter = function () {
        filterStatus = (document.getElementById('posInvStatusFilter') || {}).value || '';
        currentPage = 1;
        loadInvoices();
    };

    PosViews._invRecordPayment = function (invoiceId) {
        PosAPI.get('invoices/' + invoiceId).then(function (res) {
            var inv = res.data && res.data.invoice ? res.data.invoice : res.data;
            if (!inv) return;
            var amountDue = parseFloat(inv.amount_due || 0) || (parseFloat(inv.total || 0) - parseFloat(inv.amount_paid || 0));

            openModal(
                '<div class="pos-modal-header">' +
                    '<h3>Record Payment</h3>' +
                    '<button class="pos-modal-close" onclick="closeModal()">&times;</button>' +
                '</div>' +
                '<div class="pos-modal-body">' +
                    '<div style="text-align:center;margin-bottom:20px">' +
                        '<div style="font-size:13px;color:var(--text-muted)">Invoice ' + esc(inv.invoice_number) + '</div>' +
                        '<div style="font-size:24px;font-weight:800;color:#8b5cf6;margin-top:4px">Due: ' + money(amountDue) + '</div>' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>Amount</label>' +
                        '<input type="number" class="form-control" id="invPayAmount" step="0.01" min="0.01" max="' + amountDue.toFixed(2) + '" value="' + amountDue.toFixed(2) + '">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>Payment Method</label>' +
                        '<select class="form-control" id="invPayMethod">' +
                            '<option value="cash">Cash</option>' +
                            '<option value="card">Card</option>' +
                            '<option value="check">Check</option>' +
                            '<option value="transfer">Bank Transfer</option>' +
                        '</select>' +
                    '</div>' +
                    '<div id="invPayError" style="color:var(--danger);font-size:13px;margin-top:8px;display:none"></div>' +
                '</div>' +
                '<div class="pos-modal-footer">' +
                    '<button class="btn btn-sm btn-outline" onclick="closeModal()">Cancel</button>' +
                    '<button class="btn btn-sm btn-success" id="invPaySubmit" onclick="PosViews._invSubmitPayment(' + invoiceId + ')">Record Payment</button>' +
                '</div>'
            );
        }).catch(function (err) {
            console.error('Invoice load error:', err);
        });
    };

    PosViews._invSubmitPayment = function (invoiceId) {
        var amount = parseFloat(document.getElementById('invPayAmount').value) || 0;
        var method = (document.getElementById('invPayMethod').value || '').trim();
        var errEl = document.getElementById('invPayError');
        var btn = document.getElementById('invPaySubmit');

        if (amount <= 0) {
            errEl.textContent = 'Enter a valid amount.';
            errEl.style.display = 'block';
            return;
        }

        errEl.style.display = 'none';
        btn.disabled = true;
        btn.textContent = 'Recording...';

        PosAPI.post('invoices/' + invoiceId + '/pay', { amount: amount, method: method })
            .then(function () {
                closeModal();
                loadInvoices();
            })
            .catch(function (err) {
                errEl.textContent = err.message;
                errEl.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Record Payment';
            });
    };

    // Render shell
    root.innerHTML = '' +
        '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">' +
            '<div>' +
                '<h3 style="font-size:18px;font-weight:700">Invoices</h3>' +
                '<p style="color:var(--text-muted);font-size:13px;margin-top:4px">Manage invoices and track payments</p>' +
            '</div>' +
            '<a href="#invoice-form" class="btn btn-sm btn-success">+ Create Invoice</a>' +
        '</div>' +
        '<div class="pos-search-bar">' +
            '<select class="form-control" id="posInvStatusFilter" onchange="PosViews._invFilter()" style="max-width:160px">' +
                '<option value="">All Statuses</option>' +
                '<option value="draft">Draft</option>' +
                '<option value="sent">Sent</option>' +
                '<option value="paid">Paid</option>' +
                '<option value="partial">Partial</option>' +
                '<option value="overdue">Overdue</option>' +
                '<option value="cancelled">Cancelled</option>' +
            '</select>' +
        '</div>' +
        '<div class="card">' +
            '<div class="table-wrapper">' +
                '<table class="phoenix-table">' +
                    '<thead><tr>' +
                        '<th>Invoice #</th>' +
                        '<th>Customer</th>' +
                        '<th>Date</th>' +
                        '<th>Due Date</th>' +
                        '<th style="text-align:right">Total</th>' +
                        '<th style="text-align:right">Paid</th>' +
                        '<th>Status</th>' +
                        '<th></th>' +
                    '</tr></thead>' +
                    '<tbody id="posInvBody"></tbody>' +
                '</table>' +
            '</div>' +
            '<div class="pos-pagination" id="posInvPagination"></div>' +
        '</div>';

    loadInvoices();
};
