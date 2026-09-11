import Chart from 'chart.js/auto';

window.Chart = Chart;

const modalInstances = new WeakMap();

class Modal {
    constructor(element) {
        this.element = element;
        modalInstances.set(element, this);
    }

    show() {
        this.element.classList.add('show');
        this.element.removeAttribute('aria-hidden');
        this.element.setAttribute('aria-modal', 'true');
        document.body.classList.add('modal-open');

        const focusTarget = this.element.querySelector('[autofocus], input, select, textarea, button');
        window.setTimeout(() => focusTarget?.focus(), 0);
        this.element.dispatchEvent(new Event('shown.bs.modal'));
    }

    hide() {
        this.element.classList.remove('show');
        this.element.setAttribute('aria-hidden', 'true');
        this.element.removeAttribute('aria-modal');
        if (!document.querySelector('.modal.show')) document.body.classList.remove('modal-open');
        this.element.dispatchEvent(new Event('hidden.bs.modal'));
    }

    static getOrCreateInstance(element) {
        return modalInstances.get(element) ?? new Modal(element);
    }
}

class Toast {
    constructor(element) {
        this.element = element;
    }

    show() {
        this.element.classList.add('show');
        window.setTimeout(() => this.hide(), 5000);
    }

    hide() {
        this.element.classList.remove('show');
    }
}

const activateTab = (trigger) => {
    const targetSelector = trigger.dataset.bsTarget || trigger.getAttribute('href');
    if (!targetSelector?.startsWith('#')) return;

    const target = document.querySelector(targetSelector);
    const tabList = trigger.closest('[role="tablist"], .nav');

    tabList?.querySelectorAll('[data-bs-toggle="tab"], [data-bs-toggle="pill"]').forEach((tab) => {
        tab.classList.toggle('active', tab === trigger);
        tab.setAttribute('aria-selected', String(tab === trigger));
    });

    target?.parentElement?.querySelectorAll('.tab-pane').forEach((pane) => {
        pane.classList.toggle('active', pane === target);
        pane.classList.toggle('show', pane === target);
    });

    trigger.dispatchEvent(new Event('shown.bs.tab'));
};

document.addEventListener('click', (event) => {
    const modalTrigger = event.target.closest('[data-bs-toggle="modal"]');
    if (modalTrigger) {
        event.preventDefault();
        if (modalTrigger.dataset.bsDismiss === 'modal') {
            const currentModal = modalTrigger.closest('.modal');
            if (currentModal) Modal.getOrCreateInstance(currentModal).hide();
        }
        const target = document.querySelector(modalTrigger.dataset.bsTarget);
        if (target) Modal.getOrCreateInstance(target).show();
        return;
    }

    const tabTrigger = event.target.closest('[data-bs-toggle="tab"], [data-bs-toggle="pill"]');
    if (tabTrigger) {
        event.preventDefault();
        activateTab(tabTrigger);
        return;
    }

    const dismiss = event.target.closest('[data-bs-dismiss]');
    if (dismiss?.dataset.bsDismiss === 'modal') {
        Modal.getOrCreateInstance(dismiss.closest('.modal')).hide();
    } else if (dismiss?.dataset.bsDismiss === 'alert') {
        dismiss.closest('.alert')?.remove();
    } else if (dismiss?.dataset.bsDismiss === 'toast') {
        dismiss.closest('.toast')?.classList.remove('show');
    }
});

document.addEventListener('click', (event) => {
    if (event.target.classList.contains('modal') && event.target.classList.contains('show')) {
        Modal.getOrCreateInstance(event.target).hide();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        const openModal = document.querySelector('.modal.show');
        if (openModal) Modal.getOrCreateInstance(openModal).hide();
    }
});

window.bootstrap = { Modal, Toast };

const icons = {
    'arrow-clockwise': '<path d="M20 7v5h-5"/><path d="M20 12a8 8 0 1 1-2.34-5.66L20 8"/>',
    'arrow-left': '<path d="m15 18-6-6 6-6"/><path d="M21 12H9"/>',
    'arrow-right': '<path d="M5 12h12"/><path d="m13 6 6 6-6 6"/>',
    'bar-chart-line': '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    'box-arrow-right': '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/>',
    'buildings': '<path d="M3 21V5a2 2 0 0 1 2-2h8v18M13 9h6a2 2 0 0 1 2 2v10M7 7h2M7 11h2M7 15h2M17 13h1M17 17h1M2 21h20"/>',
    'cart-check': '<circle cx="9" cy="20" r="1"/><circle cx="19" cy="20" r="1"/><path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h8.7a2 2 0 0 0 2-1.7L22 8H7M14 11l2 2 4-4"/>',
    'calendar3': '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/>',
    'check2-circle': '<path d="m9 12 2 2 4-4"/><circle cx="12" cy="12" r="9"/>',
    'chevron-left': '<path d="m15 18-6-6 6-6"/>',
    'chevron-right': '<path d="m9 18 6-6-6-6"/>',
    'clipboard-data': '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2h6v2M9 17v-3M12 17v-6M15 17v-2"/>',
    'compass': '<circle cx="12" cy="12" r="9"/><path d="m16 8-2.5 5.5L8 16l2.5-5.5L16 8Z"/>',
    'cone-striped': '<path d="m12 3 7 18H5L12 3ZM7 16h10M9 11h6"/>',
    'file-earmark-check': '<path d="M6 2h8l4 4v16H6V2Z"/><path d="M14 2v5h5M9 14l2 2 4-4"/>',
    'file-earmark-text': '<path d="M6 2h8l4 4v16H6V2Z"/><path d="M14 2v5h5M9 13h6M9 17h6"/>',
    'folder2-open': '<path d="M3 19 5 9h16l-2 10H3Z"/><path d="M3 19V5h7l2 3h7v1"/>',
    'fuel-pump': '<path d="M5 21V3h10v18M3 21h14M8 7h4"/><path d="M15 7h2l3 3v7a2 2 0 0 0 2 2V9l-2-2"/>',
    'funnel': '<path d="M3 4h18l-7 8v6l-4 2v-8L3 4Z"/>',
    'gear-wide-connected': '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.9 4.9 7 7M17 17l2.1 2.1M2 12h3M19 12h3M4.9 19.1 7 17M17 7l2.1-2.1"/>',
    'grid': '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    'grid-1x2': '<rect x="3" y="3" width="7" height="18" rx="1"/><rect x="14" y="3" width="7" height="8" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    'grid-3x3-gap-fill': '<path d="M3 3h5v5H3zM10 3h4v5h-4zM16 3h5v5h-5zM3 10h5v4H3zM10 10h4v4h-4zM16 10h5v4h-5zM3 16h5v5H3zM10 16h4v5h-4zM16 16h5v5h-5z" fill="currentColor" stroke="none"/>',
    'house': '<path d="m3 11 9-8 9 8v10h-6v-6H9v6H3V11Z"/>',
    'list': '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
    'map': '<path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6ZM9 3v15M15 6v15"/>',
    'people': '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    'person-fill': '<circle cx="12" cy="8" r="4" fill="currentColor" stroke="none"/><path d="M4 22a8 8 0 0 1 16 0" fill="currentColor" stroke="none"/>',
    'plus': '<path d="M12 5v14M5 12h14"/>',
    'plus-lg': '<path d="M12 4v16M4 12h16"/>',
    'pencil': '<path d="m4 20 4.5-1 11-11a2.1 2.1 0 0 0-3-3l-11 11L4 20ZM14 7l3 3"/>',
    'search': '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
    'ship': '<path d="M12 2v4M8 6h8l2 6 4 2-2 6H4l-2-6 4-2 2-6ZM5 16h14M12 6v6"/>',
    'tools': '<path d="m14 6 4-4 4 4-4 4M14 10l-9 9-3 1 1-3 9-9M5 3l4 4M3 5l2-2M15 15l6 6"/>',
    'trash': '<path d="M3 6h18M8 6V3h8v3M6 6l1 15h10l1-15M10 10v7M14 10v7"/>',
    'wrench-adjustable': '<path d="M14.7 6.3a4 4 0 0 0-5-5L12 4 9 7 6.3 4.3a4 4 0 0 0 5 5L4 17l3 3 7.7-7.7a4 4 0 0 0 5-5L17 10l-3-3 2.7-2.7Z"/>',
};

document.querySelectorAll('i.bi').forEach((icon) => {
    const iconClass = [...icon.classList].find((className) => className.startsWith('bi-'));
    const name = iconClass?.slice(3);
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    const retainedClasses = [...icon.classList].filter((className) => className !== 'bi' && className !== iconClass);

    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '1.8');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    svg.setAttribute('aria-hidden', 'true');
    svg.classList.add('inline-block', 'h-[1em]', 'w-[1em]', 'shrink-0', ...retainedClasses);
    svg.innerHTML = icons[name] ?? '<circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/>';
    icon.replaceWith(svg);
});

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        if (!input) return;

        const willShow = input.type === 'password';
        input.type = willShow ? 'text' : 'password';
        button.classList.toggle('is-visible', willShow);
        button.setAttribute('aria-label', willShow ? 'Hide password' : 'Show password');
        button.setAttribute('aria-pressed', String(willShow));
    });
});

const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;',
})[character]);

const showDialog = ({ title, text: message, tone = 'primary', confirmText = 'OK', cancelText = null }) => new Promise((resolve) => {
    const dialog = document.createElement('dialog');
    const dangerous = tone === 'danger';
    dialog.className = 'villa-dialog';
    dialog.innerHTML = `
        <div class="p-6">
            <div class="mb-5 grid h-12 w-12 place-items-center rounded-2xl ${dangerous ? 'bg-rose-50 text-rose-700' : 'bg-villa-50 text-villa-700'}">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg>
            </div>
            <h2 class="mb-2 text-lg font-extrabold text-slate-900">${escapeHtml(title)}</h2>
            <p class="mb-6 text-sm leading-6 text-slate-500">${escapeHtml(message)}</p>
            <div class="flex justify-end gap-2">
                ${cancelText ? `<button type="button" data-dialog-cancel class="btn btn-outline-secondary">${escapeHtml(cancelText)}</button>` : ''}
                <button type="button" data-dialog-confirm class="btn ${dangerous ? 'btn-danger' : 'btn-primary'}">${escapeHtml(confirmText)}</button>
            </div>
        </div>`;

    const finish = (confirmed) => {
        dialog.close();
        dialog.remove();
        resolve(confirmed);
    };
    dialog.querySelector('[data-dialog-confirm]').addEventListener('click', () => finish(true));
    dialog.querySelector('[data-dialog-cancel]')?.addEventListener('click', () => finish(false));
    dialog.addEventListener('cancel', (event) => { event.preventDefault(); finish(false); });
    document.body.appendChild(dialog);
    dialog.showModal();
    dialog.querySelector('[data-dialog-confirm]').focus();
});

window.VillaDialog = {
    confirm: (options) => showDialog({ ...options, cancelText: options.cancelText ?? 'Cancel' }),
    alert: (options) => showDialog(options),
};
