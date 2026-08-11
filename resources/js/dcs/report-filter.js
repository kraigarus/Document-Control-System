// Inline report filters — auto-apply on change (no slide-out panel).

const SUB_PARENT_MAP = {
    internal_docs: 1,
    external_docs: 3,
    internal_forms: 2,
    forms: 4,
    logbooks: 5,
};

const BASE_FILTER_KEYS = [
    'period', 'date_from', 'date_to', 'as_of',
    'originator', 'source_unit', 'revision_status', 'rev_no',
];

const PERIOD_LABELS = {
    monthly: 'Monthly',
    quarterly: 'Quarterly',
    annually: 'Annually',
    custom: 'Custom',
};

function pad2(n) { return String(n).padStart(2, '0'); }

function formatIsoDate(d) {
    return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
}

function formatDisplayDate(iso) {
    if (!iso) return '';
    const d = new Date(iso + 'T00:00:00');
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

/** Compute date range from period + as-of reference date. */
export function resolveDateRangeFromState(state) {
    const period = (state.period || 'annually').trim();
    const asOf = (state.as_of || '').trim() || formatIsoDate(new Date());

    if (period === 'custom') {
        return {
            period,
            date_from: (state.date_from || '').trim(),
            date_to: (state.date_to || '').trim(),
            as_of: asOf,
        };
    }

    const end = new Date(asOf + 'T00:00:00');
    let start;

    switch (period) {
        case 'monthly':
            start = new Date(end.getFullYear(), end.getMonth(), 1);
            break;
        case 'quarterly': {
            const qStartMonth = Math.floor(end.getMonth() / 3) * 3;
            start = new Date(end.getFullYear(), qStartMonth, 1);
            break;
        }
        case 'annually':
        default:
            start = new Date(end.getFullYear(), 0, 1);
            break;
    }

    return {
        period,
        date_from: formatIsoDate(start),
        date_to: formatIsoDate(end),
        as_of: asOf,
    };
}

export function initInlineReportFilters({ docTypes = [], onChange, hasSubTabs = true } = {}) {
    const panel = document.getElementById('inlineFilters');
    const form = document.getElementById('filterForm');
    const subtypeBlock = document.getElementById('subtypeBlock');
    const subtypeGrid = document.getElementById('subtypeCheckboxes');
    const selectAllBtn = document.getElementById('subtypeSelectAll');
    const clearAllBtn = document.getElementById('subtypeClearAll');
    const customDateRow = document.getElementById('customDateRow');
    const periodSummary = document.getElementById('periodSummary');
    const periodSelect = document.getElementById('filterPeriod');

    let state = Object.fromEntries(BASE_FILTER_KEYS.map(k => [k, '']));
    let selectedSubTypes = [];
    let totalSubTypes = 0;
    let currentSub = null;
    let debounceTimer = null;

    if (!panel || !form) {
        return {
            show: () => {},
            hide: () => {},
            setSubTab: () => {},
            apply: (p) => p,
            getState: () => ({ ...state, sub_type_ids: [...selectedSubTypes] }),
            reset: () => {},
        };
    }

    if (periodSelect && !periodSelect.value) {
        periodSelect.value = 'annually';
    }

    function readForm() {
        const fd = new FormData(form);
        BASE_FILTER_KEYS.forEach(k => {
            state[k] = (fd.get(k) || '').toString().trim();
        });
        if (!state.period) state.period = 'annually';
        selectedSubTypes = [...subtypeGrid.querySelectorAll('input[name="sub_type_id[]"]:checked')]
            .map(cb => cb.value);
    }

    function syncPeriodUi() {
        readForm();
        const resolved = resolveDateRangeFromState(state);
        const isCustom = state.period === 'custom';

        if (customDateRow) customDateRow.hidden = !isCustom;

        const from = document.getElementById('filterDateFrom');
        const to = document.getElementById('filterDateTo');
        if (!isCustom) {
            if (from) from.value = resolved.date_from;
            if (to) to.value = resolved.date_to;
        }

        if (periodSummary) {
            const label = PERIOD_LABELS[state.period] || state.period;
            if (isCustom) {
                periodSummary.textContent = label + ': '
                    + (resolved.date_from ? formatDisplayDate(resolved.date_from) : '—')
                    + ' – '
                    + (resolved.date_to ? formatDisplayDate(resolved.date_to) : '—');
            } else {
                periodSummary.textContent = label + ' report (as of '
                    + formatDisplayDate(resolved.as_of) + '): '
                    + formatDisplayDate(resolved.date_from) + ' – ' + formatDisplayDate(resolved.date_to);
            }
        }
    }

    function setDefaultAsOf() {
        const asOf = document.getElementById('filterAsOf');
        if (asOf && !asOf.value) {
            asOf.value = formatIsoDate(new Date());
        }
        syncPeriodUi();
    }

    function renderSubTypes(subKey) {
        if (!subtypeGrid || !subtypeBlock) return;
        subtypeGrid.innerHTML = '';
        selectedSubTypes = [];
        totalSubTypes = 0;

        const parentId = SUB_PARENT_MAP[subKey];
        const children = parentId
            ? docTypes.filter(d => String(d.parent_id) === String(parentId))
            : [];

        if (!children.length) {
            subtypeBlock.hidden = true;
            if (selectAllBtn) selectAllBtn.hidden = true;
            if (clearAllBtn) clearAllBtn.hidden = true;
            return;
        }

        subtypeBlock.hidden = false;
        if (selectAllBtn) selectAllBtn.hidden = false;
        if (clearAllBtn) clearAllBtn.hidden = false;
        totalSubTypes = children.length;

        children.forEach(sub => {
            const id = 'subType_' + sub.id;
            const label = document.createElement('label');
            label.className = 'rpt-subtype-item';
            label.innerHTML =
                '<input type="checkbox" name="sub_type_id[]" value="' + sub.id + '" id="' + id + '" checked>' +
                '<span>' + escapeHtml(sub.doc_type_name) + '</span>';
            subtypeGrid.appendChild(label);
        });

        selectedSubTypes = children.map(c => String(c.id));
    }

    function triggerChange() {
        syncPeriodUi();
        readForm();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            if (typeof onChange === 'function') onChange();
        }, 350);
    }

    form.addEventListener('change', (e) => {
        if (e.target.id === 'filterPeriod' || e.target.id === 'filterAsOf') {
            syncPeriodUi();
        }
        triggerChange();
    });
    form.addEventListener('input', triggerChange);

    selectAllBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        subtypeGrid.querySelectorAll('input[type="checkbox"]').forEach(cb => { cb.checked = true; });
        triggerChange();
    });
    clearAllBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        subtypeGrid.querySelectorAll('input[type="checkbox"]').forEach(cb => { cb.checked = false; });
        triggerChange();
    });

    form.addEventListener('submit', (e) => e.preventDefault());

    return {
        show() {
            panel.hidden = false;
            setDefaultAsOf();
            syncPeriodUi();
            readForm();
        },
        hide() {
            panel.hidden = true;
        },
        setSubTab(subKey) {
            currentSub = subKey;
            if (hasSubTabs) renderSubTypes(subKey);
            setDefaultAsOf();
            syncPeriodUi();
            readForm();
        },
        apply(params) {
            readForm();
            syncPeriodUi();
            const dates = resolveDateRangeFromState(state);

            if (dates.period) params.set('period', dates.period);
            else params.delete('period');

            if (dates.date_from) params.set('date_from', dates.date_from);
            else params.delete('date_from');
            if (dates.date_to) params.set('date_to', dates.date_to);
            else params.delete('date_to');
            if (dates.as_of) params.set('as_of', dates.as_of);
            else params.delete('as_of');

            ['originator', 'source_unit', 'rev_no'].forEach(k => {
                if (state[k]) params.set(k, state[k]);
                else params.delete(k);
            });
            if (state.revision_status && state.revision_status !== 'all') {
                params.set('revision_status', state.revision_status);
            } else {
                params.delete('revision_status');
            }

            params.delete('sub_type_ids');
            // Only filter by sub-type when user narrowed the checklist (not all checked).
            if (totalSubTypes > 0 && selectedSubTypes.length > 0 && selectedSubTypes.length < totalSubTypes) {
                params.set('sub_type_ids', selectedSubTypes.join(','));
            }

            return params;
        },
        getState() {
            readForm();
            return { ...state, sub_type_ids: [...selectedSubTypes], sub: currentSub, total_sub_types: totalSubTypes };
        },
        reset() {
            form.reset();
            if (periodSelect) periodSelect.value = 'annually';
            subtypeGrid.innerHTML = '';
            subtypeBlock.hidden = true;
            totalSubTypes = 0;
            state = Object.fromEntries(BASE_FILTER_KEYS.map(k => [k, '']));
            selectedSubTypes = [];
            syncPeriodUi();
        },
    };
}

function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}
