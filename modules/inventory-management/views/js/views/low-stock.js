/**
 * Inventory Management — Low Stock View
 * Filtered view showing only items below reorder point.
 * Calls api.get('items/low-stock').
 */
var InventoryViews = window.InventoryViews || {};

InventoryViews.lowStock = function (root) {
    'use strict';

    root.innerHTML = '' +
        '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">' +
            '<div>' +
                '<h3 style="font-size:18px;font-weight:700">Low Stock Alerts</h3>' +
                '<p style="color:var(--text-muted);font-size:13px;margin-top:4px">Items at or below their reorder point</p>' +
            '</div>' +
            '<a href="#items" class="btn btn-sm btn-outline">View All Items</a>' +
        '</div>' +
        '<div class="card">' +
            '<div class="table-wrapper">' +
                '<table class="phoenix-table">' +
                    '<thead><tr>' +
                        '<th>SKU</th>' +
                        '<th>Name</th>' +
                        '<th>Category</th>' +
                        '<th>Location</th>' +
                        '<th style="text-align:right">Available Qty</th>' +
                        '<th style="text-align:right">Reorder Point</th>' +
                        '<th style="text-align:right">Unit Cost</th>' +
                        '<th>Status</th>' +
                    '</tr></thead>' +
                    '<tbody id="invLowStockBody">' +
                        '<tr><td colspan="8" style="text-align:center;padding:32px"><div class="inv-loading"><div class="inv-spinner"></div> Loading...</div></td></tr>' +
                    '</tbody>' +
                '</table>' +
            '</div>' +
        '</div>';

    InventoryAPI.get('items/low-stock').then(function (res) {
        var items = (res.data && res.data.items) || [];
        var tbody = document.getElementById('invLowStockBody');
        if (!tbody) return;

        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8"><div class="inv-empty" style="padding:32px">' +
                '<div class="icon" style="color:var(--success);font-size:36px">&#10003;</div>' +
                '<h3>All Clear</h3>' +
                '<p>No items are below their reorder point.</p>' +
                '</div></td></tr>';
            return;
        }

        var html = '';
        items.forEach(function (item) {
            var avail = parseFloat(item.available_qty || 0);
            var reorder = parseInt(item.reorder_point || 0, 10);
            var pct = reorder > 0 ? Math.round((avail / reorder) * 100) : 0;

            var statusBadge;
            if (avail <= 0) {
                statusBadge = '<span class="badge badge-danger">Out of Stock</span>';
            } else if (pct <= 25) {
                statusBadge = '<span class="badge badge-danger">Critical</span>';
            } else if (pct <= 50) {
                statusBadge = '<span class="badge badge-warning">Low</span>';
            } else {
                statusBadge = '<span class="badge badge-warning">Below Reorder</span>';
            }

            html += '<tr class="inv-row-danger">' +
                '<td><strong>' + escapeHtml(item.sku) + '</strong></td>' +
                '<td>' + escapeHtml(item.name) + '</td>' +
                '<td>' + escapeHtml(item.category_name || '-') + '</td>' +
                '<td>' + escapeHtml(item.location || '-') + '</td>' +
                '<td style="text-align:right"><strong>' + formatNumber(avail) + '</strong></td>' +
                '<td style="text-align:right">' + formatNumber(reorder) + '</td>' +
                '<td style="text-align:right">' + formatCurrency(item.cost_price) + '</td>' +
                '<td>' + statusBadge + '</td>' +
            '</tr>';
        });
        tbody.innerHTML = html;

    }).catch(function (err) {
        var tbody = document.getElementById('invLowStockBody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:32px;color:var(--danger)">' + escapeHtml(err.message) + '</td></tr>';
        }
    });
};
