// resources/js/dcs/report-filter.js

const BASE_FILTER_KEYS = ['date_from', 'date_to', 'originator', 'source_unit', 'status', 'rev_no'];

export function initFilterPanel({ onApply, onClear, extraKeys = [] } = {}) {
    const FILTER_KEYS = [...BASE_FILTER_KEYS, ...extraKeys];

    const overlay   = document.getElementById('filterOverlay');
    const panel     = document.getElementById('filterPanel');
    const openBtn   = document.getElementById('openFilterBtn');
    const closeBtn  = document.getElementById('closeFilterBtn');
    const form      = document.getElementById('filterForm');
    const applyBtn  = document.getElementById('applyFilterBtn');
    const clearBtn  = document.getElementById('clearFilterBtn');

    let state = Object.fromEntries(FILTER_KEYS.map(k => [k, '']));

    if (!panel || !form) {
        return {
            apply: (params) => params,
            getState: () => ({ ...state }),
            reset: () => {},
        };
    }

    let badge = null;
    if (openBtn) {
        badge = document.createElement('span');
        badge.id = 'filterBadge';
        badge.className = 'rpt-filter-badge';
        openBtn.appendChild(badge);
    }

    function open() {
        panel.classList.add('open');
        panel.setAttribute('aria-hidden', 'false');
        overlay?.classList.add('visible');
    }

    function close() {
        panel.classList.remove('open');
        panel.setAttribute('aria-hidden', 'true');
        overlay?.classList.remove('visible');
    }

    openBtn?.addEventListener('click', open);
    closeBtn?.addEventListener('click', close);
    overlay?.addEventListener('click', close);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && panel.classList.contains('open')) close();
    });

    function readForm() {
        const fd = new FormData(form);
        state = Object.fromEntries(FILTER_KEYS.map(k => [k, (fd.get(k) || '').toString().trim()]));
    }

    function updateBadge() {
        if (!badge) return;
        const count = Object.values(state).filter(v => v !== '').length;
        if (count > 0) {
            badge.textContent = String(count);
            badge.classList.add('visible');
        } else {
            badge.classList.remove('visible');
        }
    }

    applyBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        readForm();
        updateBadge();
        close();
        if (typeof onApply === 'function') onApply();
    });

    clearBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        form.reset();
        readForm();
        updateBadge();
        close();
        if (typeof onApply === 'function') onApply();
        if (typeof onClear === 'function') onClear();
    });

    form.addEventListener('submit', (e) => e.preventDefault());

    return {
        apply(params) {
            FILTER_KEYS.forEach((key) => {
                const val = state[key];
                if (val !== '' && val !== null && val !== undefined) {
                    params.set(key, val);
                } else {
                    params.delete(key);
                }
            });
            return params;
        },
        getState() {
            return { ...state };
        },
        reset() {
            form.reset();
            readForm();
            updateBadge();
        },
    };
}