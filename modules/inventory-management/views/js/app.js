/**
 * Inventory Management — SPA Router
 * PhoenixPHP Module — CDAC Programming
 *
 * Hash-based navigation between module views.
 */
(function () {
    'use strict';

    var routes = {
        'dashboard':        { title: 'Dashboard',        render: InventoryViews.dashboard },
        'items':            { title: 'Items',            render: InventoryViews.items },
        'low-stock':        { title: 'Low Stock',        render: InventoryViews.lowStock },
        'transactions':     { title: 'Transactions',     render: InventoryViews.transactions },
        'purchase-orders':  { title: 'Purchase Orders',  render: InventoryViews.purchaseOrders },
        'suppliers':        { title: 'Suppliers',        render: InventoryViews.suppliers }
    };

    var appRoot = document.getElementById('appRoot');
    var pageTitle = document.getElementById('pageTitle');
    var navItems = document.querySelectorAll('.inv-nav-item[data-route]');

    function getRoute() {
        var hash = window.location.hash.replace(/^#\/?/, '') || 'dashboard';
        return hash.split('?')[0];
    }

    function navigate() {
        var route = getRoute();
        var entry = routes[route];

        if (!entry) {
            route = 'dashboard';
            entry = routes[route];
        }

        // Update page title
        pageTitle.textContent = entry.title;
        document.title = entry.title + ' — Inventory Management';

        // Update active nav
        navItems.forEach(function (item) {
            if (item.getAttribute('data-route') === route) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });

        // Close mobile sidebar
        var sidebar = document.getElementById('invSidebar');
        if (sidebar) sidebar.classList.remove('open');
        var overlay = document.getElementById('mobileOverlay');
        if (overlay) overlay.classList.remove('active');

        // Close any open modal
        closeModal();

        // Render loading state
        appRoot.innerHTML = '<div class="inv-loading"><div class="inv-spinner"></div> Loading...</div>';

        // Render view
        try {
            entry.render(appRoot);
        } catch (err) {
            console.error('View render error:', err);
            appRoot.innerHTML = '<div class="inv-empty"><div class="icon">&#9888;</div><h3>Error</h3><p>' + escapeHtml(err.message) + '</p></div>';
        }
    }

    // Modal helpers (global)
    window.openModal = function (html) {
        var overlay = document.getElementById('modalOverlay');
        var container = document.getElementById('modalContainer');
        container.innerHTML = html;
        overlay.classList.add('open');

        // Close on overlay click
        overlay.onclick = function (e) {
            if (e.target === overlay) closeModal();
        };

        // Close on Escape
        document.addEventListener('keydown', modalEscHandler);
    };

    window.closeModal = function () {
        var overlay = document.getElementById('modalOverlay');
        overlay.classList.remove('open');
        document.removeEventListener('keydown', modalEscHandler);
    };

    function modalEscHandler(e) {
        if (e.key === 'Escape') closeModal();
    }

    // HTML escape helper (global)
    window.escapeHtml = function (str) {
        if (str === null || str === undefined) return '';
        var div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    };

    // Format number with commas
    window.formatNumber = function (n) {
        if (n === null || n === undefined) return '0';
        return Number(n).toLocaleString('en-US');
    };

    // Format currency
    window.formatCurrency = function (n) {
        if (n === null || n === undefined) return '$0.00';
        return '$' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    // Format date
    window.formatDate = function (dateStr) {
        if (!dateStr) return '-';
        var d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    };

    // Format datetime
    window.formatDateTime = function (dateStr) {
        if (!dateStr) return '-';
        var d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) +
               ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    };

    // Listen for hash changes
    window.addEventListener('hashchange', navigate);

    // Initial load
    navigate();
})();
