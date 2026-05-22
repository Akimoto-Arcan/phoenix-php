/**
 * Inventory Management — Dashboard View
 * KPI cards: Total Items, Low Stock Count, Total Value. Recent transactions.
 */
var InventoryViews = window.InventoryViews || {};

InventoryViews.dashboard = function (root) {
    'use strict';

    root.innerHTML = '<div class="inv-loading"><div class="inv-spinner"></div> Loading dashboard...</div>';

    // Fetch items and low-stock data in parallel
    Promise.all([
        InventoryAPI.get('items', { per_page: 100 }),
        InventoryAPI.get('items/low-stock')
    ]).then(function (results) {
        var itemsData = results[0];
        var lowStockData = results[1];

        var items = (itemsData.data && itemsData.data.items) || [];
        var pagination = (itemsData.data && itemsData.data.pagination) || {};
        var lowStockItems = (lowStockData.data && lowStockData.data.items) || [];

        var totalItems = pagination.total || items.length;
        var lowStockCount = lowStockItems.length;

        // Calculate total inventory value (qty * cost_price)
        var totalValue = 0;
        items.forEach(function (item) {
            var qty = parseFloat(item.stock_qty || item.available_qty || 0);
            var cost = parseFloat(item.cost_price || 0);
            totalValue += qty * cost;
        });

        root.innerHTML = '' +
            '<div style="margin-bottom:32px">' +
                '<h1 style="font-size:28px;font-weight:800;margin-bottom:4px">Inventory Overview</h1>' +
                '<p style="color:var(--text-muted);font-size:14px">Real-time stock levels and activity</p>' +
            '</div>' +

            '<!-- KPI Cards -->' +
            '<div class="stats-grid">' +
                '<div class="stat-card">' +
                    '<div class="stat-icon green">&#128230;</div>' +
                    '<div class="stat-value">' + formatNumber(totalItems) + '</div>' +
                    '<div class="stat-label">Total Items</div>' +
                '</div>' +
                '<div class="stat-card">' +
                    '<div class="stat-icon red">&#9888;</div>' +
                    '<div class="stat-value">' + formatNumber(lowStockCount) + '</div>' +
                    '<div class="stat-label">Low Stock Alerts</div>' +
                    (lowStockCount > 0 ? '<div class="stat-change down">Needs attention</div>' : '<div class="stat-change up">All stocked</div>') +
                '</div>' +
                '<div class="stat-card">' +
                    '<div class="stat-icon blue">&#128176;</div>' +
                    '<div class="stat-value">' + formatCurrency(totalValue) + '</div>' +
                    '<div class="stat-label">Total Inventory Value</div>' +
                '</div>' +
                '<div class="stat-card">' +
                    '<div class="stat-icon amber">&#128202;</div>' +
                    '<div class="stat-value">' + formatNumber(items.length) + '</div>' +
                    '<div class="stat-label">Active SKUs</div>' +
                '</div>' +
            '</div>' +

            '<!-- Content Grid -->' +
            '<div class="grid-2">' +
                '<!-- Low Stock Alerts -->' +
                '<div class="card">' +
                    '<div class="card-header">' +
                        '<h3>Low Stock Alerts</h3>' +
                        '<a href="#low-stock" class="btn btn-sm btn-outline">View All</a>' +
                    '</div>' +
                    '<div class="card-body" id="dashLowStock"></div>' +
                '</div>' +
                '<!-- Recent Items -->' +
                '<div class="card">' +
                    '<div class="card-header">' +
                        '<h3>Recently Added Items</h3>' +
                        '<a href="#items" class="btn btn-sm btn-outline">View All</a>' +
                    '</div>' +
                    '<div class="card-body" id="dashRecentItems"></div>' +
                '</div>' +
            '</div>';

        // Render low stock alerts
        var lowStockEl = document.getElementById('dashLowStock');
        if (lowStockCount === 0) {
            lowStockEl.innerHTML = '<div class="inv-empty"><p style="color:var(--success)">All items are above reorder points.</p></div>';
        } else {
            var lowHtml = '<ul class="activity-feed">';
            lowStockItems.slice(0, 8).forEach(function (item) {
                var avail = parseFloat(item.available_qty || 0);
                var reorder = parseInt(item.reorder_point || 0, 10);
                var pct = reorder > 0 ? Math.round((avail / reorder) * 100) : 0;
                var dotClass = pct <= 25 ? 'red' : pct <= 50 ? 'amber' : 'blue';
                lowHtml += '<li class="activity-item">' +
                    '<span class="activity-dot ' + dotClass + '"></span>' +
                    '<div>' +
                        '<div class="activity-text"><strong>' + escapeHtml(item.name) + '</strong> (' + escapeHtml(item.sku) + ')</div>' +
                        '<div class="activity-time">' + formatNumber(avail) + ' on hand / reorder at ' + formatNumber(reorder) + '</div>' +
                    '</div>' +
                '</li>';
            });
            lowHtml += '</ul>';
            lowStockEl.innerHTML = lowHtml;
        }

        // Render recent items (sorted by created_at desc)
        var recentEl = document.getElementById('dashRecentItems');
        var sorted = items.slice().sort(function (a, b) {
            return (b.created_at || '').localeCompare(a.created_at || '');
        });
        if (sorted.length === 0) {
            recentEl.innerHTML = '<div class="inv-empty"><p>No items found. Add your first item to get started.</p></div>';
        } else {
            var recentHtml = '<ul class="activity-feed">';
            sorted.slice(0, 8).forEach(function (item) {
                recentHtml += '<li class="activity-item">' +
                    '<span class="activity-dot green"></span>' +
                    '<div>' +
                        '<div class="activity-text"><strong>' + escapeHtml(item.name) + '</strong></div>' +
                        '<div class="activity-time">SKU: ' + escapeHtml(item.sku) + ' &middot; ' + formatCurrency(item.cost_price) + ' &middot; ' + formatDate(item.created_at) + '</div>' +
                    '</div>' +
                '</li>';
            });
            recentHtml += '</ul>';
            recentEl.innerHTML = recentHtml;
        }

    }).catch(function (err) {
        console.error('Dashboard load error:', err);
        root.innerHTML = '<div class="inv-empty"><div class="icon">&#9888;</div><h3>Failed to load dashboard</h3><p>' + escapeHtml(err.message) + '</p></div>';
    });
};
