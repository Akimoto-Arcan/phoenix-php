/**
 * Inventory Management — Items View
 * Item list with search, filter, pagination. Add Item form.
 * Highlights rows where qty < reorder_point in red.
 */
var InventoryViews = window.InventoryViews || {};

InventoryViews.items = function (root) {
    'use strict';

    var currentPage = 1;
    var searchTerm = '';
    var perPage = 20;

    function loadItems() {
        var tableBody = document.getElementById('invItemsBody');
        var paginationEl = document.getElementById('invItemsPagination');
        if (tableBody) tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:32px"><div class="inv-loading"><div class="inv-spinner"></div> Loading...</div></td></tr>';

        var params = { page: currentPage, per_page: perPage };
        if (searchTerm) params.search = searchTerm;

        InventoryAPI.get('items', params).then(function (res) {
            var items = (res.data && res.data.items) || [];
            var pagination = (res.data && res.data.pagination) || {};

            if (!tableBody) return;

            if (items.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted)">No items found.</td></tr>';
                if (paginationEl) paginationEl.innerHTML = '';
                return;
            }

            var html = '';
            items.forEach(function (item) {
                var qty = parseFloat(item.stock_qty || item.available_qty || 0);
                var reorder = parseInt(item.reorder_point || 0, 10);
                var isLow = reorder > 0 && qty <= reorder;
                html += '<tr class="' + (isLow ? 'inv-row-danger' : '') + '">' +
                    '<td><strong>' + escapeHtml(item.sku) + '</strong></td>' +
                    '<td>' + escapeHtml(item.name) + '</td>' +
                    '<td>' + escapeHtml(item.category_name || '-') + '</td>' +
                    '<td>' + escapeHtml(item.location || '-') + '</td>' +
                    '<td style="text-align:right">' +
                        (isLow ? '<span class="badge badge-danger">' + formatNumber(qty) + '</span>' : formatNumber(qty)) +
                    '</td>' +
                    '<td style="text-align:right">' + formatNumber(reorder) + '</td>' +
                    '<td style="text-align:right">' + formatCurrency(item.cost_price) + '</td>' +
                    '<td>' + escapeHtml(item.unit_of_measure || 'each') + '</td>' +
                '</tr>';
            });
            tableBody.innerHTML = html;

            // Pagination
            if (paginationEl && pagination.pages > 1) {
                var pagHtml = '<button ' + (currentPage <= 1 ? 'disabled' : '') + ' onclick="InventoryViews._itemsPage(' + (currentPage - 1) + ')">&laquo; Prev</button>';
                pagHtml += '<span>Page ' + pagination.page + ' of ' + pagination.pages + ' (' + formatNumber(pagination.total) + ' items)</span>';
                pagHtml += '<button ' + (currentPage >= pagination.pages ? 'disabled' : '') + ' onclick="InventoryViews._itemsPage(' + (currentPage + 1) + ')"">Next &raquo;</button>';
                paginationEl.innerHTML = pagHtml;
            } else if (paginationEl) {
                paginationEl.innerHTML = pagination.total ? '<span>' + formatNumber(pagination.total) + ' items</span>' : '';
            }

        }).catch(function (err) {
            if (tableBody) tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:32px;color:var(--danger)">' + escapeHtml(err.message) + '</td></tr>';
        });
    }

    // Expose page navigation
    InventoryViews._itemsPage = function (page) {
        currentPage = page;
        loadItems();
    };

    // Expose search handler
    InventoryViews._itemsSearch = function () {
        var input = document.getElementById('invItemsSearch');
        searchTerm = input ? input.value.trim() : '';
        currentPage = 1;
        loadItems();
    };

    // Add Item modal
    InventoryViews._openAddItem = function () {
        openModal(
            '<div class="inv-modal-header">' +
                '<h3>Add New Item</h3>' +
                '<button class="inv-modal-close" onclick="closeModal()">&times;</button>' +
            '</div>' +
            '<div class="inv-modal-body">' +
                '<div class="form-group">' +
                    '<label>SKU *</label>' +
                    '<input type="text" class="form-control" id="addItemSku" placeholder="e.g. WR-001">' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Name *</label>' +
                    '<input type="text" class="form-control" id="addItemName" placeholder="Item name">' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Description</label>' +
                    '<input type="text" class="form-control" id="addItemDesc" placeholder="Optional description">' +
                '</div>' +
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">' +
                    '<div class="form-group">' +
                        '<label>Unit Cost ($)</label>' +
                        '<input type="number" step="0.01" class="form-control" id="addItemCost" placeholder="0.00">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>Sell Price ($)</label>' +
                        '<input type="number" step="0.01" class="form-control" id="addItemSell" placeholder="0.00">' +
                    '</div>' +
                '</div>' +
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">' +
                    '<div class="form-group">' +
                        '<label>Reorder Point</label>' +
                        '<input type="number" class="form-control" id="addItemReorder" placeholder="0">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>Reorder Qty</label>' +
                        '<input type="number" class="form-control" id="addItemReorderQty" placeholder="0">' +
                    '</div>' +
                '</div>' +
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">' +
                    '<div class="form-group">' +
                        '<label>Location</label>' +
                        '<input type="text" class="form-control" id="addItemLocation" placeholder="e.g. Shelf A3">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>Unit of Measure</label>' +
                        '<input type="text" class="form-control" id="addItemUom" placeholder="each" value="each">' +
                    '</div>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>UPC / Barcode</label>' +
                    '<input type="text" class="form-control" id="addItemUpc" placeholder="Optional">' +
                '</div>' +
                '<div id="addItemError" style="color:var(--danger);font-size:13px;margin-top:8px;display:none"></div>' +
            '</div>' +
            '<div class="inv-modal-footer">' +
                '<button class="btn btn-sm btn-outline" onclick="closeModal()">Cancel</button>' +
                '<button class="btn btn-sm btn-success" id="addItemSubmit" onclick="InventoryViews._submitAddItem()">Create Item</button>' +
            '</div>'
        );
    };

    InventoryViews._submitAddItem = function () {
        var sku = document.getElementById('addItemSku').value.trim();
        var name = document.getElementById('addItemName').value.trim();
        var errEl = document.getElementById('addItemError');
        var submitBtn = document.getElementById('addItemSubmit');

        if (!sku || !name) {
            errEl.textContent = 'SKU and Name are required.';
            errEl.style.display = 'block';
            return;
        }

        errEl.style.display = 'none';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Creating...';

        var body = {
            sku: sku,
            name: name,
            description: document.getElementById('addItemDesc').value.trim() || null,
            cost_price: parseFloat(document.getElementById('addItemCost').value) || 0,
            sell_price: parseFloat(document.getElementById('addItemSell').value) || 0,
            reorder_point: parseInt(document.getElementById('addItemReorder').value, 10) || 0,
            reorder_qty: parseInt(document.getElementById('addItemReorderQty').value, 10) || 0,
            location: document.getElementById('addItemLocation').value.trim() || null,
            unit_of_measure: document.getElementById('addItemUom').value.trim() || 'each',
            upc: document.getElementById('addItemUpc').value.trim() || null
        };

        InventoryAPI.post('items', body).then(function () {
            closeModal();
            loadItems();
        }).catch(function (err) {
            errEl.textContent = err.message;
            errEl.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create Item';
        });
    };

    // Render shell
    root.innerHTML = '' +
        '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">' +
            '<h3 style="font-size:18px;font-weight:700">Item Master</h3>' +
            '<button class="btn btn-sm btn-success" onclick="InventoryViews._openAddItem()">+ Add Item</button>' +
        '</div>' +
        '<div class="inv-search-bar">' +
            '<input type="text" id="invItemsSearch" placeholder="Search by SKU, name, or UPC..." onkeyup="if(event.key===\'Enter\')InventoryViews._itemsSearch()">' +
            '<button class="btn btn-sm btn-outline" onclick="InventoryViews._itemsSearch()">Search</button>' +
        '</div>' +
        '<div class="card">' +
            '<div class="table-wrapper">' +
                '<table class="phoenix-table">' +
                    '<thead><tr>' +
                        '<th>SKU</th>' +
                        '<th>Name</th>' +
                        '<th>Category</th>' +
                        '<th>Location</th>' +
                        '<th style="text-align:right">Qty On Hand</th>' +
                        '<th style="text-align:right">Reorder Point</th>' +
                        '<th style="text-align:right">Unit Cost</th>' +
                        '<th>UoM</th>' +
                    '</tr></thead>' +
                    '<tbody id="invItemsBody"></tbody>' +
                '</table>' +
            '</div>' +
            '<div class="inv-pagination" id="invItemsPagination"></div>' +
        '</div>';

    loadItems();
};
