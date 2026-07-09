document.addEventListener('DOMContentLoaded', function () {

    // ═══════════════════════════════════════════
    // ELEMENTS
    // ═══════════════════════════════════════════

    const stampModal      = document.getElementById('stampModal');
    const closeModal      = document.getElementById('closeModal');
    const cancelBtn       = document.getElementById('cancelBtn');
    const modalDocInfo    = document.getElementById('modalDocInfo');

    const fileSection     = document.getElementById('fileSection');
    const fileOptions     = document.getElementById('fileOptions');

    const typePills       = document.getElementById('typePills');
    const certifiedFields = document.getElementById('certifiedFields');
    const certifiedByInp  = document.getElementById('certifiedBy');
    const designationInp  = document.getElementById('designation');

    const positionMap     = document.getElementById('positionMap');
    const positionLabel   = document.getElementById('positionLabel');

    const stampAllPages   = document.getElementById('stampAllPages');

    const pdfPreview      = document.getElementById('pdfPreview');
    const previewFallback = document.getElementById('previewFallback');
    const openPdfLink     = document.getElementById('openPdfLink');
    const refreshPreview  = document.getElementById('refreshPreview');
    const stampOverlay    = document.getElementById('stampOverlay');

    const overlayTitle    = document.getElementById('overlayTitle');
    const overlayDivider  = document.getElementById('overlayDivider');
    const overlayFields   = document.getElementById('overlayFields');
    const overlayCertBy   = document.getElementById('overlayCertBy');
    const overlayDesig    = document.getElementById('overlayDesig');
    const overlayDate     = document.getElementById('overlayDate');
    const overlaySub      = document.getElementById('overlaySub');
    const overlayPages    = document.getElementById('overlayPages');

    const downloadBtn     = document.getElementById('downloadBtn');
    const applyBtn        = document.getElementById('applyBtn');

    const confirmModal    = document.getElementById('confirmModal');
    const confirmCancel   = document.getElementById('confirmCancel');
    const confirmApply    = document.getElementById('confirmApply');
    const confirmLabel    = document.getElementById('confirmStampLabel');

    const toastEl         = document.getElementById('stToast');
    const toastMsg        = document.getElementById('toastMsg');

    const searchInput     = document.getElementById('stSearch');
    const typeFilter      = document.getElementById('stTypeFilter');
    const clearBtn        = document.getElementById('stClearBtn');

    const stampBox        = document.getElementById('stampBox');

    // ═══════════════════════════════════════════
    // STATE
    // ═══════════════════════════════════════════

    let state = {
        files: [],
        selectedFile: null,
        docTitle: '',
        docNo: '',
        rev: '',
        stampType: null,
        position: 'bottom-right',
        allPages: true,
        certBy: '',
        desig: '',
    };

    // ═══════════════════════════════════════════
    // SEARCH & FILTER
    // ═══════════════════════════════════════════

    function filterRows() {
        const q    = searchInput.value.toLowerCase().trim();
        const type = typeFilter.value;
        document.querySelectorAll('#stTableBody tr[data-search]').forEach(row => {
            const text = row.dataset.search || '';
            const matchType  = type === 'all' || text.includes(type);
            const matchQuery = !q || text.includes(q);
            row.style.display = (matchType && matchQuery) ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', filterRows);
    typeFilter.addEventListener('change', filterRows);
    clearBtn.addEventListener('click', () => {
        searchInput.value = '';
        typeFilter.value  = 'all';
        filterRows();
    });

    // ═══════════════════════════════════════════
    // OPEN MODAL
    // ═══════════════════════════════════════════

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.st-btn-stamp');
        if (!btn) return;

        let files = [];
        try { files = JSON.parse(btn.dataset.files || '[]'); } catch (_) { files = []; }

        if (files.length === 0) {
            showToast('No files available for this document.', 'error');
            return;
        }

        state.files      = files;
        state.selectedFile = files[0];
        state.docTitle   = btn.dataset.title || '';
        state.docNo      = btn.dataset.docNo || '';
        state.rev        = btn.dataset.rev   || '0';
        state.stampType  = null;
        state.position   = 'bottom-right';
        state.allPages   = true;
        state.certBy     = '';
        state.desig      = '';

        // Header info
        modalDocInfo.textContent = state.docNo + ' — ' + state.docTitle + ' (Rev ' + state.rev + ')';

        // Build file selector
        buildFileSelector();

        // Reset config UI
        resetConfigUI();

        // Load first preview
        loadPreview();

        // Show
        stampModal.style.display = 'flex';
    });

    // ═══════════════════════════════════════════
    // FILE SELECTOR
    // ═══════════════════════════════════════════

    function buildFileSelector() {
        fileOptions.innerHTML = '';

        if (state.files.length <= 1) {
            fileSection.style.display = 'none';
            return;
        }

        fileSection.style.display = 'block';

        state.files.forEach(function (file, idx) {
            const lbl = document.createElement('label');
            lbl.className = 'st-file-option';

            const radio = document.createElement('input');
            radio.type    = 'radio';
            radio.name    = 'stampFile';
            radio.value   = idx;
            if (idx === 0) radio.checked = true;

            const span = document.createElement('span');
            span.className = 'st-file-option-label';
            span.innerHTML = '<i class="fa-solid fa-file-pdf"></i> ' + file.label;

            lbl.appendChild(radio);
            lbl.appendChild(span);
            fileOptions.appendChild(lbl);

            radio.addEventListener('change', function () {
                if (this.checked) {
                    state.selectedFile = state.files[idx];
                    loadPreview();
                }
            });
        });
    }

    // ═══════════════════════════════════════════
    // RESET CONFIG UI
    // ═══════════════════════════════════════════

    function resetConfigUI() {
        document.querySelectorAll('.st-type-pill').forEach(p => p.classList.remove('selected'));
        certifiedFields.style.display = 'none';
        certifiedByInp.value = '';
        designationInp.value = '';

        document.querySelectorAll('.st-pos-dot').forEach(d => d.classList.remove('active'));
        document.querySelector('.st-pos-dot[data-pos="bottom-right"]').classList.add('active');
        positionLabel.textContent = 'Bottom Right';

        stampAllPages.checked = true;
        stampOverlay.style.display = 'none';

        downloadBtn.disabled = true;
        applyBtn.disabled    = true;
    }

    // ═══════════════════════════════════════════
    // LOAD PREVIEW
    // ═══════════════════════════════════════════

    function loadPreview() {
        if (!state.selectedFile) return;

        const url = '/storage/' + state.selectedFile.path;

        previewFallback.style.display = 'none';
        pdfPreview.style.display      = 'block';
        pdfPreview.src                = url;
        openPdfLink.href              = url;

        updateOverlay();
    }

    // Show fallback on iframe error
    pdfPreview.addEventListener('error', function () {
        previewFallback.style.display = 'flex';
        pdfPreview.style.display      = 'none';
    });

    // ═══════════════════════════════════════════
    // STAMP TYPE
    // ═══════════════════════════════════════════

    typePills.addEventListener('click', function (e) {
        const pill = e.target.closest('.st-type-pill');
        if (!pill) return;

        document.querySelectorAll('.st-type-pill').forEach(p => p.classList.remove('selected'));
        pill.classList.add('selected');

        state.stampType = pill.dataset.type;

        certifiedFields.style.display = (state.stampType === 'certified_true_copy') ? 'block' : 'none';

        updateOverlay();
        checkReady();
    });

    // ═══════════════════════════════════════════
    // CERTIFIED FIELDS
    // ═══════════════════════════════════════════

    certifiedByInp.addEventListener('input', function () {
        state.certBy = this.value;
        updateOverlay();
        checkReady();
    });

    designationInp.addEventListener('input', function () {
        state.desig = this.value;
        updateOverlay();
        checkReady();
    });

    // ═══════════════════════════════════════════
    // POSITION
    // ═══════════════════════════════════════════

    positionMap.addEventListener('click', function (e) {
        const dot = e.target.closest('.st-pos-dot');
        if (!dot) return;

        document.querySelectorAll('.st-pos-dot').forEach(d => d.classList.remove('active'));
        dot.classList.add('active');

        state.position = dot.dataset.pos;

        var labels = {
            'top-left': 'Top Left',
            'top-right': 'Top Right',
            'center': 'Center',
            'bottom-left': 'Bottom Left',
            'bottom-right': 'Bottom Right',
        };
        positionLabel.textContent = labels[state.position] || state.position;

        updateOverlay();
    });

    // ═══════════════════════════════════════════
    // ALL PAGES
    // ═══════════════════════════════════════════

    stampAllPages.addEventListener('change', function () {
        state.allPages = this.checked;
        updateOverlay();
    });

    // ═══════════════════════════════════════════
    // UPDATE STAMP OVERLAY
    // ═══════════════════════════════════════════

    var STAMP_STYLES = {
        controlled:           { title: 'CONTROLLED',          color: '#003399', sub: 'Controlled document — do not copy' },
        obsolete:             { title: 'OBSOLETE',            color: '#b40000', sub: 'Superseded — do not use' },
        master_copy:          { title: 'MASTER COPY',         color: '#006400', sub: '' },
        reference:            { title: 'REFERENCE COPY',      color: '#646464', sub: '' },
        certified_true_copy:  { title: 'CERTIFIED TRUE COPY', color: '#003399', sub: '' },
    };

    function updateOverlay() {
        if (!state.stampType) {
            stampOverlay.style.display = 'none';
            return;
        }

        stampOverlay.style.display = 'block';
        stampOverlay.className     = 'st-stamp-overlay pos-' + state.position;

        var cfg = STAMP_STYLES[state.stampType] || STAMP_STYLES.controlled;

        // Title
        overlayTitle.textContent = cfg.title;
        overlayTitle.style.color = cfg.color;

        // Certified fields
        if (state.stampType === 'certified_true_copy') {
            overlayDivider.style.display = 'block';
            overlayDivider.style.color   = cfg.color;
            overlayFields.style.display  = 'block';
            overlayCertBy.textContent    = state.certBy || '...';
            overlayDesig.textContent     = state.desig  || '...';
        } else {
            overlayDivider.style.display = 'none';
            overlayFields.style.display  = 'none';
        }

        // Date
        overlayDate.textContent = new Date().toLocaleDateString('en-US', {
            year: 'numeric', month: 'short', day: 'numeric'
        });
        overlayDate.style.color = cfg.color;

        // Sub
        if (cfg.sub) {
            overlaySub.textContent    = cfg.sub;
            overlaySub.style.display  = 'block';
        } else {
            overlaySub.style.display  = 'none';
        }

        // Pages label
        overlayPages.textContent = state.allPages ? 'All pages' : 'First page only';

        // Box colors
        stampBox.style.borderColor                    = cfg.color;
        stampBox.querySelector('.st-stamp-inner').style.borderColor = cfg.color;
    }

    // ═══════════════════════════════════════════
    // CHECK IF READY
    // ═══════════════════════════════════════════

    function checkReady() {
        var ready = !!state.stampType;

        if (state.stampType === 'certified_true_copy') {
            ready = ready && state.certBy.trim() !== '' && state.desig.trim() !== '';
        }

        downloadBtn.disabled = !ready;
        applyBtn.disabled    = !ready;
    }

    // ═══════════════════════════════════════════
    // CLOSE MODAL
    // ═══════════════════════════════════════════

    function hideStampModal() {
        stampModal.style.display = 'none';
        pdfPreview.src = '';
        stampOverlay.style.display = 'none';
    }

    closeModal.addEventListener('click', hideStampModal);
    cancelBtn.addEventListener('click', hideStampModal);
    stampModal.addEventListener('click', function (e) {
        if (e.target === stampModal) hideStampModal();
    });

    // ═══════════════════════════════════════════
    // REFRESH PREVIEW
    // ═══════════════════════════════════════════

    refreshPreview.addEventListener('click', function () {
        if (state.selectedFile) loadPreview();
    });

    // ═══════════════════════════════════════════
    // BUILD REQUEST PAYLOAD
    // ═══════════════════════════════════════════

        function buildPayload() {
        return {
            file_path:    state.selectedFile.path,
            file_key:     state.selectedFile.key,
            doc_no:       state.docNo,
            doc_title:    state.docTitle,
            rev:          String(state.rev),
            stamp_type:   state.stampType,
            position:     state.position,
            all_pages:    state.allPages,
            certified_by: state.stampType === 'certified_true_copy' ? state.certBy : null,
            designation:  state.stampType === 'certified_true_copy' ? state.desig  : null,
        };
    }

    // ═══════════════════════════════════════════
    // DOWNLOAD (stamped copy, no overwrite)
    // ═══════════════════════════════════════════

    downloadBtn.addEventListener('click', function () {
        if (!state.selectedFile || !state.stampType) return;

        var originalHTML = downloadBtn.innerHTML;
        downloadBtn.disabled  = true;
        downloadBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>Preparing...</span>';

        fetch('/stamp/download', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/pdf',
            },
            body: JSON.stringify(buildPayload()),
        })
        .then(function (res) {
            if (!res.ok) throw new Error('Download failed');
            return res.blob();
        })
        .then(function (blob) {
            var url = URL.createObjectURL(blob);
            var a   = document.createElement('a');
            a.href     = url;
            a.download = '[' + state.stampType.toUpperCase().replace(/_/g, ' ') + '] ' + state.docNo + '.pdf';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            showToast('Downloaded stamped copy.', 'success');
        })
        .catch(function () {
            showToast('Download failed. Please try again.', 'error');
        })
        .finally(function () {
            downloadBtn.innerHTML = originalHTML;
            checkReady();
        });
    });

    // ═══════════════════════════════════════════
    // APPLY (permanent — confirm first)
    // ═══════════════════════════════════════════

    applyBtn.addEventListener('click', function () {
        if (!state.selectedFile || !state.stampType) return;

        var cfg = STAMP_STYLES[state.stampType] || {};
        confirmLabel.textContent = cfg.title || state.stampType;
        confirmModal.style.display = 'flex';
    });

    confirmCancel.addEventListener('click', function () {
        confirmModal.style.display = 'none';
    });

    confirmModal.addEventListener('click', function (e) {
        if (e.target === confirmModal) confirmModal.style.display = 'none';
    });

    confirmApply.addEventListener('click', function () {
        if (!state.selectedFile || !state.stampType) return;

        var originalHTML = confirmApply.innerHTML;
        confirmApply.disabled  = true;
        confirmApply.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Applying...';

        fetch('/stamp/apply', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify(buildPayload()),
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                showToast('Stamp applied successfully!', 'success');
                confirmModal.style.display = 'none';
                hideStampModal();
                setTimeout(function () { location.reload(); }, 1200);
            } else {
                throw new Error(data.message || 'Failed to apply stamp');
            }
        })
        .catch(function (err) {
            showToast(err.message || 'Failed to apply stamp.', 'error');
        })
        .finally(function () {
            confirmApply.innerHTML = originalHTML;
            confirmApply.disabled  = false;
        });
    });

    // ═══════════════════════════════════════════
    // KEYBOARD: ESC to close
    // ═══════════════════════════════════════════

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (confirmModal.style.display === 'flex') {
            confirmModal.style.display = 'none';
        } else if (stampModal.style.display === 'flex') {
            hideStampModal();
        }
    });

    // ═══════════════════════════════════════════
    // TOAST
    // ═══════════════════════════════════════════

    var toastTimer = null;

    function showToast(message, type) {
        type = type || 'success';
        clearTimeout(toastTimer);

        toastMsg.textContent = message;
        toastEl.className    = 'st-toast st-toast-' + type;
        toastEl.querySelector('.st-toast-icon').className =
            'st-toast-icon fa-solid fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle');
        toastEl.style.display = 'flex';
        toastEl.style.opacity = '1';

        toastTimer = setTimeout(function () {
            toastEl.style.opacity = '0';
            setTimeout(function () {
                toastEl.style.display = 'none';
                toastEl.style.opacity = '1';
            }, 300);
        }, 3500);
    }

});