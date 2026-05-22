/**
 * Point of Sale — Purchase Order Form View
 * PhoenixPHP Module — CDAC Programming
 *
 * Create or edit a purchase order with supplier selector and line items.
 */
window.PosViews = window.PosViews || {};

PosViews.poForm = function (root) {
    'use strict';

    var lineItems = [];
    var nextLineId = 1;
    var suppliers = [];

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

    // Check if editing an existing PO (hash: #po-form?id=123)
    var editId = null;
    var hashParts = window.location.hash.split('?');
    if (hashParts.length > 1) {
        var qp = new URLSearchParams(hashParts[1]);
        editId = qp.get('id') ? parseInt(qp.get('id'), 10) : null;
    }

    root.innerHTML = '<div class="pos-loading"><div class="pos-spinner"></div> Loading...</div>';

    Promise.all([
        PosAPI.get('suppliers', { per_page: 200 }),
        editId ? PosAPI.get('purchase-orders/' + editId) : Promise.resolve(null)
    ]).then(function (results) {
        suppliers = (results[0].data && results[0].data.suppliers) || [];

        var existing = null;
        if (results[1] && results[1].data) {
            existing = results[1].data.purchase_order || results[1].data;
        }

        if (existing && existing.items) {
            for (var j = 0; j < existing.items.length; j++) {
                var it = existing.items[j];
                lineItems.push({
                    id: nextLineId++,
                    item_id: it.item_id || null,
                    name: it.name || it.description || '',
                    sku: it.sku || '',
                    quantity: parseFloat(it.quantity) || 1,
                    unit_cost: parseFloat(it.unit_cost || it.unit_price) || 0
                });
            }
        }

        if (lineItems.length === 0) addLineItem();
        renderForm(existing);

    }).catch(function (err) {
        root.innerHTML = '<div class="pos-empty"><div class="icon">&#9888;</div><h3>Error</h3><p>' + esc(err.message) + '</p></div>';
    });

    function addLineItem() {
        lineItems.push({
            id: nextLineId++,
            item_id: null,
            name: '',
            sku: '',
            quantity: 1,
            unit_cost: 0
        });
    }

    function removeLineItem(id) {
        lineItems = lineItems.filter(function (li) { return li.id !== id; });
        if (lineItems.length === 0) addLineItem();
        renderLineItems();
    }

    function syncFromDom() {
        for (var i = 0; i < lineItems.length; i++) {
            var li = lineItems[i];
            var nameEl = document.getElementById('poLine_name_' + li.id);
            var qtyEl = document.getElementById('poLine_qty_' + li.id);
            var costEl = document.getElementById('poLine_cost_' + li.id);
            if (nameEl) li.name = nameEl.value;
            if (qtyEl) li.quantity = parseFloat(qtyEl.value) || 0;
            if (costEl) li.unit_cost = parseFloat(costEl.value) || 0;
        }
    }

    function calcTotal() {
        var total = 0;
        for (var i = 0; i < lineItems.length; i++) {
            total += lineItems[i].quantity * lineItems[i].unit_cost;
        }
        return total;
    }

    function renderLineItems() {
        var container = document.getElementById('poLineItems');
        if (!container) return;

        var html = '';
        for (var i = 0; i < lineItems.length; i++) {
            var li = lineItems[i];
            var lineTotal = li.quantity * li.unit_cost;
            html +=
                '<tr>' +
                    '<td style="padding:8px"><input type="text" class="form-control" id="poLine_name_' + li.id + '" value="' + esc(li.name) + '" placeholder="Item name or search..." style="padding:8px 12px;font-size:13px" onchange="PosViews._poFormRecalc()"></td>' +
                    '<td style="padding:8px;width:80px"><input type="number" class="form-control" id="poLine_qty_' + li.id + '" value="' + li.quantity + '" min="1" step="1" style="padding:8px;font-size:13px;text-align:center" onchange="PosViews._poFormRecalc()"></td>' +
                    '<td style="padding:8px;width:110px"><input type="number" class="form-control" id="poLine_cost_' + li.id + '" value="' + li.unit_cost.toFixed(2) + '" min="0" step="0.01" style="padding:8px;font-size:13px;text-align:right" onchange="PosViews._poFormRecalc()"></td>' +
                    '<td style="padding:8px;width:100px;text-align:right;font-weight:600">' + money(lineTotal) + '</td>' +
                    '<td style="padding:8px;width:40px"><button onclick="PosViews._poFormRemoveLine(' + li.id + ')" style="background:none;border:none;color:var(--text-muted);font-size:18px;cursor:pointer" title="Remove">&times;</button></td>' +
                '</tr>';
        }
        container.innerHTML = html;
        renderTotal();
    }

    function renderTotal() {
        var totalEl = document.getElementById('poTotal');
        if (totalEl) {
            totalEl.innerHTML =
                '<div style="display:flex;justify-content:space-between;padding:8px 0;font-size:20px;font-weight:800">' +
                    '<span>Total</span><span>' + money(calcTotal()) + '</span>' +
                '</div>';
        }
    }

    PosViews._poFormRecalc = function () { syncFromDom(); renderLineItems(); };
    PosViews._poFormAddLine = function () { syncFromDom(); addLineItem(); renderLineItems(); };
    PosViews._poFormRemoveLine = function (id) { syncFromDom(); removeLineItem(id); };

    PosViews._poFormSave = function (submit) {
        syncFromDom();

        var supplierId = (document.getElementById('poSupplier') || {}).value || '';
        var expectedDate = (document.getElementById('poExpectedDate') || {}).value || '';
        var notes = (document.getElementById('poNotes') || {}).value || '';
        var errEl = document.getElementById('poFormError');
        var draftBtn = document.getElementById('poSaveDraft');
        var submitBtn = document.getElementById('poSubmit');

        if (!supplierId) {
            errEl.textContent = 'Please select a supplier.';
            errEl.style.display = 'block';
            return;
        }

        var validItems = lineItems.filter(function (li) {
            return li.name.trim() && li.quantity > 0 && li.unit_cost > 0;
        });

        if (validItems.length === 0) {
            errEl.textContent = 'Add at least one line item with name, quantity, and cost.';
            errEl.style.display = 'block';
            return;
        }

        errEl.style.display = 'none';
        if (draftBtn) draftBtn.disabled = true;
        if (submitBtn) submitBtn.disabled = true;

        var body = {
            supplier_id: parseInt(supplierId, 10),
            expected_date: expectedDate,
            notes: notes,
            status: submit ? 'submitted' : 'draft',
            items: validItems.map(function (li) {
                return {
                    item_id: li.item_id,
                    name: li.name,
                    sku: li.sku,
                    quantity: li.quantity,
                    unit_cost: li.unit_cost
                };
            })
        };

        var promise = editId ? PosAPI.put('purchase-orders/' + editId, body) : PosAPI.post('purchase-orders', body);

        promise.then(function () {
            window.location.hash = '#purchase-orders';
        }).catch(function (err) {
            errEl.textContent = err.message;
            errEl.style.display = 'block';
            if (draftBtn) draftBtn.disabled = false;
            if (submitBtn) submitBtn.disabled = false;
        });
    };

    function renderForm(existing) {
        var supplierOptions = '<option value="">-- Select Supplier --</option>';
        for (var i = 0; i < suppliers.length; i++) {
            var s = suppliers[i];
            var sel = existing && existing.supplier_id == s.id ? ' selected' : '';
            supplierOptions += '<option value="' + s.id + '"' + sel + '>' + esc(s.name) + '</option>';
        }

        var defaultExpected = new Date();
        defaultExpected.setDate(defaultExpected.getDate() + 14);
        var expectedDate = existing ? (existing.expected_date || defaultExpected.toISOString().split('T')[0]) : defaultExpected.toISOString().split('T')[0];

        root.innerHTML = '' +
            '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">' +
                '<div>' +
                    '<h3 style="font-size:18px;font-weight:700">' + (editId ? 'Edit Purchase Order' : 'Create Purchase Order') + '</h3>' +
                    '<p style="color:var(--text-muted);font-size:13px;margin-top:4px">' +
                        (editId ? 'Update PO ' + esc(existing && existing.po_number || '') : 'Create a new purchase order for a supplier') +
                    '</p>' +
                '</div>' +
                '<a href="#purchase-orders" class="btn btn-sm btn-outline">&larr; Back to POs</a>' +
            '</div>' +

            '<div class="card">' +
                '<div class="card-body">' +
                    '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">' +
                        '<div class="form-group">' +
                            '<label>Supplier *</label>' +
                            '<select class="form-control" id="poSupplier">' + supplierOptions + '</select>' +
                        '</div>' +
                        '<div class="form-group">' +
                            '<label>Expected Delivery Date</label>' +
                            '<input type="date" class="form-control" id="poExpectedDate" value="' + esc(expectedDate) + '">' +
                        '</div>' +
                    '</div>' +

                    '<h4 style="font-size:15px;font-weight:700;margin-bottom:12px">Line Items</h4>' +
                    '<div class="table-wrapper" style="margin-bottom:12px">' +
                        '<table class="phoenix-table" style="font-size:13px">' +
                            '<thead><tr>' +
                                '<th>Item Name</th>' +
                                '<th style="width:80px;text-align:center">Qty</th>' +
                                '<th style="width:110px;text-align:right">Unit Cost</th>' +
                                '<th style="width:100px;text-align:right">Total</th>' +
                                '<th style="width:40px"></th>' +
                            '</tr></thead>' +
                            '<tbody id="poLineItems"></tbody>' +
                        '</table>' +
                    '</div>' +
                    '<button class="btn btn-sm btn-outline" onclick="PosViews._poFormAddLine()" style="margin-bottom:20px">+ Add Row</button>' +

                    '<div style="max-width:300px;margin-left:auto;margin-bottom:24px" id="poTotal"></div>' +

                    '<div class="form-group">' +
                        '<label>Notes</label>' +
                        '<textarea class="form-control" id="poNotes" rows="3" placeholder="Internal notes...">' + esc(existing ? existing.notes || '' : '') + '</textarea>' +
                    '</div>' +

                    '<div id="poFormError" style="color:var(--danger);font-size:13px;margin-top:12px;display:none"></div>' +

                    '<div style="display:flex;justify-content:flex-end;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid var(--border)">' +
                        '<a href="#purchase-orders" class="btn btn-sm btn-outline">Cancel</a>' +
                        '<button class="btn btn-sm btn-outline" id="poSaveDraft" onclick="PosViews._poFormSave(false)">Save Draft</button>' +
                        '<button class="btn btn-sm btn-success" id="poSubmit" onclick="PosViews._poFormSave(true)">Submit PO</button>' +
                    '</div>' +
                '</div>' +
            '</div>';

        renderLineItems();
    }
};
