/**
 * Inventory Management — Suppliers View
 *
 * The Supplier endpoints (GET/POST/PUT /suppliers) are listed in the API
 * route comments but are NOT implemented in routes.php.
 * This view shows a Coming Soon placeholder.
 */
var InventoryViews = window.InventoryViews || {};

InventoryViews.suppliers = function (root) {
    'use strict';

    root.innerHTML = '' +
        '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">' +
            '<div>' +
                '<h3 style="font-size:18px;font-weight:700">Supplier Directory</h3>' +
                '<p style="color:var(--text-muted);font-size:13px;margin-top:4px">Manage your supplier contacts and lead times</p>' +
            '</div>' +
        '</div>' +
        '<div class="card">' +
            '<div class="card-body">' +
                '<div class="inv-coming-soon">' +
                    '<div class="icon">&#129309;</div>' +
                    '<h3>Coming Soon</h3>' +
                    '<p>The Supplier Directory is planned and the database schema is ready (<code>inventory_suppliers</code> table). The API endpoints for managing suppliers are not yet implemented.</p>' +
                    '<div style="margin-top:24px">' +
                        '<span class="badge badge-info" style="margin:4px">GET /suppliers</span> ' +
                        '<span class="badge badge-info" style="margin:4px">POST /suppliers</span> ' +
                        '<span class="badge badge-info" style="margin:4px">PUT /suppliers/{id}</span>' +
                    '</div>' +
                    '<div style="margin-top:24px">' +
                        '<h4 style="font-size:14px;font-weight:600;margin-bottom:8px;color:var(--text-secondary)">Planned Columns</h4>' +
                        '<div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:center">' +
                            '<span class="badge badge-success">Name</span>' +
                            '<span class="badge badge-success">Contact</span>' +
                            '<span class="badge badge-success">Email</span>' +
                            '<span class="badge badge-success">Phone</span>' +
                            '<span class="badge badge-success">Address</span>' +
                            '<span class="badge badge-success">Lead Time</span>' +
                            '<span class="badge badge-success">Payment Terms</span>' +
                        '</div>' +
                    '</div>' +
                    '<p style="margin-top:16px;font-size:12px;color:var(--text-muted)">These endpoints will be available in a future update.</p>' +
                '</div>' +
            '</div>' +
        '</div>';
};
