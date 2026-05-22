/**
 * Point of Sale — Invoice Form View
 * PhoenixPHP Module — CDAC Programming
 *
 * Create or edit an invoice with customer selector, line items, and auto-calculation.
 */
window.PosViews = window.PosViews || {};

PosViews.invoiceForm = function (root) {
    'use strict';

    var lineItems = [];
    var nextLineId = 1;
    var customers = [];
    var defaultTaxRate = 0;

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

    // Check if editing an existing invoice (hash: #invoice-form?id=123)
    var editId = null;
    var hashParts = window.location.hash.split('?');
    if (hashParts.length > 1) {
        var qp = new URLSearchParams(hashParts[1]);
        editId = qp.get('id') ? parseInt(qp.get('id'), 10) : null;
    }

    // Load data
    root.innerHTML = '<div class="pos-loading"><div class="pos-spinner"></div> Loading...</div>';

    Promise.all([
        PosAPI.get('customers', { per_page: 200 }),
        PosAPI.get('tax-rates'),
        editId ? PosAPI.get('invoices/' + editId) : Promise.resolve(null)
    ]).then(function (results) {
        customers = (results[0].data && results[0].data.customers) || [];

        var rates = (results[1].data && results[1].data.rates) || [];
        for (var i = 0; i < rates.length; i++) {
            if (rates[i].is_default) {
                defaultTaxRate = parseFloat(rates[i].rate) || 0;
                break;
            }
        }

        var existing = null;
        if (results[2] && results[2].data) {
            existing = results[2].data.invoice || results[2].data;
        }

        if (existing && existing.items) {
            for (var j = 0; j < existing.items.length; j++) {
                var it = existing.items[j];
                lineItems.push({
                    id: nextLineId++,
                    description: it.description || it.name || '',
                    quantity: parseFloat(it.quantity) || 1,
                    unit_price: parseFloat(it.unit_price) || 0,
                    tax_rate: parseFloat(it.tax_rate) || defaultTaxRate
                });
            }
        }

        if (lineItems.length === 0) {
            addLineItem();
        }

        renderForm(existing);
    }).catch(function (err) {
        root.innerHTML = '<div class="pos-empty"><div class="icon">&#9888;</div><h3>Error</h3><p>' + esc(err.message) + '</p></div>';
    });

    function addLineItem() {
        lineItems.push({
            id: nextLineId++,
            description: '',
            quantity: 1,
            unit_price: 0,
            tax_rate: defaultTaxRate
        });
    }

    function removeLineItem(id) {
        lineItems = lineItems.filter(function (li) { return li.id !== id; });
        if (lineItems.length === 0) addLineItem();
        renderLineItems();
    }

    function syncLineItemsFromDom() {
        for (var i = 0; i < lineItems.length; i++) {
            var li = lineItems[i];
            var descEl = document.getElementById('invLine_desc_' + li.id);
            var qtyEl = document.getElementById('invLine_qty_' + li.id);
            var priceEl = document.getElementById('invLine_price_' + li.id);
            var taxEl = document.getElementById('invLine_tax_' + li.id);
            if (descEl) li.description = descEl.value;
            if (qtyEl) li.quantity = parseFloat(qtyEl.value) || 0;
            if (priceEl) li.unit_price = parseFloat(priceEl.value) || 0;
            if (taxEl) li.tax_rate = parseFloat(taxEl.value) || 0;
        }
    }

    function calcTotals() {
        var subtotal = 0;
        var totalTax = 0;
        for (var i = 0; i < lineItems.length; i++) {
            var lineTotal = lineItems[i].quantity * lineItems[i].unit_price;
            subtotal += lineTotal;
            totalTax += lineTotal * (lineItems[i].tax_rate / 100);
        }
        return { subtotal: subtotal, tax: totalTax, total: subtotal + totalTax };
    }

    function renderLineItems() {
        var container = document.getElementById('invLineItems');
        if (!container) return;

        var html = '';
        for (var i = 0; i < lineItems.length; i++) {
            var li = lineItems[i];
            var lineTotal = li.quantity * li.unit_price;
            html +=
                '<tr>' +
                    '<td style="padding:8px"><input type="text" class="form-control" id="invLine_desc_' + li.id + '" value="' + esc(li.description) + '" placeholder="Item description" style="padding:8px 12px;font-size:13px" onchange="PosViews._invFormRecalc()"></td>' +
                    '<td style="padding:8px;width:80px"><input type="number" class="form-control" id="invLine_qty_' + li.id + '" value="' + li.quantity + '" min="0.001" step="1" style="padding:8px;font-size:13px;text-align:center" onchange="PosViews._invFormRecalc()"></td>' +
                    '<td style="padding:8px;width:110px"><input type="number" class="form-control" id="invLine_price_' + li.id + '" value="' + li.unit_price.toFixed(2) + '" min="0" step="0.01" style="padding:8px;font-size:13px;text-align:right" onchange="PosViews._invFormRecalc()"></td>' +
                    '<td style="padding:8px;width:80px"><input type="number" class="form-control" id="invLine_tax_' + li.id + '" value="' + li.tax_rate.toFixed(1) + '" min="0" step="0.1" style="padding:8px;font-size:13px;text-align:center" onchange="PosViews._invFormRecalc()">%</td>' +
                    '<td style="padding:8px;width:100px;text-align:right;font-weight:600">' + money(lineTotal) + '</td>' +
                    '<td style="padding:8px;width:40px"><button class="pos-cart-item-remove" onclick="PosViews._invFormRemoveLine(' + li.id + ')" style="background:none;border:none;color:var(--text-muted);font-size:18px;cursor:pointer" title="Remove">&times;</button></td>' +
                '</tr>';
        }
        container.innerHTML = html;

        renderTotals();
    }

    function renderTotals() {
        var totalsEl = document.getElementById('invTotals');
        if (!totalsEl) return;
        var t = calcTotals();
        totalsEl.innerHTML =
            '<div style="display:flex;justify-content:space-between;padding:6px 0;font-size:14px;color:var(--text-secondary)">' +
                '<span>Subtotal</span><span>' + money(t.subtotal) + '</span>' +
            '</div>' +
            '<div style="display:flex;justify-content:space-between;padding:6px 0;font-size:14px;color:var(--text-secondary)">' +
                '<span>Tax</span><span>' + money(t.tax) + '</span>' +
            '</div>' +
            '<div style="display:flex;justify-content:space-between;padding:8px 0;font-size:20px;font-weight:800;border-top:1px solid var(--border);margin-top:8px">' +
                '<span>Total</span><span>' + money(t.total) + '</span>' +
            '</div>';
    }

    PosViews._invFormRecalc = function () {
        syncLineItemsFromDom();
        renderLineItems();
    };

    PosViews._invFormAddLine = function () {
        syncLineItemsFromDom();
        addLineItem();
        renderLineItems();
    };

    PosViews._invFormRemoveLine = function (id) {
        syncLineItemsFromDom();
        removeLineItem(id);
    };

    PosViews._invFormSave = function (sendAfter) {
        syncLineItemsFromDom();

        var customerId = (document.getElementById('invCustomer') || {}).value || '';
        var dueDate = (document.getElementById('invDueDate') || {}).value || '';
        var terms = (document.getElementById('invTerms') || {}).value || '';
        var notes = (document.getElementById('invNotes') || {}).value || '';
        var errEl = document.getElementById('invFormError');
        var saveDraftBtn = document.getElementById('invSaveDraft');
        var sendBtn = document.getElementById('invSend');

        if (!customerId) {
            errEl.textContent = 'Please select a customer.';
            errEl.style.display = 'block';
            return;
        }

        // Validate line items
        var validItems = lineItems.filter(function (li) {
            return li.description.trim() && li.quantity > 0 && li.unit_price > 0;
        });

        if (validItems.length === 0) {
            errEl.textContent = 'Add at least one line item with description, quantity, and price.';
            errEl.style.display = 'block';
            return;
        }

        errEl.style.display = 'none';
        if (saveDraftBtn) { saveDraftBtn.disabled = true; }
        if (sendBtn) { sendBtn.disabled = true; }

        var body = {
            customer_id: parseInt(customerId, 10),
            due_date: dueDate,
            terms: terms,
            notes: notes,
            status: sendAfter ? 'sent' : 'draft',
            items: validItems.map(function (li) {
                return {
                    description: li.description,
                    quantity: li.quantity,
                    unit_price: li.unit_price,
                    tax_rate: li.tax_rate
                };
            })
        };

        var promise = editId ? PosAPI.put('invoices/' + editId, body) : PosAPI.post('invoices', body);

        promise.then(function () {
            window.location.hash = '#invoices';
        }).catch(function (err) {
            errEl.textContent = err.message;
            errEl.style.display = 'block';
            if (saveDraftBtn) { saveDraftBtn.disabled = false; }
            if (sendBtn) { sendBtn.disabled = false; }
        });
    };

    function renderForm(existing) {
        var customerOptions = '<option value="">-- Select Customer --</option>';
        for (var i = 0; i < customers.length; i++) {
            var c = customers[i];
            var sel = existing && existing.customer_id == c.id ? ' selected' : '';
            customerOptions += '<option value="' + c.id + '"' + sel + '>' + esc(c.name) + (c.company ? ' (' + esc(c.company) + ')' : '') + '</option>';
        }

        var today = new Date().toISOString().split('T')[0];
        var defaultDue = new Date();
        defaultDue.setDate(defaultDue.getDate() + 30);
        var dueDate = existing ? (existing.due_date || defaultDue.toISOString().split('T')[0]) : defaultDue.toISOString().split('T')[0];

        root.innerHTML = '' +
            '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">' +
                '<div>' +
                    '<h3 style="font-size:18px;font-weight:700">' + (editId ? 'Edit Invoice' : 'Create Invoice') + '</h3>' +
                    '<p style="color:var(--text-muted);font-size:13px;margin-top:4px">' +
                        (editId ? 'Update invoice ' + esc(existing && existing.invoice_number || '') : 'Create a new invoice for a customer') +
                    '</p>' +
                '</div>' +
                '<a href="#invoices" class="btn btn-sm btn-outline">&larr; Back to Invoices</a>' +
            '</div>' +

            '<div class="card">' +
                '<div class="card-body">' +
                    '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">' +
                        '<div class="form-group">' +
                            '<label>Customer *</label>' +
                            '<select class="form-control" id="invCustomer">' + customerOptions + '</select>' +
                        '</div>' +
                        '<div class="form-group">' +
                            '<label>Due Date</label>' +
                            '<input type="date" class="form-control" id="invDueDate" value="' + esc(dueDate) + '">' +
                        '</div>' +
                    '</div>' +

                    '<h4 style="font-size:15px;font-weight:700;margin-bottom:12px">Line Items</h4>' +
                    '<div class="table-wrapper" style="margin-bottom:12px">' +
                        '<table class="phoenix-table" style="font-size:13px">' +
                            '<thead><tr>' +
                                '<th>Description</th>' +
                                '<th style="width:80px;text-align:center">Qty</th>' +
                                '<th style="width:110px;text-align:right">Unit Price</th>' +
                                '<th style="width:80px;text-align:center">Tax</th>' +
                                '<th style="width:100px;text-align:right">Total</th>' +
                                '<th style="width:40px"></th>' +
                            '</tr></thead>' +
                            '<tbody id="invLineItems"></tbody>' +
                        '</table>' +
                    '</div>' +
                    '<button class="btn btn-sm btn-outline" onclick="PosViews._invFormAddLine()" style="margin-bottom:20px">+ Add Row</button>' +

                    '<div style="max-width:300px;margin-left:auto;margin-bottom:24px" id="invTotals"></div>' +

                    '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">' +
                        '<div class="form-group">' +
                            '<label>Terms</label>' +
                            '<textarea class="form-control" id="invTerms" rows="3" placeholder="Payment terms...">' + esc(existing ? existing.terms || '' : 'Net 30') + '</textarea>' +
                        '</div>' +
                        '<div class="form-group">' +
                            '<label>Notes</label>' +
                            '<textarea class="form-control" id="invNotes" rows="3" placeholder="Internal notes...">' + esc(existing ? existing.notes || '' : '') + '</textarea>' +
                        '</div>' +
                    '</div>' +

                    '<div id="invFormError" style="color:var(--danger);font-size:13px;margin-top:12px;display:none"></div>' +

                    '<div style="display:flex;justify-content:flex-end;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid var(--border)">' +
                        '<a href="#invoices" class="btn btn-sm btn-outline">Cancel</a>' +
                        '<button class="btn btn-sm btn-outline" id="invSaveDraft" onclick="PosViews._invFormSave(false)">Save as Draft</button>' +
                        '<button class="btn btn-sm btn-success" id="invSend" onclick="PosViews._invFormSave(true)">Save &amp; Send</button>' +
                    '</div>' +
                '</div>' +
            '</div>';

        renderLineItems();
    }
};
