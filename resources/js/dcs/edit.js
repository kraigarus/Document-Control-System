let allOffices = [];
let allDocTypes = [];
let allOriginators = [];
let syllabiGroupCounter = 0;
let syllabiCurrentStep = 1;
let relatedDocsCache = [];
let relatedDocsSelected = window.__existingRelatedDocs || [];
let relatedDocsSelectedPanelOpen = false;

const ALLOWED_EXTENSIONS = ['pdf', 'docx'];
const MAX_FILE_SIZE = 10 * 1024 * 1024;

const CFG = window.APP_CONFIG || {};
const CURRENT_VERSION_ID = CFG.CURRENT_VERSION_ID || null;
const CURRENT_DOC_TYPE_ID = CFG.CURRENT_DOC_TYPE_ID || null;
const CURRENT_SUB_TYPE_ID = CFG.CURRENT_SUB_TYPE_ID || null;
const CURRENT_DCN_SOURCE = CFG.CURRENT_DCN_SOURCE || '';
const CURRENT_APPROVAL_BODY = CFG.CURRENT_APPROVAL_BODY || '';
const IS_EDIT_MODE = true;

// ══════════════════════════════════════════════
// SHARED HELPERS (mirrors register.js)
// ══════════════════════════════════════════════
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function checkFile(file) {
    const ext = file.name.split('.').pop().toLowerCase();
    if (!ALLOWED_EXTENSIONS.includes(ext)) return { valid: false, ext, reason: 'type' };
    if (file.size > MAX_FILE_SIZE) return { valid: false, ext, reason: 'size', sizeMB: (file.size / (1024 * 1024)).toFixed(1) };
    return { valid: true, ext };
}

function fileTypeErrorMessage(check, file) {
    return check.reason === 'type'
        ? '"' + check.ext + '" is not allowed. Only .pdf and .docx files are accepted.'
        : '"' + file.name + '" is ' + check.sizeMB + 'MB. Maximum file size is 10MB.';
}

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

function filterItems(list, labelKey, query) {
    const q = query.trim().toLowerCase();
    if (q.length < 1) return [];
    return list.filter(o => o[labelKey].toLowerCase().includes(q));
}

function injectHiddenForDisabled(selectId, hiddenName) {
    const sel = document.getElementById(selectId);
    if (!sel || !sel.disabled) return;
    // Don't duplicate a hidden input that already carries this name (blade or a prior run)
    const existingByName = sel.parentElement.querySelector('input[type="hidden"][name="' + hiddenName + '"]');
    if (existingByName) {
        existingByName.value = sel.value; // keep it in sync instead of adding a sibling
        return;
    }
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = hiddenName;
    hidden.value = sel.value;
    hidden.dataset.for = selectId;
    sel.parentElement.appendChild(hidden);
}
// ══════════════════════════════════════════════
// INIT
// ══════════════════════════════════════════════
document.addEventListener("DOMContentLoaded", async function () {

    document.querySelectorAll("select").forEach(sel => {
        sel.setAttribute("autocomplete", "off");
        sel.setAttribute("autofill", "off");
    });
    const form = document.getElementById("masterForm");
    if (form) form.setAttribute("autocomplete", "off");

    try {
        const [offices, docTypes, versionTypes, approvalBodies, originators] = await Promise.all([
            fetch("/api/offices").then(r => r.json()),
            fetch("/api/doc-types").then(r => r.json()),
            fetch("/api/version-types").then(r => r.json()),
            fetch("/api/approval-bodies").then(r => r.json()),
            fetch("/api/originators").then(r => r.json()),
        ]);

        allOffices = Array.isArray(offices) ? offices : [];
        allDocTypes = Array.isArray(docTypes) ? docTypes : [];
        allOriginators = Array.isArray(originators) ? originators : [];

        const versionSelect = document.getElementById("versionType");
        while (versionSelect.options.length > 1) versionSelect.remove(1);
        (versionTypes || []).forEach(v => {
            const opt = new Option(v.version_name, v.version_id);
            if (v.version_id == CURRENT_VERSION_ID) opt.selected = true;
            versionSelect.add(opt);
        });
        versionSelect.dataset.lastValid = CURRENT_VERSION_ID;
        versionSelect.disabled = true;

        const docTypeSelect = document.getElementById("docType");
        while (docTypeSelect.options.length > 1) docTypeSelect.remove(1);
        allDocTypes.filter(d => !d.parent_id).forEach(d => {
            const opt = new Option(d.doc_type_name, d.doc_type_id);
            if (d.doc_type_id == CURRENT_DOC_TYPE_ID) opt.selected = true;
            docTypeSelect.add(opt);
        });
        docTypeSelect.dataset.lastValid = CURRENT_DOC_TYPE_ID;
        docTypeSelect.disabled = true;

        const subTypeSelect = document.getElementById("subType");
        const children = allDocTypes.filter(d => d.parent_id == CURRENT_DOC_TYPE_ID);
        if (children.length > 0) {
            children.forEach(c => {
                const opt = new Option(c.doc_type_name, c.doc_type_id);
                if (c.doc_type_id == CURRENT_SUB_TYPE_ID) opt.selected = true;
                subTypeSelect.add(opt);
            });
            subTypeSelect.dataset.lastValid = CURRENT_SUB_TYPE_ID || "";
        } else {
            subTypeSelect.dataset.lastValid = "";
        }
        subTypeSelect.disabled = true;

        injectHiddenForDisabled('versionType', 'version_id');
        injectHiddenForDisabled('docType', 'doc_type_id');
        injectHiddenForDisabled('subType', 'sub_type_id');

        const dcnSel = document.getElementById("dcnSourceUnit");
        if (dcnSel) {
            while (dcnSel.options.length > 1) dcnSel.remove(1);
            allOffices.forEach(o => {
                const opt = new Option(o.office_name, o.office_id);
                if (o.office_id == CURRENT_DCN_SOURCE) opt.selected = true;
                dcnSel.add(opt);
            });
        }

        const approvalSelect = document.getElementById("approvalBody");
        if (approvalSelect) {
            while (approvalSelect.options.length > 1) approvalSelect.remove(1);
            (approvalBodies || []).forEach(a => {
                const opt = new Option(a.approval_name, a.approval_body_id);
                if (a.approval_body_id == CURRENT_APPROVAL_BODY) opt.selected = true;
                approvalSelect.add(opt);
            });
        }

        loadChecklists(CURRENT_VERSION_ID);

        // ── Chip widgets, seeded with existing data ──
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
            initial: (window.__existingDrfOffices || []).map(o => ({ type: 'office', id: o.id, label: o.label })),
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
            initial: (window.__existingMasterlistSource || []).map(o => ({ type: 'office', id: o.id, label: o.label })),
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
            initial: (window.__existingMasterlistOriginator || []).map((o, i) => ({ type: 'name', id: 'seed' + i, label: o.label })),
        });

        renderRelatedDocsChips();

        // ── Syllabi: rebuild wizard rows from existing grouped data ──
        seedExistingSyllabiGroups();

    } catch (err) {
        console.error("Failed to load data:", err);
        showApiError("Failed to load form data. Please refresh the page.");
    }

    (function () {
        const updateBtn = document.querySelector('.reg-btn-save');
        if (!updateBtn) return;
        const initialState = {};

        function captureInitialState() {
            const form = document.getElementById('masterForm');
            if (!form) return;
            form.querySelectorAll('input, select, textarea').forEach(el => {
                const key = el.name || el.id;
                if (!key) return;
                if (el.type === 'checkbox') {
                    initialState[key + '|' + (el.value || '')] = el.checked;
                } else if (el.type === 'radio') {
                    initialState[key] = document.querySelector('input[name="' + el.name + '"]:checked')?.value || '';
                } else if (el.type === 'file') {
                    initialState['file|' + (el.name || el.id)] = '';
                } else {
                    initialState[key] = el.value;
                }
            });
            ['section-1', 'section-2', 'section-3', 'section-4', 'section-5', 'section-approval', 'section-syllabi'].forEach(id => {
                const el = document.getElementById(id);
                if (el) initialState['visible|' + id] = el.style.display !== 'none';
            });
            const retBody = document.getElementById('retrievalBody');
            const distBody = document.getElementById('distBody');
            initialState['officeCount|retrievalBody'] = retBody ? retBody.querySelectorAll('input[type="hidden"]').length : 0;
            initialState['officeCount|distBody'] = distBody ? distBody.querySelectorAll('input[type="hidden"]').length : 0;
        }

        let stateCaptured = false;
        function isDirty() {
            if (!stateCaptured) return true;
            const form = document.getElementById('masterForm');
            if (!form) return false;
            let dirty = false;
            form.querySelectorAll('input, select, textarea').forEach(el => {
                if (dirty) return;
                const key = el.name || el.id;
                if (!key) return;
                if (el.type === 'checkbox') {
                    const initVal = initialState[key + '|' + (el.value || '')];
                    if (initVal !== undefined && el.checked !== initVal) dirty = true;
                } else if (el.type === 'radio') {
                    const current = document.querySelector('input[name="' + el.name + '"]:checked')?.value || '';
                    if (initialState[key] !== undefined && current !== initialState[key]) dirty = true;
                } else if (el.type === 'file') {
                    if (el.files && el.files.length > 0) dirty = true;
                } else {
                    if (initialState[key] !== undefined && el.value !== initialState[key]) dirty = true;
                }
            });
            ['section-1', 'section-2', 'section-3', 'section-4', 'section-5', 'section-approval', 'section-syllabi'].forEach(id => {
                if (dirty) return;
                const el = document.getElementById(id);
                if (el && initialState['visible|' + id] !== undefined) {
                    const currentVisible = el.style.display !== 'none';
                    if (currentVisible !== initialState['visible|' + id]) dirty = true;
                }
            });
            ['retrievalBody', 'distBody'].forEach(bodyId => {
                if (dirty) return;
                const tbody = document.getElementById(bodyId);
                if (!tbody) return;
                const currentCount = tbody.querySelectorAll('input[type="hidden"]').length;
                if (initialState['officeCount|' + bodyId] !== undefined && currentCount !== initialState['officeCount|' + bodyId]) dirty = true;
            });
            return dirty;
        }

        function updateButtonState() {
            if (isDirty()) {
                updateBtn.disabled = false;
                updateBtn.style.opacity = '';
                updateBtn.style.cursor = '';
                updateBtn.title = '';
            } else {
                updateBtn.disabled = true;
                updateBtn.style.opacity = '0.45';
                updateBtn.style.cursor = 'not-allowed';
                updateBtn.title = 'No changes detected';
            }
        }

        setTimeout(() => { captureInitialState(); stateCaptured = true; updateButtonState(); }, 2000);

        const form = document.getElementById('masterForm');
        if (form) {
            form.addEventListener('input', updateButtonState);
            form.addEventListener('change', updateButtonState);
        }
        const observer = new MutationObserver(() => { setTimeout(updateButtonState, 100); });
        if (form) observer.observe(form, { childList: true, subtree: true });

        const originalConfirmSave = window.confirmSave;
        window.confirmSave = function () {
            if (!isDirty()) return;
            if (originalConfirmSave) originalConfirmSave();
        };
    })();

    initSelectProtection();
    initFileInputs();

    setTimeout(() => {
        calcMasterlistTimeSpent();
        calcRetrievalTimeSpent();
        calcDistributionTimeSpent();
        updateSyllabiTotalCopies();
    }, 150);

    setTimeout(() => { revertAutofill(); }, 500);
    setTimeout(() => { revertAutofill(); }, 1500);
});

function revertAutofill() {
    const selectIds = ["versionType", "docType", "subType", "dcnSourceUnit", "approvalBody"];
    selectIds.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const lastValid = el.dataset.lastValid;
        if (lastValid !== undefined && lastValid !== "" && el.value !== lastValid) el.value = lastValid;
    });
}

function showApiError(message) {
    const existing = document.getElementById("apiErrorToast");
    if (existing) existing.remove();
    const toast = document.createElement("div");
    toast.id = "apiErrorToast";
    toast.className = "reg-toast reg-toast-error";
    toast.style.animation = "toastSlideIn 0.3s ease";
    toast.innerHTML = `
        <div class="reg-toast-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="reg-toast-content">
            <div class="reg-toast-title">Error</div>
            <div class="reg-toast-message">${escapeHtml(message)}</div>
        </div>
        <button class="reg-toast-close" onclick="this.parentElement.remove()"><i class="fa-solid fa-xmark"></i></button>
    `;
    document.querySelector(".reg-container")?.prepend(toast);
}

// ══════════════════════════════════════════════
// CHECKLISTS
// ══════════════════════════════════════════════
async function loadChecklists(versionId) {
    if (!versionId) return;
    try {
        const res = await fetch("/api/checklist-versions/" + versionId);
        if (!res.ok) throw new Error("HTTP " + res.status);
        const checklists = await res.json();
        renderChecklistsEdit(checklists);
        initializeEditState();
    } catch (err) {
        console.error("Failed to load checklists:", err);
        showApiError("Failed to load checklists. Please refresh.");
    }
}

const SECTION_MAP = { 1: "section-1", 2: "section-2", 3: "section-3", 4: "section-4", 5: "section-5" };

function renderChecklistsEdit(checklists) {
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

        const sectionId = SECTION_MAP[c.checklist_id];
        const section = sectionId ? document.getElementById(sectionId) : null;
        cb.checked = section ? (section.style.display !== "none") : false;
        cb.dataset.lastChecked = cb.checked ? "true" : "false";

        const span = document.createElement("span");
        span.textContent = c.checklist_name;

        label.appendChild(cb);
        label.appendChild(span);

        let userTouched = false;
        label.addEventListener("pointerdown", function () { userTouched = true; });
        label.addEventListener("keydown", function () { userTouched = true; });

        cb.addEventListener("change", function () {
            if (!userTouched) { this.checked = (this.dataset.lastChecked === "true"); return; }
            userTouched = false;
            this.dataset.lastChecked = this.checked ? "true" : "false";
            toggleSection(parseInt(this.value), this.checked);
        });

        container.appendChild(label);
    });
}

function initializeEditState() {
    const container = document.getElementById("dynamicCheckboxes");
    container.querySelectorAll("input[type='checkbox']").forEach(cb => {
        cb.disabled = false;
        cb.dataset.lastChecked = cb.checked ? "true" : "false";
        toggleSection(parseInt(cb.value), cb.checked);
    });
    enableApproval();
    setTimeout(initFileInputs, 100);
}

// ══════════════════════════════════════════════
// SHARED SOURCE UNIT WIDGET FACTORY (identical to register.js)
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
                        <span>${escapeHtml(item.label)}</span>
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
            `<div onmousedown="window.__sourceWidgets['${opts.key}'].pick(${o[idKey]})">${escapeHtml(o[labelKey])}</div>`
        ).join('');
        dropdown.style.display = 'block';
    }

    function handleKeydown(e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
    }

    function pick(itemId) {
        addOffice(itemId);
        document.getElementById(opts.inputId).value = '';
        document.getElementById(opts.resultsId).style.display = 'none';
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

    function reset() { selected = []; render(); }

    function seedFromString(str) {
        if (!str || selected.length > 0) return;
        str.split(',').map(s => s.trim()).filter(Boolean).forEach(part => {
            const office = allOffices.find(o => o.office_name.toLowerCase() === part.toLowerCase());
            if (office) selected.push({ type: 'office', id: office.office_id, label: office.office_name });
            else { idCounter++; selected.push({ type: 'name', id: 'n' + idCounter, label: part }); }
        });
        render();
    }

    const inputEl = document.getElementById(opts.inputId);
    const arrowEl = document.getElementById(opts.arrowId);
    inputEl.addEventListener('input', function () { closePanel(); handleSearch(this); });
    inputEl.addEventListener('keydown', function (e) { handleKeydown(e); });
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
// UNIVERSAL SOURCE UNIT OVERLAY
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

function handleSourceOverlayEsc(e) { if (e.key === 'Escape') closeSourceOverlay(); }

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
                <span>${escapeHtml(item.label)}</span>
                <button type="button" onclick="removeSourceOverlayItem('${config.key}', '${item.id}')"><i class="fa-solid fa-xmark"></i></button>
            </div>
        `).join('');
    if (config.onChange) config.onChange();
}

window.pickSourceOverlayOffice = function (key, officeId) {
    const config = window.__sourceOverlayConfigs[key];
    if (!config) return;
    config.addOffice(officeId);
    refreshSourceOverlay(config);
};

window.removeSourceOverlayItem = function (key, id) {
    const config = window.__sourceOverlayConfigs[key];
    if (!config) return;
    config.removeItem(id);
    refreshSourceOverlay(config);
};

// ══════════════════════════════════════════════
// RELATED DOCUMENTS
// ══════════════════════════════════════════════
window.handleRelatedDocFocus = function () { closeRelatedDocsSelectedPanel(); };

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
                .map(d => `<div onmousedown="pickRelatedDoc(${d.masterlist_id})">${escapeHtml(d.label)}</div>`)
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
            <span class="reg-reldocs-chip-title">${escapeHtml(d.doc_title)}</span>
            <span class="reg-reldocs-chip-no">${escapeHtml(d.doc_no || '')}</span>
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
        if (!el) return;
        el.addEventListener("pointerdown", () => { userTouched[key] = true; });
        el.addEventListener("keydown", () => { userTouched[key] = true; });
        el.addEventListener("change", function () {
            if (!userTouched[key]) { this.value = this.dataset.lastValid || ""; return; }
            userTouched[key] = false;
            this.dataset.lastValid = this.value;
            handler();
        });
        el.addEventListener("input", function () {
            if (!userTouched[key]) this.value = this.dataset.lastValid || "";
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
        input.addEventListener('change', function () { processUploadAreaFile(this, container, icon, label, originalText); });
    });

    document.querySelectorAll('.reg-upload-cell').forEach(cell => {
        const input = cell.querySelector('input[type="file"]');
        if (!input || input.dataset.bound) return;
        input.setAttribute('accept', '.pdf,.docx');
        input.dataset.bound = "true";
        input.addEventListener('change', function () { processUploadCellFile(this, cell); });
    });

    document.querySelectorAll('#revisionTableBody input[type="file"]').forEach(input => {
        if (input.dataset.bound) return;
        input.setAttribute('accept', '.pdf,.docx');
        input.dataset.bound = "true";
        input.addEventListener('change', function () { validateTableFile(this); });
    });
}

function processUploadAreaFile(input, container, icon, label, originalText) {
    container.classList.remove('reg-upload-success', 'reg-upload-error');
    removeExistingError(container);
    removeExistingRemoveBtn(container);

    if (!input.files || !input.files[0]) { resetUploadArea(container, icon, label, originalText); return; }

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
        err.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + escapeHtml(message);
        parent.appendChild(err);
    }
}

function resetUploadArea(container, icon, label, originalText) {
    container.classList.remove('reg-upload-success', 'reg-upload-error');
    container.style.borderColor = ''; container.style.background = ''; container.style.borderStyle = '';
    clearFileIcon(icon, label, originalText);
    removeExistingRemoveBtn(container); removeExistingError(container);
}

function addRemoveBtn(container, input, icon, label, originalText) {
    removeExistingRemoveBtn(container);
    const btn = document.createElement('button');
    btn.type = 'button'; btn.className = 'reg-file-remove';
    btn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
    btn.addEventListener('click', function (e) {
        e.preventDefault(); e.stopPropagation();
        input.value = '';
        resetUploadArea(container, icon, label, originalText);
    });
    container.appendChild(btn);
}

function removeExistingRemoveBtn(c) { const o = c.querySelector('.reg-file-remove'); if (o) o.remove(); }
function removeExistingError(c) { const o = c.querySelector('.reg-file-error'); if (o) o.remove(); }

function processUploadCellFile(input, cell) {
    const label = cell.querySelector('span');
    const icon = cell.querySelector('i');
    const originalText = 'No file chosen';
    cell.classList.remove('reg-upload-cell-success', 'reg-upload-cell-error');
    cell.style.borderColor = ''; cell.style.background = '';
    removeExistingError(cell);

    if (!input.files || !input.files[0]) {
        clearFileIcon(icon, label, originalText);
        return;
    }

    const file = input.files[0];
    const check = checkFile(file);
    if (!check.valid) {
        showUploadFieldError(cell, fileTypeErrorMessage(check, file));
        clearFileIcon(icon, label, originalText);
        input.value = ''; return;
    }

    cell.classList.add('reg-upload-cell-success');
    cell.style.borderColor = 'var(--reg-success-border)';
    cell.style.background = 'var(--reg-success-bg)';
    setFileIcon(icon, check.ext);
    label.textContent = file.name; label.style.color = 'var(--reg-success)'; label.style.fontWeight = '600';
}

function validateTableFile(input) {
    const td = input.closest('td');
    removeExistingError(td);
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const check = checkFile(file);
    if (!check.valid) {
        const e = document.createElement('div'); e.className = 'reg-file-error';
        e.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + escapeHtml(fileTypeErrorMessage(check, file));
        td.appendChild(e); input.value = '';
    }
}

// ══════════════════════════════════════════════
// VERSION / DOC TYPE (locked in edit mode)
// ══════════════════════════════════════════════
async function handleVersionChange() { /* locked in edit mode */ }
function handleDocTypeChange() { /* locked in edit mode */ }

function validateChecklistState() {
    const subTypeId = document.getElementById("subType").value;
    const subTypeData = allDocTypes.find(d => d.doc_type_id == subTypeId);
    const syllabiSection = document.getElementById("section-syllabi");

    window.__isSyllabiMode = !!(subTypeData && subTypeData.doc_type_name.toLowerCase() === "syllabi");

    if (subTypeId) {
        unlockChecklist();
        if (window.__isSyllabiMode && syllabiSection) syllabiSection.style.display = "block";
    }
}

function unlockChecklist() {
    document.getElementById("dynamicCheckboxes").querySelectorAll("input[type='checkbox']").forEach(cb => {
        cb.disabled = false;
        cb.dataset.lastChecked = cb.checked ? "true" : "false";
        toggleSection(parseInt(cb.value), cb.checked);
    });
    enableApproval();
    setTimeout(initFileInputs, 100);
}

window.toggleSection = function (checklistId, show) {
    if (checklistId === 1 && window.__isSyllabiMode) return;
    const el = document.getElementById(SECTION_MAP[checklistId]);
    if (el) {
        el.style.display = show ? "block" : "none";
        if (show) setTimeout(initFileInputs, 50);
    }
};

window.handleApprovalToggle = function (applicable) {
    const el = document.getElementById("section-approval");
    if (el) el.style.display = applicable ? "block" : "none";
};

function enableApproval() {
    document.querySelectorAll('input[name="approval_status"]').forEach(r => r.disabled = false);
}

// ══════════════════════════════════════════════
// TIME SPENT
// ══════════════════════════════════════════════
function calcTimeDiff(sDateId, sTimeId, eDateId, eTimeId, dispId, hidId) {
    const sd = document.getElementById(sDateId).value;
    const st = document.getElementById(sTimeId).value;
    const ed = document.getElementById(eDateId).value;
    const et = document.getElementById(eTimeId).value;
    const disp = document.getElementById(dispId);
    const hid = document.getElementById(hidId);

    const result = computeDuration(sd, st, ed, et);
    if (!result) { disp.value = "--"; disp.style.color = ""; hid.value = ""; return; }
    if (result.invalid) { disp.value = "Invalid"; disp.style.color = "var(--reg-error)"; hid.value = ""; return; }
    disp.style.color = "";
    disp.value = formatDuration(result.totalMinutes);
    hid.value = String(result.totalMinutes);
}

window.calcMasterlistTimeSpent = () => calcTimeDiff("masterlistReceiptDate", "masterlistReceiptTime", "masterlistRegisteredDate", "masterlistRegisteredTime", "masterlistTimeSpentDisplay", "masterlistTimeSpent");
window.calcRetrievalTimeSpent = () => calcTimeDiff("retrievalFormDate", "retrievalFormTime", "retrievalDate", "retrievalTime", "retrievalTimeSpentDisplay", "retrievalTimeSpent");
window.calcDistributionTimeSpent = () => calcTimeDiff("distributionFormDate", "distributionFormTime", "distributionDate", "distributionTime", "distributionTimeSpentDisplay", "distributionTimeSpent");

// ══════════════════════════════════════════════
// VALIDATION
// ══════════════════════════════════════════════
function clearValidation() {
    document.querySelectorAll(".reg-input-error").forEach(el => el.classList.remove("reg-input-error"));
    document.querySelectorAll(".reg-field-error").forEach(el => el.remove());
}

function markFieldError(fieldId, message) {
    const field = fieldId
        ? (document.getElementById(fieldId) || document.querySelector('[name="' + fieldId + '"]'))
        : null;
    if (field) {
        field.classList.add("reg-input-error");
        const parent = field.closest(".reg-field") || field.closest("td") || field.parentElement;
        if (parent && !parent.querySelector(".reg-field-error")) {
            const err = document.createElement("div"); err.className = "reg-field-error";
            err.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + escapeHtml(message);
            parent.appendChild(err);
        }
    }
}

function markSearchError(inputId, message) {
    const field = document.getElementById(inputId);
    if (!field) return;
    field.classList.add("reg-input-error");
    const parent = field.closest(".reg-search")?.parentElement;
    if (parent && !parent.querySelector(".reg-field-error")) {
        const err = document.createElement("div"); err.className = "reg-field-error";
        err.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + escapeHtml(message);
        parent.appendChild(err);
    }
}

function markChecklistError(message) {
    const container = document.getElementById("dynamicCheckboxes");
    if (!container) return;
    const parent = container.closest(".reg-panel-bottom") || container.parentElement;
    if (parent && !parent.querySelector(".reg-field-error")) {
        const err = document.createElement("div"); err.className = "reg-field-error";
        err.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + escapeHtml(message);
        parent.appendChild(err);
    }
}

function sectionVisible(id) {
    const el = document.getElementById(id);
    return el && el.style.display !== "none";
}

function validateForm() {
    clearValidation();
    const errors = [];

    const checked = document.querySelectorAll("#dynamicCheckboxes input[type='checkbox']:checked:not(:disabled)");
    if (checked.length === 0) {
        errors.push({ field: "dynamicCheckboxes", message: "Please check at least one checklist.", type: "checklist" });
        return errors;
    }

    if (sectionVisible("section-3")) {
        if (document.getElementById("masterlistTimeSpentDisplay").value === "Invalid") {
            errors.push({ field: "masterlistRegisteredDate", message: "Masterlist: Document Registered must be after Document Receipt." });
        }
    }
    if (sectionVisible("section-4")) {
        if (document.getElementById("retrievalTimeSpentDisplay").value === "Invalid") {
            errors.push({ field: "retrievalDate", message: "Retrieval: Retrieval Date must be after Form Date." });
        }
        if (document.querySelectorAll("#retrievalBody input[type='hidden']").length === 0) {
            errors.push({ field: "retrievalSearch", message: "At least one office required for Retrieval.", type: "search" });
        }
    }
    if (sectionVisible("section-5")) {
        if (document.getElementById("distributionTimeSpentDisplay").value === "Invalid") {
            errors.push({ field: "distributionDate", message: "Distribution: Distribution Date must be after Form Date." });
        }
        if (document.querySelectorAll("#distBody input[type='hidden']").length === 0) {
            errors.push({ field: "distSearch", message: "At least one office required for Distribution.", type: "search" });
        }
    }

    return errors;
}

function showValidationErrors(errors) {
    errors.forEach(err => {
        if (err.type === "search") markSearchError(err.field, err.message);
        else if (err.type === "checklist") markChecklistError(err.message);
        else markFieldError(err.field, err.message);
    });
    scrollToField(errors[0].field);
}

window.scrollToField = function (fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    field.scrollIntoView({ behavior: "smooth", block: "center" });
    setTimeout(() => { if (field.tagName === "SELECT" || field.tagName === "INPUT") field.focus(); }, 400);
};

document.addEventListener("input", function (e) {
    if (e.target.classList.contains("reg-input-error")) {
        e.target.classList.remove("reg-input-error");
        const errDiv = e.target.closest(".reg-field")?.querySelector(".reg-field-error");
        if (errDiv) errDiv.remove();
    }
});

// ══════════════════════════════════════════════
// CONFIRM SAVE / REVIEW
// ══════════════════════════════════════════════
function getInputVal(id) { const el = document.getElementById(id); return el ? el.value.trim() : ""; }
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
    if (fields.length === 0) return;
    const section = document.createElement("div");
    section.className = "review-section";
    let html = '<div class="review-section-title">' + escapeHtml(title) + '</div>';
    fields.forEach(f => {
        const hasValue = f.value && String(f.value).trim() !== "" && f.value !== "N/A";
        html += '<div class="review-row' + (hasValue ? '' : ' review-row-empty') + '">';
        html += '<span class="review-label">' + escapeHtml(f.label) + '</span>';
        if (hasValue) {
            html += f.isFile
                ? '<span class="review-value review-file"><i class="fa-solid fa-paperclip"></i> ' + escapeHtml(f.value) + '</span>'
                : '<span class="review-value">' + escapeHtml(String(f.value)) + '</span>';
        } else {
            html += '<span class="review-value review-value-missing"><i class="fa-solid fa-circle-minus"></i> Not provided</span>';
        }
        html += '</div>';
    });
    section.innerHTML = html;
    container.appendChild(section);
}

function addReviewList(container, title, items) {
    const section = document.createElement("div");
    section.className = "review-section";
    let html = '<div class="review-section-title">' + escapeHtml(title) + '</div><ul class="review-list">';
    items.forEach(item => { html += '<li>' + escapeHtml(item) + '</li>'; });
    html += '</ul>';
    section.innerHTML = html;
    container.appendChild(section);
}

window.confirmSave = function () {
    const errors = validateForm();
    if (errors.length > 0) { showValidationErrors(errors); return; }
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
    document.querySelectorAll("#syllabiTableBody tr[data-is-first='true']").forEach((r) => {
        const course = r.querySelector('.syllabi-merged-course');
        if (!course || !course.value.trim()) return;
        const copies = r.querySelector('.syllabi-merged-copies');
        addReviewSection(reviewContent, "Syllabi — " + course.value.trim(), [
            { label: "No. of Copies", value: copies?.value || "1" },
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

window.closeConfirmModal = function () { document.getElementById("confirmModal").style.display = "none"; };
window.submitForm = function () { document.getElementById("masterForm").submit(); };

// ══════════════════════════════════════════════
// REVISION TABLE
// ══════════════════════════════════════════════
window.addRevisionRow = function () {
    const tbody = document.getElementById("revisionTableBody"); if (!tbody) return;
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
    tr.querySelector('input[type="file"]').addEventListener('change', function () { validateTableFile(this); });
};

// ══════════════════════════════════════════════
// SYLLABI CONTEXT DROPDOWNS (College/Program/Semester/Year)
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

        if (collegeSel && collegeSel.dataset.loaded !== "true") {
            colleges.forEach(c => collegeSel.add(new Option(c.college_name, c.college_id)));
            collegeSel.dataset.loaded = "true";
        }
        if (semSel && semSel.dataset.loaded !== "true") {
            semesters.forEach(s => semSel.add(new Option(s.semester_name, s.semester_id)));
            semSel.dataset.loaded = "true";
        }
        if (sySel && sySel.dataset.loaded !== "true") {
            schoolYears.forEach(y => sySel.add(new Option(y.school_year, y.school_year_id)));
            sySel.dataset.loaded = "true";
        }
        return { colleges, semesters, schoolYears };
    } catch (err) {
        console.error("Failed to load syllabi context dropdowns:", err);
        return { colleges: [], semesters: [], schoolYears: [] };
    }
}

async function initSyllabiContextWiring() {
    const collegeSel = document.getElementById("syllabiCollege");
    const programSel = document.getElementById("syllabiProgram");
    const semSel = document.getElementById("syllabiSemester");
    const sySel = document.getElementById("syllabiSchoolYear");

    if (collegeSel && !collegeSel.dataset.wired) {
        collegeSel.dataset.wired = "true";
        collegeSel.addEventListener("change", async function () {
            programSel.innerHTML = '<option value="" selected disabled>Select program</option>';
            programSel.disabled = true;
            semSel.disabled = true;
            sySel.disabled = true;
            if (!this.value) return;
            try {
                const programs = await fetch("/api/programs/" + this.value).then(r => r.json());
                programs.forEach(p => programSel.add(new Option(p.program_name, p.program_id)));
                programSel.disabled = false;
            } catch (err) { console.error("Failed to load programs:", err); }
        });
    }
    if (programSel && !programSel.dataset.wired) {
        programSel.dataset.wired = "true";
        programSel.addEventListener("change", function () {
            semSel.disabled = !this.value;
            sySel.disabled = true;
        });
    }
    if (semSel && !semSel.dataset.wired) {
        semSel.dataset.wired = "true";
        semSel.addEventListener("change", function () { sySel.disabled = !this.value; });
    }
}

// ══════════════════════════════════════════════
// SEED EXISTING SYLLABI GROUPS INTO THE WIZARD
// ══════════════════════════════════════════════
async function seedExistingSyllabiGroups() {
    const groups = window.__existingSyllabiGroups || [];
    initSyllabiContextWiring();
    await loadSyllabiContextDropdowns();

    const tbody = document.getElementById("syllabiTableBody");
    if (!tbody) return;
    tbody.innerHTML = "";
    syllabiGroupCounter = 0;

    if (groups.length === 0) {
        addSyllabiRow();
        setSyllabiStep(1);
        return;
    }

    const first = groups[0];
    const collegeSel = document.getElementById("syllabiCollege");
    const programSel = document.getElementById("syllabiProgram");
    const semSel = document.getElementById("syllabiSemester");
    const sySel = document.getElementById("syllabiSchoolYear");

    if (collegeSel && first.college_id) {
        collegeSel.value = first.college_id;
        if (programSel) {
            try {
                const programs = await fetch("/api/programs/" + first.college_id).then(r => r.json());
                programs.forEach(p => programSel.add(new Option(p.program_name, p.program_id)));
                programSel.disabled = false;
                if (first.program_id) programSel.value = first.program_id;
            } catch (e) { console.error(e); }
        }
    }
    if (semSel && first.semester_id) { semSel.disabled = false; semSel.value = first.semester_id; }
    if (sySel && first.school_year_id) { sySel.disabled = false; sySel.value = first.school_year_id; }

    groups.forEach(group => {
        syllabiGroupCounter++;
        const groupId = "g" + syllabiGroupCounter;
        const rowCount = Math.max(1, group.rows.length);
        const firstRow = buildSyllabiGroupFirstRow(groupId, rowCount);
        tbody.appendChild(firstRow);

        // Set merged (visible) fields
        const courseInput = firstRow.querySelector('.syllabi-merged-course');
        const availCheckbox = firstRow.querySelector('.syllabi-merged-availability');
        const copiesInput = firstRow.querySelector('.syllabi-merged-copies');
        if (courseInput) courseInput.value = group.course_name || '';
        if (availCheckbox) availCheckbox.checked = !!group.availability;
        if (copiesInput) copiesInput.value = rowCount;

        // Build continuation rows to match rowCount
        let lastRow = firstRow;
        for (let i = 2; i <= rowCount; i++) {
            const contRow = buildSyllabiContinuationRow(groupId, i);
            lastRow.after(contRow);
            lastRow = contRow;
        }

        syncSyllabiMergedFields(groupId);

        // Fill per-copy fields for every row in this group
        const groupRows = [...document.querySelectorAll(`#syllabiTableBody tr[data-group="${groupId}"]`)]
            .sort((a, b) => parseInt(a.dataset.copyNo) - parseInt(b.dataset.copyNo));

        groupRows.forEach((tr, idx) => {
            const data = group.rows[idx];
            if (!data) return;
            setVal(tr, 'syllabiOriginator[]', data.originator);
            setVal(tr, 'syllabiNoPages[]', data.no_pages);
            setVal(tr, 'syllabiDateReceived[]', data.date_received);
            setVal(tr, 'syllabiTimeReceived[]', data.time_received);
            setVal(tr, 'syllabiDrfNo[]', data.drf_no);
            setVal(tr, 'syllabiDrfDate[]', data.drf_date);
            setVal(tr, 'syllabiDrfReceived[]', data.drf_received_date);
            setVal(tr, 'syllabiTimeSpent[]', data.time_spent);

            const drfHidden = tr.querySelector('.syllabi-hidden-toggle[name="syllabiDrfAvailability[]"]');
            const drfCheckbox = tr.querySelector('.syllabi-check-cell input[type="checkbox"]');
            if (drfHidden && drfCheckbox) {
                const checkboxes = tr.querySelectorAll('.syllabi-check-cell input[type="checkbox"]');
                if (checkboxes[0]) {
                    checkboxes[0].checked = !!data.drf_available;
                    if (drfHidden) drfHidden.value = data.drf_available ? 'available' : 'not available';
                }
                if (checkboxes[1]) {
                    checkboxes[1].checked = !!data.registered;
                    toggleSyllabiRegFields(checkboxes[1]);
                    setVal(tr, 'syllabiRegDate[]', data.date_of_registration);
                    setVal(tr, 'syllabiRegTime[]', data.time_of_registration);
                }
            }

            const display = tr.querySelector('.syllabi-time-spent-display');
            if (display && data.time_spent) display.value = data.time_spent + ' min';

            if (data.scanned_registration_name) {
                const cell = tr.querySelector('.reg-upload-cell');
                if (cell) {
                    const span = cell.querySelector('span');
                    const icon = cell.querySelector('i');
                    if (span) span.textContent = data.scanned_registration_name;
                    if (icon) icon.className = 'fa-solid fa-file-pdf';
                    cell.classList.add('reg-upload-cell-success');
                }
            }
        });
    });

    setSyllabiStep(1);
    updateSyllabiTotalCopies();
}

function setVal(tr, name, value) {
    if (value === null || value === undefined || value === '') return;
    const el = tr.querySelector(`[name="${name}"]`);
    if (el) el.value = value;
}

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
    if (syllabiCurrentStep < 3) setSyllabiStep(syllabiCurrentStep + 1);
    else confirmSave();
};
window.syllabiStepBack = function () {
    if (syllabiCurrentStep > 1) setSyllabiStep(syllabiCurrentStep - 1);
};

// ══════════════════════════════════════════════
// SYLLABI ROW BUILDER (merged-cell groups — identical to register.js)
// ══════════════════════════════════════════════
function buildSyllabiPerRowCells(mirrorHiddenHTML = '') {
    return `
        <td class="col-step1">
            ${mirrorHiddenHTML}
            <input type="text" name="syllabiOriginator[]" placeholder="Originator">
        </td>
        <td class="col-step1"><input type="number" name="syllabiNoPages[]" min="0" placeholder="0"></td>
        <td class="col-step1"><input type="date" name="syllabiDateReceived[]" oninput="calcSyllabiRowTimeSpent(this.closest('tr'))"></td>
        <td class="col-step1"><input type="time" name="syllabiTimeReceived[]" oninput="calcSyllabiRowTimeSpent(this.closest('tr'))"></td>

        <td class="col-step2 syllabi-check-cell">
            <input type="hidden" name="syllabiDrfAvailability[]" value="not available" class="syllabi-hidden-toggle">
            <input type="checkbox" onchange="this.previousElementSibling.value = this.checked ? 'available' : 'not available'">
        </td>
        <td class="col-step2"><input type="text" name="syllabiDrfNo[]" placeholder="DRF-001"></td>
        <td class="col-step2"><input type="date" name="syllabiDrfDate[]" oninput="cascadeSyllabiField(this, 'syllabiDrfDate[]')"></td>
        <td class="col-step2"><input type="date" name="syllabiDrfReceived[]" oninput="cascadeSyllabiField(this, 'syllabiDrfReceived[]')"></td>

        <td class="col-step3 syllabi-check-cell">
            <input type="hidden" name="syllabiIsRegistered[]" value="not registered" class="syllabi-hidden-toggle">
            <input type="checkbox" onchange="toggleSyllabiRegFields(this)">
        </td>
        <td class="col-step3"><input type="date" name="syllabiRegDate[]" disabled oninput="calcSyllabiRowTimeSpent(this.closest('tr'))"></td>
        <td class="col-step3"><input type="time" name="syllabiRegTime[]" disabled oninput="calcSyllabiRowTimeSpent(this.closest('tr'))"></td>
        <td class="col-step3">
            <input type="text" class="syllabi-time-spent-display" readonly placeholder="--" style="background:#f8fafc;text-align:center;">
            <input type="hidden" name="syllabiTimeSpent[]">
        </td>
        <td class="col-step3">
            <label class="reg-upload-cell">
                <input type="file" name="syllabiScannedRegistration[]" accept=".pdf,.docx">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <span>No file chosen</span>
            </label>
        </td>
    `;
}

window.cascadeSyllabiField = function (input, fieldName) {
    const tbody = document.getElementById('syllabiTableBody');
    if (!tbody) return;
    const firstRow = tbody.querySelector('tr');
    if (!firstRow || input.closest('tr') !== firstRow) return;
    const val = input.value;
    tbody.querySelectorAll('tr').forEach((tr, idx) => {
        if (idx === 0) return;
        const target = tr.querySelector(`input[name="${fieldName}"]`);
        if (target) target.value = val;
    });
};

function cascadeDrfToNewRow(newRow) {
    const tbody = document.getElementById('syllabiTableBody');
    if (!tbody) return;
    const firstRow = tbody.querySelector('tr');
    if (!firstRow || firstRow === newRow) return;
    ['syllabiDrfDate[]', 'syllabiDrfReceived[]'].forEach(name => {
        const source = firstRow.querySelector(`input[name="${name}"]`);
        const target = newRow.querySelector(`input[name="${name}"]`);
        if (source && target && source.value) target.value = source.value;
    });
}

function updateSyllabiTotalCopies() {
    let total = 0;
    document.querySelectorAll('#syllabiTableBody .syllabi-merged-copies').forEach(inp => {
        total += parseInt(inp.value) || 0;
    });
    const el = document.getElementById('totalSyllabiCopies');
    if (el) el.textContent = total;
}

function bindSyllabiRowFileInputs(tr) {
    tr.querySelectorAll('.reg-upload-cell input[type="file"]').forEach(fileInput => {
        const cell = fileInput.closest('.reg-upload-cell');
        fileInput.dataset.bound = "true";
        fileInput.addEventListener('change', function () { processUploadCellFile(this, cell); });
    });
}

function buildSyllabiGroupFirstRow(groupId, rowspan) {
    const tr = document.createElement("tr");
    tr.style.animation = "fadeSlideUp 0.25s ease";
    tr.dataset.group = groupId;
    tr.dataset.copyNo = 1;
    tr.dataset.isFirst = "true";

    tr.innerHTML = `
        <td class="col-pinned" rowspan="${rowspan}">
            <input type="text" name="syllabiCourseName[]" placeholder="Enter course name"
                class="syllabi-merged-course" oninput="syncSyllabiMergedFields('${groupId}')">
        </td>
        <td class="col-step1" rowspan="${rowspan}">
            <input type="hidden" name="syllabiAvailability[]" value="not available" class="syllabi-merged-availability-hidden">
            <label class="reg-checkbox-wrap">
                <input type="checkbox" class="syllabi-merged-availability" onchange="syncSyllabiMergedFields('${groupId}')">
            </label>
        </td>
        <td class="col-step1" rowspan="${rowspan}">
            <input type="number" name="syllabiCopies[]" min="1" value="${rowspan}"
                class="syllabi-merged-copies" oninput="handleCopiesChange(this)">
        </td>
        ${buildSyllabiPerRowCells()}
        <td class="col-pinned" rowspan="${rowspan}">
            <button type="button" class="reg-row-del" onclick="removeSyllabiGroup('${groupId}')" title="Remove course">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </td>
    `;

    bindSyllabiRowFileInputs(tr);
    return tr;
}

function buildSyllabiContinuationRow(groupId, copyNo) {
    const tr = document.createElement("tr");
    tr.style.animation = "fadeSlideUp 0.25s ease";
    tr.dataset.group = groupId;
    tr.dataset.copyNo = copyNo;

    const mirrors = `
        <input type="hidden" name="syllabiCourseName[]" class="syllabi-mirror-course">
        <input type="hidden" name="syllabiAvailability[]" value="0" class="syllabi-mirror-availability">
        <input type="hidden" name="syllabiCopies[]" class="syllabi-mirror-copies">
    `;

    tr.innerHTML = buildSyllabiPerRowCells(mirrors);
    bindSyllabiRowFileInputs(tr);
    return tr;
}

window.syncSyllabiMergedFields = function (groupId) {
    const firstRow = document.querySelector(`#syllabiTableBody tr[data-group="${groupId}"][data-is-first="true"]`);
    if (!firstRow) return;

    const courseVal = firstRow.querySelector('.syllabi-merged-course').value;
    const availCheckbox = firstRow.querySelector('.syllabi-merged-availability');
    const availHidden = firstRow.querySelector('.syllabi-merged-availability-hidden');
    availHidden.value = availCheckbox.checked ? 'available' : 'not available';
    const copiesVal = firstRow.querySelector('.syllabi-merged-copies').value;

    document.querySelectorAll(`#syllabiTableBody tr[data-group="${groupId}"]:not([data-is-first="true"])`)
        .forEach(row => {
            const mc = row.querySelector('.syllabi-mirror-course');
            const ma = row.querySelector('.syllabi-mirror-availability');
            const mp = row.querySelector('.syllabi-mirror-copies');
            if (mc) mc.value = courseVal;
            if (ma) ma.value = availHidden.value;
            if (mp) mp.value = copiesVal;
        });
};

window.addSyllabiRow = function () {
    const tbody = document.getElementById("syllabiTableBody");
    if (!tbody) return;
    syllabiGroupCounter++;
    const newRow = buildSyllabiGroupFirstRow("g" + syllabiGroupCounter, 1);
    tbody.appendChild(newRow);
    cascadeDrfToNewRow(newRow);
    updateSyllabiTotalCopies();
};

window.removeSyllabiGroup = function (groupId) {
    document.querySelectorAll(`#syllabiTableBody tr[data-group="${groupId}"]`).forEach(r => r.remove());
    updateSyllabiTotalCopies();
};

window.handleCopiesChange = function (input) {
    const firstRow = input.closest("tr");
    const group = firstRow.dataset.group;
    const desired = Math.max(1, parseInt(input.value) || 1);
    input.value = desired;

    let groupRows = [...document.querySelectorAll(`#syllabiTableBody tr[data-group="${group}"]`)]
        .sort((a, b) => parseInt(a.dataset.copyNo) - parseInt(b.dataset.copyNo));

    if (desired > groupRows.length) {
        let lastRow = groupRows[groupRows.length - 1];
        for (let i = groupRows.length + 1; i <= desired; i++) {
            const newRow = buildSyllabiContinuationRow(group, i);
            lastRow.after(newRow);
            cascadeDrfToNewRow(newRow);
            lastRow = newRow;
        }
    } else if (desired < groupRows.length) {
        for (let i = groupRows.length; i > desired; i--) {
            groupRows[i - 1].remove();
        }
    }

    firstRow.querySelectorAll('[rowspan]').forEach(td => td.setAttribute('rowspan', desired));
    syncSyllabiMergedFields(group);
    updateSyllabiTotalCopies();
};

window.toggleSyllabiRegFields = function (checkbox) {
    const row = checkbox.closest("tr");
    const regDate = row.querySelector('[name="syllabiRegDate[]"]');
    const regTime = row.querySelector('[name="syllabiRegTime[]"]');
    const hidden = checkbox.previousElementSibling;

    hidden.value = checkbox.checked ? 'registered' : 'not registered';
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
    if (!result) { display.value = "--"; display.style.color = ""; hidden.value = ""; return; }
    if (result.invalid) { display.value = "Invalid"; display.style.color = "var(--reg-error)"; hidden.value = ""; return; }
    display.style.color = "";
    display.value = result.totalMinutes + " min";
    hidden.value = String(result.totalMinutes);
};

// ══════════════════════════════════════════════
// OFFICE SEARCH (Retrieval / Distribution)
// ══════════════════════════════════════════════
window.handleSearch = function (input, resultsId, bodyId, totalId) {
    const query = input.value.trim().toLowerCase();
    const dropdown = document.getElementById(resultsId); if (!dropdown) return;
    if (query.length < 1) { dropdown.style.display = "none"; return; }
    const filtered = allOffices.filter(o => o.office_name.toLowerCase().includes(query));
    if (filtered.length === 0) { dropdown.style.display = "none"; return; }

    dropdown.innerHTML = filtered.map(o =>
        '<div onclick="addOffice(' + o.office_id + ", '" + escapeHtml(o.office_name).replace(/'/g, "&#39;") + "', '" + bodyId + "', '" + totalId + "', '" + resultsId + "')\">" + escapeHtml(o.office_name) + '</div>'
    ).join("");
    dropdown.style.display = "block";
};

window.addOffice = function (officeId, officeName, bodyId, totalId, resultsId) {
    const tbody = document.getElementById(bodyId); const dropdown = document.getElementById(resultsId); if (!tbody) return;
    const isRetrieval = bodyId === "retrievalBody";
    const officeNameAttr = isRetrieval ? "retrievalOffice[]" : "distOffice[]";
    const copiesNameAttr = isRetrieval ? "retrievalCopies[]" : "distCopies[]";

    const emptyRow = tbody.querySelector(".reg-empty-row"); if (emptyRow) emptyRow.remove();
    for (const inp of tbody.querySelectorAll('input[type="hidden"]')) {
        if (inp.value == officeId) {
            dropdown.style.display = "none";
            dropdown.parentElement.querySelector("input[type='text']").value = "";
            const row = inp.closest("tr"); row.style.animation = "none"; row.offsetHeight; row.style.animation = "flashRow 0.6s ease";
            return;
        }
    }

    const safeDisplay = escapeHtml(officeName);
    const tr = document.createElement("tr"); tr.className = "reg-office-added";
    tr.innerHTML = `<td><input type="hidden" name="${officeNameAttr}" value="${officeId}"><div class="reg-office-name"><div class="reg-office-icon"><i class="fa-solid fa-building"></i></div><span class="reg-office-text">${safeDisplay}</span></div></td><td style="text-align:center;"><input type="number" name="${copiesNameAttr}" value="1" min="1" oninput="updateTotal('${totalId}', '${bodyId}')"></td><td><button type="button" class="btn-remove" onclick="removeOffice(this, '${totalId}', '${bodyId}')"><i class="fa-solid fa-xmark"></i></button></td>`;
    tbody.appendChild(tr);
    updateTotal(totalId, bodyId);
    dropdown.style.display = "none";
    dropdown.parentElement.querySelector("input[type='text']").value = "";
};

window.removeOffice = function (btn, totalId, bodyId) {
    const tr = btn.closest("tr");
    tr.style.opacity = "0"; tr.style.transform = "translateX(20px)"; tr.style.transition = "all 0.2s ease";
    setTimeout(() => {
        tr.remove(); updateTotal(totalId, bodyId);
        const tbody = document.getElementById(bodyId);
        if (tbody && tbody.querySelectorAll("tr").length === 0) {
            tbody.innerHTML = '<tr class="reg-empty-row"><td colspan="3"><div class="reg-empty-state"><i class="fa-solid fa-building-circle-xmark"></i><span>No offices added yet</span></div></td></tr>';
        }
    }, 200);
};

window.updateTotal = function (totalId, bodyId) {
    const totalEl = document.getElementById(totalId);
    if (!totalEl) return;
    let sum = 0;
    if (bodyId) {
        const tbody = document.getElementById(bodyId);
        if (tbody) tbody.querySelectorAll('input[type="number"]').forEach(input => { sum += parseInt(input.value) || 0; });
    } else {
        const table = totalEl.closest("table");
        if (table) table.querySelectorAll('tbody input[type="number"]').forEach(input => { sum += parseInt(input.value) || 0; });
    }
    totalEl.textContent = sum;
};

document.addEventListener("click", function (e) {
    document.querySelectorAll(".reg-search-dropdown").forEach(dd => {
        if (!dd.parentElement.contains(e.target)) dd.style.display = "none";
    });
});

// ══════════════════════════════════════════════
// TOAST
// ══════════════════════════════════════════════
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