const _stack = [];

function _injectStyles() {
    if (document.getElementById('phoenix-modal-styles')) return;
    const style = document.createElement('style');
    style.id = 'phoenix-modal-styles';
    style.textContent = `
        .phoenix-modal-backdrop {
            position: fixed; inset: 0; z-index: 9000;
            background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
            display: flex; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.2s ease;
        }
        .phoenix-modal-backdrop.visible { opacity: 1; }
        .phoenix-modal {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius); box-shadow: var(--shadow);
            width: 100%; max-height: 85vh; display: flex; flex-direction: column;
            transform: translateY(20px) scale(0.97); transition: transform 0.2s ease;
        }
        .phoenix-modal-backdrop.visible .phoenix-modal { transform: translateY(0) scale(1); }
        .phoenix-modal.sm { max-width: 400px; }
        .phoenix-modal.md { max-width: 600px; }
        .phoenix-modal.lg { max-width: 800px; }
        .phoenix-modal-header {
            padding: 20px 24px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .phoenix-modal-header h3 { font-size: 16px; font-weight: 700; margin: 0; }
        .phoenix-modal-close {
            width: 32px; height: 32px; border-radius: 8px; border: none;
            background: transparent; color: var(--text-muted); font-size: 18px;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: var(--transition);
        }
        .phoenix-modal-close:hover { background: var(--bg-card-hover); color: var(--text-primary); }
        .phoenix-modal-body { padding: 24px; overflow-y: auto; flex: 1; }
        .phoenix-modal-footer {
            padding: 16px 24px; border-top: 1px solid var(--border);
            display: flex; align-items: center; justify-content: flex-end; gap: 10px;
        }
    `;
    document.head.appendChild(style);
}

export class Modal {
    static open({ title = '', body = '', footer = '', size = 'md', onClose } = {}) {
        _injectStyles();

        const backdrop = document.createElement('div');
        backdrop.className = 'phoenix-modal-backdrop';
        const zBase = 9000 + _stack.length * 10;
        backdrop.style.zIndex = zBase;

        const sizeClass = { sm: 'sm', md: 'md', lg: 'lg' }[size] || 'md';

        const modal = document.createElement('div');
        modal.className = `phoenix-modal ${sizeClass}`;

        // Header
        const header = document.createElement('div');
        header.className = 'phoenix-modal-header';
        header.innerHTML = `<h3>${title}</h3>`;
        const closeBtn = document.createElement('button');
        closeBtn.className = 'phoenix-modal-close';
        closeBtn.innerHTML = '&times;';
        closeBtn.addEventListener('click', () => Modal.close(backdrop));
        header.appendChild(closeBtn);
        modal.appendChild(header);

        // Body
        const bodyEl = document.createElement('div');
        bodyEl.className = 'phoenix-modal-body';
        if (typeof body === 'string') bodyEl.innerHTML = body;
        else if (body instanceof HTMLElement) bodyEl.appendChild(body);
        modal.appendChild(bodyEl);

        // Footer
        if (footer) {
            const footerEl = document.createElement('div');
            footerEl.className = 'phoenix-modal-footer';
            if (typeof footer === 'string') footerEl.innerHTML = footer;
            else if (footer instanceof HTMLElement) footerEl.appendChild(footer);
            modal.appendChild(footerEl);
        }

        backdrop.appendChild(modal);
        backdrop._onClose = onClose;

        // Close on backdrop click
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) Modal.close(backdrop);
        });

        // ESC key
        backdrop._escHandler = (e) => {
            if (e.key === 'Escape' && _stack[_stack.length - 1] === backdrop) {
                Modal.close(backdrop);
            }
        };
        document.addEventListener('keydown', backdrop._escHandler);

        _stack.push(backdrop);
        document.body.appendChild(backdrop);
        requestAnimationFrame(() => backdrop.classList.add('visible'));

        return backdrop;
    }

    static confirm({ title = 'Confirm', message = '', confirmText = 'Confirm', cancelText = 'Cancel', onConfirm, variant = '' } = {}) {
        const footer = document.createElement('div');
        footer.style.cssText = 'display:flex;gap:10px;width:100%;justify-content:flex-end';

        const cancelBtn = document.createElement('button');
        cancelBtn.className = 'btn btn-sm btn-outline';
        cancelBtn.textContent = cancelText;

        const confirmBtn = document.createElement('button');
        confirmBtn.className = `btn btn-sm ${variant === 'danger' ? 'btn-danger' : 'btn-primary'}`;
        confirmBtn.textContent = confirmText;

        footer.append(cancelBtn, confirmBtn);

        const modal = Modal.open({ title, body: `<p style="color:var(--text-secondary);font-size:14px;margin:0">${message}</p>`, footer, size: 'sm' });

        cancelBtn.addEventListener('click', () => Modal.close(modal));
        confirmBtn.addEventListener('click', () => {
            Modal.close(modal);
            if (onConfirm) onConfirm();
        });

        return modal;
    }

    static close(modal) {
        if (!modal) return;
        modal.classList.remove('visible');
        document.removeEventListener('keydown', modal._escHandler);
        const idx = _stack.indexOf(modal);
        if (idx !== -1) _stack.splice(idx, 1);
        setTimeout(() => {
            modal.remove();
            if (modal._onClose) modal._onClose();
        }, 200);
    }

    static closeAll() {
        [..._stack].forEach(m => Modal.close(m));
    }
}
