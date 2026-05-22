/**
 * Point of Sale — Register View
 * PhoenixPHP Module — CDAC Programming
 *
 * Split-screen POS register: product search + cart/payment.
 * Supports barcode scanner detection (rapid input + Enter).
 */
window.PosViews = window.PosViews || {};

PosViews.register = function (root) {
    'use strict';

    // ── State ────────────────────────────────────────────────────────────
    var cart = [];           // { id, item_id, sku, name, price, qty, stock }
    var taxRate = 0;         // percentage, fetched from API
    var taxRateName = '';
    var discountAmount = 0;
    var lastKeyTime = 0;
    var inputBuffer = '';
    var searchTimeout = null;

    // ── Helpers ──────────────────────────────────────────────────────────
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

    function cartId() {
        return 'cid_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
    }

    // ── Tax rate fetch ───────────────────────────────────────────────────
    function fetchTaxRate() {
        PosAPI.get('tax-rates').then(function (res) {
            var rates = (res.data && res.data.rates) || [];
            for (var i = 0; i < rates.length; i++) {
                if (rates[i].is_default) {
                    taxRate = parseFloat(rates[i].rate) || 0;
                    taxRateName = rates[i].name || 'Tax';
                    break;
                }
            }
            renderCartTotals();
        }).catch(function () {
            taxRate = 7;
            taxRateName = 'Tax';
        });
    }

    // ── Cart logic ───────────────────────────────────────────────────────
    function addToCart(product) {
        var existing = null;
        for (var i = 0; i < cart.length; i++) {
            if (cart[i].item_id === product.id || (cart[i].sku && cart[i].sku === product.sku)) {
                existing = cart[i];
                break;
            }
        }
        if (existing) {
            existing.qty += 1;
        } else {
            cart.push({
                id: cartId(),
                item_id: product.id || null,
                sku: product.sku || '',
                name: product.name,
                price: parseFloat(product.sell_price || product.price || 0),
                qty: 1,
                stock: parseFloat(product.stock_qty || product.available_qty || 0)
            });
        }
        renderCart();
        flashCartBadge();
    }

    function removeFromCart(id) {
        cart = cart.filter(function (item) { return item.id !== id; });
        renderCart();
    }

    function updateQty(id, delta) {
        for (var i = 0; i < cart.length; i++) {
            if (cart[i].id === id) {
                cart[i].qty = Math.max(1, cart[i].qty + delta);
                break;
            }
        }
        renderCart();
    }

    function setQty(id, val) {
        var n = parseInt(val, 10);
        if (isNaN(n) || n < 1) n = 1;
        for (var i = 0; i < cart.length; i++) {
            if (cart[i].id === id) {
                cart[i].qty = n;
                break;
            }
        }
        renderCartTotals();
    }

    function clearCart() {
        cart = [];
        discountAmount = 0;
        renderCart();
    }

    function getSubtotal() {
        var s = 0;
        for (var i = 0; i < cart.length; i++) s += cart[i].price * cart[i].qty;
        return s;
    }

    function getTaxAmount() {
        return (getSubtotal() - discountAmount) * (taxRate / 100);
    }

    function getTotal() {
        return getSubtotal() - discountAmount + getTaxAmount();
    }

    // ── Flash animation on cart count ────────────────────────────────────
    function flashCartBadge() {
        var badge = document.getElementById('posCartCount');
        if (!badge) return;
        badge.classList.remove('pos-flash');
        void badge.offsetWidth;
        badge.classList.add('pos-flash');
    }

    // ── Render ───────────────────────────────────────────────────────────
    function renderCart() {
        var listEl = document.getElementById('posCartItems');
        var countEl = document.getElementById('posCartCount');
        if (!listEl) return;

        if (countEl) {
            var totalQty = 0;
            for (var i = 0; i < cart.length; i++) totalQty += cart[i].qty;
            countEl.textContent = totalQty;
        }

        if (cart.length === 0) {
            listEl.innerHTML =
                '<div class="pos-cart-empty">' +
                    '<div style="font-size:48px;opacity:0.2;margin-bottom:12px">&#128722;</div>' +
                    '<p>Cart is empty</p>' +
                    '<p style="font-size:12px;margin-top:4px">Scan a barcode or search for a product</p>' +
                '</div>';
            renderCartTotals();
            return;
        }

        var html = '';
        for (var i = 0; i < cart.length; i++) {
            var c = cart[i];
            var lineTotal = c.price * c.qty;
            html +=
                '<div class="pos-cart-item" data-id="' + esc(c.id) + '">' +
                    '<div class="pos-cart-item-info">' +
                        '<div class="pos-cart-item-name">' + esc(c.name) + '</div>' +
                        '<div class="pos-cart-item-sku">' + esc(c.sku) + ' &middot; ' + money(c.price) + ' ea</div>' +
                    '</div>' +
                    '<div class="pos-cart-item-qty">' +
                        '<button class="pos-qty-btn" onclick="PosViews._regUpdateQty(\'' + c.id + '\', -1)">-</button>' +
                        '<input type="number" class="pos-qty-input" value="' + c.qty + '" min="1" ' +
                            'onchange="PosViews._regSetQty(\'' + c.id + '\', this.value)">' +
                        '<button class="pos-qty-btn" onclick="PosViews._regUpdateQty(\'' + c.id + '\', 1)">+</button>' +
                    '</div>' +
                    '<div class="pos-cart-item-total">' + money(lineTotal) + '</div>' +
                    '<button class="pos-cart-item-remove" onclick="PosViews._regRemove(\'' + c.id + '\')" title="Remove">&times;</button>' +
                '</div>';
        }
        listEl.innerHTML = html;
        renderCartTotals();
    }

    function renderCartTotals() {
        var totalsEl = document.getElementById('posCartTotals');
        if (!totalsEl) return;
        var sub = getSubtotal();
        var tax = getTaxAmount();
        var total = getTotal();

        totalsEl.innerHTML =
            '<div class="pos-totals-row">' +
                '<span>Subtotal</span><span>' + money(sub) + '</span>' +
            '</div>' +
            '<div class="pos-totals-row">' +
                '<span>' + esc(taxRateName || 'Tax') + ' (' + taxRate.toFixed(1) + '%)</span><span>' + money(tax) + '</span>' +
            '</div>' +
            '<div class="pos-totals-row">' +
                '<span>Discount</span>' +
                '<span style="display:flex;align-items:center;gap:6px">' +
                    '<span style="color:var(--text-muted)">$</span>' +
                    '<input type="number" class="pos-discount-input" value="' + discountAmount.toFixed(2) + '" min="0" step="0.01" ' +
                        'onchange="PosViews._regSetDiscount(this.value)">' +
                '</span>' +
            '</div>' +
            '<div class="pos-totals-row pos-totals-grand">' +
                '<span>Total</span><span>' + money(total) + '</span>' +
            '</div>';
    }

    // ── Product search ───────────────────────────────────────────────────
    function searchProducts(query) {
        var resultsEl = document.getElementById('posSearchResults');
        if (!resultsEl) return;

        if (!query || query.length < 1) {
            resultsEl.innerHTML =
                '<div class="pos-search-placeholder">' +
                    '<div style="font-size:56px;opacity:0.15;margin-bottom:16px">&#128270;</div>' +
                    '<p style="font-size:15px;color:var(--text-muted)">Search for products by name, SKU, or scan a barcode</p>' +
                '</div>';
            return;
        }

        resultsEl.innerHTML = '<div class="pos-loading"><div class="pos-spinner"></div> Searching...</div>';

        PosAPI.get('products/search', { q: query }).then(function (res) {
            var products = (res.data && res.data.products) || [];
            if (products.length === 0) {
                resultsEl.innerHTML =
                    '<div class="pos-search-placeholder">' +
                        '<div style="font-size:48px;opacity:0.2;margin-bottom:12px">&#128533;</div>' +
                        '<p style="color:var(--text-muted)">No products found for "' + esc(query) + '"</p>' +
                    '</div>';
                return;
            }

            var html = '<div class="pos-product-grid">';
            for (var i = 0; i < products.length; i++) {
                var p = products[i];
                var stockQty = parseFloat(p.stock_qty || p.available_qty || 0);
                var inStock = stockQty > 0;
                html +=
                    '<div class="pos-product-card ' + (inStock ? '' : 'pos-out-of-stock') + '" ' +
                        'onclick="PosViews._regAddProduct(' + p.id + ')" data-product-id="' + p.id + '">' +
                        '<div class="pos-product-name">' + esc(p.name) + '</div>' +
                        '<div class="pos-product-sku">' + esc(p.sku) + '</div>' +
                        '<div class="pos-product-meta">' +
                            '<span class="pos-product-price">' + money(p.sell_price || p.price) + '</span>' +
                            '<span class="pos-product-stock ' + (inStock ? 'in' : 'out') + '">' +
                                (inStock ? stockQty + ' in stock' : 'Out of stock') +
                            '</span>' +
                        '</div>' +
                    '</div>';
            }
            html += '</div>';
            resultsEl.innerHTML = html;
        }).catch(function (err) {
            resultsEl.innerHTML =
                '<div class="pos-search-placeholder">' +
                    '<div style="font-size:48px;opacity:0.2;margin-bottom:12px">&#9888;</div>' +
                    '<p style="color:var(--danger)">' + esc(err.message) + '</p>' +
                '</div>';
        });
    }

    // Barcode scanner detection: rapid keypresses + Enter within 100ms
    function handleSearchKeydown(e) {
        var now = Date.now();
        var input = e.target;

        if (e.key === 'Enter') {
            e.preventDefault();
            var gap = now - lastKeyTime;
            var val = input.value.trim();

            if (gap < 100 && val.length > 3) {
                // Barcode scanner detected: auto-add first result
                PosAPI.get('products/search', { q: val }).then(function (res) {
                    var products = (res.data && res.data.products) || [];
                    if (products.length > 0) {
                        addToCart(products[0]);
                        input.value = '';
                        input.focus();
                        // Show brief feedback
                        var resultsEl = document.getElementById('posSearchResults');
                        if (resultsEl) {
                            resultsEl.innerHTML =
                                '<div class="pos-scan-success">' +
                                    '<span style="font-size:24px">&#9989;</span> Added: ' + esc(products[0].name) +
                                '</div>';
                            setTimeout(function () {
                                if (resultsEl && resultsEl.querySelector('.pos-scan-success')) {
                                    resultsEl.innerHTML =
                                        '<div class="pos-search-placeholder">' +
                                            '<div style="font-size:56px;opacity:0.15;margin-bottom:16px">&#128270;</div>' +
                                            '<p style="font-size:15px;color:var(--text-muted)">Search for products by name, SKU, or scan a barcode</p>' +
                                        '</div>';
                                }
                            }, 2000);
                        }
                    } else {
                        searchProducts(val);
                    }
                }).catch(function () {
                    searchProducts(val);
                });
            } else {
                // Normal search
                searchProducts(val);
            }
            return;
        }

        lastKeyTime = now;

        // Debounced manual search (only if typing slowly)
        if (searchTimeout) clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function () {
            var val = input.value.trim();
            if (val.length >= 2) {
                searchProducts(val);
            }
        }, 400);
    }

    // ── Payment flows ────────────────────────────────────────────────────
    function openCashModal() {
        if (cart.length === 0) return;
        var total = getTotal();
        openModal(
            '<div class="pos-modal-header">' +
                '<h3>&#128181; Cash Payment</h3>' +
                '<button class="pos-modal-close" onclick="closeModal()">&times;</button>' +
            '</div>' +
            '<div class="pos-modal-body">' +
                '<div style="text-align:center;margin-bottom:24px">' +
                    '<div style="font-size:14px;color:var(--text-muted);margin-bottom:4px">Amount Due</div>' +
                    '<div style="font-size:36px;font-weight:800;color:#8b5cf6">' + money(total) + '</div>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Amount Tendered</label>' +
                    '<input type="number" class="form-control" id="posCashTendered" step="0.01" min="' + total.toFixed(2) + '" ' +
                        'value="' + Math.ceil(total).toFixed(2) + '" style="font-size:22px;text-align:center;font-weight:700" ' +
                        'oninput="PosViews._regCalcChange()">' +
                '</div>' +
                '<div style="text-align:center;padding:16px;background:var(--bg-card);border-radius:var(--radius-sm);margin-top:16px">' +
                    '<div style="font-size:13px;color:var(--text-muted)">Change Due</div>' +
                    '<div id="posCashChange" style="font-size:28px;font-weight:800;color:var(--success)">$0.00</div>' +
                '</div>' +
                '<div id="posCashError" style="color:var(--danger);font-size:13px;margin-top:12px;text-align:center;display:none"></div>' +
            '</div>' +
            '<div class="pos-modal-footer">' +
                '<button class="btn btn-sm btn-outline" onclick="closeModal()">Cancel</button>' +
                '<button class="btn btn-sm btn-success" id="posCashConfirm" onclick="PosViews._regPayCash()">Confirm Payment</button>' +
            '</div>'
        );
        PosViews._regCalcChange();
        var input = document.getElementById('posCashTendered');
        if (input) { input.focus(); input.select(); }
    }

    function openCardModal() {
        if (cart.length === 0) return;
        var total = getTotal();
        openModal(
            '<div class="pos-modal-header">' +
                '<h3>&#128179; Card Payment</h3>' +
                '<button class="pos-modal-close" onclick="closeModal()">&times;</button>' +
            '</div>' +
            '<div class="pos-modal-body">' +
                '<div style="text-align:center;margin-bottom:24px">' +
                    '<div style="font-size:14px;color:var(--text-muted);margin-bottom:4px">Charging</div>' +
                    '<div style="font-size:36px;font-weight:800;color:#8b5cf6">' + money(total) + '</div>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Last 4 Digits (optional)</label>' +
                    '<input type="text" class="form-control" id="posCardLast4" maxlength="4" placeholder="1234">' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Card Type (optional)</label>' +
                    '<select class="form-control" id="posCardType">' +
                        '<option value="">-- Select --</option>' +
                        '<option value="Visa">Visa</option>' +
                        '<option value="Mastercard">Mastercard</option>' +
                        '<option value="Amex">Amex</option>' +
                        '<option value="Discover">Discover</option>' +
                        '<option value="Other">Other</option>' +
                    '</select>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Approval Code (optional)</label>' +
                    '<input type="text" class="form-control" id="posCardApproval" placeholder="Auth code from terminal">' +
                '</div>' +
                '<div id="posCardError" style="color:var(--danger);font-size:13px;margin-top:8px;text-align:center;display:none"></div>' +
                '<div id="posCardStatus" style="text-align:center;display:none;padding:16px">' +
                    '<div class="pos-spinner" style="display:inline-block;margin-right:8px"></div>' +
                    '<span style="color:var(--text-muted)">Processing payment...</span>' +
                '</div>' +
            '</div>' +
            '<div class="pos-modal-footer">' +
                '<button class="btn btn-sm btn-outline" onclick="closeModal()">Cancel</button>' +
                '<button class="btn btn-sm btn-info" id="posCardConfirm" onclick="PosViews._regPayCard()">Process Card</button>' +
            '</div>'
        );
    }

    function openInvoiceModal() {
        if (cart.length === 0) return;
        var total = getTotal();

        // Fetch customers for selector
        PosAPI.get('customers', { per_page: 100 }).then(function (res) {
            var customers = (res.data && res.data.customers) || [];
            var options = '<option value="">-- Select Customer --</option>';
            for (var i = 0; i < customers.length; i++) {
                var c = customers[i];
                options += '<option value="' + c.id + '">' + esc(c.name) + (c.company ? ' (' + esc(c.company) + ')' : '') + '</option>';
            }

            openModal(
                '<div class="pos-modal-header">' +
                    '<h3>&#128196; Create Invoice</h3>' +
                    '<button class="pos-modal-close" onclick="closeModal()">&times;</button>' +
                '</div>' +
                '<div class="pos-modal-body">' +
                    '<div style="text-align:center;margin-bottom:24px">' +
                        '<div style="font-size:14px;color:var(--text-muted);margin-bottom:4px">Invoice Total</div>' +
                        '<div style="font-size:36px;font-weight:800;color:#8b5cf6">' + money(total) + '</div>' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>Customer *</label>' +
                        '<select class="form-control" id="posInvCustomer">' + options + '</select>' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>Due Date</label>' +
                        '<input type="date" class="form-control" id="posInvDueDate" value="' + getDefaultDueDate() + '">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>Notes</label>' +
                        '<textarea class="form-control" id="posInvNotes" rows="3" placeholder="Optional notes..."></textarea>' +
                    '</div>' +
                    '<div id="posInvError" style="color:var(--danger);font-size:13px;margin-top:8px;text-align:center;display:none"></div>' +
                '</div>' +
                '<div class="pos-modal-footer">' +
                    '<button class="btn btn-sm btn-outline" onclick="closeModal()">Cancel</button>' +
                    '<button class="btn btn-sm btn-info" id="posInvConfirm" onclick="PosViews._regPayInvoice()">Create Invoice</button>' +
                '</div>'
            );
        }).catch(function () {
            openModal(
                '<div class="pos-modal-header">' +
                    '<h3>&#128196; Create Invoice</h3>' +
                    '<button class="pos-modal-close" onclick="closeModal()">&times;</button>' +
                '</div>' +
                '<div class="pos-modal-body">' +
                    '<div style="text-align:center;margin-bottom:24px">' +
                        '<div style="font-size:14px;color:var(--text-muted);margin-bottom:4px">Invoice Total</div>' +
                        '<div style="font-size:36px;font-weight:800;color:#8b5cf6">' + money(total) + '</div>' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>Customer *</label>' +
                        '<select class="form-control" id="posInvCustomer"><option value="">-- No customers found --</option></select>' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>Due Date</label>' +
                        '<input type="date" class="form-control" id="posInvDueDate" value="' + getDefaultDueDate() + '">' +
                    '</div>' +
                    '<div id="posInvError" style="color:var(--danger);font-size:13px;margin-top:8px;text-align:center;display:none"></div>' +
                '</div>' +
                '<div class="pos-modal-footer">' +
                    '<button class="btn btn-sm btn-outline" onclick="closeModal()">Cancel</button>' +
                    '<button class="btn btn-sm btn-info" id="posInvConfirm" onclick="PosViews._regPayInvoice()">Create Invoice</button>' +
                '</div>'
            );
        });
    }

    function getDefaultDueDate() {
        var d = new Date();
        d.setDate(d.getDate() + 30);
        return d.toISOString().split('T')[0];
    }

    function createOrderAndPay(method, extra) {
        var items = [];
        for (var i = 0; i < cart.length; i++) {
            items.push({
                item_id: cart[i].item_id,
                sku: cart[i].sku,
                name: cart[i].name,
                quantity: cart[i].qty,
                unit_price: cart[i].price
            });
        }

        var orderBody = {
            items: items,
            discount_amount: discountAmount,
            tax_rate: taxRate,
            notes: ''
        };

        return PosAPI.post('orders', orderBody).then(function (res) {
            var orderId = res.data && res.data.id;
            var orderNumber = res.data && res.data.order_number;
            if (!orderId) throw new Error('Order creation failed');

            var payBody = { method: method, amount: getTotal() };
            if (extra) {
                Object.keys(extra).forEach(function (k) { payBody[k] = extra[k]; });
            }

            return PosAPI.post('orders/' + orderId + '/pay', payBody).then(function (payRes) {
                return {
                    orderId: orderId,
                    orderNumber: orderNumber,
                    payment: payRes.data
                };
            });
        });
    }

    function showReceipt(result, method, extra) {
        var itemsHtml = '';
        for (var i = 0; i < cart.length; i++) {
            var c = cart[i];
            itemsHtml +=
                '<tr>' +
                    '<td style="padding:6px 8px;font-size:13px">' + esc(c.name) + '</td>' +
                    '<td style="padding:6px 8px;font-size:13px;text-align:center">' + c.qty + '</td>' +
                    '<td style="padding:6px 8px;font-size:13px;text-align:right">' + money(c.price * c.qty) + '</td>' +
                '</tr>';
        }

        var changeHtml = '';
        if (method === 'cash' && extra && extra.amount_tendered) {
            changeHtml =
                '<div style="display:flex;justify-content:space-between;padding:8px 0">' +
                    '<span>Tendered</span><span>' + money(extra.amount_tendered) + '</span>' +
                '</div>' +
                '<div style="display:flex;justify-content:space-between;padding:8px 0;font-weight:700;color:var(--success)">' +
                    '<span>Change</span><span>' + money(extra.change_due) + '</span>' +
                '</div>';
        }

        openModal(
            '<div class="pos-modal-header">' +
                '<h3>&#9989; Payment Complete</h3>' +
                '<button class="pos-modal-close" onclick="closeModal()">&times;</button>' +
            '</div>' +
            '<div class="pos-modal-body" style="text-align:center">' +
                '<div style="font-size:48px;margin-bottom:8px">&#127881;</div>' +
                '<div style="font-size:20px;font-weight:700;margin-bottom:4px">Order ' + esc(result.orderNumber) + '</div>' +
                '<div style="font-size:13px;color:var(--text-muted);margin-bottom:20px">' +
                    'Payment: ' + esc(method.charAt(0).toUpperCase() + method.slice(1)) +
                '</div>' +
                '<table style="width:100%;border-collapse:collapse;margin-bottom:16px">' +
                    '<thead><tr>' +
                        '<th style="text-align:left;padding:8px;font-size:12px;border-bottom:1px solid var(--border);color:var(--text-muted)">Item</th>' +
                        '<th style="text-align:center;padding:8px;font-size:12px;border-bottom:1px solid var(--border);color:var(--text-muted)">Qty</th>' +
                        '<th style="text-align:right;padding:8px;font-size:12px;border-bottom:1px solid var(--border);color:var(--text-muted)">Total</th>' +
                    '</tr></thead>' +
                    '<tbody>' + itemsHtml + '</tbody>' +
                '</table>' +
                '<div style="border-top:1px solid var(--border);padding-top:12px;text-align:left">' +
                    '<div style="display:flex;justify-content:space-between;padding:4px 0;font-size:13px;color:var(--text-secondary)">' +
                        '<span>Subtotal</span><span>' + money(getSubtotal()) + '</span>' +
                    '</div>' +
                    (discountAmount > 0 ?
                        '<div style="display:flex;justify-content:space-between;padding:4px 0;font-size:13px;color:var(--danger)">' +
                            '<span>Discount</span><span>-' + money(discountAmount) + '</span>' +
                        '</div>' : '') +
                    '<div style="display:flex;justify-content:space-between;padding:4px 0;font-size:13px;color:var(--text-secondary)">' +
                        '<span>Tax (' + taxRate.toFixed(1) + '%)</span><span>' + money(getTaxAmount()) + '</span>' +
                    '</div>' +
                    '<div style="display:flex;justify-content:space-between;padding:8px 0;font-size:18px;font-weight:700;border-top:1px solid var(--border);margin-top:8px">' +
                        '<span>Total</span><span>' + money(getTotal()) + '</span>' +
                    '</div>' +
                    changeHtml +
                '</div>' +
            '</div>' +
            '<div class="pos-modal-footer">' +
                '<button class="btn btn-sm btn-success" onclick="closeModal()">Done</button>' +
            '</div>'
        );

        clearCart();
    }

    // ── Exposed methods for onclick handlers ─────────────────────────────
    PosViews._regUpdateQty = function (id, delta) { updateQty(id, delta); };
    PosViews._regSetQty = function (id, val) { setQty(id, val); };
    PosViews._regRemove = function (id) { removeFromCart(id); };
    PosViews._regClear = clearCart;

    PosViews._regSetDiscount = function (val) {
        discountAmount = Math.max(0, parseFloat(val) || 0);
        renderCartTotals();
    };

    PosViews._regCalcChange = function () {
        var tendered = parseFloat(document.getElementById('posCashTendered').value) || 0;
        var total = getTotal();
        var change = Math.max(0, tendered - total);
        var changeEl = document.getElementById('posCashChange');
        if (changeEl) changeEl.textContent = money(change);
    };

    PosViews._regAddProduct = function (productId) {
        PosAPI.get('products/' + productId).then(function (res) {
            var product = res.data && res.data.product ? res.data.product : res.data;
            if (product) addToCart(product);
        }).catch(function (err) {
            console.error('Failed to add product:', err);
        });
    };

    PosViews._regPayCash = function () {
        var tendered = parseFloat(document.getElementById('posCashTendered').value) || 0;
        var total = getTotal();
        var errEl = document.getElementById('posCashError');
        var btn = document.getElementById('posCashConfirm');

        if (tendered < total) {
            errEl.textContent = 'Amount tendered must be at least ' + money(total);
            errEl.style.display = 'block';
            return;
        }

        errEl.style.display = 'none';
        btn.disabled = true;
        btn.textContent = 'Processing...';

        var changeDue = tendered - total;
        createOrderAndPay('cash', { amount_tendered: tendered, change_due: changeDue })
            .then(function (result) {
                showReceipt(result, 'cash', { amount_tendered: tendered, change_due: changeDue });
            })
            .catch(function (err) {
                errEl.textContent = err.message;
                errEl.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Confirm Payment';
            });
    };

    PosViews._regPayCard = function () {
        var errEl = document.getElementById('posCardError');
        var statusEl = document.getElementById('posCardStatus');
        var btn = document.getElementById('posCardConfirm');

        errEl.style.display = 'none';
        statusEl.style.display = 'block';
        btn.disabled = true;
        btn.textContent = 'Processing...';

        var extra = {
            last4: (document.getElementById('posCardLast4').value || '').trim(),
            card_type: (document.getElementById('posCardType').value || ''),
            approval_code: (document.getElementById('posCardApproval').value || '').trim()
        };

        createOrderAndPay('card', extra)
            .then(function (result) {
                showReceipt(result, 'card', extra);
            })
            .catch(function (err) {
                statusEl.style.display = 'none';
                errEl.textContent = err.message;
                errEl.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Process Card';
            });
    };

    PosViews._regPayInvoice = function () {
        var customerSelect = document.getElementById('posInvCustomer');
        var errEl = document.getElementById('posInvError');
        var btn = document.getElementById('posInvConfirm');

        if (!customerSelect.value) {
            errEl.textContent = 'Please select a customer.';
            errEl.style.display = 'block';
            return;
        }

        errEl.style.display = 'none';
        btn.disabled = true;
        btn.textContent = 'Creating...';

        var extra = {
            customer_id: parseInt(customerSelect.value, 10),
            due_date: (document.getElementById('posInvDueDate') || {}).value || getDefaultDueDate(),
            notes: (document.getElementById('posInvNotes') || {}).value || ''
        };

        createOrderAndPay('invoice', extra)
            .then(function (result) {
                showReceipt(result, 'invoice', extra);
            })
            .catch(function (err) {
                errEl.textContent = err.message;
                errEl.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Create Invoice';
            });
    };

    // ── Render the register ──────────────────────────────────────────────
    root.innerHTML = '' +
        '<style>' +
            '.pos-register { display: flex; gap: 0; height: calc(100vh - 130px); margin: -32px; }' +
            '.pos-register-left { flex: 0 0 60%; display: flex; flex-direction: column; border-right: 1px solid var(--border); }' +
            '.pos-register-right { flex: 0 0 40%; display: flex; flex-direction: column; background: var(--bg-secondary); }' +

            '.pos-search-wrapper { padding: 20px 24px; border-bottom: 1px solid var(--border); background: var(--bg-secondary); }' +
            '.pos-search-input { width: 100%; padding: 16px 20px; background: var(--bg-input); border: 2px solid var(--border); border-radius: var(--radius); color: var(--text-primary); font-size: 18px; font-weight: 500; outline: none; transition: var(--transition); }' +
            '.pos-search-input:focus { border-color: #8b5cf6; box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15); }' +
            '.pos-search-input::placeholder { color: var(--text-muted); font-weight: 400; }' +

            '#posSearchResults { flex: 1; overflow-y: auto; padding: 20px 24px; }' +
            '.pos-search-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; min-height: 200px; }' +

            '.pos-product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }' +
            '.pos-product-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 16px; cursor: pointer; transition: var(--transition); }' +
            '.pos-product-card:hover { border-color: #8b5cf6; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(139, 92, 246, 0.15); }' +
            '.pos-product-card.pos-out-of-stock { opacity: 0.5; pointer-events: none; }' +
            '.pos-product-name { font-size: 14px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px; line-height: 1.3; }' +
            '.pos-product-sku { font-size: 12px; color: var(--text-muted); margin-bottom: 10px; font-family: monospace; }' +
            '.pos-product-meta { display: flex; align-items: center; justify-content: space-between; }' +
            '.pos-product-price { font-size: 16px; font-weight: 700; color: #8b5cf6; }' +
            '.pos-product-stock { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 4px; }' +
            '.pos-product-stock.in { background: rgba(16, 185, 129, 0.12); color: var(--success); }' +
            '.pos-product-stock.out { background: rgba(239, 68, 68, 0.12); color: var(--danger); }' +

            '.pos-scan-success { display: flex; align-items: center; gap: 12px; justify-content: center; padding: 24px; font-size: 16px; font-weight: 600; color: var(--success); background: rgba(16, 185, 129, 0.08); border-radius: var(--radius-sm); animation: pos-fade-in 0.3s; }' +
            '@keyframes pos-fade-in { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }' +

            '.pos-cart-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }' +
            '.pos-cart-header h3 { font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px; }' +
            '.pos-cart-badge { background: #8b5cf6; color: #fff; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; min-width: 22px; text-align: center; transition: transform 0.2s; }' +
            '.pos-flash { animation: pos-badge-flash 0.3s; }' +
            '@keyframes pos-badge-flash { 0% { transform: scale(1); } 50% { transform: scale(1.4); } 100% { transform: scale(1); } }' +

            '#posCartItems { flex: 1; overflow-y: auto; padding: 0; }' +
            '.pos-cart-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; min-height: 200px; color: var(--text-muted); font-size: 14px; }' +

            '.pos-cart-item { display: flex; align-items: center; gap: 10px; padding: 12px 20px; border-bottom: 1px solid rgba(42, 42, 74, 0.3); transition: background 0.2s; }' +
            '.pos-cart-item:hover { background: var(--bg-card); }' +
            '.pos-cart-item-info { flex: 1; min-width: 0; }' +
            '.pos-cart-item-name { font-size: 13px; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }' +
            '.pos-cart-item-sku { font-size: 11px; color: var(--text-muted); }' +
            '.pos-cart-item-qty { display: flex; align-items: center; gap: 4px; }' +
            '.pos-qty-btn { width: 28px; height: 28px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-primary); font-size: 16px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: var(--transition); }' +
            '.pos-qty-btn:hover { border-color: #8b5cf6; color: #8b5cf6; }' +
            '.pos-qty-input { width: 44px; text-align: center; padding: 4px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-input); color: var(--text-primary); font-size: 14px; font-weight: 600; outline: none; }' +
            '.pos-qty-input:focus { border-color: #8b5cf6; }' +
            '.pos-cart-item-total { font-size: 14px; font-weight: 700; color: var(--text-primary); min-width: 70px; text-align: right; }' +
            '.pos-cart-item-remove { background: none; border: none; color: var(--text-muted); font-size: 18px; cursor: pointer; padding: 4px 6px; border-radius: 4px; transition: var(--transition); }' +
            '.pos-cart-item-remove:hover { background: rgba(239, 68, 68, 0.12); color: var(--danger); }' +

            '.pos-cart-totals { padding: 16px 20px; border-top: 1px solid var(--border); background: var(--bg-card); }' +
            '.pos-totals-row { display: flex; align-items: center; justify-content: space-between; padding: 5px 0; font-size: 13px; color: var(--text-secondary); }' +
            '.pos-totals-grand { font-size: 20px; font-weight: 800; color: var(--text-primary); border-top: 1px solid var(--border); margin-top: 8px; padding-top: 12px; }' +
            '.pos-discount-input { width: 72px; text-align: right; padding: 4px 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg-input); color: var(--text-primary); font-size: 13px; outline: none; }' +
            '.pos-discount-input:focus { border-color: #8b5cf6; }' +

            '.pos-cart-actions { padding: 16px 20px; border-top: 1px solid var(--border); display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }' +
            '.pos-pay-btn { padding: 14px 8px; border: none; border-radius: var(--radius-sm); font-size: 14px; font-weight: 700; cursor: pointer; transition: var(--transition); display: flex; align-items: center; justify-content: center; gap: 6px; }' +
            '.pos-pay-btn:hover { transform: translateY(-1px); }' +
            '.pos-pay-btn:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }' +
            '.pos-pay-cash { background: linear-gradient(135deg, #10b981, #059669); color: #fff; }' +
            '.pos-pay-cash:hover:not(:disabled) { box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4); }' +
            '.pos-pay-card { background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; }' +
            '.pos-pay-card:hover:not(:disabled) { box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4); }' +
            '.pos-pay-invoice { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: #fff; }' +
            '.pos-pay-invoice:hover:not(:disabled) { box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4); }' +
            '.pos-pay-clear { background: var(--bg-input); color: var(--text-secondary); border: 1px solid var(--border); }' +
            '.pos-pay-clear:hover:not(:disabled) { border-color: var(--danger); color: var(--danger); }' +

            '@media (max-width: 1024px) {' +
                '.pos-register { flex-direction: column; height: auto; margin: -16px; }' +
                '.pos-register-left { flex: none; border-right: none; border-bottom: 1px solid var(--border); }' +
                '.pos-register-right { flex: none; }' +
                '#posSearchResults { min-height: 200px; max-height: 40vh; }' +
                '.pos-product-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); }' +
            '}' +
            '@media (max-width: 640px) {' +
                '.pos-cart-actions { grid-template-columns: 1fr 1fr; }' +
                '.pos-product-grid { grid-template-columns: 1fr 1fr; }' +
                '.pos-cart-item { padding: 10px 12px; }' +
            '}' +
        '</style>' +

        '<div class="pos-register">' +
            '<!-- Left: Search & Products -->' +
            '<div class="pos-register-left">' +
                '<div class="pos-search-wrapper">' +
                    '<input type="text" class="pos-search-input" id="posSearchInput" ' +
                        'placeholder="Scan barcode or search by SKU / name..." autocomplete="off" autofocus>' +
                '</div>' +
                '<div id="posSearchResults">' +
                    '<div class="pos-search-placeholder">' +
                        '<div style="font-size:56px;opacity:0.15;margin-bottom:16px">&#128270;</div>' +
                        '<p style="font-size:15px;color:var(--text-muted)">Search for products by name, SKU, or scan a barcode</p>' +
                    '</div>' +
                '</div>' +
            '</div>' +

            '<!-- Right: Cart & Payment -->' +
            '<div class="pos-register-right">' +
                '<div class="pos-cart-header">' +
                    '<h3>&#128722; Cart <span class="pos-cart-badge" id="posCartCount">0</span></h3>' +
                '</div>' +
                '<div id="posCartItems">' +
                    '<div class="pos-cart-empty">' +
                        '<div style="font-size:48px;opacity:0.2;margin-bottom:12px">&#128722;</div>' +
                        '<p>Cart is empty</p>' +
                        '<p style="font-size:12px;margin-top:4px">Scan a barcode or search for a product</p>' +
                    '</div>' +
                '</div>' +
                '<div class="pos-cart-totals" id="posCartTotals"></div>' +
                '<div class="pos-cart-actions">' +
                    '<button class="pos-pay-btn pos-pay-cash" onclick="PosViews._regPayCashOpen()">&#128181; Cash</button>' +
                    '<button class="pos-pay-btn pos-pay-card" onclick="PosViews._regPayCardOpen()">&#128179; Card</button>' +
                    '<button class="pos-pay-btn pos-pay-invoice" onclick="PosViews._regPayInvoiceOpen()">&#128196; Invoice</button>' +
                    '<button class="pos-pay-btn pos-pay-clear" onclick="PosViews._regClear()">&#128465; Clear</button>' +
                '</div>' +
            '</div>' +
        '</div>';

    // Wire up modal openers
    PosViews._regPayCashOpen = openCashModal;
    PosViews._regPayCardOpen = openCardModal;
    PosViews._regPayInvoiceOpen = openInvoiceModal;

    // Wire up search input
    var searchInput = document.getElementById('posSearchInput');
    if (searchInput) {
        searchInput.addEventListener('keydown', handleSearchKeydown);
        searchInput.focus();
    }

    // Initial cart render
    renderCart();
    fetchTaxRate();
};
