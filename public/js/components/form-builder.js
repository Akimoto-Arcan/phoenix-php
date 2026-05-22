export class FormBuilder {
    constructor({ fields = [], values = {}, onSubmit, submitLabel = 'Submit' } = {}) {
        this.fields = fields;
        this.values = { ...values };
        this.onSubmit = onSubmit;
        this.submitLabel = submitLabel;
        this._errors = {};
        this._inputs = {};
        this.el = this._build();
    }

    render() {
        return this.el;
    }

    getData() {
        const data = {};
        this.fields.forEach(f => {
            const input = this._inputs[f.name];
            if (!input) return;
            if (f.type === 'checkbox') data[f.name] = input.checked;
            else if (f.type === 'number') data[f.name] = input.value === '' ? null : Number(input.value);
            else data[f.name] = input.value;
        });
        return data;
    }

    setData(values) {
        this.values = { ...values };
        this.fields.forEach(f => {
            const input = this._inputs[f.name];
            if (!input) return;
            if (f.type === 'checkbox') input.checked = !!values[f.name];
            else input.value = values[f.name] ?? '';
        });
        this._clearErrors();
    }

    validate() {
        this._clearErrors();
        let valid = true;

        this.fields.forEach(f => {
            const input = this._inputs[f.name];
            if (!input) return;
            const val = f.type === 'checkbox' ? input.checked : input.value.trim();

            if (f.required && (val === '' || val === false)) {
                this._showError(f.name, `${f.label} is required`);
                valid = false;
                return;
            }

            if (f.type === 'email' && val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
                this._showError(f.name, 'Invalid email address');
                valid = false;
                return;
            }

            if (f.type === 'number' && val !== '') {
                const num = Number(val);
                if (f.min !== undefined && num < f.min) {
                    this._showError(f.name, `Minimum value is ${f.min}`);
                    valid = false;
                } else if (f.max !== undefined && num > f.max) {
                    this._showError(f.name, `Maximum value is ${f.max}`);
                    valid = false;
                }
            }
        });

        return valid;
    }

    reset() {
        this.fields.forEach(f => {
            const input = this._inputs[f.name];
            if (!input) return;
            if (f.type === 'checkbox') input.checked = false;
            else input.value = '';
        });
        this._clearErrors();
    }

    _build() {
        const form = document.createElement('form');
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (this.validate() && this.onSubmit) {
                this.onSubmit(this.getData());
            }
        });

        this.fields.forEach(f => {
            const group = document.createElement('div');
            group.className = 'form-group';

            if (f.type === 'checkbox') {
                group.style.cssText = 'display:flex;align-items:center;gap:10px';
                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.name = f.name;
                cb.id = `fb-${f.name}`;
                cb.checked = !!this.values[f.name];
                cb.style.cssText = 'width:18px;height:18px;accent-color:var(--accent)';
                this._inputs[f.name] = cb;

                const label = document.createElement('label');
                label.htmlFor = cb.id;
                label.textContent = f.label;
                label.style.cssText = 'margin:0;text-transform:none;letter-spacing:0;font-size:14px;cursor:pointer';

                group.append(cb, label);
            } else {
                const label = document.createElement('label');
                label.htmlFor = `fb-${f.name}`;
                label.textContent = f.label + (f.required ? ' *' : '');
                group.appendChild(label);

                let input;
                if (f.type === 'select') {
                    input = document.createElement('select');
                    input.className = 'form-control';
                    if (f.placeholder) {
                        const opt = document.createElement('option');
                        opt.value = '';
                        opt.textContent = f.placeholder;
                        opt.disabled = true;
                        opt.selected = !this.values[f.name];
                        input.appendChild(opt);
                    }
                    (f.options || []).forEach(o => {
                        const opt = document.createElement('option');
                        if (typeof o === 'object') {
                            opt.value = o.value;
                            opt.textContent = o.label;
                        } else {
                            opt.value = o;
                            opt.textContent = o;
                        }
                        if (String(this.values[f.name]) === String(opt.value)) opt.selected = true;
                        input.appendChild(opt);
                    });
                } else if (f.type === 'textarea') {
                    input = document.createElement('textarea');
                    input.className = 'form-control';
                    input.rows = 4;
                    input.value = this.values[f.name] ?? '';
                    input.style.resize = 'vertical';
                } else {
                    input = document.createElement('input');
                    input.type = f.type || 'text';
                    input.className = 'form-control';
                    input.value = this.values[f.name] ?? '';
                }

                input.id = `fb-${f.name}`;
                input.name = f.name;
                if (f.placeholder && f.type !== 'select') input.placeholder = f.placeholder;
                if (f.required) input.required = true;
                if (f.min !== undefined) input.min = f.min;
                if (f.max !== undefined) input.max = f.max;

                this._inputs[f.name] = input;
                group.appendChild(input);
            }

            // Error placeholder
            const errorEl = document.createElement('div');
            errorEl.className = 'fb-error';
            errorEl.dataset.field = f.name;
            errorEl.style.cssText = 'font-size:12px;color:var(--danger);margin-top:4px;display:none';
            group.appendChild(errorEl);

            // Help text
            if (f.helpText) {
                const help = document.createElement('div');
                help.style.cssText = 'font-size:12px;color:var(--text-muted);margin-top:4px';
                help.textContent = f.helpText;
                group.appendChild(help);
            }

            form.appendChild(group);
        });

        // Submit button
        const submitBtn = document.createElement('button');
        submitBtn.type = 'submit';
        submitBtn.className = 'btn btn-primary';
        submitBtn.style.marginTop = '8px';
        submitBtn.textContent = this.submitLabel;
        form.appendChild(submitBtn);

        return form;
    }

    _showError(name, msg) {
        const el = this.el.querySelector(`.fb-error[data-field="${name}"]`);
        if (el) {
            el.textContent = msg;
            el.style.display = 'block';
        }
        const input = this._inputs[name];
        if (input) input.style.borderColor = 'var(--danger)';
        this._errors[name] = msg;
    }

    _clearErrors() {
        this._errors = {};
        this.el.querySelectorAll('.fb-error').forEach(el => {
            el.textContent = '';
            el.style.display = 'none';
        });
        Object.values(this._inputs).forEach(input => {
            input.style.borderColor = '';
        });
    }
}
