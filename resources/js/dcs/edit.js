let allOffices = [];
let allDocTypes = [];

const ALLOWED_EXTENSIONS = ['pdf', 'docx'];
const MAX_FILE_SIZE = 10 * 1024 * 1024;

// Pre-loaded data for selects (read from Blade-injected global config)
const CFG = window.APP_CONFIG || {};
const CURRENT_VERSION_ID = CFG.CURRENT_VERSION_ID || null;
const CURRENT_DOC_TYPE_ID = CFG.CURRENT_DOC_TYPE_ID || null;
const CURRENT_SUB_TYPE_ID = CFG.CURRENT_SUB_TYPE_ID || null;
const CURRENT_DRF_SOURCE = CFG.CURRENT_DRF_SOURCE || '';
const CURRENT_DCN_SOURCE = CFG.CURRENT_DCN_SOURCE || '';
const CURRENT_APPROVAL_BODY = CFG.CURRENT_APPROVAL_BODY || '';
const IS_EDIT_MODE = true;

// ═══ HELPERS ═══
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function escapeJsAttr(str) {
    return str.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

function injectHiddenForDisabled(selectId, hiddenName) {
    const sel = document.getElementById(selectId);
    if (!sel || !sel.disabled) return;
    const existing = document.querySelector('input[type="hidden"][data-for="' + selectId + '"]');
    if (existing) existing.remove();
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = hiddenName;
    hidden.value = sel.value;
    hidden.dataset.for = selectId;
    sel.parentElement.appendChild(hidden);
}

// ═══ INIT ═══
document.addEventListener("DOMContentLoaded", async function () {

    // ── BLOCK CHROME AUTOFILL on selects ──
    document.querySelectorAll("select").forEach(sel => {
        sel.setAttribute("autocomplete", "off");
        sel.setAttribute("autofill", "off");
    });
    // Block autofill on the form itself
    const form = document.getElementById("masterForm");
    if (form) form.setAttribute("autocomplete", "off");

    try {
        const [offices, docTypes, versionTypes, approvalBodies] = await Promise.all([
            fetch("/api/offices").then(r => r.json()),
            fetch("/api/doc-types").then(r => r.json()),
            fetch("/api/version-types").then(r => r.json()),
            fetch("/api/approval-bodies").then(r => r.json()),
        ]);

        allOffices = Array.isArray(offices) ? offices : [];
        allDocTypes = Array.isArray(docTypes) ? docTypes : [];

        // Populate Version Type
        const versionSelect = document.getElementById("versionType");
        while (versionSelect.options.length > 1) versionSelect.remove(1);
        (versionTypes || []).forEach(v => {
            const opt = new Option(v.version_name, v.version_id);
            if (v.version_id == CURRENT_VERSION_ID) opt.selected = true;
            versionSelect.add(opt);
        });
        versionSelect.dataset.lastValid = CURRENT_VERSION_ID;
        versionSelect.disabled = true;

        // Populate Doc Type
        const docTypeSelect = document.getElementById("docType");
        while (docTypeSelect.options.length > 1) docTypeSelect.remove(1);
        allDocTypes.filter(d => !d.parent_id).forEach(d => {
            const opt = new Option(d.doc_type_name, d.doc_type_id);
            if (d.doc_type_id == CURRENT_DOC_TYPE_ID) opt.selected = true;
            docTypeSelect.add(opt);
        });
        docTypeSelect.dataset.lastValid = CURRENT_DOC_TYPE_ID;
        docTypeSelect.disabled = true;

        // Populate Sub Type
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

        // Inject hidden inputs for disabled selects
        injectHiddenForDisabled('versionType', 'version_id');
        injectHiddenForDisabled('docType', 'doc_type_id');
        injectHiddenForDisabled('subType', 'sub_type_id');

        // Populate DRF & DCN Source
        ["drfSourceUnit", "dcnSourceUnit"].forEach(id => {
            const sel = document.getElementById(id);
            if (sel) {
                while (sel.options.length > 1) sel.remove(1);
                allOffices.forEach(o => {
                    const opt = new Option(o.office_name, o.office_id);
                    if (id === "drfSourceUnit" && o.office_id == CURRENT_DRF_SOURCE) opt.selected = true;
                    if (id === "dcnSourceUnit" && o.office_id == CURRENT_DCN_SOURCE) opt.selected = true;
                    sel.add(opt);
                });
            }
        });

        // Populate Approval
        const approvalSelect = document.getElementById("approvalBody");
        if (approvalSelect) {
            while (approvalSelect.options.length > 1) approvalSelect.remove(1);
            (approvalBodies || []).forEach(a => {
                const opt = new Option(a.approval_name, a.approval_body_id);
                if (a.approval_body_id == CURRENT_APPROVAL_BODY) opt.selected = true;
                approvalSelect.add(opt);
            });
        }

        // Load checklists for this version
        loadChecklists(CURRENT_VERSION_ID);

    } catch (err) {
        console.error("Failed to load data:", err);
        showApiError("Failed to load form data. Please refresh the page.");
    }

    (function () {
        const updateBtn = document.querySelector('.reg-btn-save');
        if (!updateBtn) return;

        // Store initial state
        const initialState = {};

        function captureInitialState() {
            const form = document.getElementById('masterForm');
            if (!form) return;

            // All inputs, selects, textareas
            form.querySelectorAll('input, select, textarea').forEach(el => {
                const key = el.name || el.id;
                if (!key) return;

                if (el.type === 'checkbox') {
                    initialState[key + '|' + (el.value || '')] = el.checked;
                } else if (el.type === 'radio') {
                    initialState[key] = document.querySelector('input[name="' + el.name + '"]:checked')?.value || '';
                } else if (el.type === 'file') {
                    // Files can't be pre-captured, track by id
                    initialState['file|' + (el.name || el.id)] = '';
                } else {
                    initialState[key] = el.value;
                }
            });

            // Capture which checklist sections are visible
            ['section-1', 'section-2', 'section-3', 'section-4', 'section-5', 'section-approval', 'section-syllabi'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    initialState['visible|' + id] = el.style.display !== 'none';
                }
            });

            // Capture office rows count for retrieval and distribution
            const retBody = document.getElementById('retrievalBody');
            const distBody = document.getElementById('distBody');
            initialState['officeCount|retrievalBody'] = retBody ? retBody.querySelectorAll('input[type="hidden"]').length : 0;
            initialState['officeCount|distBody'] = distBody ? distBody.querySelectorAll('input[type="hidden"]').length : 0;
        }

        function isDirty() {
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

            // Check section visibility changes
            ['section-1', 'section-2', 'section-3', 'section-4', 'section-5', 'section-approval', 'section-syllabi'].forEach(id => {
                if (dirty) return;
                const el = document.getElementById(id);
                if (el && initialState['visible|' + id] !== undefined) {
                    const currentVisible = el.style.display !== 'none';
                    if (currentVisible !== initialState['visible|' + id]) dirty = true;
                }
            });

            // Check office row additions/removals
            ['retrievalBody', 'distBody'].forEach(bodyId => {
                if (dirty) return;
                const tbody = document.getElementById(bodyId);
                if (!tbody) return;
                const currentCount = tbody.querySelectorAll('input[type="hidden"]').length;
                if (initialState['officeCount|' + bodyId] !== undefined && currentCount !== initialState['officeCount|' + bodyId]) {
                    dirty = true;
                }
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

        // Capture initial state after everything is loaded
        setTimeout(() => {
            captureInitialState();
            updateButtonState();
        }, 2000);

        // Listen for changes on the entire form
        const form = document.getElementById('masterForm');
        if (form) {
            form.addEventListener('input', updateButtonState);
            form.addEventListener('change', updateButtonState);
        }

        // Also watch for dynamic changes (office add/remove, checklist toggle, revision row add/remove)
        const observer = new MutationObserver(() => {
            setTimeout(updateButtonState, 100);
        });

        if (form) {
            observer.observe(form, { childList: true, subtree: true });
        }

        // Override confirmSave to allow submission even when button is disabled
        const originalConfirmSave = window.confirmSave;
        window.confirmSave = function () {
            if (!isDirty()) return;
            if (originalConfirmSave) originalConfirmSave();
        };

    })();

    initSelectProtection();
    initFileInputs();

    // Trigger time spent calculations
    setTimeout(() => {
        calcMasterlistTimeSpent();
        calcRetrievalTimeSpent();
        calcDistributionTimeSpent();
    }, 100);

    // ── AUTOFILL SAFEGUARD: after 500ms, force selects back to valid values ──
    setTimeout(() => {
        revertAutofill();
    }, 500);
    setTimeout(() => {
        revertAutofill();
    }, 1500);
});

// ── Revert any Chrome autofill changes ──
function revertAutofill() {
    const selectIds = ["versionType", "docType", "subType", "drfSourceUnit", "dcnSourceUnit", "approvalBody"];
    selectIds.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const lastValid = el.dataset.lastValid;
        if (lastValid !== undefined && lastValid !== "" && el.value !== lastValid) {
            el.value = lastValid;
        }
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

// ═══ CHECKLISTS ═══
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

        // Check if the corresponding section is currently visible
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

// ═══ SOURCE SEARCH (Masterlist) ═══
window.handleSourceSearch = function (input, dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    if (!dropdown) return;

    const fullValue = input.value;
    const parts = fullValue.split(",");
    const currentQuery = parts[parts.length - 1].trim().toLowerCase();

    if (currentQuery.length < 1) { dropdown.style.display = "none"; return; }

    const filtered = allOffices.filter(o => o.office_name.toLowerCase().includes(currentQuery));
    if (filtered.length === 0) { dropdown.style.display = "none"; return; }

    dropdown.innerHTML = filtered.map(o => {
        const safeDisplay = escapeHtml(o.office_name);
        const safeAttr = escapeJsAttr(o.office_name);
        return '<div onmousedown="pickSource(\'' + input.id + "', '" + dropdownId + "', '" + safeAttr + '\')">' + safeDisplay + '</div>';
    }).join("");
    dropdown.style.display = "block";
};

window.pickSource = function (inputId, dropdownId, officeName) {
    const input = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);

    if (inputId === "masterlistSourceUnit") {
        const parts = input.value.split(",");
        parts[parts.length - 1] = " " + officeName;
        input.value = parts.join(",");
    } else {
        input.value = officeName;
    }

    dropdown.style.display = "none";
    input.focus();
};

document.addEventListener("click", function (e) {
    const dd = document.getElementById("masterlistSourceResults");
    if (dd && !dd.parentElement.contains(e.target)) dd.style.display = "none";
});

// ═══ SELECT PROTECTION ═══
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

// ═══ FILE INPUTS ═══
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

    if (!input.files || !input.files[0]) {
        resetUploadArea(container, icon, label, originalText);
        return;
    }

    const file = input.files[0];
    const ext = file.name.split('.').pop().toLowerCase();

    if (!ALLOWED_EXTENSIONS.includes(ext)) {
        showUploadFieldError(container, '"' + ext + '" is not allowed. Only .pdf and .docx files are accepted.');
        container.classList.add('reg-upload-error');
        icon.className = 'fa-solid fa-cloud-arrow-up'; icon.style.color = '';
        label.textContent = originalText; label.style.color = ''; label.style.fontWeight = '';
        input.value = '';
        return;
    }

    if (file.size > MAX_FILE_SIZE) {
        showUploadFieldError(container, 'Max file size is 10MB.');
        container.classList.add('reg-upload-error');
        icon.className = 'fa-solid fa-cloud-arrow-up'; icon.style.color = '';
        label.textContent = originalText; label.style.color = ''; label.style.fontWeight = '';
        input.value = '';
        return;
    }

    container.classList.add('reg-upload-success');
    container.style.borderColor = 'var(--reg-success-border)';
    container.style.background = 'var(--reg-success-bg)';
    container.style.borderStyle = 'solid';
    icon.className = ext === 'pdf' ? 'fa-solid fa-file-pdf' : 'fa-solid fa-file-word';
    icon.style.color = ext === 'pdf' ? 'var(--reg-error)' : 'var(--reg-accent)';
    label.textContent = file.name; label.style.color = 'var(--reg-success)'; label.style.fontWeight = '600';
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
    icon.className = 'fa-solid fa-cloud-arrow-up'; icon.style.color = '';
    label.textContent = originalText; label.style.color = ''; label.style.fontWeight = '';
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
        icon.className = 'fa-solid fa-cloud-arrow-up'; icon.style.color = '';
        label.textContent = originalText; label.style.color = ''; label.style.fontWeight = '';
        return;
    }

    const file = input.files[0];
    const ext = file.name.split('.').pop().toLowerCase();
    if (!ALLOWED_EXTENSIONS.includes(ext)) {
        showUploadFieldError(cell, '.pdf and .docx only');
        icon.className = 'fa-solid fa-cloud-arrow-up'; icon.style.color = '';
        label.textContent = originalText; label.style.color = ''; label.style.fontWeight = '';
        input.value = ''; return;
    }
    if (file.size > MAX_FILE_SIZE) {
        showUploadFieldError(cell, 'Max 10MB');
        icon.className = 'fa-solid fa-cloud-arrow-up'; icon.style.color = '';
        label.textContent = originalText; label.style.color = ''; label.style.fontWeight = '';
        input.value = ''; return;
    }

    cell.classList.add('reg-upload-cell-success');
    cell.style.borderColor = 'var(--reg-success-border)';
    cell.style.background = 'var(--reg-success-bg)';
    icon.className = ext === 'pdf' ? 'fa-solid fa-file-pdf' : 'fa-solid fa-file-word';
    icon.style.color = ext === 'pdf' ? 'var(--reg-error)' : 'var(--reg-accent)';
    label.textContent = file.name; label.style.color = 'var(--reg-success)'; label.style.fontWeight = '600';
}

function validateTableFile(input) {
    const td = input.closest('td');
    removeExistingError(td);
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const ext = file.name.split('.').pop().toLowerCase();
    if (!ALLOWED_EXTENSIONS.includes(ext)) {
        const e = document.createElement('div'); e.className = 'reg-file-error';
        e.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> .pdf and .docx only';
        td.appendChild(e); input.value = ''; return;
    }
    if (file.size > MAX_FILE_SIZE) {
        const e = document.createElement('div'); e.className = 'reg-file-error';
        e.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Max 10MB';
        td.appendChild(e); input.value = '';
    }
}

// ═══ VERSION / DOC TYPE / SUB TYPE ═══
async function handleVersionChange() {
    if (IS_EDIT_MODE) return;
    const versionId = document.getElementById("versionType").value;
    document.getElementById("docType").value = "";
    document.getElementById("docType").disabled = !versionId;
    document.getElementById("subType").innerHTML = '<option value="" selected disabled>Select sub-type</option>';
    document.getElementById("subType").disabled = true;

    ["section-1", "section-2", "section-3", "section-4", "section-5", "section-approval", "section-syllabi"].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = "none";
    });

    if (!versionId) return;
    try {
        const res = await fetch("/api/checklist-versions/" + versionId);
        if (!res.ok) throw new Error("HTTP " + res.status);
        const checklists = await res.json();
        renderChecklistsEdit(checklists);
    } catch (err) {
        console.error("Failed to load checklists:", err);
        showApiError("Failed to load checklists for this version.");
    }
}

function handleDocTypeChange() {
    if (IS_EDIT_MODE) return;
    const docTypeId = parseInt(document.getElementById("docType").value);
    const subTypeSelect = document.getElementById("subType");
    subTypeSelect.innerHTML = '<option value="" selected disabled>Select sub-type</option>';
    subTypeSelect.disabled = true;

    const children = allDocTypes.filter(d => d.parent_id === docTypeId);
    if (children.length > 0) {
        children.forEach(c => subTypeSelect.add(new Option(c.doc_type_name, c.doc_type_id)));
        subTypeSelect.disabled = false;
    } else {
        unlockChecklist();
    }
}

function validateChecklistState() {
    const subTypeId = document.getElementById("subType").value;
    const subTypeData = allDocTypes.find(d => d.doc_type_id == subTypeId);
    const syllabiSection = document.getElementById("section-syllabi");
    if (syllabiSection) syllabiSection.style.display = "none";

    if (subTypeId) {
        unlockChecklist();
        if (subTypeData && subTypeData.doc_type_name.toLowerCase() === "syllabi") {
            if (syllabiSection) syllabiSection.style.display = "block";
        }
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

// ═══ TIME SPENT ═══
function calcTimeDiff(sDateId, sTimeId, eDateId, eTimeId, dispId, hidId) {
    const sd = document.getElementById(sDateId).value;
    const st = document.getElementById(sTimeId).value;
    const ed = document.getElementById(eDateId).value;
    const et = document.getElementById(eTimeId).value;
    const disp = document.getElementById(dispId);
    const hid = document.getElementById(hidId);

    if (!sd || !st || !ed || !et) { disp.value = "--"; disp.style.color = ""; hid.value = ""; return; }
    const diff = new Date(ed + "T" + et) - new Date(sd + "T" + st);
    if (diff < 0) { disp.value = "Invalid"; disp.style.color = "var(--reg-error)"; hid.value = ""; return; }
    const min = Math.floor(diff / 60000);
    const d = Math.floor(min / 1440), h = Math.floor((min % 1440) / 60), m = min % 60;
    disp.style.color = "";
    disp.value = d > 0 ? d + "d " + h + "hr " + m + "min" : h > 0 ? h + "hr " + m + "min" : m + " min";
    hid.value = String(min);
}

window.calcMasterlistTimeSpent = () => calcTimeDiff("masterlistReceiptDate", "masterlistReceiptTime", "masterlistRegisteredDate", "masterlistRegisteredTime", "masterlistTimeSpentDisplay", "masterlistTimeSpent");
window.calcRetrievalTimeSpent = () => calcTimeDiff("retrievalFormDate", "retrievalFormTime", "retrievalDate", "retrievalTime", "retrievalTimeSpentDisplay", "retrievalTimeSpent");
window.calcDistributionTimeSpent = () => calcTimeDiff("distributionFormDate", "distributionFormTime", "distributionDate", "distributionTime", "distributionTimeSpentDisplay", "distributionTimeSpent");

// ═══ VALIDATION ═══
function clearValidation() {
    document.querySelectorAll(".reg-input-error").forEach(el => el.classList.remove("reg-input-error"));
    document.querySelectorAll(".reg-field-error").forEach(el => el.remove());
}

function markFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    field.classList.add("reg-input-error");
    const parent = field.closest(".reg-field") || field.closest("td") || field.parentElement;
    if (parent && !parent.querySelector(".reg-field-error")) {
        const err = document.createElement("div"); err.className = "reg-field-error";
        err.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + escapeHtml(message);
        parent.appendChild(err);
    }
}

function markTableError(tableId, message) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const parent = table.closest(".reg-field") || table.closest(".reg-table-wrap")?.parentElement;
    if (parent && !parent.querySelector(".reg-field-error")) {
        const err = document.createElement("div"); err.className = "reg-field-error";
        err.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + escapeHtml(message);
        parent.appendChild(err);
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

function validateForm() {
    clearValidation();
    const errors = [];

    if (!document.getElementById("versionType").value) errors.push({ field: "versionType", message: "Version Type is required." });
    if (!document.getElementById("docType").value) errors.push({ field: "docType", message: "Document Type is required." });
    if (errors.length > 0) return errors;

    const hasChildren = allDocTypes.some(d => d.parent_id == document.getElementById("docType").value);
    if (hasChildren && !document.getElementById("subType").value) {
        errors.push({ field: "subType", message: "Sub-Type is required." }); return errors;
    }

    const checked = document.querySelectorAll("#dynamicCheckboxes input[type='checkbox']:checked:not(:disabled)");
    if (checked.length === 0) {
        errors.push({ field: "dynamicCheckboxes", message: "Please check at least one checklist.", type: "checklist" }); return errors;
    }

    const s1 = document.getElementById("section-1");
    if (s1 && s1.style.display !== "none") {
        if (!document.getElementById("drfNo").value.trim()) errors.push({ field: "drfNo", message: "DRF No. is required." });
        if (!document.getElementById("drfDate").value) errors.push({ field: "drfDate", message: "DRF Date is required." });
        if (!document.getElementById("drfReceiptDate").value) errors.push({ field: "drfReceiptDate", message: "Date Receipt is required." });
        if (!document.getElementById("drfTime").value) errors.push({ field: "drfTime", message: "Time Receipt is required." });
        if (!document.getElementById("drfTitle").value.trim()) errors.push({ field: "drfTitle", message: "Document Title is required." });
        if (!document.getElementById("drfSourceUnit").value) errors.push({ field: "drfSourceUnit", message: "Source Unit is required." });
    }

    const s2 = document.getElementById("section-2");
    if (s2 && s2.style.display !== "none") {
        if (!document.getElementById("dcnNumber").value.trim()) errors.push({ field: "dcnNumber", message: "DCN No. is required." });
        if (!document.getElementById("noticeDate").value) errors.push({ field: "noticeDate", message: "DCN Date is required." });
        if (!document.getElementById("receiptDate").value) errors.push({ field: "receiptDate", message: "Receipt Date is required." });
        if (!document.getElementById("receiptTime").value) errors.push({ field: "receiptTime", message: "Receipt Time is required." });
        if (!document.getElementById("dcnSourceUnit").value) errors.push({ field: "dcnSourceUnit", message: "Source Unit is required." });
    }

    const s3 = document.getElementById("section-3");
    if (s3 && s3.style.display !== "none") {
        if (!document.getElementById("masterlistDocNo").value.trim()) errors.push({ field: "masterlistDocNo", message: "Document No. is required." });
        if (!document.getElementById("masterlistDocTitle").value.trim()) errors.push({ field: "masterlistDocTitle", message: "Document Title is required." });
        if (!document.getElementById("masterlistSourceUnit").value.trim()) errors.push({ field: "masterlistSourceUnit", message: "Source Unit is required." });
        if (document.getElementById("masterlistTimeSpentDisplay").value === "Invalid") errors.push({ field: "masterlistRegisteredDate", message: "Time is invalid." });
    }

    const s4 = document.getElementById("section-4");
    if (s4 && s4.style.display !== "none") {
        if (!document.getElementById("retrievalFormDate").value) errors.push({ field: "retrievalFormDate", message: "Form Date is required." });
        if (!document.getElementById("retrievalFormTime").value) errors.push({ field: "retrievalFormTime", message: "Form Time is required." });
        if (!document.getElementById("retrievalDate").value) errors.push({ field: "retrievalDate", message: "Retrieval Date is required." });
        if (!document.getElementById("retrievalTime").value) errors.push({ field: "retrievalTime", message: "Retrieval Time is required." });
        if (document.getElementById("retrievalTimeSpentDisplay").value === "Invalid") errors.push({ field: "retrievalDate", message: "Time is invalid." });
        if (document.querySelectorAll("#retrievalBody input[type='hidden']").length === 0) errors.push({ field: "retrievalSearch", message: "At least one office required.", type: "search" });
    }

    const s5 = document.getElementById("section-5");
    if (s5 && s5.style.display !== "none") {
        if (!document.getElementById("distributionFormDate").value) errors.push({ field: "distributionFormDate", message: "Form Date is required." });
        if (!document.getElementById("distributionFormTime").value) errors.push({ field: "distributionFormTime", message: "Form Time is required." });
        if (!document.getElementById("distributionDate").value) errors.push({ field: "distributionDate", message: "Distribution Date is required." });
        if (!document.getElementById("distributionTime").value) errors.push({ field: "distributionTime", message: "Distribution Time is required." });
        if (document.getElementById("distributionTimeSpentDisplay").value === "Invalid") errors.push({ field: "distributionDate", message: "Time is invalid." });
        if (document.querySelectorAll("#distBody input[type='hidden']").length === 0) errors.push({ field: "distSearch", message: "At least one office required.", type: "search" });
    }

    return errors;
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

// ═══ CONFIRM SAVE ═══
window.confirmSave = function () {
    const errors = validateForm();

    if (errors.length > 0) {
        showValidationErrors(errors);
        return;
    }

    clearValidation();

    const reviewContent = document.getElementById("reviewContent");
    reviewContent.innerHTML = "";

    // ── Syllabi ──
    const ss = document.getElementById("section-syllabi");
    if (ss && ss.style.display !== "none") {
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

            const fmtDate = (val) => {
                if (!val) return "";
                const d = new Date(val + "T00:00:00");
                if (isNaN(d.getTime())) return val;
                return d.toLocaleDateString("en-US", { year: "numeric", month: "short", day: "numeric" });
            };

            const selText = (sel) => {
                if (!sel || sel.selectedIndex <= 0) return "";
                return sel.options[sel.selectedIndex].text;
            };

            addReviewSection(reviewContent, "Syllabi — " + course.value.trim(), [
                { label: "Availability", value: selText(avail) },
                { label: "No. of Pages", value: pages?.value || "" },
                { label: "DRF Availability", value: selText(drfAvail) },
                { label: "DRF No.", value: drfNo?.value?.trim() || "" },
                { label: "DRF Date", value: fmtDate(drfDate?.value) },
                { label: "DRF Received", value: fmtDate(drfReceived?.value) },
                { label: "Scanned DRF", value: fileInput?.files?.length > 0 ? fileInput.files[0].name : null, isFile: true },
            ]);
        });
    }

    // ── DRF ──
    const s1 = document.getElementById("section-1");
    if (s1 && s1.style.display !== "none") {
        const f = document.getElementById("drfFile").files;
        addReviewSection(reviewContent, "Document Request Form", [
            { label: "DRF No.", value: getInputVal("drfNo") },
            { label: "DRF Date", value: formatInputDate("drfDate") },
            { label: "Receipt", value: formatInputDate("drfReceiptDate") + " " + getInputVal("drfTime") },
            { label: "Title", value: getInputVal("drfTitle") },
            { label: "Source Unit", value: getSelectText("drfSourceUnit") },
            { label: "File", value: f.length > 0 ? f[0].name : null, isFile: true },
        ]);
    }

    // ── DCN ──
    const s2 = document.getElementById("section-2");
    if (s2 && s2.style.display !== "none") {
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

    // ── Masterlist ──
    const s3 = document.getElementById("section-3");
    if (s3 && s3.style.display !== "none") {
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
            { label: "In-charge", value: getInputVal("masterlistInCharge") },
            { label: "Source Unit", value: getInputVal("masterlistSourceUnit") || null },
            { label: "Purpose", value: getInputVal("briefPurpose") },
            { label: "Related Docs", value: getInputVal("relatedDocuments") },
            { label: "File", value: f.length > 0 ? f[0].name : null, isFile: true },
        ]);
    }

    // ── Approval ──
    const sa = document.getElementById("section-approval");
    if (sa && sa.style.display !== "none") {
        addReviewSection(reviewContent, "Approval Details", [
            { label: "Body", value: getSelectText("approvalBody") },
            { label: "Date", value: formatInputDate("approvalDate") },
            { label: "No.", value: getInputVal("approvalNo") },
        ]);
    }

    // ── Retrieval ──
    const s4 = document.getElementById("section-4");
    if (s4 && s4.style.display !== "none") {
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

    // ── Distribution ──
    const s5 = document.getElementById("section-5");
    if (s5 && s5.style.display !== "none") {
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

    if (!reviewContent.children.length) {
        reviewContent.innerHTML = '<div class="review-empty">No data to review.</div>';
    }

    document.getElementById("confirmModal").style.display = "flex";
};

// ═══ REVIEW HELPERS ═══
function getRadioText(name) {
    const checked = document.querySelector('input[name="' + name + '"]:checked');
    if (!checked) return "";
    const label = checked.closest("label");
    return label ? label.textContent.trim() : checked.value;
}

function getFileStatus(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return "No file";

    // If a new file was selected
    if (input.files && input.files[0]) {
        return input.files[0].name;
    }

    // Check if there's an existing file shown
    const parent = input.closest(".reg-field") || input.closest("td") || input.parentElement;
    const existingFile = parent?.querySelector(".reg-current-file span");
    if (existingFile && existingFile.textContent.trim()) {
        return existingFile.textContent.trim() + " (existing)";
    }

    return "No file";
}

function getTableRows(tbodyId, columns) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return [];
    const rows = [];
    tbody.querySelectorAll("tr").forEach(tr => {
        const rowData = {};
        let hasData = false;
        columns.forEach(col => {
            let value = "";
            if (col.type === "select") {
                const sel = tr.querySelector('select[name="' + col.name + '"]');
                value = sel && sel.selectedIndex > 0 ? sel.options[sel.selectedIndex].text : "";
            } else if (col.type === "date") {
                const inp = tr.querySelector('input[name="' + col.name + '"]');
                value = inp ? formatInputDateValue(inp.value) : "";
            } else {
                const inp = tr.querySelector('input[name="' + col.name + '"]');
                value = inp ? inp.value.trim() : "";
            }
            rowData[col.label] = value;
            if (value) hasData = true;
        });
        if (hasData) rows.push(rowData);
    });
    return rows;
}

function formatInputDateValue(val) {
    if (!val) return "";
    const date = new Date(val + "T00:00:00");
    if (isNaN(date.getTime())) return val;
    return date.toLocaleDateString("en-US", { year: "numeric", month: "short", day: "numeric" });
}

window.closeConfirmModal = function () {
    document.getElementById("confirmModal").style.display = "none";
};

window.submitForm = function () {
    document.getElementById("masterForm").submit();
};
function addReviewSection(container, title, fields) {
    const visibleFields = fields.filter(f => f.value && f.value.trim() !== "" && f.value !== "N/A");
    if (visibleFields.length === 0) return;

    const section = document.createElement("div");
    section.className = "review-section";

    let html = '<div class="review-section-title">' + title + '</div>';
    visibleFields.forEach(f => {
        html += '<div class="review-row">';
        html += '<span class="review-label">' + f.label + '</span>';
        if (f.isFile) {
            html += '<span class="review-value review-file"><i class="fa-solid fa-paperclip"></i> ' + f.value + '</span>';
        } else {
            html += '<span class="review-value">' + f.value + '</span>';
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
    items.forEach(item => {
        html += '<li>' + escapeHtml(item) + '</li>';
    });
    html += '</ul>';

    section.innerHTML = html;
    container.appendChild(section);
}

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

// ═══ TABLES ═══
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

window.addSyllabiRow = function () {
    const tbody = document.getElementById("syllabiTableBody"); if (!tbody) return;
    const tr = document.createElement("tr");
    tr.style.animation = "fadeSlideUp 0.25s ease";
    tr.innerHTML = `
        <td><input type="text" name="syllabiCourseName[]" placeholder="Enter course name"></td>
        <td><select name="syllabiAvailability[]"><option value="" disabled selected>Select</option><option value="available">Available</option><option value="not_available">Not Available</option></select></td>
        <td><input type="number" name="syllabiNoPages[]" min="0" placeholder="0"></td>
        <td><select name="syllabiDrfAvailability[]"><option value="" disabled selected>Select</option><option value="available">Available</option><option value="not_available">Not Available</option></select></td>
        <td><input type="text" name="syllabiDrfNo[]" placeholder="DRF-001"></td>
        <td><input type="date" name="syllabiDrfDate[]"></td>
        <td><input type="date" name="syllabiDrfReceived[]"></td>
        <td><label class="reg-upload-cell"><input type="file" name="syllabiScannedDrf[]" accept=".pdf,.docx"><i class="fa-solid fa-cloud-arrow-up"></i><span>No file chosen</span></label></td>
        <td><button type="button" class="reg-row-del" onclick="this.closest('tr').remove()"><i class="fa-solid fa-trash-can"></i></button></td>
    `;
    tbody.appendChild(tr);
    const fileInput = tr.querySelector('.reg-upload-cell input[type="file"]');
    const cell = tr.querySelector('.reg-upload-cell');
    fileInput.addEventListener('change', function () { processUploadCellFile(this, cell); });
};

// ═══ OFFICE SEARCH ═══
window.handleSearch = function (input, resultsId, bodyId, totalId) {
    const query = input.value.trim().toLowerCase();
    const dropdown = document.getElementById(resultsId); if (!dropdown) return;
    if (query.length < 1) { dropdown.style.display = "none"; return; }
    const filtered = allOffices.filter(o => o.office_name.toLowerCase().includes(query));
    if (filtered.length === 0) { dropdown.style.display = "none"; return; }

    dropdown.innerHTML = filtered.map(o => {
        const safeDisplay = escapeHtml(o.office_name);
        const safeAttr = escapeJsAttr(o.office_name);
        return '<div onclick="addOffice(' + o.office_id + ", '" + safeAttr + "', '" + bodyId + "', '" + totalId + "', '" + resultsId + "')\">" + safeDisplay + '</div>';
    }).join("");
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

document.addEventListener("click", function (e) {
    document.querySelectorAll(".reg-search-dropdown").forEach(dd => {
        if (!dd.parentElement.contains(e.target)) dd.style.display = "none";
    });
});

// ═══ TOAST ═══
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