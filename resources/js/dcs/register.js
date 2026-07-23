// ══════════════════════════════════════════════
// STATE
// ══════════════════════════════════════════════
let allOffices = [];
let allDocTypes = [];
let allOriginators = [];
let syllabiGroupCounter = 0;
let syllabiCurrentStep = 1;
let relatedDocsCache = [];
let relatedDocsSelected = window.__existingRelatedDocs || [];
let relatedDocsSelectedPanelOpen = false;
let docNoDuplicate = false;


const ALLOWED_EXTENSIONS = ['pdf', 'docx'];
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

// ══════════════════════════════════════════════
// SHARED HELPERS
// (previously duplicated across upload / time / office-search code)
// ══════════════════════════════════════════════

/** Validate a File against the allowed extension / size rules. */
function checkFile(file) {
    const ext = file.name.split('.').pop().toLowerCase();
    if (!ALLOWED_EXTENSIONS.includes(ext)) {
        return { valid: false, ext, reason: 'type' };
    }
    if (file.size > MAX_FILE_SIZE) {
        return { valid: false, ext, reason: 'size', sizeMB: (file.size / (1024 * 1024)).toFixed(1) };
    }
    return { valid: true, ext };
}

function fileTypeErrorMessage(check, file) {
    return check.reason === 'type'
        ? '"' + check.ext + '" is not allowed. Only .pdf and .docx files are accepted.'
        : '"' + file.name + '" is ' + check.sizeMB + 'MB. Maximum file size is 10MB.';
}

/** Set a widget's icon to the pdf/docx glyph for the given extension. */
function setFileIcon(icon, ext) {
    icon.className = ext === 'pdf' ? 'fa-solid fa-file-pdf' : 'fa-solid fa-file-word';
    icon.style.color = ext === 'pdf' ? 'var(--reg-error)' : 'var(--reg-accent)';
}

function clearFileIcon(icon, label, originalText) {
    icon.className = 'fa-solid fa-cloud-arrow-up';
    icon.style.color = '';
    label.textContent = originalText;
    label.style.color = '';
    label.style.fontWeight = '';
}

/** Compute a start→end duration in minutes; null = incomplete inputs, {invalid:true} = end before start. */
function computeDuration(startDate, startTime, endDate, endTime) {
    if (!startDate || !startTime || !endDate || !endTime) return null;
    const start = new Date(startDate + "T" + startTime);
    const end = new Date(endDate + "T" + endTime);
    const diffMs = end - start;
    if (diffMs < 0) return { invalid: true };
    return { invalid: false, totalMinutes: Math.floor(diffMs / 60000) };
}

function formatDuration(totalMinutes) {
    const days = Math.floor(totalMinutes / 1440);
    const hours = Math.floor((totalMinutes % 1440) / 60);
    const minutes = totalMinutes % 60;
    if (days > 0) return days + "d " + hours + "hr " + minutes + "min";
    if (hours > 0) return hours + "hr " + minutes + "min";
    return minutes + " min";
}

function filterOffices(query) {
    const q = query.trim().toLowerCase();
    if (q.length < 1) return [];
    return allOffices.filter(o => o.office_name.toLowerCase().includes(q));
}

function emptyOfficeRowHTML() {
    return '<tr class="reg-empty-row">' +
                '<td colspan="3">' +
                    '<div class="reg-empty-state">' +
                        '<i class="fa-solid fa-building-circle-xmark"></i>' +
                        '<span>No offices added yet</span>' +
                    '</div>' +
                '</td>' +
            '</tr>';
}

function filterItems(list, labelKey, query) {
    const q = query.trim().toLowerCase();
    if (q.length < 1) return [];
    return list.filter(o => o[labelKey].toLowerCase().includes(q));
}

// ══════════════════════════════════════════════
// DOM READY — single consolidated handler
// ══════════════════════════════════════════════
document.addEventListener("DOMContentLoaded", async function () {
    // ── Fetch dropdown data ──
    try {
        const [offices, docTypes, versionTypes, approvalBodies, originators] = await Promise.all([
            fetch("/api/offices").then(r => r.json()),
            fetch("/api/doc-types").then(r => r.json()),
            fetch("/api/version-types").then(r => r.json()),
            fetch("/api/approval-bodies").then(r => r.json()),
            fetch("/api/originators").then(r => r.json()),
        ]);

        allOffices = offices;
        allDocTypes = docTypes;
        allOriginators = originators;

        const versionSelect = document.getElementById("versionType");
        versionTypes.forEach(v => versionSelect.add(new Option(v.version_name, v.version_id)));

        const docTypeSelect = document.getElementById("docType");
        docTypes.filter(d => !d.parent_id).forEach(d => {
            docTypeSelect.add(new Option(d.doc_type_name, d.doc_type_id));
        });

        ["dcnSourceUnit"].forEach(id => {
            const sel = document.getElementById(id);
            if (sel) offices.forEach(o => sel.add(new Option(o.office_name, o.office_id)));
        });

        const approvalSelect = document.getElementById("approvalBody");
        if (approvalSelect) approvalBodies.forEach(a => approvalSelect.add(new Option(a.approval_name, a.approval_body_id)));

    } catch (err) {
        console.error("Failed to load data:", err);
    }

    initSelectProtection();
    initFileInputs();

    // ── Lock revision field for new registrations ──
    const revField = document.getElementById('masterlistRevisionNo');

    // ── Version type change → apply revision mode ──
    const versionTypeEl = document.getElementById('versionType');
    if (versionTypeEl) {
        versionTypeEl.addEventListener('change', () => {
            applyRevisionMode();
        });
    }

    // ── Document No. live lookup (both modes) ──
    initDocNoLookup(revField);

    // ── Apply initial revision mode ──
    applyRevisionMode();

    // ── Auto-copy DRF title to Masterlist title ──
    const drfTitle = document.getElementById('drfTitle');
    const mlTitle = document.getElementById('masterlistDocTitle');
    if (drfTitle && mlTitle) {
        drfTitle.addEventListener('input', () => {
            mlTitle.value = drfTitle.value;
        });
    }

    createSourceUnitWidget({
        key: 'drf',
        widgetId: 'drfSourceUnitWidget',
        inputId: 'drfSourceUnitSearch',
        arrowId: 'drfSourceArrowBtn',
        resultsId: 'drfSourceResults',
        chipsId: 'drfSourceInlineChips',
        allowFreeText: false,
        officeFieldName: 'drfSourceUnit[]',
        nameFieldName: null,
        initial: []
    });

    createSourceUnitWidget({
        key: 'masterlist',
        widgetId: 'masterlistSourceWidget',
        inputId: 'masterlistSourceSearch',
        arrowId: 'masterlistSourceArrowBtn',
        resultsId: 'masterlistSourceSuggestions',
        chipsId: 'masterlistSourceInlineChips',
        allowFreeText: true,
        officeFieldName: 'masterlistOfficeIds[]',
        nameFieldName: 'masterlistOriginatorNames[]',
        initial: []
    });

    createSourceUnitWidget({
        key: 'masterlistOriginator',
        widgetId: 'masterlistOriginatorWidget',
        inputId: 'masterlistOriginatorSearch',
        arrowId: 'masterlistOriginatorArrowBtn',
        resultsId: 'masterlistOriginatorResults',
        chipsId: 'masterlistOriginatorInlineChips',
        allowFreeText: true,
        singleSelect: true,
        fieldName: 'masterlistOriginator',
        dataListGetter: () => allOriginators,
        idKey: 'originator_id',
        labelKey: 'originator_name',
        itemLabelPlural: 'originators',
        overlayTitle: 'Originator',
        initial: []
    });
});

// ══════════════════════════════════════════════
// DOCUMENT NO. LOOKUP
// ══════════════════════════════════════════════
function initDocNoLookup(revField) {
    const docNoInput = document.getElementById('masterlistDocNo');
    const hintEl = document.getElementById('docNoHint');
    if (!docNoInput) return;

    let docNoTimer = null;

    docNoInput.addEventListener('input', () => {
        clearTimeout(docNoTimer);
        const docNo = docNoInput.value.trim();

        if (!docNo) {
            handleEmptyDocNo(hintEl, revField);
            return;
        }

        if (hintEl) {
            hintEl.innerHTML = '<span style="color:#94a3b8"><i class="fa-solid fa-spinner fa-spin"></i> Checking document...</span>';
            hintEl.style.color = '';
            hintEl.dataset.valid = '';
        }

        // ← CHANGED: disable save in BOTH modes while checking
        setSaveEnabled(false);

        docNoTimer = setTimeout(() => runDocNoLookup(docNo, hintEl, revField), 500);
    });
}

function handleEmptyDocNo(hintEl, revField) {
    if (isRevisedMode()) {
        if (hintEl) {
            hintEl.innerHTML = '<span style="color:#94a3b8"><i class="fa-solid fa-circle-info"></i> Enter an existing document number to continue</span>';
            hintEl.style.color = '';
            hintEl.dataset.valid = '';
        }
        if (revField) {
            revField.value = '';
            revField.readOnly = false;
            revField.style.background = '';
            revField.style.cursor = '';
        }
        setSaveEnabled(false);
    } else {
        docNoDuplicate = false; // ← ADD THIS
        if (hintEl) {
            hintEl.innerHTML = '';
            hintEl.dataset.valid = '';
        }
        setSaveEnabled(true);
    }
}

// NOTE: kept the exact original markup/coloring rules per-branch below,
// since the hint styling differs subtly (span-wrapped vs colored <i> line) between states.
async function runDocNoLookup(docNo, hintEl, revField) {
    try {
        const docTypeId = document.getElementById('docType').value;
        const subTypeId = document.getElementById('subType').value;
        const url = '/register/check-docno?doc_no=' + encodeURIComponent(docNo) +
                    (docTypeId ? '&doc_type_id=' + docTypeId : '') +
                    (subTypeId ? '&sub_type_id=' + subTypeId : '');
        const res = await fetch(url);
        const data = await res.json();

        if (isRevisedMode()) {
            applyRevisedModeLookupResult(data, hintEl, revField);
        } else {
            applyNewModeLookupResult(data, hintEl, revField);
        }
    } catch (e) {
        console.error('DocNo lookup failed:', e);
        if (!isRevisedMode()) setSaveEnabled(true);
    }
}

function applyRevisedModeLookupResult(data, hintEl, revField) {
    if (data.exists) {
        setSaveEnabled(true);

        if (revField) {
            revField.value = data.next_rev;
            revField.readOnly = false;
            revField.style.background = '';
            revField.style.cursor = '';
            revField.setAttribute('min', data.next_rev);
            revField.setAttribute('title', 'Suggested: Rev ' + data.next_rev + ' (latest is ' + data.latest_rev + ')');
        }

        const titleField = document.getElementById('masterlistDocTitle');
        if (titleField && data.latest_title && !titleField.value.trim()) {
            titleField.value = data.latest_title;
        }

        if (data.latest_originator && window.__sourceWidgets.masterlist) window.__sourceWidgets.masterlist.seedFromString(data.latest_originator);

        if (hintEl) {
            hintEl.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + data.message;
            hintEl.style.color = '#16a34a';
            hintEl.dataset.valid = 'true';
        }
    } else {
        setSaveEnabled(false);

        if (revField) {
            revField.value = '';
            revField.readOnly = false;
            revField.style.background = '';
            revField.style.cursor = '';
        }

        if (hintEl) {
            const icon = data.wrong_type ? 'fa-triangle-exclamation' : 'fa-circle-exclamation';
            const color = data.wrong_type ? '#d97706' : '#dc2626';
            hintEl.innerHTML = '<i class="fa-solid ' + icon + '"></i> ' + data.message +
                '<br><span style="font-weight:400;font-size:11px;">You cannot save until you enter a valid document number.</span>';
            hintEl.style.color = color;
            hintEl.dataset.valid = data.wrong_type ? 'wrong_type' : 'not_found';
        }
    }
}

function applyNewModeLookupResult(data, hintEl, revField) {
    if (data.exists) {
        docNoDuplicate = true;
        setSaveEnabled(false);

        if (hintEl) {
            hintEl.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' +
                'This document number is already registered under <strong>' +
                (data.existing_type_name || 'this document type') +
                '</strong>. Use <strong>Revised Registration</strong> to create a new revision.' +
                '<br><span style="font-weight:400;font-size:11px;">You cannot save until you enter a unique document number.</span>';
            hintEl.style.color = '#dc2626';
            hintEl.dataset.valid = 'duplicate';
        }
    } else if (data.wrong_type) {
        docNoDuplicate = false;
        setSaveEnabled(true);
        if (hintEl) {
            hintEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + data.message +
                '<br><span style="font-weight:400;font-size:11px;">This number is registered under a different document type. You may continue.</span>';
            hintEl.style.color = '#d97706';
            hintEl.dataset.valid = 'different_type';
        }
    } else {
        docNoDuplicate = false;
        setSaveEnabled(true);
        if (hintEl) {
            hintEl.innerHTML = '<i class="fa-solid fa-circle-check"></i> Document number is available.';
            hintEl.style.color = '#16a34a';
            hintEl.dataset.valid = 'available';
        }
    }
}

// ══════════════════════════════════════════════
// REVISION MODE HELPERS
// ══════════════════════════════════════════════
function isRevisedMode() {
    const hidden = document.getElementById('registrationMode');
    return hidden && hidden.value === 'revised';
}

function applyRevisionMode() {
    const revField = document.getElementById('masterlistRevisionNo');
    const hintEl = document.getElementById('docNoHint');

    if (isRevisedMode()) {
        if (revField) {
            revField.value = '';
            revField.readOnly = false;
            revField.style.background = '';
            revField.style.cursor = '';
            revField.removeAttribute('min');
            revField.removeAttribute('title');
        }
        if (hintEl) {
            hintEl.innerHTML = '<span style="color:#94a3b8"><i class="fa-solid fa-circle-info"></i> Enter an existing document number to continue</span>';
            hintEl.style.color = '';
            hintEl.dataset.valid = '';
        }
    } else {
        docNoDuplicate = false;
        if (revField) {
            revField.value = 0;
            revField.readOnly = false;                   // ← editable
            revField.style.background = '';               // ← no locked look
            revField.style.cursor = '';                   // ← normal cursor
            revField.removeAttribute('min');
            revField.setAttribute('title', 'Suggested: 0 for new documents');
        }
        if (hintEl) {
            hintEl.innerHTML = '';
            hintEl.dataset.valid = '';
        }
    }
    setSaveEnabled(true);
}

function updateRegistrationMode() {
    const sel = document.getElementById('versionType');
    const hidden = document.getElementById('registrationMode');
    if (!sel || !hidden) return;

    const text = sel.options[sel.selectedIndex]?.text?.toLowerCase() || '';
    hidden.value = (text.includes('revised') || text.includes('revision') || text.includes('revise')) ? 'revised' : 'new';
    applyRevisionMode();
}

function setSaveEnabled(enabled) {
    // Don't re-enable if duplicate is detected
    if (enabled && docNoDuplicate) return;

    const saveBtn = document.getElementById('btnSaveDocument');
    if (!saveBtn) return;

    if (enabled) {
        saveBtn.disabled = false;
        saveBtn.removeAttribute('disabled');
        saveBtn.style.opacity = '';
        saveBtn.style.cursor = '';
        saveBtn.style.pointerEvents = '';
        saveBtn.style.filter = '';
    } else {
        saveBtn.disabled = true;
        saveBtn.setAttribute('disabled', 'disabled');
        saveBtn.style.opacity = '0.4';
        saveBtn.style.cursor = 'not-allowed';
        saveBtn.style.pointerEvents = 'none';
        saveBtn.style.filter = 'grayscale(1)';
    }
}

// ══════════════════════════════════════════════
// SHARED SOURCE UNIT WIDGET FACTORY
// Used identically by DRF and Masterlist — one implementation, no duplication.
// ══════════════════════════════════════════════
window.__sourceWidgets = {};

function createSourceUnitWidget(opts) {

    let selected = opts.initial || [];
    let idCounter = 0;
    let panelOpen = false;
    let scrollResizeHandler = null;

    const idKey = opts.idKey || 'office_id';
    const labelKey = opts.labelKey || 'office_name';
    const getList = opts.dataListGetter || (() => allOffices);
    const itemLabelPlural = opts.itemLabelPlural || 'offices';

    function isOfficeSelected(itemId) {
        return selected.some(i => i.type === 'office' && String(i.id) === String(itemId));
    }

    function addOffice(itemId) {
        const item = getList().find(o => o[idKey] == itemId);
        if (!item) return;
        if (opts.singleSelect) selected = [];
        else if (isOfficeSelected(itemId)) return;
        selected.push({ type: 'office', id: item[idKey], label: item[labelKey] });
        render();
    }

    function addFreeText(val) {
        if (!opts.allowFreeText || !val) return;
        if (opts.singleSelect) selected = [];
        idCounter++;
        selected.push({ type: 'name', id: 'n' + idCounter, label: val });
        render();
    }

    function removeItem(type, id) {
        selected = selected.filter(i => !(i.type === type && String(i.id) === String(id)));
        render();
    }

    function render() {
        const widget = document.getElementById(opts.widgetId);
        widget.querySelectorAll('input[data-source-hidden]').forEach(el => el.remove());
        selected.forEach(item => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.dataset.sourceHidden = 'true';
            input.name = opts.fieldName || (item.type === 'office' ? opts.officeFieldName : opts.nameFieldName);
            input.value = opts.fieldName ? item.label : (item.type === 'office' ? item.id : item.label);
            widget.appendChild(input);
        });

        const chipsEl = document.getElementById(opts.chipsId);
        if (chipsEl) {
            chipsEl.innerHTML = selected.length === 0
            ? '<div class="reg-reldocs-empty">Nothing selected yet</div>'
            : selected.map(item => `
                <div class="reg-inline-chip">
                    <span>${item.label}</span>
                    <button type="button" onclick="event.stopPropagation(); window.__sourceWidgets['${opts.key}'].removeItem('${item.type}','${item.id}')"><i class="fa-solid fa-xmark"></i></button>
                </div>
            `).join('');
        }

        if (window.__sourceOverlayConfigs[opts.key] && document.getElementById('universalSourceOverlay')) {
            refreshSourceOverlay(window.__sourceOverlayConfigs[opts.key]);
        }
    }

    function handleSearch(input) {
        const dropdown = document.getElementById(opts.resultsId);
        const q = input.value.trim();
        if (q.length < 1) { dropdown.style.display = 'none'; return; }
        const filtered = filterItems(getList(), labelKey, q).filter(o => !isOfficeSelected(o[idKey]));
        if (filtered.length === 0) {
            dropdown.innerHTML = `<div class="reg-reldocs-noresult">No matching ${itemLabelPlural} found</div>`;
            dropdown.style.display = 'block';
            return;
        }
        dropdown.innerHTML = filtered.map(o =>
            `<div onmousedown="window.__sourceWidgets['${opts.key}'].pick(${o[idKey]})">${o[labelKey]}</div>`
        ).join('');
        dropdown.style.display = 'block';
    }

    function handleKeydown(e, input) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
    }

    function pick(itemId) {
        addOffice(itemId);
        document.getElementById(opts.inputId).value = '';
        document.getElementById(opts.resultsId).style.display = 'none';
    }

    function openOverlay() {
        document.getElementById(opts.resultsId).style.display = 'none';
        openSourceOverlay({
            key: opts.key,
            title: opts.overlayTitle || 'Selected',
            searchPlaceholder: opts.allowFreeText ? `Search ${itemLabelPlural}, or type a name and press Enter...` : `Search ${itemLabelPlural}...`,
            allowFreeText: opts.allowFreeText,
            getList, idKey, labelKey, itemLabelPlural,
            getSelected: () => selected.map(i => ({ id: i.type + ':' + i.id, label: i.label })),
            addOffice,
            addFreeText,
            removeItem: (compoundId) => {
                const [type, id] = compoundId.split(':');
                removeItem(type, id);
            },
            onChange: render
        });
    }

    function positionPanel(panelEl, anchorEl) {
        const rect = anchorEl.getBoundingClientRect();
        panelEl.style.position = 'fixed';
        panelEl.style.top = (rect.bottom + 6) + 'px';
        panelEl.style.left = rect.left + 'px';
        panelEl.style.width = rect.width + 'px';
        panelEl.style.zIndex = 9500;
    }

    function togglePanel(e) {
        if (e) e.stopPropagation();
        document.getElementById(opts.resultsId).style.display = 'none';
        panelOpen = !panelOpen;
        const chipsEl = document.getElementById(opts.chipsId);
        if (!chipsEl) return;

        if (panelOpen) {
            document.body.appendChild(chipsEl);
            positionPanel(chipsEl, document.getElementById(opts.widgetId));
            chipsEl.style.display = 'block';

            scrollResizeHandler = () => positionPanel(chipsEl, document.getElementById(opts.widgetId));
            window.addEventListener('scroll', scrollResizeHandler, true);
            window.addEventListener('resize', scrollResizeHandler);
        } else {
            chipsEl.style.display = 'none';
            if (scrollResizeHandler) {
                window.removeEventListener('scroll', scrollResizeHandler, true);
                window.removeEventListener('resize', scrollResizeHandler);
                scrollResizeHandler = null;
            }
        }
    }

    function closePanel() {
        panelOpen = false;
        const chipsEl = document.getElementById(opts.chipsId);
        if (chipsEl) chipsEl.style.display = 'none';
        if (scrollResizeHandler) {
            window.removeEventListener('scroll', scrollResizeHandler, true);
            window.removeEventListener('resize', scrollResizeHandler);
            scrollResizeHandler = null;
        }
    }

    function reset() {
        selected = [];
        render();
    }

    function seedFromString(str) {
        if (!str || selected.length > 0) return;
        str.split(',').map(s => s.trim()).filter(Boolean).forEach(part => {
            const office = allOffices.find(o => o.office_name.toLowerCase() === part.toLowerCase());
            if (office) selected.push({ type: 'office', id: office.office_id, label: office.office_name });
            else { idCounter++; selected.push({ type: 'name', id: 'n' + idCounter, label: part }); }
        });
        render();
    }

    // wire the DOM once
    const inputEl = document.getElementById(opts.inputId);
    const arrowEl = document.getElementById(opts.arrowId);
    inputEl.addEventListener('input', function () { closePanel(); handleSearch(this); });
    inputEl.addEventListener('keydown', function (e) { handleKeydown(e, this); });
    arrowEl.addEventListener('click', togglePanel);
    document.addEventListener('click', function (e) {
        const widget = document.getElementById(opts.widgetId);
        const chipsEl = document.getElementById(opts.chipsId);
        const insideWidget = widget && widget.contains(e.target);
        const insidePanel = chipsEl && chipsEl.contains(e.target);
        if (!insideWidget && !insidePanel) {
            document.getElementById(opts.resultsId).style.display = 'none';
            closePanel();
        }
    });

    render();

    const api = { pick, removeItem, reset, seedFromString, get selected() { return selected; } };
    window.__sourceWidgets[opts.key] = api;
    return api;
}

// ══════════════════════════════════════════════
// UNIVERSAL SOURCE UNIT OVERLAY (fixed, never clipped)
// ══════════════════════════════════════════════
window.__sourceOverlayConfigs = window.__sourceOverlayConfigs || {};

function closeSourceOverlay() {
    const overlay = document.getElementById('universalSourceOverlay');
    const backdrop = document.getElementById('universalSourceBackdrop');
    if (overlay) overlay.remove();
    if (backdrop) backdrop.remove();
    document.removeEventListener('keydown', handleSourceOverlayEsc);
}
window.closeSourceOverlay = closeSourceOverlay;

function handleSourceOverlayEsc(e) {
    if (e.key === 'Escape') closeSourceOverlay();
}

function openSourceOverlay(config) {
    closeSourceOverlay();
    window.__sourceOverlayConfigs[config.key] = config;

    const backdrop = document.createElement('div');
    backdrop.id = 'universalSourceBackdrop';
    backdrop.className = 'drf-overlay-backdrop';
    backdrop.onclick = closeSourceOverlay;
    document.body.appendChild(backdrop);

    const overlay = document.createElement('div');
    overlay.id = 'universalSourceOverlay';
    overlay.innerHTML = `
        <div class="drf-overlay-header">
            <span>${config.title}</span>
            <button type="button" onclick="closeSourceOverlay()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="drf-overlay-search">
            <div class="reg-search">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                </svg>
                <input type="text" id="universalSourceOverlaySearch" placeholder="${config.searchPlaceholder}" autocomplete="off">
            </div>
        </div>
        <div id="universalSourceOverlaySuggestions" class="drf-overlay-suggestions"></div>
        <div class="drf-overlay-selected">
            <div class="drf-overlay-selected-label">Selected (<span id="universalSourceOverlayCount">0</span>)</div>
            <div id="universalSourceOverlayChips" class="drf-overlay-chips"></div>
        </div>
    `;
    document.body.appendChild(overlay);

    const searchInput = document.getElementById('universalSourceOverlaySearch');
    searchInput.addEventListener('input', () => handleSourceOverlaySearch(config, searchInput));

    document.addEventListener('keydown', handleSourceOverlayEsc);
    searchInput.focus();
    refreshSourceOverlay(config);
}

function refreshSourceOverlay(config) {
    const container = document.getElementById('universalSourceOverlayChips');
    const countEl = document.getElementById('universalSourceOverlayCount');
    if (!container) return;
    const items = config.getSelected();
    countEl.textContent = items.length;

    container.innerHTML = items.length === 0
        ? '<div class="drf-overlay-empty">No offices selected yet</div>'
        : items.map(item => `
            <div class="drf-overlay-chip">
                <i class="fa-solid fa-building"></i>
                <span>${item.label}</span>
                <button type="button" onclick="removeSourceOverlayItem('${config.key}', '${item.id}')"><i class="fa-solid fa-xmark"></i></button>
            </div>
        `).join('');

    if (config.onChange) config.onChange();
}

function handleSourceOverlaySearch(config, input) {
    const dropdown = document.getElementById('universalSourceOverlaySuggestions');
    const q = input.value.trim();
    if (q.length < 1) { dropdown.style.display = 'none'; return; }

    const selectedIds = config.getSelected()
        .map(i => i.id.split(':').pop());
    const filtered = filterItems(config.getList(), config.labelKey, q)
        .filter(o => !selectedIds.includes(String(o[config.idKey])));

    if (filtered.length === 0) {
        dropdown.innerHTML = `<div class="drf-overlay-noresult">No matching ${config.itemLabelPlural} found</div>`;
        dropdown.style.display = 'block';
        return;
    }

    dropdown.innerHTML = filtered.map(o =>
        `<div onmousedown="pickSourceOverlayOffice('${config.key}', ${o[config.idKey]})">${o[config.labelKey]}</div>`
    ).join('');
    dropdown.style.display = 'block';
}

window.pickSourceOverlayOffice = function (key, officeId) {
    const config = window.__sourceOverlayConfigs[key];
    if (!config) return;
    config.addOffice(officeId);
    document.getElementById('universalSourceOverlaySearch').value = '';
    document.getElementById('universalSourceOverlaySuggestions').style.display = 'none';
    refreshSourceOverlay(config);
};

window.removeSourceOverlayItem = function (key, id) {
    const config = window.__sourceOverlayConfigs[key];
    if (!config) return;
    config.removeItem(id);
    refreshSourceOverlay(config);
};

document.addEventListener('DOMContentLoaded', renderRelatedDocsChips);

window.handleRelatedDocFocus = function () {
    closeRelatedDocsSelectedPanel();
};

window.handleRelatedDocSearch = function (input) {
    clearTimeout(window.__relatedDocsTimer);
    const q = input.value.trim();
    const dropdown = document.getElementById('relatedDocsResults');

    closeRelatedDocsSelectedPanel();

    if (q.length < 1) { dropdown.style.display = 'none'; return; }

    window.__relatedDocsTimer = setTimeout(async () => {
        try {
            const excludeId = document.getElementById('requestId')?.value || '';
            const url = '/api/documents/search?q=' + encodeURIComponent(q)
                + (excludeId ? '&exclude_request_id=' + excludeId : '');
            const data = await fetch(url).then(r => r.json());
            relatedDocsCache = data.filter(d => !relatedDocsSelected.some(s => s.masterlist_id === d.masterlist_id));

            if (relatedDocsCache.length === 0) {
                dropdown.innerHTML = '<div class="reg-reldocs-noresult">No matching documents found</div>';
                dropdown.style.display = 'block';
                return;
            }
            dropdown.innerHTML = relatedDocsCache
                .map(d => `<div onmousedown="pickRelatedDoc(${d.masterlist_id})">${d.label}</div>`)
                .join('');
            dropdown.style.display = 'block';
        } catch (e) { console.error('Related doc search failed:', e); }
    }, 300);
};

window.pickRelatedDoc = function (id) {
    const doc = relatedDocsCache.find(d => d.masterlist_id === id);
    if (!doc || relatedDocsSelected.some(d => d.masterlist_id === id)) return;
    relatedDocsSelected.push(doc);
    renderRelatedDocsChips();

    const input = document.getElementById('relatedDocsSearch');
    input.value = '';
    document.getElementById('relatedDocsResults').style.display = 'none';
};

window.removeRelatedDoc = function (id, event) {
    if (event) event.stopPropagation();
    relatedDocsSelected = relatedDocsSelected.filter(d => d.masterlist_id !== id);
    renderRelatedDocsChips();
};

window.toggleRelatedDocsSelected = function (e) {
    if (e) e.stopPropagation();
    document.getElementById('relatedDocsResults').style.display = 'none';

    relatedDocsSelectedPanelOpen = !relatedDocsSelectedPanelOpen;
    const panel = document.getElementById('relatedDocsSelectedPanel');
    const icon = document.getElementById('relatedDocsArrowIcon');
    panel.style.display = relatedDocsSelectedPanelOpen ? 'block' : 'none';
    icon.style.transform = relatedDocsSelectedPanelOpen ? 'rotate(180deg)' : '';
};

function closeRelatedDocsSelectedPanel() {
    relatedDocsSelectedPanelOpen = false;
    const panel = document.getElementById('relatedDocsSelectedPanel');
    const icon = document.getElementById('relatedDocsArrowIcon');
    if (panel) panel.style.display = 'none';
    if (icon) icon.style.transform = '';
}

function renderRelatedDocsChips() {
    const container = document.getElementById('relatedDocsChips');
    if (!container) return;

    if (relatedDocsSelected.length === 0) {
        container.innerHTML = '<div class="reg-reldocs-empty">No documents selected yet</div>';
        return;
    }

    container.innerHTML = relatedDocsSelected.map(d => `
        <div class="reg-reldocs-chip">
            <input type="hidden" name="relatedDocumentIds[]" value="${d.masterlist_id}">
            <span class="reg-reldocs-chip-title">${d.doc_title}</span>
            <span class="reg-reldocs-chip-no">${d.doc_no || ''}</span>
            <button type="button" onclick="removeRelatedDoc(${d.masterlist_id}, event)"><i class="fa-solid fa-xmark"></i></button>
        </div>`).join('');
}

document.addEventListener('click', function (e) {
    const widget = document.getElementById('relatedDocsWidget');
    if (widget && !widget.contains(e.target)) {
        document.getElementById('relatedDocsResults').style.display = 'none';
        closeRelatedDocsSelectedPanel();
    }
});

// ══════════════════════════════════════════════
// SELECT PROTECTION
// (guards against selects being changed by anything other than a real user gesture)
// ══════════════════════════════════════════════
function initSelectProtection() {
    const selects = {
        versionType: { el: document.getElementById("versionType"), handler: handleVersionChange },
        docType: { el: document.getElementById("docType"), handler: handleDocTypeChange },
        subType: { el: document.getElementById("subType"), handler: validateChecklistState },
    };

    const userTouched = {};

    Object.keys(selects).forEach(key => {
        userTouched[key] = false;
        const { el, handler } = selects[key];

        el.addEventListener("pointerdown", () => { userTouched[key] = true; });
        el.addEventListener("keydown", () => { userTouched[key] = true; });

        el.addEventListener("change", function () {
            if (!userTouched[key]) {
                this.value = this.dataset.lastValid || "";
                return;
            }
            userTouched[key] = false;
            this.dataset.lastValid = this.value;
            handler();
        });

        el.addEventListener("input", function () {
            if (!userTouched[key]) {
                this.value = this.dataset.lastValid || "";
            }
        });
    });
}

// ══════════════════════════════════════════════
// FILE INPUTS
// ══════════════════════════════════════════════
function initFileInputs() {
    document.querySelectorAll('.reg-upload').forEach(container => {
        const input = container.querySelector('input[type="file"]');
        if (!input || input.dataset.bound) return;
        input.setAttribute('accept', '.pdf,.docx');
        input.dataset.bound = "true";

        const icon = container.querySelector('i');
        const label = container.querySelector('span');
        const originalText = label ? label.textContent : 'Choose .pdf or .docx file';

        input.addEventListener('change', function () {
            processUploadAreaFile(this, container, icon, label, originalText);
        });

        container.addEventListener('dragover', function (e) {
            e.preventDefault();
            container.classList.add('reg-upload-drag');
        });

        container.addEventListener('dragleave', function () {
            container.classList.remove('reg-upload-drag');
        });

        container.addEventListener('drop', function (e) {
            e.preventDefault();
            container.classList.remove('reg-upload-drag');
            if (e.dataTransfer.files.length > 0) {
                input.files = e.dataTransfer.files;
                processUploadAreaFile(input, container, icon, label, originalText);
            }
        });
    });

    document.querySelectorAll('.reg-upload-cell').forEach(cell => {
        const input = cell.querySelector('input[type="file"]');
        if (!input || input.dataset.bound) return;
        input.setAttribute('accept', '.pdf,.docx');
        input.dataset.bound = "true";

        input.addEventListener('change', function () {
            processUploadCellFile(this, cell);
        });
    });

    document.querySelectorAll('#revisionTableBody input[type="file"]').forEach(input => {
        if (input.dataset.bound) return;
        input.setAttribute('accept', '.pdf,.docx');
        input.dataset.bound = "true";
        input.addEventListener('change', function () {
            validateTableFile(this);
        });
    });
}

function processUploadAreaFile(input, container, icon, label, originalText) {
    container.classList.remove('reg-upload-success', 'reg-upload-error', 'reg-upload-drag');
    removeExistingError(container);
    removeExistingRemoveBtn(container);

    if (!input.files || !input.files[0]) {
        resetUploadArea(container, icon, label, originalText);
        return;
    }

    const file = input.files[0];
    const check = checkFile(file);

    if (!check.valid) {
        showUploadFieldError(container, fileTypeErrorMessage(check, file));
        container.classList.add('reg-upload-error');
        clearFileIcon(icon, label, originalText);
        input.value = '';
        return;
    }

    container.classList.add('reg-upload-success');
    container.style.borderColor = 'var(--reg-success-border)';
    container.style.background = 'var(--reg-success-bg)';
    container.style.borderStyle = 'solid';

    setFileIcon(icon, check.ext);
    label.textContent = file.name;
    label.style.color = 'var(--reg-success)';
    label.style.fontWeight = '600';

    addRemoveBtn(container, input, icon, label, originalText);
}

function showUploadFieldError(container, message) {
    removeExistingError(container);

    const parent = container.closest('.reg-field') || container.parentElement;
    if (parent) {
        const err = document.createElement('div');
        err.className = 'reg-file-error';
        err.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + message;
        parent.appendChild(err);
    }

    container.style.animation = 'none';
    container.offsetHeight;
    container.style.animation = 'shake 0.4s ease';
}

function resetUploadArea(container, icon, label, originalText) {
    container.classList.remove('reg-upload-success', 'reg-upload-error', 'reg-upload-drag');
    container.style.borderColor = '';
    container.style.background = '';
    container.style.borderStyle = '';
    container.style.animation = '';

    clearFileIcon(icon, label, originalText);

    removeExistingRemoveBtn(container);
    removeExistingError(container);
}

function addRemoveBtn(container, input, icon, label, originalText) {
    removeExistingRemoveBtn(container);

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'reg-file-remove';
    btn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
    btn.title = 'Remove file';

    btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        input.value = '';
        resetUploadArea(container, icon, label, originalText);
    });

    container.appendChild(btn);
}

function removeExistingRemoveBtn(container) {
    const old = container.querySelector('.reg-file-remove');
    if (old) old.remove();
}

function removeExistingError(container) {
    const old = container.querySelector('.reg-file-error');
    if (old) old.remove();
}

function resetUploadCell(cell, icon, label, originalText) {
    cell.classList.remove('reg-upload-cell-success', 'reg-upload-cell-error');
    cell.style.borderColor = '';
    cell.style.background = '';
    clearFileIcon(icon, label, originalText);
    removeExistingError(cell);
}

function processUploadCellFile(input, cell) {
    const label = cell.querySelector('span');
    const icon = cell.querySelector('i');
    const originalText = 'No file chosen';

    resetUploadCell(cell, icon, label, originalText);

    if (!input.files || !input.files[0]) return;

    const file = input.files[0];
    const check = checkFile(file);

    if (!check.valid) {
        showUploadFieldError(cell, '.pdf and .docx only');
        clearFileIcon(icon, label, originalText);
        input.value = '';
        return;
    }

    cell.classList.add('reg-upload-cell-success');
    cell.style.borderColor = 'var(--reg-success-border)';
    cell.style.background = 'var(--reg-success-bg)';
    setFileIcon(icon, check.ext);
    label.textContent = file.name;
    label.style.color = 'var(--reg-success)';
    label.style.fontWeight = '600';
}

function validateTableFile(input) {
    const td = input.closest('td');
    removeExistingError(td);

    if (!input.files || !input.files[0]) return;

    const file = input.files[0];
    const check = checkFile(file);

    if (!check.valid) {
        const errDiv = document.createElement('div');
        errDiv.className = 'reg-file-error';
        errDiv.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' +
            (check.reason === 'type' ? '.pdf and .docx only' : 'Max 10MB');
        td.appendChild(errDiv);
        input.value = '';
    }
}

// ══════════════════════════════════════════════
// VERSION CHANGE
// ══════════════════════════════════════════════
async function handleVersionChange() {
    const versionSelect = document.getElementById("versionType");
    const versionId = versionSelect.value;
    const docTypeSelect = document.getElementById("docType");
    const subTypeSelect = document.getElementById("subType");

    ["section-1", "section-2", "section-3", "section-4", "section-5", "section-approval", "section-syllabi", "formActions"].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = "none";
    });

    docTypeSelect.value = "";
    docTypeSelect.disabled = !versionId;
    subTypeSelect.innerHTML = '<option value="" selected disabled>Select sub-type</option>';
    subTypeSelect.disabled = true;
    disableApproval();

    if (!versionId) {
        renderChecklists([
            { checklist_id: 1, checklist_name: "Document Request Form" },
            { checklist_id: 2, checklist_name: "Document Change Notice" },
            { checklist_id: 3, checklist_name: "Masterlist Registration" },
            { checklist_id: 4, checklist_name: "Document Retrieval" },
            { checklist_id: 5, checklist_name: "Document Distribution" },
        ], true);
        applyRevisionMode();
        return;
    }

    try {
        const res = await fetch("/api/checklist-versions/" + versionId);
        const checklists = await res.json();
        renderChecklists(checklists, true);
    } catch (err) {
        console.error("Failed to load checklists:", err);
    }

    updateRegistrationMode();
}

// ══════════════════════════════════════════════
// DOC TYPE CHANGE
// ══════════════════════════════════════════════
function resetTextLikeInputs(el) {
    el.querySelectorAll('input[type="text"], input[type="number"], input[type="date"], input[type="time"], textarea').forEach(input => {
        input.value = '';
    });
    el.querySelectorAll('select').forEach(sel => { sel.selectedIndex = 0; });
}

function resetFileWidgetsIn(el) {
    el.querySelectorAll('input[type="file"]').forEach(file => {
        file.value = '';
        const container = file.closest('.reg-upload');
        if (container) {
            const icon = container.querySelector('i');
            const label = container.querySelector('span');
            if (icon && label) resetUploadArea(container, icon, label, 'Choose .pdf or .docx file');
        }
    });

    el.querySelectorAll('.reg-upload-cell').forEach(cell => {
        const icon = cell.querySelector('i');
        const label = cell.querySelector('span');
        if (icon && label) resetUploadCell(cell, icon, label, 'No file chosen');
    });
}

function handleDocTypeChange() {
    docNoDuplicate = false;
    setSaveEnabled(true);
    const docTypeSelect = document.getElementById("docType");
    const docTypeId = parseInt(docTypeSelect.value);
    const subTypeSelect = document.getElementById("subType");
    const syllabiSection = document.getElementById("section-syllabi");

    subTypeSelect.innerHTML = '<option value="" selected disabled>Select sub-type</option>';
    subTypeSelect.disabled = true;

    if (syllabiSection) syllabiSection.style.display = "none";

    const hintEl = document.getElementById('docNoHint');
    if (hintEl) {
        hintEl.innerHTML = '';
        hintEl.style.color = '';
        hintEl.dataset.valid = '';
    }

    ["section-1", "section-2", "section-3", "section-4", "section-5", "section-approval", "formActions"].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.style.display = "none";
            resetTextLikeInputs(el);
            resetFileWidgetsIn(el);
        }
    });

    ['masterlistTimeSpentDisplay', 'retrievalTimeSpentDisplay', 'distributionTimeSpentDisplay'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.value = '--';
            el.style.color = '';
        }
    });
    ['masterlistTimeSpent', 'retrievalTimeSpent', 'distributionTimeSpent'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });

    if (window.__sourceWidgets.drf) window.__sourceWidgets.drf.reset();
    const drfSearchInput = document.getElementById('drfSourceUnitSearch');
    if (drfSearchInput) drfSearchInput.value = '';

    ['retrievalBody', 'distBody'].forEach(tbodyId => {
        const tbody = document.getElementById(tbodyId);
        if (tbody) tbody.innerHTML = emptyOfficeRowHTML();
    });
    ['totalRetrievalCopies', 'totalDistCopies'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = '0';
    });

    const revisionBody = document.getElementById('revisionTableBody');
    if (revisionBody) {
        revisionBody.innerHTML =
            '<tr>' +
                '<td><input type="text" name="documentTitle[]" placeholder="Enter Document Title"></td>' +
                '<td><input type="text" name="documentNo[]" placeholder="Enter Document No."></td>' +
                '<td><input type="date" name="effectiveDate[]"></td>' +
                '<td><input type="number" name="revisionNo[]" placeholder="0"></td>' +
                '<td><input type="file" name="scannedCopy[]" accept=".pdf,.docx"></td>' +
                '<td><input type="text" name="revisionPurpose[]" placeholder="Enter Purpose"></td>' +
                '<td><button type="button" class="reg-row-del" onclick="this.closest(\'tr\').remove()"><i class="fa-solid fa-trash-can"></i></button></td>' +
            '</tr>';
        bindTableFileInput(revisionBody.querySelector('input[type="file"]'));
    }

    const syllabiBody = document.getElementById('syllabiTableBody');
    if (syllabiBody) {
        syllabiBody.innerHTML = '';
        syllabiGroupCounter = 0;
        addSyllabiRow();
        setSyllabiStep(1);
    }

    ['syllabiDocNo', 'syllabiDocTitle', 'syllabiEffectivityDate', 'syllabiDeadline'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });

    const collegeSel = document.getElementById('syllabiCollege');
    const programSel = document.getElementById('syllabiProgram');
    const semSel = document.getElementById('syllabiSemester');
    const sySel = document.getElementById('syllabiSchoolYear');
    if (collegeSel) collegeSel.selectedIndex = 0;
    if (programSel) { programSel.innerHTML = '<option value="" selected disabled>Select program</option>'; programSel.disabled = true; }
    if (semSel) { semSel.selectedIndex = 0; semSel.disabled = true; }
    if (sySel) { sySel.selectedIndex = 0; sySel.disabled = true; }

    disableApproval();
    clearValidation();

    if (!docTypeId) {
        lockChecklist();
        return;
    }

    const children = allDocTypes.filter(d => d.parent_id === docTypeId);

    if (children.length > 0) {
        children.forEach(c => subTypeSelect.add(new Option(c.doc_type_name, c.doc_type_id)));
        subTypeSelect.disabled = false;
        lockChecklist();
    } else {
        unlockChecklist();
    }
}

function bindTableFileInput(fileInput) {
    if (!fileInput) return;
    fileInput.dataset.bound = "true";
    fileInput.addEventListener('change', function () { validateTableFile(this); });
}

// ══════════════════════════════════════════════
// SUB-TYPE CHANGE
// ══════════════════════════════════════════════
function validateChecklistState() {
    const subTypeSelect = document.getElementById("subType");
    const subTypeId = subTypeSelect.value;
    const subTypeData = allDocTypes.find(d => d.doc_type_id == subTypeId);

    const syllabiSection = document.getElementById("section-syllabi");
    if (syllabiSection) syllabiSection.style.display = "none";

    if (!subTypeId) {
        lockChecklist();
        return;
    }

    unlockChecklist();
    const isSyllabi = subTypeData && subTypeData.doc_type_name.toLowerCase() === "syllabi";
    if (!isSyllabi) return;

    const section3 = document.getElementById("section-3");
    if (section3) section3.style.display = "none";

    if (!syllabiSection) return;

    syllabiSection.style.display = "block";
    setSyllabiStep(1);
    loadSyllabiContextDropdowns();
    if (document.getElementById("syllabiTableBody").children.length === 0) {
        addSyllabiRow();
    }
    setTimeout(() => {
        syllabiSection.querySelectorAll('.reg-upload-cell').forEach(cell => {
            const input = cell.querySelector('input[type="file"]');
            if (input && !input.dataset.bound) {
                input.setAttribute('accept', '.pdf,.docx');
                input.dataset.bound = "true";
                input.addEventListener('change', function () {
                    processUploadCellFile(this, cell);
                });
            }
        });
    }, 50);
}

// ══════════════════════════════════════════════
// CHECKLIST
// ══════════════════════════════════════════════
function renderChecklists(checklists, disabled) {
    const container = document.getElementById("dynamicCheckboxes");
    container.innerHTML = "";

    checklists.forEach(c => {
        const label = document.createElement("label");
        label.className = "reg-check-item";

        const cb = document.createElement("input");
        cb.type = "checkbox";
        cb.name = "checklists[]";
        cb.value = c.checklist_id;
        cb.autocomplete = "off";
        if (disabled) cb.disabled = true;

        const span = document.createElement("span");
        span.textContent = c.checklist_name;

        label.appendChild(cb);
        label.appendChild(span);

        let userTouched = false;

        label.addEventListener("pointerdown", function (e) {
            if (e.target.disabled) return;
            userTouched = true;
        });

        label.addEventListener("keydown", function () {
            if (cb.disabled) return;
            userTouched = true;
        });

        cb.addEventListener("change", function () {
            if (!userTouched) {
                this.checked = (this.dataset.lastChecked === "true");
                return;
            }
            userTouched = false;
            this.dataset.lastChecked = this.checked ? "true" : "false";
            toggleSection(parseInt(this.value), this.checked);
        });

        container.appendChild(label);
    });
}

function lockChecklist() {
    const container = document.getElementById("dynamicCheckboxes");
    container.querySelectorAll("input[type='checkbox']").forEach(cb => {
        cb.disabled = true;
        cb.checked = false;
        cb.dataset.lastChecked = "false";
        toggleSection(parseInt(cb.value), false);
    });
    hideFormActions();
    disableApproval();

    const syllabiSection = document.getElementById("section-syllabi");
    if (syllabiSection) syllabiSection.style.display = "none";
}

function unlockChecklist() {
    const container = document.getElementById("dynamicCheckboxes");
    container.querySelectorAll("input[type='checkbox']").forEach(cb => {
        cb.disabled = false;
        cb.checked = true;
        cb.dataset.lastChecked = "true";
        toggleSection(parseInt(cb.value), true);
    });
    showFormActions();
    enableApproval();
    setTimeout(initFileInputs, 100);
}

// ══════════════════════════════════════════════
// TOGGLE SECTIONS
// ══════════════════════════════════════════════
window.toggleSection = function (checklistId, show) {
    const sectionMap = { 1: "section-1", 2: "section-2", 3: "section-3", 4: "section-4", 5: "section-5" };
    const sectionId = sectionMap[checklistId];
    if (!sectionId) return;

    const el = document.getElementById(sectionId);
    if (!el) return;

    el.style.display = show ? "block" : "none";
    if (show) setTimeout(initFileInputs, 50);
};

// ══════════════════════════════════════════════
// FORM ACTIONS
// ══════════════════════════════════════════════
function showFormActions() {
    const el = document.getElementById("formActions");
    if (el) el.style.display = "flex";
}

function hideFormActions() {
    const el = document.getElementById("formActions");
    if (el) el.style.display = "none";
}

// ══════════════════════════════════════════════
// AUTO TIME SPENT CALCULATION
// ══════════════════════════════════════════════
function calcTimeDiff(startDateId, startTimeId, endDateId, endTimeId, displayId, hiddenId) {
    const startDate = document.getElementById(startDateId).value;
    const startTime = document.getElementById(startTimeId).value;
    const endDate = document.getElementById(endDateId).value;
    const endTime = document.getElementById(endTimeId).value;

    const display = document.getElementById(displayId);
    const hidden = document.getElementById(hiddenId);

    const result = computeDuration(startDate, startTime, endDate, endTime);

    if (!result) {
        display.value = "--";
        display.style.color = "";
        hidden.value = "";
        return;
    }
    if (result.invalid) {
        display.value = "Invalid";
        display.style.color = "var(--reg-error)";
        hidden.value = "";
        return;
    }

    display.style.color = "";
    display.value = formatDuration(result.totalMinutes);
    hidden.value = String(result.totalMinutes);
}

window.calcRetrievalTimeSpent = function () {
    calcTimeDiff("retrievalFormDate", "retrievalFormTime", "retrievalDate", "retrievalTime", "retrievalTimeSpentDisplay", "retrievalTimeSpent");
};

window.calcDistributionTimeSpent = function () {
    calcTimeDiff("distributionFormDate", "distributionFormTime", "distributionDate", "distributionTime", "distributionTimeSpentDisplay", "distributionTimeSpent");
};

window.calcMasterlistTimeSpent = function () {
    calcTimeDiff("masterlistReceiptDate", "masterlistReceiptTime", "masterlistRegisteredDate", "masterlistRegisteredTime", "masterlistTimeSpentDisplay", "masterlistTimeSpent");
};

// ══════════════════════════════════════════════
// FORM VALIDATION
// ══════════════════════════════════════════════
function clearValidation() {
    document.querySelectorAll(".reg-input-error").forEach(el => el.classList.remove("reg-input-error"));
    document.querySelectorAll(".reg-field-error").forEach(el => el.remove());
}

function markFieldError(fieldId, message) {
    // Fix 1: also search by [name] so dynamic hidden inputs are found
    const field = fieldId
        ? (document.getElementById(fieldId) || document.querySelector('[name="' + fieldId + '"]'))
        : null;

    if (field) {
        field.classList.add("reg-input-error");
        const parent = field.closest(".reg-field") || field.closest("td") || field.parentElement;
        if (parent && !parent.querySelector(".reg-field-error")) {
            const err = document.createElement("div");
            err.className = "reg-field-error";
            err.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + message;
            parent.appendChild(err);
        }
    } else {
        // Fix 2: use appendChild — .reg-table-wrap is nested, not a direct child of #section-syllabi
        const syllabiSection = document.getElementById("section-syllabi");
        if (syllabiSection && !syllabiSection.querySelector('.reg-field-error')) {
            const err = document.createElement("div");
            err.className = "reg-field-error";
            err.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + message;
            syllabiSection.appendChild(err);
        }
    }
}

function markTableError(tableId, message) {
    const table = document.getElementById(tableId);
    if (!table) return;

    const parent = table.closest(".reg-field") || table.closest(".reg-table-wrap")?.parentElement;
    if (parent && !parent.querySelector(".reg-field-error")) {
        const err = document.createElement("div");
        err.className = "reg-field-error";
        err.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + message;
        parent.appendChild(err);
    }
}

function markSearchError(inputId, message) {
    const field = document.getElementById(inputId);
    if (!field) return;

    field.classList.add("reg-input-error");

    const parent = field.closest(".reg-search")?.parentElement;
    if (parent && !parent.querySelector(".reg-field-error")) {
        const err = document.createElement("div");
        err.className = "reg-field-error";
        err.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + message;
        parent.appendChild(err);
    }
}

function markChecklistError(message) {
    const container = document.getElementById("dynamicCheckboxes");
    if (!container) return;

    const parent = container.closest(".reg-panel-bottom") || container.parentElement;
    if (parent && !parent.querySelector(".reg-field-error")) {
        const err = document.createElement("div");
        err.className = "reg-field-error";
        err.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + message;
        parent.appendChild(err);
    }
}

/** Pushes an error into `errors` when a required text-like field is blank. */
function requireField(errors, id, message) {
    const el = document.getElementById(id);
    const val = el ? (el.value || '').trim() : '';
    if (!val) errors.push({ field: id, message });
    return val;
}

function validateForm() {
    clearValidation();
    const errors = [];

    if (!document.getElementById("versionType").value) {
        errors.push({ field: "versionType", message: "Version Type is required." });
    }
    if (!document.getElementById("docType").value) {
        errors.push({ field: "docType", message: "Document Type is required." });
    }

    if (errors.length > 0) return errors;

    const hasChildren = allDocTypes.some(d => d.parent_id == document.getElementById("docType").value);
    if (hasChildren && !document.getElementById("subType").value) {
        errors.push({ field: "subType", message: "Sub-Type is required." });
        return errors;
    }

    const checkedBoxes = document.querySelectorAll("#dynamicCheckboxes input[type='checkbox']:checked:not(:disabled)");
    if (checkedBoxes.length === 0) {
        errors.push({ field: "dynamicCheckboxes", message: "Please check at least one checklist to proceed.", type: "checklist" });
        return errors;
    }

    validateDrfSection(errors);
    validateDcnSection(errors);
    validateMasterlistSection(errors);
    validateRetrievalSection(errors);
    validateDistributionSection(errors);
    validateSyllabiSection(errors);

    return errors;
}

function sectionVisible(id) {
    const el = document.getElementById(id);
    return el && el.style.display !== "none";
}

function validateDrfSection(errors) {
    if (!sectionVisible("section-1")) return;
    requireField(errors, "drfNo", "DRF No. is required.");
    requireField(errors, "drfDate", "DRF Date is required.");
    requireField(errors, "drfReceiptDate", "Date Receipt is required.");
    requireField(errors, "drfTime", "Time Receipt is required.");
    requireField(errors, "drfTitle", "Document Title is required.");

    if (!window.__sourceWidgets.drf || window.__sourceWidgets.drf.selected.length === 0) {
        errors.push({ field: "drfSourceUnitSearch", message: "Source Unit is required." });
    }
}

function validateDcnSection(errors) {
    if (!sectionVisible("section-2")) return;
    requireField(errors, "dcnNumber", "DCN No. is required.");
    requireField(errors, "noticeDate", "DCN Date is required.");
    requireField(errors, "receiptDate", "DCN Receipt Date is required.");
    requireField(errors, "receiptTime", "DCN Receipt Time is required.");
    requireField(errors, "dcnSourceUnit", "Source Unit is required.");

    let hasRevision = false;
    document.querySelectorAll("#revisionTableBody tr").forEach(row => {
        const title = row.querySelector('input[name="documentTitle[]"]');
        if (title && title.value.trim()) hasRevision = true;
    });
    if (!hasRevision) errors.push({ field: "revisionTableBody", message: "At least one revision document is required.", type: "table" });
}

function validateMasterlistSection(errors) {
    if (!sectionVisible("section-3")) return;

    requireField(errors, "masterlistDocNo", "Document No. is required.");
    requireField(errors, "masterlistDocTitle", "Document Title is required.");
    requireField(errors, "deadlineOfSubmission", "Deadline of Submission is required.");
    requireField(errors, "masterlistReceiptDate", "Document Receipt Date is required.");
    requireField(errors, "masterlistReceiptTime", "Document Receipt Time is required.");
    requireField(errors, "masterlistRegisteredDate", "Document Registered Date is required.");
    requireField(errors, "masterlistRegisteredTime", "Document Registered Time is required.");
    requireField(errors, "masterlistEffectivityDate", "Effectivity Date is required.");

    const revVal = document.getElementById("masterlistRevisionNo").value.trim();
    if (revVal === '' || (revVal !== '0' && isNaN(parseInt(revVal)))) {
        errors.push({ field: "masterlistRevisionNo", message: "Revision No. is required." });
    }

    requireField(errors, "masterlistNoOfPages", "No. of Pages is required.");
    if (!window.__sourceWidgets.masterlistOriginator || window.__sourceWidgets.masterlistOriginator.selected.length === 0) {
        errors.push({ field: "masterlistOriginatorSearch", message: "Originator is required." });
    }
    if (!window.__sourceWidgets.masterlist || window.__sourceWidgets.masterlist.selected.length === 0) {
        errors.push({ field: "masterlistSourceSearch", message: "Source Unit is required." });
    }
    requireField(errors, "briefPurpose", "Brief Purpose is required.");

    const mlTimeDisplay = document.getElementById("masterlistTimeSpentDisplay");
    if (mlTimeDisplay.value === "Invalid") {
        errors.push({ field: "masterlistRegisteredDate", message: "Time is invalid. Document Registered must be after Document Receipt." });
    }
}

function validateRetrievalSection(errors) {
    if (!sectionVisible("section-4")) return;

    requireField(errors, "retrievalFormDate", "Retrieval Form Date is required.");
    requireField(errors, "retrievalFormTime", "Retrieval Form Time is required.");
    requireField(errors, "retrievalDate", "Retrieval Date is required.");
    requireField(errors, "retrievalTime", "Retrieval Time is required.");

    const retTimeDisplay = document.getElementById("retrievalTimeSpentDisplay");
    if (retTimeDisplay.value === "Invalid") {
        errors.push({ field: "retrievalDate", message: "Time is invalid. Retrieval Date must be after Form Date." });
    }

    const retOffices = document.querySelectorAll("#retrievalBody input[type='hidden']");
    if (retOffices.length === 0) errors.push({ field: "retrievalSearch", message: "At least one retrieval office is required.", type: "search" });
}

function validateDistributionSection(errors) {
    if (!sectionVisible("section-5")) return;

    requireField(errors, "distributionFormDate", "Distribution Form Date is required.");
    requireField(errors, "distributionFormTime", "Distribution Form Time is required.");
    requireField(errors, "distributionDate", "Distribution Date is required.");
    requireField(errors, "distributionTime", "Distribution Time is required.");
    requireField(errors, "distributionRemarks", "Distribution Remarks is required.");

    const distTimeDisplay = document.getElementById("distributionTimeSpentDisplay");
    if (distTimeDisplay.value === "Invalid") {
        errors.push({ field: "distributionDate", message: "Time is invalid. Distribution Date must be after Form Date." });
    }

    const distOffices = document.querySelectorAll("#distBody input[type='hidden']");
    if (distOffices.length === 0) errors.push({ field: "distSearch", message: "At least one distribution office is required.", type: "search" });
}

function validateSyllabiSection(errors) {
    const sectionSyllabi = document.getElementById("section-syllabi");
    if (!sectionSyllabi || sectionSyllabi.style.display === "none") return;

    requireField(errors, "syllabiCollege", "College is required.");
    requireField(errors, "syllabiProgram", "Program is required.");
    requireField(errors, "syllabiSemester", "Semester is required.");
    requireField(errors, "syllabiSchoolYear", "School Year is required.");
    requireField(errors, "syllabiDocNo", "Document No. is required.");
    requireField(errors, "syllabiDocTitle", "Document Title is required.");
    requireField(errors, "syllabiEffectivityDate", "Effectivity Date is required.");
    requireField(errors, "syllabiDeadline", "Deadline is required.");

    document.querySelectorAll("#syllabiTableBody tr").forEach((row, index) => {
        validateSyllabiRow(row, index + 1);
    });
}

function validateSyllabiRow(row, rowNum) {
    const fields = [
        { el: row.querySelector('input[name="syllabiCourseName[]"]'), test: v => v.value.trim(), label: 'Course Name is required.' },
        { el: row.querySelector('select[name="syllabiAvailability[]"]'), test: v => v.value, label: 'Availability is required.' },
        { el: row.querySelector('input[name="syllabiNoPages[]"]'), test: v => v.value && parseInt(v.value) > 0, label: 'No. of Pages is required and must be greater than 0.' },
        { el: row.querySelector('select[name="syllabiDrfAvailability[]"]'), test: v => v.value, label: 'DRF Availability is required.' },
        { el: row.querySelector('input[name="syllabiDrfNo[]"]'), test: v => v.value.trim(), label: 'DRF No. is required.' },
        { el: row.querySelector('input[name="syllabiDrfDate[]"]'), test: v => v.value, label: 'DRF Date is required.' },
        { el: row.querySelector('input[name="syllabiDrfReceived[]"]'), test: v => v.value, label: 'DRF Received Date is required.' },
    ];

    fields.forEach(({ el, test, label }) => {
        if (!el || !test(el)) {
            markFieldError(el?.id || 'syllabiTableBody', 'Syllabi Row ' + rowNum + ': ' + label);
            if (el) el.classList.add('reg-input-error');
        }
    });

    const fileInput = row.querySelector('input[name="syllabiScannedDrf[]"]');
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        const cell = fileInput?.closest('.reg-upload-cell') || fileInput?.closest('td');
        if (cell && !cell.querySelector('.reg-file-error')) {
            const err = document.createElement('div');
            err.className = 'reg-file-error';
            err.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Syllabi Row ' + rowNum + ': Scanned DRF is required.';
            cell.appendChild(err);
        }
    }
}

function showValidationErrors(errors) {
    errors.forEach(err => {
        if (err.type === "table") markTableError(err.field, err.message);
        else if (err.type === "search") markSearchError(err.field, err.message);
        else if (err.type === "checklist") markChecklistError(err.message);
        else markFieldError(err.field, err.message);
    });

    scrollToField(errors[0].field);
}

window.scrollToField = function (fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;

    const section = field.closest(".reg-card");
    if (section && section.style.display === "none") return;

    field.scrollIntoView({ behavior: "smooth", block: "center" });

    setTimeout(() => {
        if (field.tagName === "SELECT" || field.tagName === "INPUT") field.focus();
    }, 400);
};

document.addEventListener("input", function (e) {
    if (e.target.classList.contains("reg-input-error")) {
        e.target.classList.remove("reg-input-error");
        const errDiv = e.target.closest(".reg-field")?.querySelector(".reg-field-error");
        if (errDiv) errDiv.remove();
    }
});

document.addEventListener("change", function (e) {
    if (e.target.classList.contains("reg-input-error")) {
        e.target.classList.remove("reg-input-error");
        const errDiv = e.target.closest(".reg-field")?.querySelector(".reg-field-error");
        if (errDiv) errDiv.remove();
    }

    if (e.target.type === "file") {
        const parent = e.target.closest(".reg-field") || e.target.closest(".reg-upload-cell") || e.target.closest("td");
        if (parent) {
            const err = parent.querySelector(".reg-file-error");
            if (err) err.remove();
        }
        const container = e.target.closest(".reg-upload");
        if (container) {
            const field = container.closest(".reg-field");
            if (field) {
                const err = field.querySelector(".reg-file-error");
                if (err) err.remove();
            }
        }
    }
});

// ══════════════════════════════════════════════
// CONFIRM SAVE / REVIEW
// ══════════════════════════════════════════════
function getInputVal(id) {
    const el = document.getElementById(id);
    return el ? el.value.trim() : "";
}

function getSelectText(id) {
    const el = document.getElementById(id);
    if (!el || el.selectedIndex < 0) return "";
    return el.options[el.selectedIndex].text;
}

function formatInputDate(id) {
    const val = getInputVal(id);
    if (!val) return "";
    const date = new Date(val + "T00:00:00");
    if (isNaN(date.getTime())) return val;
    return date.toLocaleDateString("en-US", { year: "numeric", month: "short", day: "numeric" });
}

function fmtDateValue(val) {
    if (!val) return "";
    const d = new Date(val + "T00:00:00");
    return isNaN(d.getTime()) ? val : d.toLocaleDateString("en-US", { year: "numeric", month: "short", day: "numeric" });
}

function selectText(sel) {
    if (!sel || sel.selectedIndex <= 0) return "";
    return sel.options[sel.selectedIndex].text;
}

function getOfficeList(tbodyId) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return [];
    const offices = [];
    tbody.querySelectorAll("tr").forEach(row => {
        const name = row.querySelector(".reg-office-text")?.textContent?.trim();
        const copies = row.querySelector('input[type="number"]')?.value || "1";
        if (name) offices.push(name + " (" + copies + " copies)");
    });
    return offices;
}

function addReviewSection(container, title, fields) {
    const visibleFields = fields.filter(f => f.value && f.value.trim() !== "" && f.value !== "N/A");
    if (visibleFields.length === 0) return;

    const section = document.createElement("div");
    section.className = "review-section";

    let html = '<div class="review-section-title">' + title + '</div>';
    visibleFields.forEach(f => {
        html += '<div class="review-row">';
        html += '<span class="review-label">' + f.label + '</span>';
        html += f.isFile
            ? '<span class="review-value review-file"><i class="fa-solid fa-paperclip"></i> ' + f.value + '</span>'
            : '<span class="review-value">' + f.value + '</span>';
        html += '</div>';
    });

    section.innerHTML = html;
    container.appendChild(section);
}

function addReviewList(container, title, items) {
    const section = document.createElement("div");
    section.className = "review-section";

    let html = '<div class="review-section-title">' + title + '</div><ul class="review-list">';
    items.forEach(item => { html += '<li>' + item + '</li>'; });
    html += '</ul>';

    section.innerHTML = html;
    container.appendChild(section);
}

window.confirmSave = function () {
    if (docNoDuplicate) {
        scrollToField('masterlistDocNo');
        document.getElementById('masterlistDocNo').focus();
        return;
    }
    const errors = validateForm();

    if (errors.length > 0) {
        showValidationErrors(errors);
        return;
    }

    clearValidation();

    const reviewContent = document.getElementById("reviewContent");
    reviewContent.innerHTML = "";

    buildSyllabiInfoReview(reviewContent);
    buildSyllabiRowsReview(reviewContent);
    buildDrfReview(reviewContent);
    buildDcnReview(reviewContent);
    buildMasterlistReview(reviewContent);
    buildApprovalReview(reviewContent);
    buildRetrievalReview(reviewContent);
    buildDistributionReview(reviewContent);

    if (!reviewContent.children.length) {
        reviewContent.innerHTML = '<div class="review-empty">No data to review.</div>';
    }

    document.getElementById("confirmModal").style.display = "flex";
};

function buildSyllabiInfoReview(reviewContent) {
    const ss = document.getElementById("section-syllabi");
    if (!ss || ss.style.display === "none") return;

    addReviewSection(reviewContent, "Syllabi — Document Info", [
        { label: "College", value: getSelectText("syllabiCollege") },
        { label: "Program", value: getSelectText("syllabiProgram") },
        { label: "Semester", value: getSelectText("syllabiSemester") },
        { label: "School Year", value: getSelectText("syllabiSchoolYear") },
        { label: "Document No.", value: getInputVal("syllabiDocNo") },
        { label: "Document Title", value: getInputVal("syllabiDocTitle") },
        { label: "Effectivity Date", value: formatInputDate("syllabiEffectivityDate") },
        { label: "Deadline", value: formatInputDate("syllabiDeadline") },
    ]);
}

function buildSyllabiRowsReview(reviewContent) {
    const ss = document.getElementById("section-syllabi");
    if (!ss || ss.style.display === "none") return;

    document.querySelectorAll("#syllabiTableBody tr").forEach((r) => {
        const course = r.querySelector('input[name="syllabiCourseName[]"]');
        if (!course || !course.value.trim()) return;

        const avail = r.querySelector('select[name="syllabiAvailability[]"]');
        const pages = r.querySelector('input[name="syllabiNoPages[]"]');
        const drfAvail = r.querySelector('select[name="syllabiDrfAvailability[]"]');
        const drfNo = r.querySelector('input[name="syllabiDrfNo[]"]');
        const drfDate = r.querySelector('input[name="syllabiDrfDate[]"]');
        const drfReceived = r.querySelector('input[name="syllabiDrfReceived[]"]');
        const fileInput = r.querySelector('input[name="syllabiScannedDrf[]"]');

        addReviewSection(reviewContent, "Syllabi — " + course.value.trim(), [
            { label: "Availability", value: selectText(avail) },
            { label: "No. of Pages", value: pages?.value || "" },
            { label: "DRF Availability", value: selectText(drfAvail) },
            { label: "DRF No.", value: drfNo?.value?.trim() || "" },
            { label: "DRF Date", value: fmtDateValue(drfDate?.value) },
            { label: "DRF Received", value: fmtDateValue(drfReceived?.value) },
            { label: "Scanned DRF", value: fileInput?.files?.length > 0 ? fileInput.files[0].name : null, isFile: true },
        ]);
    });
}

function buildDrfReview(reviewContent) {
    const s1 = document.getElementById("section-1");
    if (!s1 || s1.style.display === "none") return;

    const f = document.getElementById("drfFile").files;
    addReviewSection(reviewContent, "Document Request Form", [
        { label: "DRF No.", value: getInputVal("drfNo") },
        { label: "DRF Date", value: formatInputDate("drfDate") },
        { label: "Receipt", value: formatInputDate("drfReceiptDate") + " " + getInputVal("drfTime") },
        { label: "Title", value: getInputVal("drfTitle") },
        { label: "Source Unit", value: window.__sourceWidgets.drf?.selected.length > 0 ? window.__sourceWidgets.drf.selected.map(o => o.label).join(', ') : null },
        { label: "File", value: f.length > 0 ? f[0].name : null, isFile: true },
    ]);
}

function buildDcnReview(reviewContent) {
    const s2 = document.getElementById("section-2");
    if (!s2 || s2.style.display === "none") return;

    const f = document.getElementById("dcnFile").files;
    addReviewSection(reviewContent, "Document Change Notice", [
        { label: "DCN No.", value: getInputVal("dcnNumber") },
        { label: "DCN Date", value: formatInputDate("noticeDate") },
        { label: "Receipt", value: formatInputDate("receiptDate") + " " + getInputVal("receiptTime") },
        { label: "Source Unit", value: getSelectText("dcnSourceUnit") },
        { label: "File", value: f.length > 0 ? f[0].name : null, isFile: true },
    ]);

    const rev = [];
    document.querySelectorAll("#revisionTableBody tr").forEach((r, i) => {
        const t = r.querySelector('input[name="documentTitle[]"]');
        if (t && t.value.trim()) rev.push("Row " + (i + 1) + ": " + t.value);
    });
    if (rev.length) addReviewList(reviewContent, "Revisions", rev);
}

function buildMasterlistReview(reviewContent) {
    const s3 = document.getElementById("section-3");
    if (!s3 || s3.style.display === "none") return;

    const f = document.getElementById("uploadScannedCopy").files;
    addReviewSection(reviewContent, "Masterlist Registration", [
        { label: "Doc No.", value: getInputVal("masterlistDocNo") },
        { label: "Title", value: getInputVal("masterlistDocTitle") },
        { label: "Deadline", value: formatInputDate("deadlineOfSubmission") },
        { label: "Receipt", value: formatInputDate("masterlistReceiptDate") + " " + getInputVal("masterlistReceiptTime") },
        { label: "Registered", value: formatInputDate("masterlistRegisteredDate") + " " + getInputVal("masterlistRegisteredTime") },
        { label: "Time Spent", value: document.getElementById("masterlistTimeSpentDisplay").value || null },
        { label: "Effectivity", value: formatInputDate("masterlistEffectivityDate") },
        { label: "Revision No.", value: getInputVal("masterlistRevisionNo") },
        { label: "Pages", value: getInputVal("masterlistNoOfPages") },
        { label: "Originator", value: window.__sourceWidgets.masterlistOriginator?.selected.length > 0 ? window.__sourceWidgets.masterlistOriginator.selected.map(o => o.label).join(', ') : null },
        { label: "Source Unit", value: window.__sourceWidgets.masterlist?.selected.length > 0 ? window.__sourceWidgets.masterlist.selected.map(i => i.label).join(', ') : null },
        { label: "Purpose", value: getInputVal("briefPurpose") },
        { label: "Related Docs", value: relatedDocsSelected.length > 0 ? relatedDocsSelected.map(d => d.doc_title).join(', ') : null },
        { label: "File", value: f.length > 0 ? f[0].name : null, isFile: true },
    ]);
}

function buildApprovalReview(reviewContent) {
    const sa = document.getElementById("section-approval");
    if (!sa || sa.style.display === "none") return;

    addReviewSection(reviewContent, "Approval Details", [
        { label: "Body", value: getSelectText("approvalBody") },
        { label: "Date", value: formatInputDate("approvalDate") },
        { label: "No.", value: getInputVal("approvalNo") },
    ]);
}

function buildRetrievalReview(reviewContent) {
    const s4 = document.getElementById("section-4");
    if (!s4 || s4.style.display === "none") return;

    const f = document.getElementById("scannedRet").files;
    addReviewSection(reviewContent, "Document Retrieval", [
        { label: "Form Date", value: formatInputDate("retrievalFormDate") + " " + getInputVal("retrievalFormTime") },
        { label: "Retrieval Date", value: formatInputDate("retrievalDate") + " " + getInputVal("retrievalTime") },
        { label: "Time Spent", value: document.getElementById("retrievalTimeSpentDisplay").value || null },
        { label: "Remarks", value: getInputVal("retrievalRemarks") },
        { label: "File", value: f.length > 0 ? f[0].name : null, isFile: true },
    ]);
    const off = getOfficeList("retrievalBody");
    if (off.length) addReviewList(reviewContent, "Receiving Offices (Retrieval)", off);
}

function buildDistributionReview(reviewContent) {
    const s5 = document.getElementById("section-5");
    if (!s5 || s5.style.display === "none") return;

    const f = document.getElementById("scanneddist").files;
    addReviewSection(reviewContent, "Document Distribution", [
        { label: "Form Date", value: formatInputDate("distributionFormDate") + " " + getInputVal("distributionFormTime") },
        { label: "Distribution Date", value: formatInputDate("distributionDate") + " " + getInputVal("distributionTime") },
        { label: "Time Spent", value: document.getElementById("distributionTimeSpentDisplay").value || null },
        { label: "Remarks", value: getInputVal("distributionRemarks") },
        { label: "File", value: f.length > 0 ? f[0].name : null, isFile: true },
    ]);
    const off = getOfficeList("distBody");
    if (off.length) addReviewList(reviewContent, "Receiving Offices (Distribution)", off);
}

window.closeConfirmModal = function () {
    document.getElementById("confirmModal").style.display = "none";
};

window.submitForm = function () {
    document.getElementById("masterForm").submit();
};

window.handleGenerateReport = function () {
    alert("Report generation coming soon.");
};

// ══════════════════════════════════════════════
// APPROVAL
// ══════════════════════════════════════════════
window.handleApprovalToggle = function (applicable) {
    const approval = document.getElementById("section-approval");
    if (approval) approval.style.display = applicable ? "block" : "none";
};

function enableApproval() {
    document.querySelectorAll('input[name="approval_status"]').forEach(r => r.disabled = false);
}

function disableApproval() {
    document.querySelectorAll('input[name="approval_status"]').forEach(r => {
        r.disabled = true;
        r.checked = r.value === "not_applicable";
    });
    const approval = document.getElementById("section-approval");
    if (approval) approval.style.display = "none";
}

// ══════════════════════════════════════════════
// REVISION TABLE
// ══════════════════════════════════════════════
window.addRevisionRow = function () {
    const tbody = document.getElementById("revisionTableBody");
    if (!tbody) return;
    const tr = document.createElement("tr");
    tr.innerHTML = `
        <td><input type="text" name="documentTitle[]" placeholder="Title"></td>
        <td><input type="text" name="documentNo[]" placeholder="Doc No."></td>
        <td><input type="date" name="effectiveDate[]"></td>
        <td><input type="text" name="revisionNo[]" placeholder="0"></td>
        <td><input type="file" name="scannedCopy[]" accept=".pdf,.docx"></td>
        <td><input type="text" name="revisionPurpose[]" placeholder="Purpose"></td>
        <td><button type="button" class="reg-row-del" onclick="this.closest('tr').remove()"><i class="fa-solid fa-trash-can"></i></button></td>
    `;
    tbody.appendChild(tr);
    bindTableFileInput(tr.querySelector('input[type="file"]'));
};

// ══════════════════════════════════════════════
// SYLLABI CONTEXT DROPDOWNS
// ══════════════════════════════════════════════
async function loadSyllabiContextDropdowns() {
    try {
        const [colleges, semesters, schoolYears] = await Promise.all([
            fetch("/api/colleges").then(r => r.json()),
            fetch("/api/semesters").then(r => r.json()),
            fetch("/api/school-years").then(r => r.json()),
        ]);

        const collegeSel = document.getElementById("syllabiCollege");
        const semSel = document.getElementById("syllabiSemester");
        const sySel = document.getElementById("syllabiSchoolYear");

        if (collegeSel && collegeSel.options.length <= 1) {
            colleges.forEach(c => collegeSel.add(new Option(c.college_name, c.college_id)));
        }
        if (semSel && semSel.options.length <= 1) {
            semesters.forEach(s => semSel.add(new Option(s.semester_name, s.semester_id)));
        }
        if (sySel && sySel.options.length <= 1) {
            schoolYears.forEach(y => sySel.add(new Option(y.school_year, y.school_year_id)));
        }
    } catch (err) {
        console.error("Failed to load syllabi context dropdowns:", err);
    }
}

document.addEventListener("DOMContentLoaded", () => {
    const collegeSel = document.getElementById("syllabiCollege");
    const programSel = document.getElementById("syllabiProgram");
    const semSel = document.getElementById("syllabiSemester");
    const sySel = document.getElementById("syllabiSchoolYear");

    if (collegeSel) {
        collegeSel.addEventListener("change", async function () {
            programSel.innerHTML = '<option value="" selected disabled>Select program</option>';
            programSel.disabled = true;
            semSel.value = "";
            semSel.disabled = true;
            sySel.value = "";
            sySel.disabled = true;

            if (!this.value) return;

            try {
                const programs = await fetch("/api/programs/" + this.value).then(r => r.json());
                programs.forEach(p => programSel.add(new Option(p.program_name, p.program_id)));
                programSel.disabled = false;
            } catch (err) {
                console.error("Failed to load programs:", err);
            }
        });
    }

    if (programSel) {
        programSel.addEventListener("change", function () {
            semSel.value = "";
            semSel.disabled = !this.value;
            sySel.value = "";
            sySel.disabled = true;
        });
    }

    if (semSel) {
        semSel.addEventListener("change", function () {
            sySel.value = "";
            sySel.disabled = !this.value;
        });
    }
});

// ══════════════════════════════════════════════
// SYLLABI WIZARD — STEP NAVIGATION
// ══════════════════════════════════════════════
function setSyllabiStep(step) {
    syllabiCurrentStep = step;
    const table = document.getElementById("syllabiWizardTable");
    if (table) table.dataset.activeStep = step;

    document.querySelectorAll("#syllabiStepIndicator .reg-wizard-step").forEach(el => {
        const s = parseInt(el.dataset.step);
        el.classList.toggle("is-active", s === step);
        el.classList.toggle("is-done", s < step);
    });

    const backBtn = document.getElementById("syllabiBackBtn");
    const nextBtn = document.getElementById("syllabiNextBtn");
    if (backBtn) backBtn.style.display = step === 1 ? "none" : "";
    if (nextBtn) {
        nextBtn.innerHTML = step === 3
            ? '<i class="fa-solid fa-check"></i> Review &amp; Confirm'
            : 'Next <i class="fa-solid fa-arrow-right"></i>';
    }
}

window.syllabiStepNext = function () {
    if (syllabiCurrentStep < 3) {
        setSyllabiStep(syllabiCurrentStep + 1);
    } else {
        confirmSave(); // opens the shared review modal
    }
};

window.syllabiStepBack = function () {
    if (syllabiCurrentStep > 1) setSyllabiStep(syllabiCurrentStep - 1);
};

// ══════════════════════════════════════════════
// SYLLABI ROW BUILDER
// ══════════════════════════════════════════════
function buildSyllabiRow({ groupId, copyNo = 1, courseName = "", availability = "", originator = "", noPages = "", copies = 1 } = {}) {
    const tr = document.createElement("tr");
    tr.style.animation = "fadeSlideUp 0.25s ease";
    tr.dataset.group = groupId;
    tr.dataset.copyNo = copyNo;

    tr.innerHTML = `
        <td class="col-pinned"><input type="text" name="syllabiCourseName[]" placeholder="Enter course name" value="${courseName}"></td>

        <td class="col-step1">
            <select name="syllabiAvailability[]">
                <option value="" disabled ${!availability ? "selected" : ""}>Select</option>
                <option value="available" ${availability === "available" ? "selected" : ""}>Available</option>
                <option value="not_available" ${availability === "not_available" ? "selected" : ""}>Not Available</option>
            </select>
        </td>
        <td class="col-step1"><input type="number" name="syllabiCopies[]" min="1" value="${copies}" oninput="handleCopiesChange(this)"></td>
        <td class="col-step1"><input type="text" name="syllabiOriginator[]" placeholder="Originator" value="${originator}"></td>
        <td class="col-step1"><input type="number" name="syllabiNoPages[]" min="0" placeholder="0" value="${noPages}"></td>
        <td class="col-step1"><input type="date" name="syllabiDateReceived[]" oninput="calcSyllabiRowTimeSpent(this.closest('tr'))"></td>
        <td class="col-step1"><input type="time" name="syllabiTimeReceived[]" oninput="calcSyllabiRowTimeSpent(this.closest('tr'))"></td>

        <td class="col-step2">
            <select name="syllabiDrfAvailability[]">
                <option value="" disabled selected>Select</option>
                <option value="available">Available</option>
                <option value="not_available">Not Available</option>
            </select>
        </td>
        <td class="col-step2"><input type="text" name="syllabiDrfNo[]" placeholder="DRF-001"></td>
        <td class="col-step2"><input type="date" name="syllabiDrfDate[]"></td>
        <td class="col-step2"><input type="date" name="syllabiDrfReceived[]"></td>
        <td class="col-step2">
            <label class="reg-upload-cell">
                <input type="file" name="syllabiScannedDrf[]" accept=".pdf,.docx">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <span>No file chosen</span>
            </label>
        </td>

        <td class="col-step3" style="text-align:center;">
            <input type="checkbox" name="syllabiIsRegistered[]" value="1" onchange="toggleSyllabiRegFields(this)">
        </td>
        <td class="col-step3"><input type="date" name="syllabiRegDate[]" disabled oninput="calcSyllabiRowTimeSpent(this.closest('tr'))"></td>
        <td class="col-step3"><input type="time" name="syllabiRegTime[]" disabled oninput="calcSyllabiRowTimeSpent(this.closest('tr'))"></td>
        <td class="col-step3">
            <input type="text" class="syllabi-time-spent-display" readonly placeholder="--" style="background:#f8fafc;text-align:center;">
            <input type="hidden" name="syllabiTimeSpent[]">
        </td>

        <td><button type="button" class="reg-row-del" onclick="removeSyllabiRow(this)"><i class="fa-solid fa-trash-can"></i></button></td>
    `;

    const fileInput = tr.querySelector('.reg-upload-cell input[type="file"]');
    const cell = tr.querySelector('.reg-upload-cell');
    fileInput.dataset.bound = "true";
    fileInput.addEventListener('change', function () { processUploadCellFile(this, cell); });

    return tr;
}

window.addSyllabiRow = function () {
    const tbody = document.getElementById("syllabiTableBody");
    if (!tbody) return;
    syllabiGroupCounter++;
    tbody.appendChild(buildSyllabiRow({ groupId: "g" + syllabiGroupCounter, copyNo: 1, copies: 1 }));
};

window.removeSyllabiRow = function (btn) {
    const row = btn.closest("tr");
    const group = row.dataset.group;
    row.remove();

    const remaining = [...document.querySelectorAll(`#syllabiTableBody tr[data-group="${group}"]`)];
    remaining.forEach((r, idx) => {
        r.dataset.copyNo = idx + 1;
        const copiesInput = r.querySelector('[name="syllabiCopies[]"]');
        if (copiesInput) copiesInput.value = remaining.length;
    });
};

// ══════════════════════════════════════════════
// COPY SPLITTING
// ══════════════════════════════════════════════
window.handleCopiesChange = function (input) {
    const row = input.closest("tr");
    const group = row.dataset.group;
    const desired = Math.max(1, parseInt(input.value) || 1);

    let groupRows = [...document.querySelectorAll(`#syllabiTableBody tr[data-group="${group}"]`)]
        .sort((a, b) => parseInt(a.dataset.copyNo) - parseInt(b.dataset.copyNo));

    if (desired > groupRows.length) {
        const template = groupRows[0];
        const courseName = template.querySelector('[name="syllabiCourseName[]"]').value;
        const availability = template.querySelector('[name="syllabiAvailability[]"]').value;
        const originator = template.querySelector('[name="syllabiOriginator[]"]').value;
        const noPages = template.querySelector('[name="syllabiNoPages[]"]').value;

        let lastRow = groupRows[groupRows.length - 1];
        for (let i = groupRows.length + 1; i <= desired; i++) {
            const newRow = buildSyllabiRow({ groupId: group, copyNo: i, courseName, availability, originator, noPages, copies: desired });
            lastRow.after(newRow);
            lastRow = newRow;
        }
    } else if (desired < groupRows.length) {
        for (let i = groupRows.length; i > desired; i--) {
            groupRows[i - 1].remove();
        }
    }

    document.querySelectorAll(`#syllabiTableBody tr[data-group="${group}"] [name="syllabiCopies[]"]`)
        .forEach(el => { el.value = desired; });
};

// ══════════════════════════════════════════════
// REGISTRATION STEP — checkbox + time spent
// ══════════════════════════════════════════════
window.toggleSyllabiRegFields = function (checkbox) {
    const row = checkbox.closest("tr");
    const regDate = row.querySelector('[name="syllabiRegDate[]"]');
    const regTime = row.querySelector('[name="syllabiRegTime[]"]');

    regDate.disabled = !checkbox.checked;
    regTime.disabled = !checkbox.checked;
    if (!checkbox.checked) {
        regDate.value = "";
        regTime.value = "";
        calcSyllabiRowTimeSpent(row);
    }
};

window.calcSyllabiRowTimeSpent = function (row) {
    const dR = row.querySelector('[name="syllabiDateReceived[]"]')?.value;
    const tR = row.querySelector('[name="syllabiTimeReceived[]"]')?.value;
    const dG = row.querySelector('[name="syllabiRegDate[]"]')?.value;
    const tG = row.querySelector('[name="syllabiRegTime[]"]')?.value;
    const display = row.querySelector('.syllabi-time-spent-display');
    const hidden = row.querySelector('[name="syllabiTimeSpent[]"]');
    if (!display || !hidden) return;

    const result = computeDuration(dR, tR, dG, tG);

    if (!result) {
        display.value = "--";
        display.style.color = "";
        hidden.value = "";
        return;
    }
    if (result.invalid) {
        display.value = "Invalid";
        display.style.color = "var(--reg-error)";
        hidden.value = "";
        return;
    }

    display.style.color = "";
    display.value = result.totalMinutes + " min";
    hidden.value = String(result.totalMinutes);
};

// ══════════════════════════════════════════════
// OFFICE SEARCH (Retrieval / Distribution)
// ══════════════════════════════════════════════
window.handleSearch = function (input, resultsId, bodyId, totalId) {
    const dropdown = document.getElementById(resultsId);
    if (!dropdown) return;

    const filtered = filterOffices(input.value);
    if (input.value.trim().length < 1 || filtered.length === 0) {
        dropdown.style.display = "none";
        return;
    }

    dropdown.innerHTML = filtered
        .map(o => '<div onclick="addOffice(' + o.office_id + ", '" + o.office_name.replace(/'/g, "\\'") + "', '" + bodyId + "', '" + totalId + "', '" + resultsId + "')\">" + o.office_name + '</div>')
        .join("");
    dropdown.style.display = "block";
};

window.addOffice = function (officeId, officeName, bodyId, totalId, resultsId) {
    const tbody = document.getElementById(bodyId);
    const dropdown = document.getElementById(resultsId);
    if (!tbody) return;

    const isRetrieval = bodyId === "retrievalBody";
    const officeNameAttr = isRetrieval ? "retrievalOffice[]" : "distOffice[]";
    const copiesNameAttr = isRetrieval ? "retrievalCopies[]" : "distCopies[]";

    const emptyRow = tbody.querySelector(".reg-empty-row");
    if (emptyRow) emptyRow.remove();

    const existingInputs = tbody.querySelectorAll('input[type="hidden"]');
    for (const inp of existingInputs) {
        if (inp.value == officeId) {
            dropdown.style.display = "none";
            dropdown.parentElement.querySelector("input[type='text']").value = "";
            const existingRow = inp.closest("tr");
            existingRow.style.animation = "none";
            existingRow.offsetHeight;
            existingRow.style.animation = "flashRow 0.6s ease";
            return;
        }
    }

    const tr = document.createElement("tr");
    tr.className = "reg-office-added";
    tr.innerHTML = `
        <td>
            <input type="hidden" name="${officeNameAttr}" value="${officeId}">
            <div class="reg-office-name">
                <div class="reg-office-icon"><i class="fa-solid fa-building"></i></div>
                <span class="reg-office-text">${officeName}</span>
            </div>
        </td>
        <td style="text-align: center;">
            <input type="number" name="${copiesNameAttr}" value="1" min="1" oninput="updateTotal('${totalId}')">
        </td>
        <td>
            <button type="button" class="btn-remove" onclick="removeOffice(this, '${totalId}', '${bodyId}')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);

    updateTotal(totalId, bodyId);
    dropdown.style.display = "none";
    dropdown.parentElement.querySelector("input[type='text']").value = "";
};

window.removeOffice = function (btn, totalId, bodyId) {
    const tr = btn.closest("tr");
    tr.style.opacity = "0";
    tr.style.transform = "translateX(20px)";
    tr.style.transition = "all 0.2s ease";

    setTimeout(() => {
        tr.remove();
        updateTotal(totalId, bodyId);
        const tbody = document.getElementById(bodyId);
        if (tbody && tbody.querySelectorAll("tr").length === 0) {
            tbody.innerHTML = emptyOfficeRowHTML();
        }
    }, 200);
};

window.updateTotal = function (totalId, bodyId) {
    const totalEl = document.getElementById(totalId);
    if (!totalEl) return;

    let sum = 0;
    if (bodyId) {
        const tbody = document.getElementById(bodyId);
        if (tbody) {
            tbody.querySelectorAll('input[type="number"]').forEach(input => {
                sum += parseInt(input.value) || 0;
            });
        }
    } else {
        const table = totalEl.closest("table");
        if (table) {
            table.querySelectorAll('tbody input[type="number"]').forEach(input => {
                sum += parseInt(input.value) || 0;
            });
        }
    }
    totalEl.textContent = sum;
};

// ══════════════════════════════════════════════
// TOAST AUTO DISMISS
// ══════════════════════════════════════════════

function showToast(type, message) {
    // Remove existing toasts
    document.querySelectorAll('.reg-toast').forEach(t => t.remove());

    const icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation';
    const toast = document.createElement('div');
    toast.className = `reg-toast reg-toast-${type}`;
    toast.id = type + 'Toast';
    toast.innerHTML = `
        <div class="reg-toast-icon"><i class="fa-solid ${icon}"></i></div>
        <div class="reg-toast-content">
            <span class="reg-toast-title">${type === 'success' ? 'Success' : 'Error'}</span>
            <span class="reg-toast-message">${message}</span>
        </div>
        <button type="button" class="reg-toast-close" onclick="closeToast()">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="reg-toast-progress"></div>
    `;
    document.querySelector('.reg-container').prepend(toast);
    setTimeout(() => closeToast(), 5000);
}

(function () {
    const toast = document.getElementById("successToast") || document.getElementById("errorToast");
    if (!toast) return;
    setTimeout(() => { closeToast(); }, 5000);
})();

window.closeToast = function () {
    const toast = document.getElementById("successToast") || document.getElementById("errorToast");
    if (!toast) return;
    toast.style.animation = "toastSlideOut 0.3s ease forwards";
    setTimeout(() => { toast.remove(); }, 300);
};