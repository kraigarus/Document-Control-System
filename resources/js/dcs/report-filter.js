export function initFilterPanel({ onApply, onReset } = {}) {
    const $ = (id) => document.getElementById(id);

    const filterOverlay   = $('filterOverlay');
    const filterPanel     = $('filterPanel');
    const openFilterBtn   = $('openFilterBtn');
    const closeFilterBtn  = $('closeFilterBtn');
    const resetFilterBtn  = $('resetFilterBtn');
    const applyFilterBtn  = $('applyFilterBtn');
    const originatorInput = $('filterOriginator');
    const sourceUnitInput = $('filterSourceUnit');
    const statusInput     = $('filterStatus');
    const revNoInput      = $('filterRevNo');

    function open()  { filterPanel?.classList.add('visible');    filterOverlay?.classList.add('visible'); }
    function close() { filterPanel?.classList.remove('visible'); filterOverlay?.classList.remove('visible'); }

    openFilterBtn?.addEventListener('click', open);
    closeFilterBtn?.addEventListener('click', close);
    filterOverlay?.addEventListener('click', close);

    applyFilterBtn?.addEventListener('click', () => {
        close();
        onApply?.();
    });

    resetFilterBtn?.addEventListener('click', () => {
        clear();
        onReset?.();
    });

    function clear() {
        if (originatorInput) originatorInput.value = '';
        if (sourceUnitInput) sourceUnitInput.value = '';
        if (statusInput) statusInput.value = '';
        if (revNoInput) revNoInput.value = '';
    }

    // Appends filter values onto an existing URLSearchParams and returns it
    function apply(params) {
        if (originatorInput?.value) params.set('originator', originatorInput.value);
        if (sourceUnitInput?.value) params.set('source_unit', sourceUnitInput.value);
        if (statusInput?.value)     params.set('status', statusInput.value);
        if (revNoInput?.value)      params.set('rev_no', revNoInput.value);
        return params;
    }

    return { open, close, clear, apply };
} 