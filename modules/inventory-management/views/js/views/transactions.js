/**
 * Inventory Management — Transactions View
 * Transaction log with Receive Stock and Ship Stock action buttons.
 *
 * NOTE: The GET transactions endpoint is listed in the API comments but is NOT
 * implemented in routes.php. The POST receive and POST ship endpoints ARE implemented.
 * This view shows the action forms and displays a note about the transaction log.
 */
var InventoryViews = window.InventoryViews || {};

InventoryViews.transactions = function (root) {
    'use strict';

    root.innerHTML = '' +
        '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">' +
            '<div>' +
                '<h3 style="font-size:18px;font-weight:700">Transactions</h3>' +
                '<p style="color:var(--text-muted);font-size:13px;margin-top:4px">Receive and ship inventory</p>' +
            '</div>' +
            '<div style="display:flex;gap:8px">' +
                '<button class="btn btn-sm btn-success" onclick="InventoryViews._openReceive()">+ Receive Stock</button>' +
                '<button class="btn btn-sm btn-info" onclick="InventoryViews._openShip()">Ship Stock</button>' +
            '</div>' +
        '</div>' +

        '<!-- Transaction Log (Coming Soon) -->' +
        '<div class="card">' +
            '<div class="card-header">' +
                '<h3>Transaction Log</h3>' +
                '<span class="badge badge-warning">Coming Soon</span>' +
            '</div>' +
            '<div class="card-body">' +
                '<div class="inv-coming-soon" style="padding:40px">' +
                    '<div class="icon">&#128259;</div>' +
                    '<h3>Transaction History</h3>' +
                    '<p>The transaction log endpoint is planned but not yet implemented in the API. Use the Receive and Ship buttons above to process inventory movements.</p>' +
                '</div>' +
            '</div>' +
        '</div>' +

        '<!-- Quick Actions -->' +
        '<div class="grid-2" style="margin-top:24px">' +
            '<div class="card">' +
                '<div class="card-header"><h3>Receive Inventory</h3></div>' +
                '<div class="card-body">' +
                    '<p style="color:var(--text-secondary);font-size:14px;margin-bottom:16px">Record incoming stock from suppliers or returns.</p>' +
                    '<button class="btn btn-sm btn-success" onclick="InventoryViews._openReceive()">+ Receive Stock</button>' +
                '</div>' +
            '</div>' +
            '<div class="card">' +
                '<div class="card-header"><h3>Ship Inventory</h3></div>' +
                '<div class="card-body">' +
                    '<p style="color:var(--text-secondary);font-size:14px;margin-bottom:16px">Record outgoing shipments and fulfillments.</p>' +
                    '<button class="btn btn-sm btn-info" onclick="InventoryViews._openShip()">Ship Stock</button>' +
                '</div>' +
            '</div>' +
        '</div>';

    // --- Receive Stock Modal ---
    InventoryViews._openReceive = function () {
        openModal(
            '<div class="inv-modal-header">' +
                '<h3>Receive Stock</h3>' +
                '<button class="inv-modal-close" onclick="closeModal()">&times;</button>' +
            '</div>' +
            '<div class="inv-modal-body">' +
                '<div class="form-group">' +
                    '<label>Item ID *</label>' +
                    '<input type="number" class="form-control" id="rcvItemId" placeholder="Item ID">' +
                    '<div style="color:var(--text-muted);font-size:12px;margin-top:4px">Enter the numeric item ID from the Items list.</div>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Quantity *</label>' +
                    '<input type="number" step="0.001" class="form-control" id="rcvQty" placeholder="0">' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Reference Number</label>' +
                    '<input type="text" class="form-control" id="rcvRef" placeholder="e.g. PO-12345">' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>PO Number</label>' +
                    '<input type="text" class="form-control" id="rcvPo" placeholder="e.g. PO-2026-001">' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Cost per Unit ($)</label>' +
                    '<input type="number" step="0.01" class="form-control" id="rcvCost" placeholder="0.00">' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Notes</label>' +
                    '<input type="text" class="form-control" id="rcvNotes" placeholder="Optional notes">' +
                '</div>' +
                '<div id="rcvError" style="color:var(--danger);font-size:13px;margin-top:8px;display:none"></div>' +
                '<div id="rcvSuccess" style="color:var(--success);font-size:13px;margin-top:8px;display:none"></div>' +
            '</div>' +
            '<div class="inv-modal-footer">' +
                '<button class="btn btn-sm btn-outline" onclick="closeModal()">Cancel</button>' +
                '<button class="btn btn-sm btn-success" id="rcvSubmit" onclick="InventoryViews._submitReceive()">Receive</button>' +
            '</div>'
        );
    };

    InventoryViews._submitReceive = function () {
        var itemId = parseInt(document.getElementById('rcvItemId').value, 10);
        var qty = parseFloat(document.getElementById('rcvQty').value);
        var errEl = document.getElementById('rcvError');
        var successEl = document.getElementById('rcvSuccess');
        var submitBtn = document.getElementById('rcvSubmit');

        errEl.style.display = 'none';
        successEl.style.display = 'none';

        if (!itemId || isNaN(itemId) || itemId <= 0) {
            errEl.textContent = 'Valid Item ID is required.';
            errEl.style.display = 'block';
            return;
        }
        if (!qty || isNaN(qty) || qty <= 0) {
            errEl.textContent = 'Quantity must be greater than 0.';
            errEl.style.display = 'block';
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Processing...';

        var body = {
            item_id: itemId,
            quantity: qty,
            reference_number: document.getElementById('rcvRef').value.trim() || null,
            po_number: document.getElementById('rcvPo').value.trim() || null,
            cost_per_unit: parseFloat(document.getElementById('rcvCost').value) || null,
            notes: document.getElementById('rcvNotes').value.trim() || null
        };

        InventoryAPI.post('receive', body).then(function (res) {
            var msg = (res.data && res.data.message) || 'Inventory received successfully.';
            successEl.textContent = msg;
            successEl.style.display = 'block';
            submitBtn.textContent = 'Done';

            // Reset form after short delay
            setTimeout(function () {
                closeModal();
            }, 1500);
        }).catch(function (err) {
            errEl.textContent = err.message;
            errEl.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Receive';
        });
    };

    // --- Ship Stock Modal ---
    InventoryViews._openShip = function () {
        openModal(
            '<div class="inv-modal-header">' +
                '<h3>Ship Stock</h3>' +
                '<button class="inv-modal-close" onclick="closeModal()">&times;</button>' +
            '</div>' +
            '<div class="inv-modal-body">' +
                '<div class="form-group">' +
                    '<label>Item ID *</label>' +
                    '<input type="number" class="form-control" id="shipItemId" placeholder="Item ID">' +
                    '<div style="color:var(--text-muted);font-size:12px;margin-top:4px">Enter the numeric item ID from the Items list.</div>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Quantity *</label>' +
                    '<input type="number" step="0.001" class="form-control" id="shipQty" placeholder="0">' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Reference Number</label>' +
                    '<input type="text" class="form-control" id="shipRef" placeholder="e.g. SO-12345">' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Notes</label>' +
                    '<input type="text" class="form-control" id="shipNotes" placeholder="Optional notes">' +
                '</div>' +
                '<div id="shipError" style="color:var(--danger);font-size:13px;margin-top:8px;display:none"></div>' +
                '<div id="shipSuccess" style="color:var(--success);font-size:13px;margin-top:8px;display:none"></div>' +
            '</div>' +
            '<div class="inv-modal-footer">' +
                '<button class="btn btn-sm btn-outline" onclick="closeModal()">Cancel</button>' +
                '<button class="btn btn-sm btn-info" id="shipSubmit" onclick="InventoryViews._submitShip()">Ship</button>' +
            '</div>'
        );
    };

    InventoryViews._submitShip = function () {
        var itemId = parseInt(document.getElementById('shipItemId').value, 10);
        var qty = parseFloat(document.getElementById('shipQty').value);
        var errEl = document.getElementById('shipError');
        var successEl = document.getElementById('shipSuccess');
        var submitBtn = document.getElementById('shipSubmit');

        errEl.style.display = 'none';
        successEl.style.display = 'none';

        if (!itemId || isNaN(itemId) || itemId <= 0) {
            errEl.textContent = 'Valid Item ID is required.';
            errEl.style.display = 'block';
            return;
        }
        if (!qty || isNaN(qty) || qty <= 0) {
            errEl.textContent = 'Quantity must be greater than 0.';
            errEl.style.display = 'block';
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Processing...';

        var body = {
            item_id: itemId,
            quantity: qty,
            reference_number: document.getElementById('shipRef').value.trim() || null,
            notes: document.getElementById('shipNotes').value.trim() || null
        };

        InventoryAPI.post('ship', body).then(function (res) {
            var msg = (res.data && res.data.message) || 'Inventory shipped successfully.';
            successEl.textContent = msg;
            successEl.style.display = 'block';
            submitBtn.textContent = 'Done';

            setTimeout(function () {
                closeModal();
            }, 1500);
        }).catch(function (err) {
            errEl.textContent = err.message;
            errEl.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Ship';
        });
    };
};
