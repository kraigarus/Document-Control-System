document.addEventListener('DOMContentLoaded', () => {
    const selectAll = document.getElementById('selectAll');
    const batchBtn = document.getElementById('btnBatchStamp');
    const batchCount = document.getElementById('batchCount');
    const stampModal = document.getElementById('stampModal');
    const detailDrawer = document.getElementById('detailDrawer');
    const searchInput = document.getElementById('searchInput');
    const filterStatus = document.getElementById('filterStatus');
    const toastContainer = document.getElementById('toastContainer');

    // ── Select All ──
    selectAll.addEventListener('change', () => {
        document.querySelectorAll('#tableBody tr').forEach(row => {
            if (row.style.display !== 'none') {
                const cb = row.querySelector('.row-check');
                if (cb) cb.checked = selectAll.checked;
            }
        });
        updateBatch();
    });

    document.querySelectorAll('.row-check').forEach(cb => {
        cb.addEventListener('change', updateBatch);
    });

    function updateBatch() {
        const checked = document.querySelectorAll('.row-check:checked');
        batchCount.textContent = checked.length;
        batchBtn.disabled = checked.length === 0;
    }

    // ── Stamp buttons ──
    let currentIds = [];
    let batchMode = false;

    document.querySelectorAll('.stmp-icon-btn-stamp:not(:disabled)').forEach(btn => {
        btn.addEventListener('click', () => openStamp([btn.dataset.id], false));
    });

    batchBtn.addEventListener('click', () => {
        const ids = [...document.querySelectorAll('.row-check:checked')].map(c => c.value);
        openStamp(ids, true);
    });

    function openStamp(ids, batch) {
        currentIds = ids;
        batchMode = batch;

        if (batch) {
            document.getElementById('previewDocNo').textContent = ids.length + ' documents selected';
            document.getElementById('previewTitle').textContent = 'Batch stamping';
            document.getElementById('previewRev').textContent = '—';
        } else {
            const row = document.querySelector('tr[data-id="' + ids[0] + '"]');
            if (row) {
                const td = row.querySelectorAll('td');
                document.getElementById('previewDocNo').textContent = td[2]?.textContent?.trim() || 'N/A';
                document.getElementById('previewTitle').textContent = td[3]?.textContent?.trim() || 'N/A';
                document.getElementById('previewRev').textContent = td[6]?.textContent?.trim() || 'N/A';
            }
        }

        stampModal.classList.add('stmp-open');
    }

    function closeStamp() {
        stampModal.classList.remove('stmp-open');
        document.getElementById('stampType').value = '';
        document.getElementById('stampRemarks').value = '';
        document.getElementById('stampCopies').value = 1;
    }

    document.getElementById('closeModal').addEventListener('click', closeStamp);
    document.getElementById('cancelModal').addEventListener('click', closeStamp);
    stampModal.addEventListener('click', e => { if (e.target === stampModal) closeStamp(); });

    // ── Confirm stamp ──
    document.getElementById('confirmStamp').addEventListener('click', () => {
        const type = document.getElementById('stampType').value;
        const date = document.getElementById('stampDate').value;

        if (!type) { showToast('Please select a stamp type.', 'error'); return; }
        if (!date) { showToast('Please select a stamp date.', 'error'); return; }

        currentIds.forEach(id => {
            const row = document.querySelector('tr[data-id="' + id + '"]');
            if (row) {
                row.dataset.status = 'stamped';
                const badge = row.querySelector('.stmp-badge');
                if (badge) {
                    badge.className = 'stmp-badge stmp-badge-stamped';
                    badge.textContent = 'Stamped';
                }
                const stampBtn = row.querySelector('.stmp-icon-btn-stamp');
                if (stampBtn) stampBtn.disabled = true;
                row.classList.add('stmp-row-done');
                setTimeout(() => row.classList.remove('stmp-row-done'), 800);
                const cb = row.querySelector('.row-check');
                if (cb) cb.checked = false;
            }
        });

        const n = currentIds.length;
        closeStamp();
        selectAll.checked = false;
        updateBatch();
        showToast(n + ' document' + (n > 1 ? 's' : '') + ' stamped successfully.', 'success');
    });

    // ── Copies +/- ──
    document.getElementById('copyMinus').addEventListener('click', () => {
        const inp = document.getElementById('stampCopies');
        inp.value = Math.max(1, parseInt(inp.value) - 1);
    });
    document.getElementById('copyPlus').addEventListener('click', () => {
        const inp = document.getElementById('stampCopies');
        inp.value = Math.min(999, parseInt(inp.value) + 1);
    });

    // ── View / Drawer ──
    document.querySelectorAll('.stmp-icon-btn-view').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = document.querySelector('tr[data-id="' + btn.dataset.id + '"]');
            if (row) {
                const td = row.querySelectorAll('td');
                document.getElementById('detailId').textContent = btn.dataset.id;
                document.getElementById('detailDocNo').textContent = td[2]?.textContent?.trim() || 'N/A';
                document.getElementById('detailTitle').textContent = td[3]?.textContent?.trim() || 'N/A';
                document.getElementById('detailDrf').textContent = td[4]?.textContent?.trim() || 'N/A';
                document.getElementById('detailType').textContent = td[5]?.textContent?.trim() || 'N/A';
                document.getElementById('detailRev').textContent = td[6]?.textContent?.trim() || 'N/A';
            }
            detailDrawer.classList.add('stmp-open');
        });
    });

    document.getElementById('closeDrawer').addEventListener('click', () => detailDrawer.classList.remove('stmp-open'));
    detailDrawer.addEventListener('click', e => { if (e.target === detailDrawer) detailDrawer.classList.remove('stmp-open'); });

    // ── Filters ──
    searchInput.addEventListener('input', applyFilters);
    filterStatus.addEventListener('change', applyFilters);

    document.getElementById('btnClearFilters').addEventListener('click', () => {
        searchInput.value = '';
        filterStatus.value = '';
        document.getElementById('filterDocType').value = '';
        applyFilters();
    });

    function applyFilters() {
        const q = searchInput.value.toLowerCase().trim();
        const status = filterStatus.value;
        let visible = 0;

        document.querySelectorAll('#tableBody tr').forEach(row => {
            const text = row.textContent.toLowerCase();
            const rs = row.dataset.status;
            const show = (!q || text.includes(q)) && (!status || rs === status);
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        const info = document.querySelector('.stmp-page-info');
        if (info) info.innerHTML = 'Showing <strong>' + visible + '</strong> entries';
    }

    // ── Toast ──
    function showToast(msg, type) {
        const icons = {
            success: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>',
            error: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>',
        };
        const el = document.createElement('div');
        el.className = 'stmp-toast stmp-toast-' + type;
        el.innerHTML = (icons[type] || '') + '<span>' + msg + '</span>';
        toastContainer.appendChild(el);
        setTimeout(() => {
            el.classList.add('stmp-toast-out');
            setTimeout(() => el.remove(), 200);
        }, 3000);
    }

    // ── Keyboard ──
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeStamp();
            detailDrawer.classList.remove('stmp-open');
        }
    });
});