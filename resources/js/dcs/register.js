// register.js

let allOffices = [];
let allDocTypes = [];

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

        ["drfSourceUnit", "dcnSourceUnit", "masterlistSourceUnit"].forEach(id => {
            const sel = document.getElementById(id);
            if (sel) offices.forEach(o => sel.add(new Option(o.office_name, o.office_id)));
        });

        const approvalSelect = document.getElementById("approvalBody");
        if (approvalSelect) approvalBodies.forEach(a => approvalSelect.add(new Option(a.approval_name, a.approval_body_id)));

    } catch (err) {
        console.error("Failed to load data:", err);
    }

    document.querySelectorAll('.reg-upload-cell input[type="file"]').forEach(input => {
        input.addEventListener('change', function () {
            const label = this.closest('.reg-upload-cell').querySelector('span');
            label.textContent = this.files[0] ? this.files[0].name : 'No file chosen';
        });
    });
});

// ── Version Type selected ──
window.handleVersionChange = async function () {
    const versionId = document.getElementById("versionType").value;
    const docTypeSelect = document.getElementById("docType");
    const subTypeSelect = document.getElementById("subType");

    // Hide all sections + actions
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
        return;
    }

    try {
        const res = await fetch(`/api/checklist-versions/${versionId}`);
        const checklists = await res.json();
        renderChecklists(checklists, true);
    } catch (err) {
        console.error("Failed to load checklists:", err);
    }
};

// ── Doc Type selected ──
window.handleDocTypeChange = function () {
    const docTypeId = parseInt(document.getElementById("docType").value);
    const subTypeSelect = document.getElementById("subType");

    subTypeSelect.innerHTML = '<option value="" selected disabled>Select sub-type</option>';
    subTypeSelect.disabled = true;

    const children = allDocTypes.filter(d => d.parent_id === docTypeId);

    if (children.length > 0) {
        children.forEach(c => subTypeSelect.add(new Option(c.doc_type_name, c.doc_type_id)));
        subTypeSelect.disabled = false;
        lockChecklist();
    } else {
        unlockChecklist();
    }
};

// ── Sub-Type selected ──
window.validateChecklistState = function () {
    const subTypeId = document.getElementById("subType").value;
    const subTypeData = allDocTypes.find(d => d.doc_type_id == subTypeId);

    // Hide syllabi section by default
    const syllabiSection = document.getElementById("section-syllabi");
    if (syllabiSection) syllabiSection.style.display = "none";

    if (subTypeId) {
        unlockChecklist();

        // Show syllabi section only when sub-type is "Syllabi"
        if (subTypeData && subTypeData.doc_type_name.toLowerCase() === "syllabi") {
            if (syllabiSection) syllabiSection.style.display = "block";
        }
    } else {
        lockChecklist();
    }
};

// ── Render checklists ──
function renderChecklists(checklists, disabled) {
    const container = document.getElementById("dynamicCheckboxes");
    container.innerHTML = "";

    checklists.forEach(c => {
        const label = document.createElement("label");
        label.className = "reg-check-item";
        label.innerHTML = `
            <input type="checkbox" name="checklists[]" value="${c.checklist_id}"
                   onchange="toggleSection(${c.checklist_id}, this.checked)" ${disabled ? "disabled" : ""}>
            <span>${c.checklist_name}</span>
        `;
        container.appendChild(label);
    });
}

// ── Lock / Unlock ──
function lockChecklist() {
    const container = document.getElementById("dynamicCheckboxes");
    container.querySelectorAll("input[type='checkbox']").forEach(cb => {
        cb.disabled = true;
        cb.checked = false;
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
        toggleSection(parseInt(cb.value), true);
    });
    showFormActions();
    enableApproval();
}

// ── Toggle sections ──
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
        if (el) el.style.display = show ? "block" : "none";
    }
};

// ── Form actions visibility ──
function showFormActions() {
    const el = document.getElementById("formActions");
    if (el) el.style.display = "flex";
}

function hideFormActions() {
    const el = document.getElementById("formActions");
    if (el) el.style.display = "none";
}

// ── Confirmation modal ──
window.confirmSave = function () {
    document.getElementById("confirmModal").style.display = "flex";
};

window.closeConfirmModal = function () {
    document.getElementById("confirmModal").style.display = "none";
};

window.submitForm = function () {
    closeConfirmModal();
    document.getElementById("masterForm").submit();
};

// ── Approval ──
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

// ── Add revision row ──
window.addRevisionRow = function () {
    const tbody = document.getElementById("revisionTableBody");
    if (!tbody) return;
    const tr = document.createElement("tr");
    tr.innerHTML = `
        <td><input type="text" name="documentTitle[]" placeholder="Title"></td>
        <td><input type="text" name="documentNo[]" placeholder="Doc No."></td>
        <td><input type="date" name="effectiveDate[]"></td>
        <td><input type="text" name="revisionNo[]" placeholder="0"></td>
        <td><input type="file" name="scannedCopy[]"></td>
        <td><input type="text" name="revisionPurpose[]" placeholder="Purpose"></td>
        <td><button type="button" class="reg-row-del" onclick="this.closest('tr').remove()"><i class="fa-solid fa-trash-can"></i></button></td>
    `;
    tbody.appendChild(tr);
};

window.addSyllabiRow = function () {
    const tbody = document.getElementById("syllabiTableBody");
    if (!tbody) return;
    const tr = document.createElement("tr");
    tr.style.animation = "fadeSlideUp 0.25s ease";
    tr.innerHTML = `
        <td>
            <input type="text" name="syllabiCourseName[]" placeholder="Enter course name">
        </td>
        <td>
            <select name="syllabiAvailability[]">
                <option value="" disabled selected>Select</option>
                <option value="available">Available</option>
                <option value="not_available">Not Available</option>
            </select>
        </td>
        <td>
            <input type="number" name="syllabiNoPages[]" min="0" placeholder="0">
        </td>
        <td>
            <select name="syllabiDrfAvailability[]">
                <option value="" disabled selected>Select</option>
                <option value="available">Available</option>
                <option value="not_available">Not Available</option>
            </select>
        </td>
        <td>
            <input type="text" name="syllabiDrfNo[]" placeholder="DRF-001">
        </td>
        <td>
            <input type="date" name="syllabiDrfDate[]">
        </td>
        <td>
            <input type="date" name="syllabiDrfReceived[]">
        </td>
        <td>
            <label class="reg-upload-cell">
                <input type="file" name="syllabiScannedDrf[]" accept=".pdf,.jpg,.png">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <span>No file chosen</span>
            </label>
        </td>
        <td>
            <button type="button" class="reg-row-del" onclick="this.closest('tr').remove()">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);

    // Update file label when file is chosen
    tr.querySelector('input[type="file"]').addEventListener('change', function () {
        const label = this.closest('.reg-upload-cell').querySelector('span');
        label.textContent = this.files[0] ? this.files[0].name : 'No file chosen';
    });
};

// ── Office search ──
window.handleSearch = function (input, resultsId, bodyId, totalId) {
    const query = input.value.trim().toLowerCase();
    const dropdown = document.getElementById(resultsId);
    if (!dropdown) return;

    if (query.length < 1) {
        dropdown.style.display = "none";
        return;
    }

    const filtered = allOffices.filter(o => o.office_name.toLowerCase().includes(query));

    if (filtered.length === 0) {
        dropdown.style.display = "none";
        return;
    }

    dropdown.innerHTML = filtered
        .map(o => `<div onclick="addOffice(${o.office_id}, '${o.office_name.replace(/'/g, "\\'")}', '${bodyId}', '${totalId}', '${resultsId}')">${o.office_name}</div>`)
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

    const existingInputs = tbody.querySelectorAll('input[type="hidden"]');
    for (const inp of existingInputs) {
        if (inp.value == officeId) {
            dropdown.style.display = "none";
            return;
        }
    }

    const tr = document.createElement("tr");
    tr.innerHTML = `
        <td><input type="hidden" name="${officeNameAttr}" value="${officeId}">${officeName}</td>
        <td><input type="number" name="${copiesNameAttr}" value="1" min="1"
                   style="width:60px; height:32px; border:1px solid #e2e8f0; border-radius:4px; padding:0 8px; font-size:0.82rem;"
                   oninput="updateTotal('${totalId}')"></td>
        <td><button type="button" class="btn-remove" onclick="removeOffice(this, '${totalId}')"><i class="fa-solid fa-xmark"></i></button></td>
    `;
    tbody.appendChild(tr);

    updateTotal(totalId);
    dropdown.style.display = "none";
    dropdown.parentElement.querySelector("input[type='text']").value = "";
};

window.removeOffice = function (btn, totalId) {
    btn.closest("tr").remove();
    updateTotal(totalId);
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
        if (!dd.parentElement.contains(e.target)) {
            dd.style.display = "none";
        }
    });
});

window.handleGenerateReport = function () {
    alert("Report generation coming soon.");
};