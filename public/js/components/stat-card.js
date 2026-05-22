export function StatCard({ value, label, icon = '', change, variant = 'amber' } = {}) {
    const card = document.createElement('div');
    card.className = 'stat-card';

    const variantColors = {
        amber: 'amber', blue: 'blue', green: 'green', red: 'red'
    };
    const colorClass = variantColors[variant] || 'amber';

    card.innerHTML = `
        <div class="stat-icon ${colorClass}">${icon}</div>
        <div class="stat-value" data-target="${value}">0</div>
        <div class="stat-label">${label}</div>
        ${change ? `<span class="stat-change ${change.direction || ''}">${change.direction === 'up' ? '&#9650;' : '&#9660;'} ${change.value}</span>` : ''}
    `;

    // Animated counter on mount
    const valueEl = card.querySelector('.stat-value');
    const target = typeof value === 'number' ? value : parseFloat(value);
    const isFloat = !Number.isInteger(target);
    const suffix = typeof value === 'string' ? value.replace(/[\d.,\-]/g, '') : '';

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            observer.disconnect();
            _animateCounter(valueEl, target, isFloat, suffix);
        });
    }, { threshold: 0.1 });

    requestAnimationFrame(() => observer.observe(card));

    return card;
}

function _animateCounter(el, target, isFloat, suffix) {
    const duration = 1200;
    const start = performance.now();

    function step(now) {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        const current = target * eased;

        if (isFloat) {
            el.textContent = current.toFixed(1) + suffix;
        } else {
            el.textContent = Math.floor(current).toLocaleString() + suffix;
        }

        if (progress < 1) requestAnimationFrame(step);
        else el.textContent = (isFloat ? target.toFixed(1) : target.toLocaleString()) + suffix;
    }

    requestAnimationFrame(step);
}
