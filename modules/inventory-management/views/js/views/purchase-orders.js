/**
 * Inventory Management — Purchase Orders View
 *
 * The PO endpoints (GET/POST/PUT /po, POST /po/{id}/receive) are listed
 * in the API route comments but are NOT implemented in routes.php.
 * This view shows a Coming Soon placeholder.
 */
var InventoryViews = window.InventoryViews || {};

InventoryViews.purchaseOrders = function (root) {
    'use strict';

    root.innerHTML = '' +
        '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">' +
            '<div>' +
                '<h3 style="font-size:18px;font-weight:700">Purchase Orders</h3>' +
                '<p style="color:var(--text-muted);font-size:13px;margin-top:4px">Create and track purchase orders</p>' +
            '</div>' +
        '</div>' +
        '<div class="card">' +
            '<div class="card-body">' +
                '<div class="inv-coming-soon">' +
                    '<div class="icon">&#128196;</div>' +
                    '<h3>Coming Soon</h3>' +
                    '<p>The Purchase Orders module is planned and the database schema is ready. The API endpoints for listing, creating, and receiving against POs are not yet implemented.</p>' +
                    '<div style="margin-top:24px">' +
                        '<span class="badge badge-info" style="margin:4px">GET /po</span> ' +
                        '<span class="badge badge-info" style="margin:4px">POST /po</span> ' +
                        '<span class="badge badge-info" style="margin:4px">PUT /po/{id}</span> ' +
                        '<span class="badge badge-info" style="margin:4px">POST /po/{id}/receive</span>' +
                    '</div>' +
                    '<p style="margin-top:16px;font-size:12px;color:var(--text-muted)">These endpoints will be available in a future update.</p>' +
                '</div>' +
            '</div>' +
        '</div>';
};
