let allOffices = [];
let allDocTypes = [];
let allOriginators = [];
let syllabiGroupCounter = 0;
let syllabiCurrentStep = 1;
let syllabiRowUidCounter = 0;
let relatedDocsCache = [];
let relatedDocsSelected = window.__existingRelatedDocs || [];
let relatedDocsSelectedPanelOpen = false;
let docNoDuplicate = false;
let revisionRowUidCounter = 0;
let revSearchCache = {};
let revSearchTimers = {};
window.__isSyllabiMode = false;


const ALLOWED_EXTENSIONS = ['pdf', 'docx'];
const MAX_FILE_SIZE = 10 * 1024 * 1024;

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

/** Seed a single office row directly into a distribution/retrieval-style table,
 *  without touching the search dropdown (used for pre-fill, not manual pick). */
function seedOfficeRow(tbodyId, totalId, officeId, officeName, copies) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;

    const isRetrieval = tbodyId === "retrievalBody";
    const officeNameAttr = isRetrieval ? "retrievalOffice[]" : "distOffice[]";
    const copiesNameAttr = isRetrieval ? "retrievalCopies[]" : "distCopies[]";

    const emptyRow = tbody.querySelector(".reg-empty-row");
    if (emptyRow) emptyRow.remove();

    // Don't duplicate if it's already there
    const existing = [...tbody.querySelectorAll('input[type="hidden"]')]
        .find(inp => inp.value == officeId);
    if (existing) return;

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
            <input type="number" name="${copiesNameAttr}" value="${copies}" min="1" oninput="updateTotal('${totalId}')">
        </td>
        <td>
            <button type="button" class="btn-remove" onclick="removeOffice(this, '${totalId}', '${tbodyId}')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
}

/** Returns true if the given office table has no real rows yet (only the empty placeholder). */
function tableIsEmpty(tbodyId) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return true;
    return tbody.querySelectorAll('input[type="hidden"]').length === 0;
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

    // ── Wire search/autofill on the initial DCN revision row ──
    const initialRevisionRow = document.querySelector('#revisionTableBody tr');
    if (initialRevisionRow) bindRevisionRowSearch(initialRevisionRow);

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

        // ── Source Unit (offices/names) ──
        if (data.latest_source_unit && window.__sourceWidgets.masterlist) {
            window.__sourceWidgets.masterlist.seedFromString(data.latest_source_unit);
        }

        // ── Originator (fixed: was incorrectly seeding into 'masterlist' before) ──
        if (data.latest_originator && window.__sourceWidgets.masterlistOriginator) {
            window.__sourceWidgets.masterlistOriginator.seedFromString(data.latest_originator);
        }

        // ── Effectivity Date ──
        const effField = document.getElementById('masterlistEffectivityDate');
        if (effField && !effField.value && data.latest_effectivity_date) {
            effField.value = data.latest_effectivity_date;
        }

        // ── No. of Pages ──
        const pagesField = document.getElementById('masterlistNoOfPages');
        if (pagesField && !pagesField.value && data.latest_no_pages) {
            pagesField.value = data.latest_no_pages;
        }

        // ── Deadline of Submission ──
        const deadlineField = document.getElementById('deadlineOfSubmission');
        if (deadlineField && !deadlineField.value && data.latest_deadline) {
            deadlineField.value = data.latest_deadline;
        }

        // ── Justification / Brief Purpose ──
        const purposeField = document.getElementById('briefPurpose');
        if (purposeField && !purposeField.value && data.latest_brief_purpose) {
            purposeField.value = data.latest_brief_purpose;
        }

        // ── Related Documents ──
        if (data.latest_related_documents && data.latest_related_documents.length > 0 && relatedDocsSelected.length === 0) {
            relatedDocsSelected = data.latest_related_documents.slice();
            renderRelatedDocsChips();
        }
        // ── NEW: pre-fill Retrieval offices from the last revision's Distribution,
        // only if the user hasn't already started picking retrieval offices ──
        if (data.latest_distribution_offices && data.latest_distribution_offices.length > 0
            && tableIsEmpty('retrievalBody')) {
            data.latest_distribution_offices.forEach(o => {
                seedOfficeRow('retrievalBody', 'totalRetrievalCopies', o.office_id, o.office_name, o.copies);
            });
            updateTotal('totalRetrievalCopies', 'retrievalBody');
        }

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
// DCN — REVISION TABLE: DOCUMENT SEARCH + AUTOFILL
// ══════════════════════════════════════════════
function getOrCreateRevSearchDropdown(key) {
    let dd = document.getElementById('revSearchDropdown_' + key);
    if (!dd) {
        dd = document.createElement('div');
        dd.id = 'revSearchDropdown_' + key;
        dd.className = 'reg-reldocs-dropdown';
        dd.style.display = 'none';
        document.body.appendChild(dd);
    }
    return dd;
}

function closeRevSearchDropdown(key) {
    const dd = document.getElementById('revSearchDropdown_' + key);
    if (dd) dd.style.display = 'none';
}

function removeRevSearchDropdown(key) {
    const dd = document.getElementById('revSearchDropdown_' + key);
    if (dd) dd.remove();
}

function handleRevisionSearchInput(input, key) {
    if (input.readOnly) return; // locked rows never search again

    clearTimeout(revSearchTimers[key]);
    const dd = getOrCreateRevSearchDropdown(key);
    const q = input.value.trim();
    if (q.length < 1) { dd.style.display = 'none'; return; }

    revSearchTimers[key] = setTimeout(async () => {
        try {
            const url = '/api/documents/search?q=' + encodeURIComponent(q);
            const data = await fetch(url).then(r => r.json());
            revSearchCache[key] = data;

            dd.innerHTML = data.length === 0
                ? '<div class="reg-reldocs-noresult">No matching documents found</div>'
                : data.map((d, idx) => `<div onmousedown="pickRevisionDocument('${key}', ${idx})">${d.label}</div>`).join('');

            positionFixedDropdown(dd, input);
            dd.style.display = 'block';
        } catch (e) {
            console.error('Revision doc search failed:', e);
        }
    }, 300);
}

window.pickRevisionDocument = function (key, idx) {
    const doc = (revSearchCache[key] || [])[idx];
    if (!doc) return;

    const uid = key.split('_')[0];
    const row = document.querySelector(`#revisionTableBody tr[data-uid="${uid}"]`);
    if (!row) return;

    const titleInput   = row.querySelector('input[name="documentTitle[]"]');
    const noInput      = row.querySelector('input[name="documentNo[]"]');
    const effField     = row.querySelector('input[name="effectiveDate[]"]');
    const revField     = row.querySelector('input[name="revisionNo[]"]');
    const purposeField = row.querySelector('input[name="revisionPurpose[]"]');

    if (titleInput) titleInput.value = doc.doc_title || '';
    if (noInput) noInput.value = doc.doc_no || '';
    if (effField && doc.effectivity_date) effField.value = doc.effectivity_date;
    if (revField && doc.revise_no !== null && doc.revise_no !== undefined) revField.value = doc.revise_no;
    if (purposeField && doc.brief_purpose) purposeField.value = doc.brief_purpose;

    lockRevisionRowFields(row);
    lockRevisionScannedCopyCell(row, doc.scanned_copy_url);
    closeRevSearchDropdown(key);
};

/** Locks every text/date field in the row once a document has been picked. The row can
 *  only be undone by deleting it entirely (trash-can button) — no partial re-editing. */
function lockRevisionRowFields(row) {
    row.dataset.linked = "true";

    row.querySelectorAll(
        'input[name="documentTitle[]"], input[name="documentNo[]"], input[name="effectiveDate[]"], input[name="revisionNo[]"], input[name="revisionPurpose[]"]'
    ).forEach(el => {
        el.readOnly = true;
        el.classList.add('reg-revrow-locked');
        el.style.background = '#f8fafc';
        el.style.cursor = 'not-allowed';
        el.style.color = '#475569';
    });
}

/** Replaces the file-upload cell with either a viewable link to the existing scan,
 *  or an error notice if the linked document has no scanned copy on file. */
function lockRevisionScannedCopyCell(row, scannedCopyUrl) {
    const fileInput = row.querySelector('input[name="scannedCopy[]"]');
    if (!fileInput) return;
    const cell = fileInput.closest('td');
    if (!cell) return;

    if (scannedCopyUrl) {
        cell.innerHTML = `
            <button type="button" class="reg-revrow-viewfile" onclick="window.open('${scannedCopyUrl}', '_blank')">
                <i class="fa-solid fa-file-lines"></i> View file
            </button>
        `;
    } else {
        cell.innerHTML = `
            <div class="reg-file-error" style="margin:0;">
                <i class="fa-solid fa-circle-exclamation"></i> No scanned copy on file
            </div>
        `;
    }
}

function bindRevisionSearchInput(input, uid, field) {
    if (!input || input.dataset.searchBound) return;
    input.dataset.searchBound = 'true';

    const key = uid + '_' + field;
    if (!input.id) input.id = 'revSearchInput_' + key;

    input.addEventListener('input', () => handleRevisionSearchInput(input, key));
    input.addEventListener('focus', () => {
        if (!input.readOnly && input.value.trim().length >= 1) handleRevisionSearchInput(input, key);
    });

    const reposition = () => {
        const dd = document.getElementById('revSearchDropdown_' + key);
        if (dd && dd.style.display === 'block') positionFixedDropdown(dd, input);
    };
    window.addEventListener('scroll', reposition, true);
    window.addEventListener('resize', reposition);
}

/** Assigns a uid to a revision row (if it doesn't have one yet) and wires both its search fields. */
function bindRevisionRowSearch(tr) {
    if (!tr.dataset.uid) tr.dataset.uid = ++revisionRowUidCounter;
    const uid = tr.dataset.uid;
    bindRevisionSearchInput(tr.querySelector('input[name="documentTitle[]"]'), uid, 'title');
    bindRevisionSearchInput(tr.querySelector('input[name="documentNo[]"]'), uid, 'no');
}

/** Clean up both body-attached dropdowns for a row before it's removed. */
function removeRevisionRowDropdowns(tr) {
    if (!tr.dataset.uid) return;
    removeRevSearchDropdown(tr.dataset.uid + '_title');
    removeRevSearchDropdown(tr.dataset.uid + '_no');
}

window.removeRevisionRow = function (btn) {
    const tr = btn.closest('tr');
    if (tr) {
        removeRevisionRowDropdowns(tr);
        tr.remove();
    }
};

// Close any open revision-search dropdown on outside click
document.addEventListener('click', function (e) {
    document.querySelectorAll('[id^="revSearchDropdown_"]').forEach(dd => {
        const key = dd.id.replace('revSearchDropdown_', '');
        const input = document.getElementById('revSearchInput_' + key);
        if (dd.style.display === 'block' && !dd.contains(e.target) && e.target !== input) {
            dd.style.display = 'none';
        }
    });
});

function initSyllabiOriginatorWidget(uid) {
    createSourceUnitWidget({
        key: 'syllabiOriginator_' + uid,
        widgetId: 'syllabiOriginatorWidget_' + uid,
        inputId: 'syllabiOriginatorSearch_' + uid,
        arrowId: 'syllabiOriginatorArrowBtn_' + uid,
        resultsId: 'syllabiOriginatorResults_' + uid,
        chipsId: 'syllabiOriginatorInlineChips_' + uid,
        allowFreeText: true,
        singleSelect: true,
        fieldName: 'syllabiOriginatorRaw_' + uid, // internal only — never read server-side
        dataListGetter: () => allOriginators,
        idKey: 'originator_id',
        labelKey: 'originator_name',
        itemLabelPlural: 'originators',
        overlayTitle: 'Originator',
        initial: []
    });
}

function cleanupSyllabiOriginatorWidget(uid) {
    if (uid) delete window.__sourceWidgets['syllabiOriginator_' + uid];
}

/** Keeps the one real, always-present syllabiOriginator[] hidden input
 *  synced with whatever's picked in each row's typeahead, right before submit. */
function syncSyllabiOriginatorHiddenFields() {
    document.querySelectorAll('#syllabiTableBody tr[data-uid]').forEach(row => {
        const uid = row.dataset.uid;
        const hidden = document.getElementById('syllabiOriginatorValue_' + uid);
        if (!hidden) return;
        const widget = window.__sourceWidgets['syllabiOriginator_' + uid];
        hidden.value = (widget && widget.selected.length > 0) ? widget.selected[0].label : '';
    });
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

    if (input.id === 'drfFile' && check.ext === 'pdf') {
        triggerScanExtraction(input, file);
    }
}

function triggerScanExtraction(input, file) {
    const formData = new FormData();
    formData.append('scan', file);
    formData.append('section', 'drf');
    formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value);

    const container = input.closest('.reg-upload');
    const label = container?.querySelector('span');
    const originalLabelText = label ? label.textContent : '';
    if (label) label.textContent = 'Reading scanned document...';

    fetch('/register/extract-scan', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (label) label.textContent = originalLabelText;
            if (data.extracted) {
                autofillDrfFields(data.fields);
                console.log('OCR raw text preview:', data.raw_text_preview);
            } else {
                console.warn('Extraction failed:', data.reason);
            }
        })
        .catch(err => {
            if (label) label.textContent = originalLabelText;
            console.error('Extraction request failed:', err);
        });
}

function autofillDrfFields(fields) {
    const map = {
        drfNo: 'drfNo',
        drfDate: 'drfDate',
        drfTitle: 'drfTitle',
    };
    Object.entries(map).forEach(([fieldKey, elId]) => {
        const value = fields[fieldKey];
        const el = document.getElementById(elId);
        if (value && el && !el.value) {
            el.value = value;
            el.classList.add('reg-autofilled');
        }
    });
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
    window.__isSyllabiMode = false;
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
        revisionBody.querySelectorAll('tr[data-uid]').forEach(tr => removeRevisionRowDropdowns(tr));
        revisionBody.innerHTML =
            '<tr>' +
                '<td><input type="text" name="documentTitle[]" placeholder="Enter Document Title"></td>' +
                '<td><input type="text" name="documentNo[]" placeholder="Enter Document No."></td>' +
                '<td><input type="date" name="effectiveDate[]"></td>' +
                '<td><input type="number" name="revisionNo[]" placeholder="0"></td>' +
                '<td><input type="file" name="scannedCopy[]" accept=".pdf,.docx"></td>' +
                '<td><input type="text" name="revisionPurpose[]" placeholder="Enter Purpose"></td>' +
                '<td><button type="button" class="reg-row-del" onclick="removeRevisionRow(this)"><i class="fa-solid fa-trash-can"></i></button></td>' +
            '</tr>';
        const newRow = revisionBody.querySelector('tr');
        bindTableFileInput(newRow.querySelector('input[type="file"]'));
        bindRevisionRowSearch(newRow);
    }

    const syllabiBody = document.getElementById('syllabiTableBody');
    if (syllabiBody) {
        syllabiBody.querySelectorAll('tr[data-uid]').forEach(tr => removeSyllabiOriginatorDropdown(tr.dataset.uid));
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
        window.__isSyllabiMode = false;
        lockChecklist();
        return;
    }

    const isSyllabi = subTypeData && subTypeData.doc_type_name.toLowerCase() === "syllabi";
    window.__isSyllabiMode = isSyllabi;

    unlockChecklist();

    if (!isSyllabi) return;

    // DRF section already suppressed by toggleSection via __isSyllabiMode flag
    // Masterlist (section-3) stays visible — unlockChecklist showed it

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

    // In syllabi mode, only DRF stays hidden — Masterlist now shows in full
    if (checklistId === 1 && window.__isSyllabiMode) return;

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

function sectionVisible(id) {
    const el = document.getElementById(id);
    return el && el.style.display !== "none";
}

// ══════════════════════════════════════════════
// FORM VALIDATION — structural only; content fields are optional
// ══════════════════════════════════════════════
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

    // ── Time Spent must never be logically invalid (end before start) ──
    // this blocks saving outright — it's a data-integrity error, not a missing value.
    validateTimeSpentFields(errors);

    // ── DCN revision rows must reference an actual registered document ──
    validateRevisionRowsLinked(errors);

    // Content fields are otherwise optional — missing values are
    // surfaced in the review modal instead, with a confirm-anyway step.
    return errors;
}

/** Blocks save if a "Documents for Revision" row has typed text but was never
 *  linked to a real registered document via the search suggestions. */
function validateRevisionRowsLinked(errors) {
    if (!sectionVisible("section-2")) return;

    document.querySelectorAll("#revisionTableBody tr").forEach((row, idx) => {
        const titleInput = row.querySelector('input[name="documentTitle[]"]');
        const noInput = row.querySelector('input[name="documentNo[]"]');
        const hasTypedText = (titleInput && titleInput.value.trim()) || (noInput && noInput.value.trim());
        const isLinked = row.dataset.linked === "true";

        if (hasTypedText && !isLinked) {
            errors.push({
                field: "revisionTableBody",
                message: "DCN Row " + (idx + 1) + ": Please select an existing registered document from the suggestions — this document is not registered.",
                type: "table"
            });
            row.querySelectorAll('input[name="documentTitle[]"], input[name="documentNo[]"]')
                .forEach(el => el.classList.add("reg-input-error"));
        }
    });
}

/** Blocks save if any visible Time Spent calculation shows "Invalid". */
function validateTimeSpentFields(errors) {
    if (sectionVisible("section-3")) {
        const ml = document.getElementById("masterlistTimeSpentDisplay");
        if (ml && ml.value === "Invalid") {
            errors.push({
                field: "masterlistRegisteredDate",
                message: "Masterlist: Document Registered must be after Document Receipt."
            });
            // highlight both ends of the bad range, not just the field key used for scrolling
            ["masterlistReceiptDate", "masterlistReceiptTime", "masterlistRegisteredDate", "masterlistRegisteredTime"]
                .forEach(id => document.getElementById(id)?.classList.add("reg-input-error"));
        }
    }

    if (sectionVisible("section-4")) {
        const ret = document.getElementById("retrievalTimeSpentDisplay");
        if (ret && ret.value === "Invalid") {
            errors.push({
                field: "retrievalDate",
                message: "Retrieval: Retrieval Date must be after Form Date."
            });
            ["retrievalFormDate", "retrievalFormTime", "retrievalDate", "retrievalTime"]
                .forEach(id => document.getElementById(id)?.classList.add("reg-input-error"));
        }
    }

    if (sectionVisible("section-5")) {
        const dist = document.getElementById("distributionTimeSpentDisplay");
        if (dist && dist.value === "Invalid") {
            errors.push({
                field: "distributionDate",
                message: "Distribution: Distribution Date must be after Form Date."
            });
            ["distributionFormDate", "distributionFormTime", "distributionDate", "distributionTime"]
                .forEach(id => document.getElementById(id)?.classList.add("reg-input-error"));
        }
    }

    // BROKEN — inside validateTimeSpentFields()
    const sectionSyllabi = document.getElementById("section-syllabi");
    if (sectionSyllabi && sectionSyllabi.style.display !== "none") {
        document.querySelectorAll("#syllabiTableBody tr").forEach((row, idx) => {
            const display = row.querySelector(".syllabi-time-spent-display");
            const course = row.querySelector('input[name="syllabiCourseName[]"]');
            const rowLabel = "Syllabi Row " + (idx + 1);
            if (display && display.value === "Invalid") { /* ...ok... */ }
            if (!course || !course.value.trim()) missing.push(rowLabel + ": Course Name"); // ← missing is undefined here
            const pages = row.querySelector('input[name="syllabiNoPages[]"]');
            if (!pages || !pages.value) missing.push(rowLabel + ": No. of Pages"); // ← same
            const originator = row.querySelector('input[name="syllabiOriginator[]"]');
            if (!originator || !originator.value.trim()) missing.push(rowLabel + ": Originator"); // ← same
        });
    }
}

/** Collects a human-readable list of fields left blank, per visible section.
 *  Used only for the review modal's "missing information" summary — never blocks saving. */
function collectMissingFields() {
    const missing = [];

    const checkText = (sectionLabel, id, label) => {
        const el = document.getElementById(id);
        if (!el) return;
        if (!el.value || !el.value.trim()) missing.push(sectionLabel + ": " + label);
    };

    if (sectionVisible("section-1")) {
        checkText("DRF", "drfNo", "DRF No.");
        checkText("DRF", "drfDate", "DRF Date");
        checkText("DRF", "drfReceiptDate", "Date Receipt");
        checkText("DRF", "drfTime", "Time Receipt");
        checkText("DRF", "drfTitle", "Document Title");
        if (!window.__sourceWidgets.drf || window.__sourceWidgets.drf.selected.length === 0) {
            missing.push("DRF: Source Unit");
        }
    }

    if (sectionVisible("section-2")) {
        checkText("DCN", "dcnNumber", "DCN No.");
        checkText("DCN", "noticeDate", "DCN Date");
        checkText("DCN", "receiptDate", "DCN Receipt Date");
        checkText("DCN", "receiptTime", "DCN Receipt Time");
        checkText("DCN", "dcnSourceUnit", "Source Unit");
        let hasRevision = false;
        document.querySelectorAll("#revisionTableBody tr").forEach(row => {
            const title = row.querySelector('input[name="documentTitle[]"]');
            if (title && title.value.trim()) hasRevision = true;
        });
        if (!hasRevision) missing.push("DCN: At least one revision document");
    }

    if (sectionVisible("section-3")) {
        checkText("Masterlist", "masterlistDocNo", "Document No.");
        checkText("Masterlist", "masterlistDocTitle", "Document Title");
        checkText("Masterlist", "deadlineOfSubmission", "Deadline of Submission");
        checkText("Masterlist", "masterlistReceiptDate", "Document Receipt Date");
        checkText("Masterlist", "masterlistReceiptTime", "Document Receipt Time");
        checkText("Masterlist", "masterlistRegisteredDate", "Document Registered Date");
        checkText("Masterlist", "masterlistRegisteredTime", "Document Registered Time");
        checkText("Masterlist", "masterlistEffectivityDate", "Effectivity Date");
        checkText("Masterlist", "masterlistNoOfPages", "No. of Pages");
        checkText("Masterlist", "briefPurpose", "Brief Purpose");
        if (!window.__sourceWidgets.masterlistOriginator || window.__sourceWidgets.masterlistOriginator.selected.length === 0) {
            missing.push("Masterlist: Originator");
        }
        if (!window.__sourceWidgets.masterlist || window.__sourceWidgets.masterlist.selected.length === 0) {
            missing.push("Masterlist: Source Unit");
        }
    }

    if (sectionVisible("section-4")) {
        checkText("Retrieval", "retrievalFormDate", "Retrieval Form Date");
        checkText("Retrieval", "retrievalFormTime", "Retrieval Form Time");
        checkText("Retrieval", "retrievalDate", "Retrieval Date");
        checkText("Retrieval", "retrievalTime", "Retrieval Time");
        if (document.querySelectorAll("#retrievalBody input[type='hidden']").length === 0) {
            missing.push("Retrieval: At least one office");
        }
    }

    if (sectionVisible("section-5")) {
        checkText("Distribution", "distributionFormDate", "Distribution Form Date");
        checkText("Distribution", "distributionFormTime", "Distribution Form Time");
        checkText("Distribution", "distributionDate", "Distribution Date");
        checkText("Distribution", "distributionTime", "Distribution Time");
        checkText("Distribution", "distributionRemarks", "Remarks");
        if (document.querySelectorAll("#distBody input[type='hidden']").length === 0) {
            missing.push("Distribution: At least one office");
        }
    }

    const sectionSyllabi = document.getElementById("section-syllabi");
    if (sectionSyllabi && sectionSyllabi.style.display !== "none") {
        checkText("Syllabi", "syllabiCollege", "College");
        checkText("Syllabi", "syllabiProgram", "Program");
        checkText("Syllabi", "syllabiSemester", "Semester");
        checkText("Syllabi", "syllabiSchoolYear", "School Year");
        checkText("Syllabi", "syllabiDocNo", "Document No.");
        checkText("Syllabi", "syllabiDocTitle", "Document Title");
        checkText("Syllabi", "syllabiEffectivityDate", "Effectivity Date");
        checkText("Syllabi", "syllabiDeadline", "Deadline");

        document.querySelectorAll("#syllabiTableBody tr").forEach((row, idx) => {
            const course = row.querySelector('input[name="syllabiCourseName[]"]');
            const rowLabel = "Syllabi Row " + (idx + 1);
            if (!course || !course.value.trim()) missing.push(rowLabel + ": Course Name");
            const pages = row.querySelector('input[name="syllabiNoPages[]"]');
            if (!pages || !pages.value) missing.push(rowLabel + ": No. of Pages");
            const drfNo = row.querySelector('input[name="syllabiDrfNo[]"]');
            if (!drfNo || !drfNo.value.trim()) missing.push(rowLabel + ": DRF No.");
            const drfDate = row.querySelector('input[name="syllabiDrfDate[]"]');
            if (!drfDate || !drfDate.value) missing.push(rowLabel + ": DRF Date");
            const drfReceived = row.querySelector('input[name="syllabiDrfReceived[]"]');
            if (!drfReceived || !drfReceived.value) missing.push(rowLabel + ": DRF Received Date");
        });
    }

    return missing;
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

    let html = '<div class="review-section-title">' + title + '</div>';
    fields.forEach(f => {
        const hasValue = f.value && String(f.value).trim() !== "" && f.value !== "N/A";
        html += '<div class="review-row' + (hasValue ? '' : ' review-row-empty') + '">';
        html += '<span class="review-label">' + f.label + '</span>';
        if (hasValue) {
            html += f.isFile
                ? '<span class="review-value review-file"><i class="fa-solid fa-paperclip"></i> ' + f.value + '</span>'
                : '<span class="review-value">' + f.value + '</span>';
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
    syncSyllabiOriginatorHiddenFields();

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

    const missing = collectMissingFields();
    renderMissingFieldsWarning(reviewContent, missing);

    document.getElementById("confirmModal").style.display = "flex";
};

/** Shows a redesigned warning card + a required "save anyway" checkbox when fields are blank.
 *  Confirm Save button stays disabled until the checkbox is ticked (only when needed). */
function renderMissingFieldsWarning(container, missing) {
    const confirmBtn = document.getElementById("btnConfirmSaveModal");

    if (missing.length === 0) {
        if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.style.opacity = ''; confirmBtn.style.cursor = ''; }
        return;
    }

    // Group "Section: Field" strings by their section for a cleaner layout
    const grouped = {};
    missing.forEach(m => {
        const idx = m.indexOf(":");
        const section = idx > -1 ? m.slice(0, idx).trim() : "Other";
        const field = idx > -1 ? m.slice(idx + 1).trim() : m;
        if (!grouped[section]) grouped[section] = [];
        grouped[section].push(field);
    });

    const groupsHtml = Object.entries(grouped).map(([section, fields]) => `
        <div class="missing-group">
            <div class="missing-group-title">${section}</div>
            <div class="missing-group-chips">
                ${fields.map(f => `<span class="missing-chip"><i class="fa-solid fa-circle-minus"></i>${f}</span>`).join('')}
            </div>
        </div>
    `).join('');

    const warn = document.createElement("div");
    warn.className = "review-missing-warning";
    warn.innerHTML = `
        <div class="review-missing-header">
            <div class="review-missing-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="review-missing-heading">
                <span class="review-missing-title">Missing Information</span>
                <span class="review-missing-count">${missing.length} field${missing.length === 1 ? '' : 's'} left blank</span>
            </div>
        </div>
        <div class="review-missing-body">
            ${groupsHtml}
        </div>
        <label class="review-missing-confirm">
            <input type="checkbox" id="confirmSaveAnyway" onchange="handleConfirmSaveAnywayToggle(this)">
            <span>I understand some information above is missing, and I still want to save this document.</span>
        </label>
    `;
    container.prepend(warn);

    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.style.opacity = '0.5';
        confirmBtn.style.cursor = 'not-allowed';
    }
}

/** Fully syncs the Confirm Save button's enabled state AND its visual style
 *  to the "save anyway" checkbox — fixes the bug where the button became
 *  clickable but still looked greyed-out/disabled after checking the box. */
window.handleConfirmSaveAnywayToggle = function (checkbox) {
    const confirmBtn = document.getElementById('btnConfirmSaveModal');
    if (!confirmBtn) return;

    confirmBtn.disabled = !checkbox.checked;

    if (checkbox.checked) {
        confirmBtn.style.opacity = '';
        confirmBtn.style.cursor = '';
    } else {
        confirmBtn.style.opacity = '0.5';
        confirmBtn.style.cursor = 'not-allowed';
    }
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

        const pages = r.querySelector('input[name="syllabiNoPages[]"]');
        const originator = r.querySelector('input[name="syllabiOriginator[]"]');
        const drfAvail = r.querySelector('.syllabi-hidden-toggle[name="syllabiDrfAvailability[]"]');
        const drfNo = r.querySelector('input[name="syllabiDrfNo[]"]');
        const drfDate = r.querySelector('input[name="syllabiDrfDate[]"]');
        const drfReceived = r.querySelector('input[name="syllabiDrfReceived[]"]');

        addReviewSection(reviewContent, "Syllabi — " + course.value.trim(), [
            { label: "No. of Pages", value: pages?.value || "" },
            { label: "Originator", value: originator?.value?.trim() || null },
            { label: "DRF Availability", value: drfAvail?.value === 'available' ? 'Available' : 'Not Available' },
            { label: "DRF No.", value: drfNo?.value?.trim() || "" },
            { label: "DRF Date", value: fmtDateValue(drfDate?.value) },
            { label: "DRF Received", value: fmtDateValue(drfReceived?.value) },
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
    syncSyllabiOriginatorHiddenFields();
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
        <td><button type="button" class="reg-row-del" onclick="removeRevisionRow(this)"><i class="fa-solid fa-trash-can"></i></button></td>
    `;
    tbody.appendChild(tr);
    bindTableFileInput(tr.querySelector('input[type="file"]'));
    bindRevisionRowSearch(tr);
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
// ══════════════════════════════════════════════
// SYLLABI ROW BUILDER (merged-cell groups)
// ══════════════════════════════════════════════

function buildSyllabiOriginatorCellHTML(uid) {
    return `
        <div class="syllabi-originator-wrap" style="position:relative;">
            <input type="text" name="syllabiOriginator[]" id="syllabiOriginatorInput_${uid}"
                class="syllabi-originator-input" placeholder="Type a name" autocomplete="off">
        </div>
    `;
}

/** Cells shared by every copy-row: originator through registration upload. */
function buildSyllabiPerRowCells(uid, mirrorHiddenHTML = '') {
    return `
        <td class="col-step1">
            ${mirrorHiddenHTML}
            ${buildSyllabiOriginatorCellHTML(uid)}
        </td>
        <td class="col-step1"><input type="number" name="syllabiNoPages[]" min="0" placeholder="0"></td>
        <td class="col-step1"><input type="date" name="syllabiDateReceived[]" oninput="calcSyllabiRowTimeSpent(this.closest('tr'))"></td>
        <td class="col-step1"><input type="time" name="syllabiTimeReceived[]" oninput="calcSyllabiRowTimeSpent(this.closest('tr'))"></td>

        <td class="col-step2 syllabi-check-cell">
            <input type="hidden" name="syllabiDrfAvailability[]" value="not available" class="syllabi-hidden-toggle">
            <input type="checkbox" onchange="this.previousElementSibling.value = this.checked ? 'available' : 'not available'">
        </td>
        <td class="col-step2"><input type="text" name="syllabiDrfNo[]" placeholder="Enter DRF No."></td>
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

/** Positions a fixed-position dropdown directly under its input, escaping
 *  any parent's overflow/scroll clipping (this is what kills the double-scrollbar bug). */
/** Positions a fixed-position dropdown directly under its input. Since it lives
 *  in document.body, no transformed/filtered ancestor can hijack the fixed positioning. */
function positionFixedDropdown(dropdownEl, inputEl) {
    const rect = inputEl.getBoundingClientRect();
    dropdownEl.style.position = 'fixed';
    dropdownEl.style.top = (rect.bottom + 4) + 'px';
    dropdownEl.style.left = rect.left + 'px';
    dropdownEl.style.width = Math.max(rect.width, 160) + 'px';
    dropdownEl.style.maxHeight = '220px';
    dropdownEl.style.overflowY = 'auto';
    dropdownEl.style.zIndex = 9999;
}

function getOrCreateSyllabiOriginatorDropdown(uid) {
    let dd = document.getElementById('syllabiOriginatorDropdown_' + uid);
    if (!dd) {
        dd = document.createElement('div');
        dd.id = 'syllabiOriginatorDropdown_' + uid;
        dd.className = 'reg-reldocs-dropdown';
        dd.style.display = 'none';
        document.body.appendChild(dd);
    }
    return dd;
}

function closeSyllabiOriginatorDropdown(uid) {
    const dd = document.getElementById('syllabiOriginatorDropdown_' + uid);
    if (dd) dd.style.display = 'none';
}

function renderSyllabiOriginatorDropdown(uid) {
    const input = document.getElementById('syllabiOriginatorInput_' + uid);
    const dd = getOrCreateSyllabiOriginatorDropdown(uid);
    if (!input) return;

    const q = input.value.trim().toLowerCase();
    if (q.length < 1) { dd.style.display = 'none'; return; }

    const matches = allOriginators.filter(o => o.originator_name.toLowerCase().includes(q));

    if (matches.length === 0) {
        dd.innerHTML = '<div class="reg-reldocs-noresult">No matching originators found</div>';
    } else {
        dd.innerHTML = matches.map(o =>
            `<div onmousedown="pickSyllabiOriginator('${uid}', '${o.originator_name.replace(/'/g, "\\'")}')">${o.originator_name}</div>`
        ).join('');
    }

    positionFixedDropdown(dd, input);
    dd.style.display = 'block';
}

window.pickSyllabiOriginator = function (uid, name) {
    const input = document.getElementById('syllabiOriginatorInput_' + uid);
    if (input) input.value = name;
    closeSyllabiOriginatorDropdown(uid);
};

/** Wire a row's originator input once it's attached to the DOM. */
function bindSyllabiOriginatorInput(uid) {
    const input = document.getElementById('syllabiOriginatorInput_' + uid);
    if (!input || input.dataset.bound) return;
    input.dataset.bound = 'true';

    input.addEventListener('input', () => renderSyllabiOriginatorDropdown(uid));
    input.addEventListener('focus', () => renderSyllabiOriginatorDropdown(uid));

    const reposition = () => {
        const dd = document.getElementById('syllabiOriginatorDropdown_' + uid);
        if (dd && dd.style.display === 'block') positionFixedDropdown(dd, input);
    };
    window.addEventListener('scroll', reposition, true);
    window.addEventListener('resize', reposition);
}

/** Remove the body-attached dropdown when its row is deleted, so it doesn't linger orphaned. */
function removeSyllabiOriginatorDropdown(uid) {
    const dd = document.getElementById('syllabiOriginatorDropdown_' + uid);
    if (dd) dd.remove();
}

// Close any open syllabi-originator dropdown on outside click
document.addEventListener('click', function (e) {
    document.querySelectorAll('[id^="syllabiOriginatorDropdown_"]').forEach(dd => {
        const uid = dd.id.replace('syllabiOriginatorDropdown_', '');
        const input = document.getElementById('syllabiOriginatorInput_' + uid);
        if (dd.style.display === 'block' && !dd.contains(e.target) && e.target !== input) {
            dd.style.display = 'none';
        }
    });
});

// ══════════════════════════════════════════════
// SYLLABI — DRF DATE CASCADE + TOTAL COPIES
// ══════════════════════════════════════════════

/** When the first row's DRF Date or DRF Received changes, cascade to all other rows. */
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

/** When adding a new row, fill DRF Date/Received from the first row if they have values. */
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

/** Update the total copies display below the syllabi table. */
function updateSyllabiTotalCopies() {
    let total = 0;
    // Only count the visible copies inputs in the first row of each group
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

/** First row of a course group — holds the merged (rowspan) Course/Availability/Copies cells. */
function buildSyllabiGroupFirstRow(groupId, rowspan) {
    const tr = document.createElement("tr");
    tr.style.animation = "fadeSlideUp 0.25s ease";
    tr.dataset.group = groupId;
    tr.dataset.copyNo = 1;
    tr.dataset.isFirst = "true";
    const uid = ++syllabiRowUidCounter;
    tr.dataset.uid = uid;

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
            <input type="number" name="syllabiCopies[]" min="1" value="1"
                class="syllabi-merged-copies" oninput="handleCopiesChange(this)">
        </td>
        ${buildSyllabiPerRowCells(uid)}
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
    const uid = ++syllabiRowUidCounter;
    tr.dataset.uid = uid;

    const mirrors = `
        <input type="hidden" name="syllabiCourseName[]" class="syllabi-mirror-course">
        <input type="hidden" name="syllabiAvailability[]" value="0" class="syllabi-mirror-availability">
        <input type="hidden" name="syllabiCopies[]" class="syllabi-mirror-copies">
    `;

    tr.innerHTML = buildSyllabiPerRowCells(uid, mirrors);
    bindSyllabiRowFileInputs(tr);
    return tr;
}

/** Keeps the hidden mirror inputs in continuation rows synced to the merged (visible) fields. */
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
    bindSyllabiOriginatorInput(newRow.dataset.uid);
    cascadeDrfToNewRow(newRow);
    updateSyllabiTotalCopies();
};

window.removeSyllabiGroup = function (groupId) {
    document.querySelectorAll(`#syllabiTableBody tr[data-group="${groupId}"]`).forEach(r => {
        removeSyllabiOriginatorDropdown(r.dataset.uid);
        r.remove();
    });
    updateSyllabiTotalCopies();
};

// ══════════════════════════════════════════════
// COPY SPLITTING — driven only by the No. Copies field
// ══════════════════════════════════════════════
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
            bindSyllabiOriginatorInput(newRow.dataset.uid);
            cascadeDrfToNewRow(newRow);
            lastRow = newRow;
        }
    } else if (desired < groupRows.length) {
        for (let i = groupRows.length; i > desired; i--) {
            removeSyllabiOriginatorDropdown(groupRows[i - 1].dataset.uid);
            groupRows[i - 1].remove();
        }
    }
    firstRow.querySelectorAll('[rowspan]').forEach(td => td.setAttribute('rowspan', desired));
    syncSyllabiMergedFields(group);
    updateSyllabiTotalCopies();
};

// ══════════════════════════════════════════════
// REGISTRATION STEP — checkbox + time spent
// ══════════════════════════════════════════════
window.toggleSyllabiRegFields = function (checkbox) {
    const row = checkbox.closest("tr");
    const regDate = row.querySelector('[name="syllabiRegDate[]"]');
    const regTime = row.querySelector('[name="syllabiRegTime[]"]');
    const hidden = checkbox.previousElementSibling; // syllabiIsRegistered[] hidden mirror

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

