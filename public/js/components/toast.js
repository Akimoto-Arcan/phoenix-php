function _getContainer() {
    let container = document.getElementById('phoenix-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'phoenix-toast-container';
        Object.assign(container.style, {
            position: 'fixed', bottom: '24px', right: '24px', zIndex: '10000',
            display: 'flex', flexDirection: 'column-reverse', gap: '10px',
            pointerEvents: 'none', maxWidth: '380px'
        });
        document.body.appendChild(container);
    }
    return container;
}

function _show(message, { duration = 3000, variant = 'info' } = {}) {
    const container = _getContainer();

    const colors = {
        success: { bg: 'var(--success)', icon: '&#10003;' },
        error:   { bg: 'var(--danger)',  icon: '&#10007;' },
        info:    { bg: 'var(--info)',    icon: '&#8505;' },
        warning: { bg: 'var(--warning)', icon: '&#9888;' }
    };
    const c = colors[variant] || colors.info;

    const el = document.createElement('div');
    Object.assign(el.style, {
        display: 'flex', alignItems: 'center', gap: '10px',
        background: 'var(--bg-card)', border: '1px solid var(--border)',
        borderRadius: 'var(--radius-sm)', padding: '14px 18px',
        boxShadow: 'var(--shadow)', pointerEvents: 'auto',
        transform: 'translateX(120%)', transition: 'transform 0.3s cubic-bezier(0.4,0,0.2,1), opacity 0.3s ease',
        opacity: '0', fontSize: '14px', color: 'var(--text-primary)', maxWidth: '100%'
    });

    const dot = document.createElement('span');
    Object.assign(dot.style, {
        width: '8px', height: '8px', borderRadius: '50%',
        background: c.bg, flexShrink: '0',
        boxShadow: `0 0 8px ${c.bg}`
    });

    const text = document.createElement('span');
    text.style.flex = '1';
    text.textContent = message;

    const closeBtn = document.createElement('button');
    Object.assign(closeBtn.style, {
        background: 'none', border: 'none', color: 'var(--text-muted)',
        cursor: 'pointer', fontSize: '16px', padding: '0 0 0 8px', lineHeight: '1'
    });
    closeBtn.innerHTML = '&times;';
    closeBtn.addEventListener('click', () => dismiss(el));

    el.append(dot, text, closeBtn);
    container.appendChild(el);

    requestAnimationFrame(() => {
        el.style.transform = 'translateX(0)';
        el.style.opacity = '1';
    });

    const timer = setTimeout(() => dismiss(el), duration);
    el._timer = timer;

    return el;
}

function dismiss(el) {
    clearTimeout(el._timer);
    el.style.transform = 'translateX(120%)';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 300);
}

export const toast = {
    success(message, duration = 3000) { return _show(message, { duration, variant: 'success' }); },
    error(message, duration = 5000)   { return _show(message, { duration, variant: 'error' }); },
    info(message, duration = 3000)    { return _show(message, { duration, variant: 'info' }); },
    warning(message, duration = 4000) { return _show(message, { duration, variant: 'warning' }); }
};
