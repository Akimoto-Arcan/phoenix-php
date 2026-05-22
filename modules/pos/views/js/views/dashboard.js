/**
 * Point of Sale — Dashboard View
 * PhoenixPHP Module — CDAC Programming
 *
 * KPI cards, 7-day sales bar chart, recent orders table.
 */
window.PosViews = window.PosViews || {};

PosViews.dashboard = function (root) {
    'use strict';

    root.innerHTML = '<div class="pos-loading"><div class="pos-spinner"></div> Loading dashboard...</div>';

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
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    function fmtDateTime(dateStr) {
        if (!dateStr) return '-';
        var d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) +
               ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }

    function statusBadge(status) {
        var map = { open: 'info', paid: 'success', refunded: 'warning', voided: 'danger' };
        var cls = map[status] || 'info';
        return '<span class="badge badge-' + cls + '">' + esc(status) + '</span>';
    }

    Promise.all([
        PosAPI.get('stats'),
        PosAPI.get('orders', { limit: 10 })
    ]).then(function (results) {
        var stats = results[0].data || {};
        var ordersData = results[1].data || {};
        var orders = ordersData.orders || [];

        var todaySales = stats.today_sales || 0;
        var todayOrders = stats.today_orders || 0;
        var avgOrder = todayOrders > 0 ? todaySales / todayOrders : 0;
        var topMethod = stats.top_payment_method || 'N/A';
        var dailySales = stats.daily_sales || [];

        root.innerHTML = '' +
            '<div style="margin-bottom:32px">' +
                '<h1 style="font-size:28px;font-weight:800;margin-bottom:4px">Sales Dashboard</h1>' +
                '<p style="color:var(--text-muted);font-size:14px">Today\'s performance at a glance</p>' +
            '</div>' +

            '<!-- KPI Cards -->' +
            '<div class="stats-grid">' +
                '<div class="stat-card">' +
                    '<div class="stat-icon green">&#128176;</div>' +
                    '<div class="stat-value">' + money(todaySales) + '</div>' +
                    '<div class="stat-label">Today\'s Sales</div>' +
                '</div>' +
                '<div class="stat-card">' +
                    '<div class="stat-icon blue">&#128203;</div>' +
                    '<div class="stat-value">' + todayOrders + '</div>' +
                    '<div class="stat-label">Orders Today</div>' +
                '</div>' +
                '<div class="stat-card">' +
                    '<div class="stat-icon amber">&#128200;</div>' +
                    '<div class="stat-value">' + money(avgOrder) + '</div>' +
                    '<div class="stat-label">Avg Order Value</div>' +
                '</div>' +
                '<div class="stat-card">' +
                    '<div class="stat-icon red">&#128179;</div>' +
                    '<div class="stat-value" style="font-size:24px">' + esc(topMethod) + '</div>' +
                    '<div class="stat-label">Top Payment Method</div>' +
                '</div>' +
            '</div>' +

            '<!-- Chart + Recent Orders -->' +
            '<div class="grid-2">' +
                '<div class="card">' +
                    '<div class="card-header">' +
                        '<h3>Sales — Last 7 Days</h3>' +
                    '</div>' +
                    '<div class="card-body">' +
                        '<div id="posSalesChart" style="height:240px;display:flex;align-items:flex-end;gap:8px;padding:16px 0"></div>' +
                    '</div>' +
                '</div>' +
                '<div class="card">' +
                    '<div class="card-header">' +
                        '<h3>Recent Orders</h3>' +
                        '<a href="#orders" class="btn btn-sm btn-outline">View All</a>' +
                    '</div>' +
                    '<div class="card-body" id="posRecentOrders"></div>' +
                '</div>' +
            '</div>';

        // Render simple bar chart
        renderBarChart(dailySales);

        // Render recent orders
        renderRecentOrders(orders);

    }).catch(function (err) {
        console.error('Dashboard load error:', err);
        root.innerHTML = '<div class="pos-empty"><div class="icon">&#9888;</div><h3>Failed to load dashboard</h3><p>' + esc(err.message) + '</p></div>';
    });

    function renderBarChart(dailySales) {
        var chartEl = document.getElementById('posSalesChart');
        if (!chartEl || !dailySales || dailySales.length === 0) {
            if (chartEl) chartEl.innerHTML = '<div style="text-align:center;width:100%;color:var(--text-muted);align-self:center">No sales data yet</div>';
            return;
        }

        var maxVal = 0;
        for (var i = 0; i < dailySales.length; i++) {
            var val = parseFloat(dailySales[i].total || 0);
            if (val > maxVal) maxVal = val;
        }
        if (maxVal === 0) maxVal = 1;

        var html = '';
        for (var i = 0; i < dailySales.length; i++) {
            var day = dailySales[i];
            var val = parseFloat(day.total || 0);
            var pct = Math.max(2, (val / maxVal) * 100);
            var label = day.date ? fmtDate(day.date) : 'Day ' + (i + 1);

            html +=
                '<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px">' +
                    '<div style="font-size:11px;font-weight:600;color:var(--text-secondary)">' + money(val) + '</div>' +
                    '<div style="width:100%;height:' + pct + '%;min-height:4px;background:linear-gradient(180deg, #8b5cf6, #7c3aed);border-radius:6px 6px 0 0;transition:height 0.5s"></div>' +
                    '<div style="font-size:11px;color:var(--text-muted)">' + esc(label) + '</div>' +
                '</div>';
        }
        chartEl.innerHTML = html;
    }

    function renderRecentOrders(orders) {
        var el = document.getElementById('posRecentOrders');
        if (!el) return;

        if (orders.length === 0) {
            el.innerHTML = '<div class="pos-empty"><p>No orders yet. Make your first sale!</p></div>';
            return;
        }

        var html = '<ul class="activity-feed">';
        for (var i = 0; i < orders.length; i++) {
            var o = orders[i];
            var dotClass = o.status === 'paid' ? 'green' : o.status === 'voided' ? 'red' : o.status === 'refunded' ? 'amber' : 'blue';
            html +=
                '<li class="activity-item">' +
                    '<span class="activity-dot ' + dotClass + '"></span>' +
                    '<div style="flex:1">' +
                        '<div class="activity-text">' +
                            '<strong>' + esc(o.order_number) + '</strong> &mdash; ' + money(o.total) +
                        '</div>' +
                        '<div class="activity-time">' +
                            esc(o.payment_method || 'pending') + ' &middot; ' + fmtDateTime(o.created_at) +
                        '</div>' +
                    '</div>' +
                    '<div>' + statusBadge(o.status) + '</div>' +
                '</li>';
        }
        html += '</ul>';
        el.innerHTML = html;
    }
};
