/**
 * Point of Sale — Settings View
 * PhoenixPHP Module — CDAC Programming
 *
 * Tax rates, payment gateway selector, register management.
 */
window.PosViews = window.PosViews || {};

PosViews.settings = function (root) {
    'use strict';

    function esc(s) {
        if (s === null || s === undefined) return '';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    root.innerHTML = '<div class="pos-loading"><div class="pos-spinner"></div> Loading settings...</div>';

    Promise.all([
        PosAPI.get('tax-rates'),
        PosAPI.get('registers'),
        PosAPI.get('settings').catch(function () { return { data: {} }; })
    ]).then(function (results) {
        var taxRates = (results[0].data && results[0].data.rates) || [];
        var registers = (results[1].data && results[1].data.registers) || [];
        var settings = results[2].data || {};

        renderSettings(taxRates, registers, settings);
    }).catch(function (err) {
        root.innerHTML = '<div class="pos-empty"><div class="icon">&#9888;</div><h3>Error</h3><p>' + esc(err.message) + '</p></div>';
    });

    function renderTaxRatesTable(taxRates) {
        var tbody = document.getElementById('posSettingsTaxBody');
        if (!tbody) return;

        if (taxRates.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:24px;color:var(--text-muted)">No tax rates configured.</td></tr>';
            return;
        }

        var html = '';
        for (var i = 0; i < taxRates.length; i++) {
            var r = taxRates[i];
            html +=
                '<tr>' +
                    '<td><strong>' + esc(r.name) + '</strong></td>' +
                    '<td style="text-align:right">' + parseFloat(r.rate).toFixed(3) + '%</td>' +
                    '<td style="text-align:center">' +
                        (r.is_default ? '<span class="badge badge-success">Default</span>' : '<span class="badge badge-outline">No</span>') +
                    '</td>' +
                    '<td style="text-align:center">' +
                        (r.is_active ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>') +
                    '</td>' +
                    '<td>' +
                        '<button class="btn btn-sm btn-outline" onclick="PosViews._settingsEditTax(' + r.id + ', \'' + esc(r.name) + '\', ' + r.rate + ', ' + (r.is_default ? 1 : 0) + ')" style="padding:4px 10px;font-size:12px">Edit</button>' +
                    '</td>' +
                '</tr>';
        }
        tbody.innerHTML = html;
    }

    function renderRegistersTable(registers) {
        var tbody = document.getElementById('posSettingsRegBody');
        if (!tbody) return;

        if (registers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:24px;color:var(--text-muted)">No registers configured.</td></tr>';
            return;
        }

        var html = '';
        for (var i = 0; i < registers.length; i++) {
            var reg = registers[i];
            html +=
                '<tr>' +
                    '<td><strong>' + esc(reg.name) + '</strong></td>' +
                    '<td>' + esc(reg.location || '-') + '</td>' +
                    '<td style="text-align:center">' +
                        (reg.status === 'active' ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>') +
                    '</td>' +
                    '<td>' +
                        '<button class="btn btn-sm btn-outline" onclick="PosViews._settingsEditReg(' + reg.id + ', \'' + esc(reg.name) + '\', \'' + esc(reg.location || '') + '\', \'' + esc(reg.status) + '\')" style="padding:4px 10px;font-size:12px">Edit</button>' +
                    '</td>' +
                '</tr>';
        }
        tbody.innerHTML = html;
    }

    // Tax Rate CRUD
    PosViews._settingsAddTax = function () {
        openModal(
            '<div class="pos-modal-header">' +
                '<h3>Add Tax Rate</h3>' +
                '<button class="pos-modal-close" onclick="closeModal()">&times;</button>' +
            '</div>' +
            '<div class="pos-modal-body">' +
                '<div class="form-group">' +
                    '<label>Name *</label>' +
                    '<input type="text" class="form-control" id="taxName" placeholder="e.g. Standard, Reduced">' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Rate (%) *</label>' +
                    '<input type="number" class="form-control" id="taxRate" step="0.001" min="0" placeholder="7.000">' +
                '</div>' +
                '<div class="form-group">' +
                    '<label style="display:flex;align-items:center;gap:8px;cursor:pointer">' +
                        '<input type="checkbox" id="taxDefault"> Set as default rate' +
                    '</label>' +
                '</div>' +
                '<div id="taxFormError" style="color:var(--danger);font-size:13px;display:none"></div>' +
            '</div>' +
            '<div class="pos-modal-footer">' +
                '<button class="btn btn-sm btn-outline" onclick="closeModal()">Cancel</button>' +
                '<button class="btn btn-sm btn-success" id="taxFormSubmit" onclick="PosViews._settingsSubmitTax(0)">Create</button>' +
            '</div>'
        );
    };

    PosViews._settingsEditTax = function (id, name, rate, isDefault) {
        openModal(
            '<div class="pos-modal-header">' +
                '<h3>Edit Tax Rate</h3>' +
                '<button class="pos-modal-close" onclick="closeModal()">&times;</button>' +
            '</div>' +
            '<div class="pos-modal-body">' +
                '<div class="form-group">' +
                    '<label>Name *</label>' +
                    '<input type="text" class="form-control" id="taxName" value="' + esc(name) + '">' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Rate (%) *</label>' +
                    '<input type="number" class="form-control" id="taxRate" step="0.001" min="0" value="' + rate + '">' +
                '</div>' +
                '<div class="form-group">' +
                    '<label style="display:flex;align-items:center;gap:8px;cursor:pointer">' +
                        '<input type="checkbox" id="taxDefault" ' + (isDefault ? 'checked' : '') + '> Set as default rate' +
                    '</label>' +
                '</div>' +
                '<div id="taxFormError" style="color:var(--danger);font-size:13px;display:none"></div>' +
            '</div>' +
            '<div class="pos-modal-footer">' +
                '<button class="btn btn-sm btn-outline" onclick="closeModal()">Cancel</button>' +
                '<button class="btn btn-sm btn-success" id="taxFormSubmit" onclick="PosViews._settingsSubmitTax(' + id + ')">Update</button>' +
            '</div>'
        );
    };

    PosViews._settingsSubmitTax = function (id) {
        var name = (document.getElementById('taxName').value || '').trim();
        var rate = parseFloat(document.getElementById('taxRate').value);
        var isDefault = document.getElementById('taxDefault').checked ? 1 : 0;
        var errEl = document.getElementById('taxFormError');
        var btn = document.getElementById('taxFormSubmit');

        if (!name || isNaN(rate)) {
            errEl.textContent = 'Name and rate are required.';
            errEl.style.display = 'block';
            return;
        }

        errEl.style.display = 'none';
        btn.disabled = true;
        btn.textContent = 'Saving...';

        var body = { name: name, rate: rate, is_default: isDefault };
        var promise = id ? PosAPI.put('tax-rates/' + id, body) : PosAPI.post('tax-rates', body);

        promise.then(function () {
            closeModal();
            // Reload tax rates
            return PosAPI.get('tax-rates');
        }).then(function (res) {
            var rates = (res.data && res.data.rates) || [];
            renderTaxRatesTable(rates);
        }).catch(function (err) {
            errEl.textContent = err.message;
            errEl.style.display = 'block';
            btn.disabled = false;
            btn.textContent = id ? 'Update' : 'Create';
        });
    };

    // Register CRUD
    PosViews._settingsAddReg = function () {
        openModal(
            '<div class="pos-modal-header">' +
                '<h3>Add Register</h3>' +
                '<button class="pos-modal-close" onclick="closeModal()">&times;</button>' +
            '</div>' +
            '<div class="pos-modal-body">' +
                '<div class="form-group">' +
                    '<label>Name *</label>' +
                    '<input type="text" class="form-control" id="regName" placeholder="e.g. Register 2">' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Location</label>' +
                    '<input type="text" class="form-control" id="regLocation" placeholder="e.g. Front Counter">' +
                '</div>' +
                '<div id="regFormError" style="color:var(--danger);font-size:13px;display:none"></div>' +
            '</div>' +
            '<div class="pos-modal-footer">' +
                '<button class="btn btn-sm btn-outline" onclick="closeModal()">Cancel</button>' +
                '<button class="btn btn-sm btn-success" id="regFormSubmit" onclick="PosViews._settingsSubmitReg(0)">Create</button>' +
            '</div>'
        );
    };

    PosViews._settingsEditReg = function (id, name, location, status) {
        openModal(
            '<div class="pos-modal-header">' +
                '<h3>Edit Register</h3>' +
                '<button class="pos-modal-close" onclick="closeModal()">&times;</button>' +
            '</div>' +
            '<div class="pos-modal-body">' +
                '<div class="form-group">' +
                    '<label>Name *</label>' +
                    '<input type="text" class="form-control" id="regName" value="' + esc(name) + '">' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Location</label>' +
                    '<input type="text" class="form-control" id="regLocation" value="' + esc(location) + '">' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Status</label>' +
                    '<select class="form-control" id="regStatus">' +
                        '<option value="active"' + (status === 'active' ? ' selected' : '') + '>Active</option>' +
                        '<option value="inactive"' + (status === 'inactive' ? ' selected' : '') + '>Inactive</option>' +
                    '</select>' +
                '</div>' +
                '<div id="regFormError" style="color:var(--danger);font-size:13px;display:none"></div>' +
            '</div>' +
            '<div class="pos-modal-footer">' +
                '<button class="btn btn-sm btn-outline" onclick="closeModal()">Cancel</button>' +
                '<button class="btn btn-sm btn-success" id="regFormSubmit" onclick="PosViews._settingsSubmitReg(' + id + ')">Update</button>' +
            '</div>'
        );
    };

    PosViews._settingsSubmitReg = function (id) {
        var name = (document.getElementById('regName').value || '').trim();
        var location = (document.getElementById('regLocation').value || '').trim();
        var statusEl = document.getElementById('regStatus');
        var status = statusEl ? statusEl.value : 'active';
        var errEl = document.getElementById('regFormError');
        var btn = document.getElementById('regFormSubmit');

        if (!name) {
            errEl.textContent = 'Name is required.';
            errEl.style.display = 'block';
            return;
        }

        errEl.style.display = 'none';
        btn.disabled = true;
        btn.textContent = 'Saving...';

        var body = { name: name, location: location || null, status: status };
        var promise = id ? PosAPI.put('registers/' + id, body) : PosAPI.post('registers', body);

        promise.then(function () {
            closeModal();
            return PosAPI.get('registers');
        }).then(function (res) {
            var regs = (res.data && res.data.registers) || [];
            renderRegistersTable(regs);
        }).catch(function (err) {
            errEl.textContent = err.message;
            errEl.style.display = 'block';
            btn.disabled = false;
            btn.textContent = id ? 'Update' : 'Create';
        });
    };

    function renderSettings(taxRates, registers, settings) {
        var currentGateway = settings.payment_gateway || 'manual';

        root.innerHTML = '' +
            '<div style="margin-bottom:32px">' +
                '<h1 style="font-size:28px;font-weight:800;margin-bottom:4px">POS Settings</h1>' +
                '<p style="color:var(--text-muted);font-size:14px">Configure tax rates, payment gateways, and registers</p>' +
            '</div>' +

            '<!-- Tax Rates -->' +
            '<div class="card" style="margin-bottom:24px">' +
                '<div class="card-header">' +
                    '<h3>Tax Rates</h3>' +
                    '<button class="btn btn-sm btn-success" onclick="PosViews._settingsAddTax()">+ Add Rate</button>' +
                '</div>' +
                '<div class="card-body" style="padding:0">' +
                    '<div class="table-wrapper">' +
                        '<table class="phoenix-table">' +
                            '<thead><tr>' +
                                '<th>Name</th>' +
                                '<th style="text-align:right">Rate</th>' +
                                '<th style="text-align:center">Default</th>' +
                                '<th style="text-align:center">Status</th>' +
                                '<th></th>' +
                            '</tr></thead>' +
                            '<tbody id="posSettingsTaxBody"></tbody>' +
                        '</table>' +
                    '</div>' +
                '</div>' +
            '</div>' +

            '<!-- Payment Gateway -->' +
            '<div class="card" style="margin-bottom:24px">' +
                '<div class="card-header">' +
                    '<h3>Payment Gateway</h3>' +
                '</div>' +
                '<div class="card-body">' +
                    '<div class="form-group" style="max-width:400px">' +
                        '<label>Active Gateway</label>' +
                        '<select class="form-control" id="posGatewaySelect" onchange="PosViews._settingsSaveGateway()">' +
                            '<option value="manual"' + (currentGateway === 'manual' ? ' selected' : '') + '>Manual Card Entry (External Terminal)</option>' +
                            '<option value="stripe"' + (currentGateway === 'stripe' ? ' selected' : '') + '>Stripe</option>' +
                            '<option value="square"' + (currentGateway === 'square' ? ' selected' : '') + '>Square</option>' +
                        '</select>' +
                    '</div>' +
                    '<p style="font-size:13px;color:var(--text-muted);margin-top:8px">' +
                        'The Manual Card Entry gateway is used when payments are processed on an external card terminal. ' +
                        'The operator confirms the transaction and enters the approval code.' +
                    '</p>' +
                    '<div id="gatewayStatus" style="margin-top:12px"></div>' +
                '</div>' +
            '</div>' +

            '<!-- Registers -->' +
            '<div class="card">' +
                '<div class="card-header">' +
                    '<h3>Registers</h3>' +
                    '<button class="btn btn-sm btn-success" onclick="PosViews._settingsAddReg()">+ Add Register</button>' +
                '</div>' +
                '<div class="card-body" style="padding:0">' +
                    '<div class="table-wrapper">' +
                        '<table class="phoenix-table">' +
                            '<thead><tr>' +
                                '<th>Name</th>' +
                                '<th>Location</th>' +
                                '<th style="text-align:center">Status</th>' +
                                '<th></th>' +
                            '</tr></thead>' +
                            '<tbody id="posSettingsRegBody"></tbody>' +
                        '</table>' +
                    '</div>' +
                '</div>' +
            '</div>';

        renderTaxRatesTable(taxRates);
        renderRegistersTable(registers);
    }

    PosViews._settingsSaveGateway = function () {
        var gateway = (document.getElementById('posGatewaySelect') || {}).value || 'manual';
        var statusEl = document.getElementById('gatewayStatus');

        PosAPI.put('settings', { payment_gateway: gateway })
            .then(function () {
                if (statusEl) statusEl.innerHTML = '<span class="badge badge-success">Gateway updated to ' + esc(gateway) + '</span>';
                setTimeout(function () { if (statusEl) statusEl.innerHTML = ''; }, 3000);
            })
            .catch(function (err) {
                if (statusEl) statusEl.innerHTML = '<span class="badge badge-danger">' + esc(err.message) + '</span>';
            });
    };
};
