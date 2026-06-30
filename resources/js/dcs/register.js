// register.js

let allOffices = [];
let allDocTypes = [];

const ALLOWED_EXTENSIONS = ['pdf', 'docx'];
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

document.addEventListener("DOMContentLoaded", async function () {
    try {
        const [offices, docTypes, versionTypes, approvalBodies] = await Promise.all([
            fetch("/api/offices").then(r => r.json()),
            fetch("/api/doc-types").then(r => r.json()),
            fetch("/api/version-types").then(r => r.json()),
            fetch("/api/approval-bodies").then(r => r.json()),
        ]);

        allOffices = offices;
        allDocTypes = docTypes;

        const versionSelect = document.getElementById("versionType");
        versionTypes.forEach(v => versionSelect.add(new Option(v.version_name, v.version_id)));

        const docTypeSelect = document.getElementById("docType");
        docTypes.filter(d => !d.parent_id).forEach(d => {
            docTypeSelect.add(new Option(d.doc_type_name, d.doc_type_id));
        });

        ["drfSourceUnit", "dcnSourceUnit"].forEach(id => {
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
});

document.addEventListener('DOMContentLoaded', () => {
    const revField = document.getElementById('masterlistRevisionNo');
    if (revField) {
        // Lock to 0
        revField.value = 0;
        revField.readOnly = true;
        revField.style.background = '#f1f5f9';
        revField.style.cursor = 'not-allowed';

        // Prevent any manual override
        revField.addEventListener('input', () => {
            if (parseInt(revField.value) > 0) {
                revField.value = 0;
                showToast('error', 'Cannot set revision higher than 0 for a new document. Use Revised Registration instead.');
            }
        });
    }
});

// ═══════════════════════════════════════════
// Revision number logic — depends on page
// ═══════════════════════════════════════════
// ═══════════════════════════════════════════
// Revision number logic — depends on page
// ═══════════════════════════════════════════
const isRevisedPage = window.location.pathname.includes('/register/revised');
const revField = document.getElementById('masterlistRevisionNo');
const docNoInput = document.getElementById('masterlistDocNo');
const hintEl = document.getElementById('docNoHint');
let docNoTimer = null;
function isRevisedMode() {
    const sel = document.getElementById('versionType');
    if (!sel || sel.selectedIndex < 1) return false;
    const text = sel.options[sel.selectedIndex].text.toLowerCase();
    return text.includes('revised') || text.includes('revision') || text.includes('revise');
}

function applyRevisionMode() {
    if (isRevisedMode()) {
        // ── REVISED: unlock revision, enable auto-suggest ──
        if (revField) {
            revField.value = '';
            revField.readOnly = false;
            revField.style.background = '';
            revField.style.cursor = '';
        }
        if (hintEl) {
            hintEl.innerHTML = '<span style="color:#94a3b8"><i class="fa-solid fa-circle-info"></i> Enter an existing document number</span>';
        }
    } else {
        // ── NEW: lock revision to 0 ──
        if (revField) {
            revField.value = 0;
            revField.readOnly = true;
            revField.style.background = '#f1f5f9';
            revField.style.cursor = 'not-allowed';
        }
        if (hintEl) hintEl.innerHTML = '';
    }
}

// Listen for version type change
document.getElementById('versionType').addEventListener('change', () => {
    applyRevisionMode();
});

// Listen for doc no input (only active in revised mode)
if (docNoInput) {
    docNoInput.addEventListener('input', () => {
        if (!isRevisedMode()) return;

        clearTimeout(docNoTimer);
        const docNo = docNoInput.value.trim();

        if (!docNo) {
            if (hintEl) {
                hintEl.innerHTML = '<span style="color:#94a3b8"><i class="fa-solid fa-circle-info"></i> Enter an existing document number</span>';
            }
            if (revField) {
                revField.value = '';
                revField.readOnly = false;
                revField.style.background = '';
                revField.style.cursor = '';
            }
            return;
        }

        docNoTimer = setTimeout(async () => {
        try {
            const docTypeId = document.getElementById('docType').value;
            const url = '/register/check-docno?doc_no=' + encodeURIComponent(docNo) +
                        (docTypeId ? '&doc_type_id=' + docTypeId : '');
            const res = await fetch(url);
            const data = await res.json();

            if (data.exists) {
                // Doc found under same type — auto-fill
                if (revField) {
                    revField.value = data.next_rev;
                    revField.readOnly = true;
                    revField.style.background = '#f0fdf4';
                    revField.style.cursor = 'default';
                }

                const titleField = document.getElementById('masterlistDocTitle');
                if (titleField && data.latest_title && !titleField.value.trim()) {
                    titleField.value = data.latest_title;
                }

                const originatorField = document.getElementById('masterlistSourceUnit');
                if (originatorField && data.latest_originator && !originatorField.value.trim()) {
                    originatorField.value = data.latest_originator;
                }

                if (hintEl) {
                    hintEl.innerHTML =
                        '<i class="fa-solid fa-circle-check"></i> ' +
                        data.message +
                        ' — Next revision: <strong>Rev ' + data.next_rev + '</strong>';
                    hintEl.style.color = '#16a34a';
                }
            } else if (data.wrong_type) {
                // Doc exists but wrong type — show warning
                if (revField) {
                    revField.value = '';
                    revField.readOnly = false;
                    revField.style.background = '';
                    revField.style.cursor = '';
                }

                if (hintEl) {
                    hintEl.innerHTML =
                        '<i class="fa-solid fa-triangle-exclamation"></i> ' +
                        data.message;
                    hintEl.style.color = '#d97706';
                }
            } else {
                // Doc doesn't exist at all
                if (revField) {
                    revField.value = '';
                    revField.readOnly = false;
                    revField.style.background = '';
                    revField.style.cursor = '';
                }

                if (hintEl) {
                    hintEl.innerHTML =
                        '<i class="fa-solid fa-circle-exclamation"></i> ' +
                        data.message;
                    hintEl.style.color = '#dc2626';
                }
            }
        } catch (e) {
            console.error('DocNo lookup failed:', e);
        }
    }, 500);
    });
}

// Apply on page load too
applyRevisionMode();

// ══════════════════════════════════════════════
// SOURCE UNIT — autocomplete for Masterlist
// ══════════════════════════════════════════════
window.handleSourceSearch = function (input, dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    if (!dropdown) return;

    const fullValue = input.value;
    const parts = fullValue.split(",");
    const currentQuery = parts[parts.length - 1].trim().toLowerCase();

    if (currentQuery.length < 1) {
        dropdown.style.display = "none";
        return;
    }

    const filtered = allOffices.filter(o => o.office_name.toLowerCase().includes(currentQuery));

    if (filtered.length === 0) {
        dropdown.style.display = "none";
        return;
    }

    dropdown.innerHTML = filtered.map(o =>
        '<div onmousedown="pickSource(\'' + input.id + "', '" + dropdownId + "', '" +
        o.office_name.replace(/'/g, "\\'") + '\')">' + o.office_name + '</div>'
    ).join("");
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
    const ext = file.name.split('.').pop().toLowerCase();

    if (!ALLOWED_EXTENSIONS.includes(ext)) {
        showUploadFieldError(container, '"' + ext + '" is not allowed. Only .pdf and .docx files are accepted.');
        container.classList.add('reg-upload-error');
        icon.className = 'fa-solid fa-cloud-arrow-up';
        icon.style.color = '';
        label.textContent = originalText;
        label.style.color = '';
        label.style.fontWeight = '';
        input.value = '';
        return;
    }

    if (file.size > MAX_FILE_SIZE) {
        const sizeMB = (file.size / (1024 * 1024)).toFixed(1);
        showUploadFieldError(container, '"' + file.name + '" is ' + sizeMB + 'MB. Maximum file size is 10MB.');
        container.classList.add('reg-upload-error');
        icon.className = 'fa-solid fa-cloud-arrow-up';
        icon.style.color = '';
        label.textContent = originalText;
        label.style.color = '';
        label.style.fontWeight = '';
        input.value = '';
        return;
    }

    container.classList.add('reg-upload-success');
    container.style.borderColor = 'var(--reg-success-border)';
    container.style.background = 'var(--reg-success-bg)';
    container.style.borderStyle = 'solid';

    icon.className = ext === 'pdf' ? 'fa-solid fa-file-pdf' : 'fa-solid fa-file-word';
    icon.style.color = ext === 'pdf' ? 'var(--reg-error)' : 'var(--reg-accent)';

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

    icon.className = 'fa-solid fa-cloud-arrow-up';
    icon.style.color = '';

    label.textContent = originalText;
    label.style.color = '';
    label.style.fontWeight = '';

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

function processUploadCellFile(input, cell) {
    const label = cell.querySelector('span');
    const icon = cell.querySelector('i');
    const originalText = 'No file chosen';

    cell.classList.remove('reg-upload-cell-success', 'reg-upload-cell-error');
    cell.style.borderColor = '';
    cell.style.background = '';
    removeExistingError(cell);

    if (!input.files || !input.files[0]) {
        icon.className = 'fa-solid fa-cloud-arrow-up';
        icon.style.color = '';
        label.textContent = originalText;
        label.style.color = '';
        label.style.fontWeight = '';
        return;
    }

    const file = input.files[0];
    const ext = file.name.split('.').pop().toLowerCase();

    if (!ALLOWED_EXTENSIONS.includes(ext)) {
        showUploadFieldError(cell, '.pdf and .docx only');
        icon.className = 'fa-solid fa-cloud-arrow-up';
        icon.style.color = '';
        label.textContent = originalText;
        label.style.color = '';
        label.style.fontWeight = '';
        input.value = '';
        return;
    }

    if (file.size > MAX_FILE_SIZE) {
        const sizeMB = (file.size / (1024 * 1024)).toFixed(1);
        showUploadFieldError(cell, sizeMB + 'MB exceeds 10MB limit');
        icon.className = 'fa-solid fa-cloud-arrow-up';
        icon.style.color = '';
        label.textContent = originalText;
        label.style.color = '';
        label.style.fontWeight = '';
        input.value = '';
        return;
    }

    cell.classList.add('reg-upload-cell-success');
    cell.style.borderColor = 'var(--reg-success-border)';
    cell.style.background = 'var(--reg-success-bg)';
    icon.className = ext === 'pdf' ? 'fa-solid fa-file-pdf' : 'fa-solid fa-file-word';
    icon.style.color = ext === 'pdf' ? 'var(--reg-error)' : 'var(--reg-accent)';
    label.textContent = file.name;
    label.style.color = 'var(--reg-success)';
    label.style.fontWeight = '600';
}

function validateTableFile(input) {
    const td = input.closest('td');
    removeExistingError(td);

    if (!input.files || !input.files[0]) return;

    const file = input.files[0];
    const ext = file.name.split('.').pop().toLowerCase();

    if (!ALLOWED_EXTENSIONS.includes(ext)) {
        const errDiv = document.createElement('div');
        errDiv.className = 'reg-file-error';
        errDiv.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> .pdf and .docx only';
        td.appendChild(errDiv);
        input.value = '';
        return;
    }

    if (file.size > MAX_FILE_SIZE) {
        const errDiv = document.createElement('div');
        errDiv.className = 'reg-file-error';
        errDiv.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Max 10MB';
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
        applyRevisionMode(); // ← add this
        return;
    }

    try {
        const res = await fetch("/api/checklist-versions/" + versionId);
        const checklists = await res.json();
        renderChecklists(checklists, true);
    } catch (err) {
        console.error("Failed to load checklists:", err);
    }

    applyRevisionMode(); // ← add this
}


// ══════════════════════════════════════════════
// DOC TYPE CHANGE
// ══════════════════════════════════════════════
function handleDocTypeChange() {
    const docTypeSelect = document.getElementById("docType");
    const docTypeId = parseInt(docTypeSelect.value);
    const subTypeSelect = document.getElementById("subType");

    subTypeSelect.innerHTML = '<option value="" selected disabled>Select sub-type</option>';
    subTypeSelect.disabled = true;

    // ── Always hide syllabi when doc type changes ──
    const syllabiSection = document.getElementById("section-syllabi");
    if (syllabiSection) syllabiSection.style.display = "none";

    const children = allDocTypes.filter(d => d.parent_id === docTypeId);

    if (children.length > 0) {
        children.forEach(c => subTypeSelect.add(new Option(c.doc_type_name, c.doc_type_id)));
        subTypeSelect.disabled = false;
        lockChecklist();
    } else {
        unlockChecklist();
    }
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

    if (subTypeId) {
        unlockChecklist();
        if (subTypeData && subTypeData.doc_type_name.toLowerCase() === "syllabi") {
            if (syllabiSection) {
                syllabiSection.style.display = "block";
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
        }
    } else {
        lockChecklist();
    }
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
    const sectionMap = {
        1: "section-1",
        2: "section-2",
        3: "section-3",
        4: "section-4",
        5: "section-5",
    };
    const sectionId = sectionMap[checklistId];
    if (sectionId) {
        const el = document.getElementById(sectionId);
        if (el) {
            el.style.display = show ? "block" : "none";
            if (show) {
                setTimeout(initFileInputs, 50);
            }
        }
    }
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

    if (!startDate || !startTime || !endDate || !endTime) {
        display.value = "--";
        display.style.color = "";
        hidden.value = "";
        return;
    }

    const start = new Date(startDate + "T" + startTime);
    const end = new Date(endDate + "T" + endTime);
    const diffMs = end - start;

    if (diffMs < 0) {
        display.value = "Invalid";
        display.style.color = "var(--reg-error)";
        hidden.value = "";
        return;
    }

    const totalMinutes = Math.floor(diffMs / 60000);
    const days = Math.floor(totalMinutes / 1440);
    const hours = Math.floor((totalMinutes % 1440) / 60);
    const minutes = totalMinutes % 60;

    display.style.color = "";

    if (days > 0) {
        display.value = days + "d " + hours + "hr " + minutes + "min";
    } else if (hours > 0) {
        display.value = hours + "hr " + minutes + "min";
    } else {
        display.value = minutes + " min";
    }

    hidden.value = String(totalMinutes);
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
    const field = document.getElementById(fieldId);
    if (!field) return;

    field.classList.add("reg-input-error");

    const parent = field.closest(".reg-field") || field.closest("td") || field.parentElement;
    if (parent && !parent.querySelector(".reg-field-error")) {
        const err = document.createElement("div");
        err.className = "reg-field-error";
        err.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + message;
        parent.appendChild(err);
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

function validateForm() {
    clearValidation();
    const errors = [];

    // ── Version & Doc Type first ──
    if (!document.getElementById("versionType").value) {
        errors.push({ field: "versionType", message: "Version Type is required." });
    }
    if (!document.getElementById("docType").value) {
        errors.push({ field: "docType", message: "Document Type is required." });
    }

    // If these are missing, stop here — no point checking sections
    if (errors.length > 0) return errors;

    const hasChildren = allDocTypes.some(d => d.parent_id == document.getElementById("docType").value);
    if (hasChildren && !document.getElementById("subType").value) {
        errors.push({ field: "subType", message: "Sub-Type is required." });
        return errors;
    }

    // ── At least one checklist must be checked ──
    const checkedBoxes = document.querySelectorAll("#dynamicCheckboxes input[type='checkbox']:checked:not(:disabled)");
    if (checkedBoxes.length === 0) {
        errors.push({ field: "dynamicCheckboxes", message: "Please check at least one checklist to proceed.", type: "checklist" });
        return errors;
    }

    // ── Section 1: DRF ──
    const section1 = document.getElementById("section-1");
    if (section1 && section1.style.display !== "none") {
        if (!document.getElementById("drfNo").value.trim()) errors.push({ field: "drfNo", message: "DRF No. is required." });
        if (!document.getElementById("drfDate").value) errors.push({ field: "drfDate", message: "DRF Date is required." });
        if (!document.getElementById("drfReceiptDate").value) errors.push({ field: "drfReceiptDate", message: "Date Receipt is required." });
        if (!document.getElementById("drfTime").value) errors.push({ field: "drfTime", message: "Time Receipt is required." });
        if (!document.getElementById("drfTitle").value.trim()) errors.push({ field: "drfTitle", message: "Document Title is required." });
        if (!document.getElementById("drfSourceUnit").value) errors.push({ field: "drfSourceUnit", message: "Source Unit is required." });
    }

    // ── Section 2: DCN ──
    const section2 = document.getElementById("section-2");
    if (section2 && section2.style.display !== "none") {
        if (!document.getElementById("dcnNumber").value.trim()) errors.push({ field: "dcnNumber", message: "DCN No. is required." });
        if (!document.getElementById("noticeDate").value) errors.push({ field: "noticeDate", message: "DCN Date is required." });
        if (!document.getElementById("receiptDate").value) errors.push({ field: "receiptDate", message: "DCN Receipt Date is required." });
        if (!document.getElementById("receiptTime").value) errors.push({ field: "receiptTime", message: "DCN Receipt Time is required." });
        if (!document.getElementById("dcnSourceUnit").value) errors.push({ field: "dcnSourceUnit", message: "Source Unit is required." });

        let hasRevision = false;
        document.querySelectorAll("#revisionTableBody tr").forEach(row => {
            const title = row.querySelector('input[name="documentTitle[]"]');
            if (title && title.value.trim()) hasRevision = true;
        });
        if (!hasRevision) errors.push({ field: "revisionTableBody", message: "At least one revision document is required.", type: "table" });
    }

    // ── Section 3: Masterlist ──
    const section3 = document.getElementById("section-3");
    if (section3 && section3.style.display !== "none") {
        if (!document.getElementById("masterlistDocNo").value.trim()) errors.push({ field: "masterlistDocNo", message: "Document No. is required." });
        if (!document.getElementById("masterlistDocTitle").value.trim()) errors.push({ field: "masterlistDocTitle", message: "Document Title is required." });
        if (!document.getElementById("masterlistSourceUnit").value.trim()) {
            errors.push({ field: "masterlistSourceUnit", message: "Source Unit / Originator is required." });
        }
        const mlTimeDisplay = document.getElementById("masterlistTimeSpentDisplay");
        if (mlTimeDisplay.value === "Invalid") {
            errors.push({ field: "masterlistRegisteredDate", message: "Time is invalid. Document Registered must be after Document Receipt." });
        }
    }

    // ── Section 4: Retrieval ──
    const section4 = document.getElementById("section-4");
    if (section4 && section4.style.display !== "none") {
        if (!document.getElementById("retrievalFormDate").value) errors.push({ field: "retrievalFormDate", message: "Retrieval Form Date is required." });
        if (!document.getElementById("retrievalFormTime").value) errors.push({ field: "retrievalFormTime", message: "Retrieval Form Time is required." });
        if (!document.getElementById("retrievalDate").value) errors.push({ field: "retrievalDate", message: "Retrieval Date is required." });
        if (!document.getElementById("retrievalTime").value) errors.push({ field: "retrievalTime", message: "Retrieval Time is required." });

        const retTimeDisplay = document.getElementById("retrievalTimeSpentDisplay");
        if (retTimeDisplay.value === "Invalid") {
            errors.push({ field: "retrievalDate", message: "Time is invalid. Retrieval Date must be after Form Date." });
        }

        const retOffices = document.querySelectorAll("#retrievalBody input[type='hidden']");
        if (retOffices.length === 0) errors.push({ field: "retrievalSearch", message: "At least one retrieval office is required.", type: "search" });
    }

    // ── Section 5: Distribution ──
    const section5 = document.getElementById("section-5");
    if (section5 && section5.style.display !== "none") {
        if (!document.getElementById("distributionFormDate").value) errors.push({ field: "distributionFormDate", message: "Distribution Form Date is required." });
        if (!document.getElementById("distributionFormTime").value) errors.push({ field: "distributionFormTime", message: "Distribution Form Time is required." });
        if (!document.getElementById("distributionDate").value) errors.push({ field: "distributionDate", message: "Distribution Date is required." });
        if (!document.getElementById("distributionTime").value) errors.push({ field: "distributionTime", message: "Distribution Time is required." });

        const distTimeDisplay = document.getElementById("distributionTimeSpentDisplay");
        if (distTimeDisplay.value === "Invalid") {
            errors.push({ field: "distributionDate", message: "Time is invalid. Distribution Date must be after Form Date." });
        }

        const distOffices = document.querySelectorAll("#distBody input[type='hidden']");
        if (distOffices.length === 0) errors.push({ field: "distSearch", message: "At least one distribution office is required.", type: "search" });
    }

    // ── Syllabi ──
    const sectionSyllabi = document.getElementById("section-syllabi");
    if (sectionSyllabi && sectionSyllabi.style.display !== "none") {
        let hasSyllabi = false;
        document.querySelectorAll("#syllabiTableBody tr").forEach(row => {
            const course = row.querySelector('input[name="syllabiCourseName[]"]');
            if (course && course.value.trim()) hasSyllabi = true;
        });
        if (!hasSyllabi) errors.push({ field: "syllabiTableBody", message: "At least one syllabi course is required.", type: "table" });
    }

    return errors;
}

function showValidationErrors(errors) {
    errors.forEach(err => {
        if (err.type === "table") {
            markTableError(err.field, err.message);
        } else if (err.type === "search") {
            markSearchError(err.field, err.message);
        } else if (err.type === "checklist") {
            markChecklistError(err.message);
        } else {
            markFieldError(err.field, err.message);
        }
    });

    scrollToField(errors[0].field);
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

window.scrollToField = function (fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;

    const section = field.closest(".reg-card");
    if (section && section.style.display === "none") return;

    field.scrollIntoView({ behavior: "smooth", block: "center" });

    setTimeout(() => {
        if (field.tagName === "SELECT" || field.tagName === "INPUT") {
            field.focus();
        }
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
// CONFIRM SAVE
// ══════════════════════════════════════════════
window.confirmSave = function () {
    const errors = validateForm();

    if (errors.length > 0) {
        showValidationErrors(errors);
        return;
    }

    clearValidation();

    const reviewContent = document.getElementById("reviewContent");
    reviewContent.innerHTML = "";

    // DRF
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

    // DCN
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

    // Masterlist
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

    // Approval
    const sa = document.getElementById("section-approval");
    if (sa && sa.style.display !== "none") {
        addReviewSection(reviewContent, "Approval Details", [
            { label: "Body", value: getSelectText("approvalBody") },
            { label: "Date", value: formatInputDate("approvalDate") },
            { label: "No.", value: getInputVal("approvalNo") },
        ]);
    }

    // Retrieval
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

    // Distribution
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

    // Syllabi
    const ss = document.getElementById("section-syllabi");
    if (ss && ss.style.display !== "none") {
        const list = [];
        document.querySelectorAll("#syllabiTableBody tr").forEach((r, i) => {
            const c = r.querySelector('input[name="syllabiCourseName[]"]');
            if (c && c.value.trim()) list.push("Row " + (i + 1) + ": " + c.value);
        });
        if (list.length) addReviewList(reviewContent, "Syllabi", list);
    }

    if (!reviewContent.children.length) {
        reviewContent.innerHTML = '<div class="review-empty">No data to review.</div>';
    }

    document.getElementById("confirmModal").style.display = "flex";
};

window.closeConfirmModal = function () {
    document.getElementById("confirmModal").style.display = "none";
};

window.submitForm = function () {
    const form = document.getElementById("masterForm");
    if (form) {
        form.submit();
    }
};

window.handleGenerateReport = function () {
    alert("Report generation coming soon.");
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

    let html = '<div class="review-section-title">' + title + '</div><ul class="review-list">';
    items.forEach(item => { html += '<li>' + item + '</li>'; });
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
    tr.querySelector('input[type="file"]').addEventListener('change', function () { validateTableFile(this); });
};


// ══════════════════════════════════════════════
// SYLLABI TABLE
// ══════════════════════════════════════════════
window.addSyllabiRow = function () {
    const tbody = document.getElementById("syllabiTableBody");
    if (!tbody) return;
    const tr = document.createElement("tr");
    tr.style.animation = "fadeSlideUp 0.25s ease";
    tr.innerHTML = `
        <td><input type="text" name="syllabiCourseName[]" placeholder="Enter course name"></td>
        <td>
            <select name="syllabiAvailability[]">
                <option value="" disabled selected>Select</option>
                <option value="available">Available</option>
                <option value="not_available">Not Available</option>
            </select>
        </td>
        <td><input type="number" name="syllabiNoPages[]" min="0" placeholder="0"></td>
        <td>
            <select name="syllabiDrfAvailability[]">
                <option value="" disabled selected>Select</option>
                <option value="available">Available</option>
                <option value="not_available">Not Available</option>
            </select>
        </td>
        <td><input type="text" name="syllabiDrfNo[]" placeholder="DRF-001"></td>
        <td><input type="date" name="syllabiDrfDate[]"></td>
        <td><input type="date" name="syllabiDrfReceived[]"></td>
        <td>
            <label class="reg-upload-cell">
                <input type="file" name="syllabiScannedDrf[]" accept=".pdf,.docx">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <span>No file chosen</span>
            </label>
        </td>
        <td><button type="button" class="reg-row-del" onclick="this.closest('tr').remove()"><i class="fa-solid fa-trash-can"></i></button></td>
    `;
    tbody.appendChild(tr);

    const fileInput = tr.querySelector('.reg-upload-cell input[type="file"]');
    const cell = tr.querySelector('.reg-upload-cell');
    fileInput.addEventListener('change', function () { processUploadCellFile(this, cell); });
};


// ══════════════════════════════════════════════
// OFFICE SEARCH
// ══════════════════════════════════════════════
window.handleSearch = function (input, resultsId, bodyId, totalId) {
    const query = input.value.trim().toLowerCase();
    const dropdown = document.getElementById(resultsId);
    if (!dropdown) return;

    if (query.length < 1) { dropdown.style.display = "none"; return; }

    const filtered = allOffices.filter(o => o.office_name.toLowerCase().includes(query));
    if (filtered.length === 0) { dropdown.style.display = "none"; return; }

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

    updateTotal(totalId);
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
        updateTotal(totalId);
        const tbody = document.getElementById(bodyId);
        if (tbody && tbody.querySelectorAll("tr").length === 0) {
            tbody.innerHTML = `
                <tr class="reg-empty-row">
                    <td colspan="3">
                        <div class="reg-empty-state">
                            <i class="fa-solid fa-building-circle-xmark"></i>
                            <span>No offices added yet</span>
                        </div>
                    </td>
                </tr>
            `;
        }
    }, 200);
};

function updateTotal(totalId) {
    const totalEl = document.getElementById(totalId);
    if (!totalEl) return;
    const inputs = totalEl.closest("table").querySelectorAll("tbody input[type='number']");
    let sum = 0;
    inputs.forEach(input => sum += parseInt(input.value) || 0);
    totalEl.textContent = sum;
}

document.addEventListener("click", function (e) {
    document.querySelectorAll(".reg-search-dropdown").forEach(dd => {
        if (!dd.parentElement.contains(e.target)) dd.style.display = "none";
    });
});


// ══════════════════════════════════════════════
// TOAST AUTO DISMISS
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