document.addEventListener("DOMContentLoaded", function () {

    const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute("content");
    const overlay = document.getElementById("settingsOverlay");
    const modalBox = document.getElementById("settingsModalBox");
    const toast = document.getElementById("settingsToast");

    const BASE = "/settings";

    // ══════════════════════════════════════════════
    // UTILITY
    // ══════════════════════════════════════════════
    function escapeHtml(value) {
        const str = String(value ?? "");
        return str
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#39;");
    }

    // ══════════════════════════════════════════════
    // TOAST
    // ══════════════════════════════════════════════
    function showToast(message, type = "success") {
        toast.textContent = message;
        toast.className = "settings-toast show " + type;
        clearTimeout(toast._timer);
        toast._timer = setTimeout(() => {
            toast.className = "settings-toast";
        }, 3500);
    }

    // ══════════════════════════════════════════════
    // MODAL
    // ══════════════════════════════════════════════
    function openModal(html) {
        modalBox.innerHTML = html;
        overlay.style.display = "flex";
        document.body.style.overflow = "hidden";
    }

    function closeModal() {
        overlay.style.display = "none";
        modalBox.innerHTML = "";
        document.body.style.overflow = "";
    }
    window.closeSettingsModal = closeModal;

    overlay.addEventListener("click", (e) => {
        if (e.target === overlay) closeModal();
    });

    // Close on Escape
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && overlay.style.display === "flex") {
            closeModal();
        }
    });

    // ══════════════════════════════════════════════
    // INLINE FIELD ERRORS
    // ══════════════════════════════════════════════
    function clearFieldErrors() {
        modalBox.querySelectorAll(".field-error").forEach(el => el.remove());
        modalBox.querySelectorAll(".st-input.error").forEach(el => el.classList.remove("error"));
    }

    function showFieldErrors(errors) {
        clearFieldErrors();
        for (const [field, messages] of Object.entries(errors)) {
            const input = findFieldInput(field);
            if (input) {
                input.classList.add("error");
                const msg = document.createElement("div");
                msg.className = "field-error";
                msg.textContent = messages[0];
                input.parentNode.appendChild(msg);
            }
        }
        const first = modalBox.querySelector(".st-input.error");
        if (first) first.focus();
    }

    function findFieldInput(fieldName) {
        const candidates = [
            fieldName + "Input",
            fieldName.replace(/_([a-z])/g, (_, c) => c.toUpperCase()) + "Input",
            fieldName + "_input",
            fieldName,
        ];
        for (const id of candidates) {
            const el = document.getElementById(id);
            if (el) return el;
        }
        return modalBox.querySelector(`[name="${fieldName}"]`);
    }

    // ══════════════════════════════════════════════
    // LOADING STATE ON BUTTONS
    // ══════════════════════════════════════════════
    function setButtonLoading(btn, loading) {
        if (!btn) return;
        if (loading) {
            btn.dataset.originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
        } else {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.originalHtml || btn.innerHTML;
        }
    }

    // ══════════════════════════════════════════════
    // API CALL WITH FULL ERROR HANDLING
    // ══════════════════════════════════════════════
    async function apiCall(url, method, body = null) {
        const options = {
            method,
            headers: {
                "X-CSRF-TOKEN": CSRF,
                "Accept": "application/json",
            },
        };
        if (body) {
            options.headers["Content-Type"] = "application/json";
            options.body = JSON.stringify(body);
        }

        let res, data;

        try {
            res = await fetch(url, options);
        } catch (err) {
            return {
                success: false,
                status: 0,
                message: "Network error. Please check your connection and try again.",
                errors: {},
            };
        }

        try {
            data = await res.json();
        } catch (e) {
            return {
                success: false,
                status: res.status,
                message: "Unexpected response from the server.",
                errors: {},
            };
        }

        const isSuccess = res.ok && data.success !== false;

        if (isSuccess) {
            return {
                success: true,
                status: res.status,
                message: data.message || "Done.",
                ...data,
            };
        }

        let message = data.message || "";

        if (res.status === 422) {
            message = message || "Please fix the highlighted fields.";
        } else if (res.status === 404) {
            message = message || "The requested record was not found.";
        } else if (res.status === 403) {
            message = message || "You don't have permission to perform this action.";
        } else if (res.status === 405) {
            message = message || "This action is not allowed. Please refresh the page.";
        } else if (res.status === 409) {
            message = message || "A conflict occurred. This record may already exist.";
        } else if (res.status === 419) {
            message = message || "Your session has expired. Please refresh the page.";
        } else if (res.status >= 500) {
            message = message || "A server error occurred. Please try again later.";
        } else if (!res.ok) {
            message = message || `Request failed (HTTP ${res.status}).`;
        }

        return {
            success: false,
            status: res.status,
            message: message,
            errors: data.errors || {},
        };
    }

    // ══════════════════════════════════════════════
// DELETE CONFIRMATION MODAL
// ══════════════════════════════════════════════
function confirmDelete({ title = "Delete", message = "This action cannot be undone.", url, successCallback }) {
    openModal(`
        <div class="st-modal delete-modal">
            <div class="st-modal-top">
                <div class="st-modal-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <button class="st-modal-close" onclick="closeSettingsModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="delete-title">${escapeHtml(title)}</div>
            <div class="delete-message">${escapeHtml(message)}</div>
            <div class="st-actions-row">
                <button class="st-btn st-btn-ghost" onclick="closeSettingsModal()">Cancel</button>
                <button class="st-btn st-btn-danger" id="confirmDeleteBtn">
                    <i class="fa-solid fa-trash"></i> Delete
                </button>
            </div>
        </div>
    `);

    document.getElementById("confirmDeleteBtn").addEventListener("click", async () => {
        const btn = document.getElementById("confirmDeleteBtn");
            setButtonLoading(btn, true);
            const data = await apiCall(url, "DELETE");
            setButtonLoading(btn, false);

            if (data.success) {
                showToast(data.message, "success");
                closeModal();
                if (successCallback) {
                    successCallback(data);
                } else {
                    setTimeout(() => window.location.reload(), 600);
                }
            } else {
                showToast(data.message || "Delete failed.", "error");
            }
        });
    }

    // ══════════════════════════════════════════════
    // GENERIC SUBMIT HANDLER
    // ══════════════════════════════════════════════
    async function handleSubmit(btnSelector, url, method, body, onSuccess) {
        const btn = typeof btnSelector === "string"
            ? document.querySelector(btnSelector)
            : btnSelector;

        clearFieldErrors();
        setButtonLoading(btn, true);

        const data = await apiCall(url, method, body);

        setButtonLoading(btn, false);

        if (data.success) {
            showToast(data.message, "success");
            closeModal();
            if (onSuccess) {
                onSuccess(data);
            } else {
                setTimeout(() => window.location.reload(), 600);
            }
        } else {
            if (data.errors && Object.keys(data.errors).length > 0) {
                showFieldErrors(data.errors);
            }
            showToast(data.message, "error");
        }

        return data;
    }

    // ══════════════════════════════════════════════
    // TABS  (persists active tab, scrolls into view)
    // ══════════════════════════════════════════════
    function activateTab(tabName) {
        document.querySelectorAll(".tab-btn").forEach((b) => b.classList.remove("active"));
        document.querySelectorAll(".tab-panel").forEach((p) => p.classList.remove("active"));
        const btn = document.querySelector(`.tab-btn[data-tab="${tabName}"]`);
        const panel = document.getElementById("panel-" + tabName);
        if (btn && panel) {
            btn.classList.add("active");
            panel.classList.add("active");
            // Scroll tab into view on mobile
            btn.scrollIntoView({
                behavior: "smooth",
                block: "nearest",
                inline: "center",
            });
        }
    }

    document.querySelectorAll(".tab-btn").forEach((btn) => {
        btn.addEventListener("click", () => {
            activateTab(btn.dataset.tab);
            sessionStorage.setItem("settingsActiveTab", btn.dataset.tab);
        });
    });

    const savedTab = sessionStorage.getItem("settingsActiveTab");
    if (savedTab) activateTab(savedTab);

    // ══════════════════════════════════════════════
    // DOCUMENT TYPES
    // ══════════════════════════════════════════════
    window.openDocTypeModal = function (id = null, parentId = null) {
        const isEdit = !!id;
        let currentName = "";
        if (isEdit) {
            const row = document.querySelector(`[data-id="${id}"] .icon-btn[data-name]`) ||
                        document.querySelector(`.doctype-group[data-id="${id}"] .icon-btn[data-name]`) ||
                        document.querySelector(`.doctype-sub-row[data-id="${id}"] .icon-btn[data-name]`);
            currentName = row ? row.getAttribute("data-name") : "";
        }

        openModal(`
            <div class="st-modal">
                <div class="st-modal-top">
                    <div class="st-modal-icon"><i class="fa-solid fa-tags"></i></div>
                    <button class="st-modal-close" onclick="closeSettingsModal()"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="st-modal-title">${isEdit ? "Edit Document Type" : (parentId ? "Add Sub-type" : "Add Document Type")}</div>
                <div class="st-field">
                    <label class="st-label">Name</label>
                    <input type="text" id="doc_type_nameInput" class="st-input" placeholder="e.g. Internal, Syllabi" value="${escapeHtml(currentName)}">
                </div>
                <div class="st-actions-row">
                    <button class="st-btn st-btn-ghost" onclick="closeSettingsModal()">Cancel</button>
                    <button class="st-btn st-btn-primary" id="docTypeSubmitBtn" onclick="submitDocType(${id ?? "null"}, ${parentId ?? "null"})">
                        <i class="fa-solid fa-check"></i> Save
                    </button>
                </div>
            </div>
        `);
        setTimeout(() => document.getElementById("doc_type_nameInput")?.focus(), 50);
    };

    window.submitDocType = async function (id, parentId) {
        const name = document.getElementById("doc_type_nameInput").value.trim();
        if (!name) { showToast("Name is required.", "error"); return; }

        const isEdit = id !== null;
        const url = isEdit ? `${BASE}/doc-types/${id}` : `${BASE}/doc-types`;
        const method = isEdit ? "PUT" : "POST";
        const body = { doc_type_name: name };
        if (!isEdit) body.parent_id = parentId;

        await handleSubmit("#docTypeSubmitBtn", url, method, body);
    };

    window.deleteDocType = function (id) {
        confirmDelete({
            title: "Delete Document Type",
            message: "This document type will be permanently removed. This cannot be undone.",
            url: `${BASE}/doc-types/${id}`,
        });
    };

    // ══════════════════════════════════════════════
    // OFFICES
    // ══════════════════════════════════════════════
    window.openOfficeModal = function (id = null) {
        const isEdit = !!id;
        let currentName = "";
        if (isEdit) {
            const row = document.querySelector(`#officesTableBody tr[data-id="${id}"] .icon-btn[data-name]`);
            currentName = row ? row.getAttribute("data-name") : "";
        }

        openModal(`
            <div class="st-modal">
                <div class="st-modal-top">
                    <div class="st-modal-icon"><i class="fa-solid fa-building"></i></div>
                    <button class="st-modal-close" onclick="closeSettingsModal()"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="st-modal-title">${isEdit ? "Edit Office" : "Add Office"}</div>
                <div class="st-field">
                    <label class="st-label">Office Name</label>
                    <input type="text" id="office_nameInput" class="st-input" placeholder="e.g. Registrar's Office" value="${escapeHtml(currentName)}">
                </div>
                <div class="st-actions-row">
                    <button class="st-btn st-btn-ghost" onclick="closeSettingsModal()">Cancel</button>
                    <button class="st-btn st-btn-primary" id="officeSubmitBtn" onclick="submitOffice(${id ?? "null"})">
                        <i class="fa-solid fa-check"></i> Save
                    </button>
                </div>
            </div>
        `);
        setTimeout(() => document.getElementById("office_nameInput")?.focus(), 50);
    };

    window.submitOffice = async function (id) {
        const name = document.getElementById("office_nameInput").value.trim();
        if (!name) { showToast("Office name is required.", "error"); return; }

        const isEdit = id !== null;
        const url = isEdit ? `${BASE}/offices/${id}` : `${BASE}/offices`;
        const method = isEdit ? "PUT" : "POST";

        await handleSubmit("#officeSubmitBtn", url, method, { office_name: name });
    };

    window.toggleOfficeStatus = async function (id, checkbox) {
        const data = await apiCall(`${BASE}/offices/${id}/toggle-status`, "POST");
        if (data.success) {
            const label = checkbox.closest(".status-toggle").querySelector(".toggle-label");
            label.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
            showToast(data.message, "success");
        } else {
            checkbox.checked = !checkbox.checked;
            showToast(data.message || "Failed to update status.", "error");
        }
    };

    window.deleteOffice = function (id) {
        confirmDelete({
            title: "Delete Office",
            message: "This office will be permanently removed. Consider setting it inactive instead.",
            url: `${BASE}/offices/${id}`,
        });
    };

    // ══════════════════════════════════════════════
    // VERSION TYPES
    // ══════════════════════════════════════════════
    window.openVersionTypeModal = function (id = null) {
        const isEdit = !!id;
        let currentName = "";
        if (isEdit) {
            const row = document.querySelector(`#versionTypesTableBody tr[data-id="${id}"] .icon-btn[data-name]`);
            currentName = row ? row.getAttribute("data-name") : "";
        }

        openModal(`
            <div class="st-modal">
                <div class="st-modal-top">
                    <div class="st-modal-icon"><i class="fa-solid fa-code-branch"></i></div>
                    <button class="st-modal-close" onclick="closeSettingsModal()"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="st-modal-title">${isEdit ? "Edit Version Type" : "Add Version Type"}</div>
                <div class="st-field">
                    <label class="st-label">Version Name</label>
                    <input type="text" id="version_nameInput" class="st-input" placeholder="e.g. Original, Revised" value="${escapeHtml(currentName)}">
                </div>
                <div class="st-actions-row">
                    <button class="st-btn st-btn-ghost" onclick="closeSettingsModal()">Cancel</button>
                    <button class="st-btn st-btn-primary" id="versionTypeSubmitBtn" onclick="submitVersionType(${id ?? "null"})">
                        <i class="fa-solid fa-check"></i> Save
                    </button>
                </div>
            </div>
        `);
        setTimeout(() => document.getElementById("version_nameInput")?.focus(), 50);
    };

    window.submitVersionType = async function (id) {
        const name = document.getElementById("version_nameInput").value.trim();
        if (!name) { showToast("Version name is required.", "error"); return; }

        const isEdit = id !== null;
        const url = isEdit ? `${BASE}/version-types/${id}` : `${BASE}/version-types`;
        const method = isEdit ? "PUT" : "POST";

        await handleSubmit("#versionTypeSubmitBtn", url, method, { version_name: name });
    };

    window.deleteVersionType = function (id) {
        confirmDelete({
            title: "Delete Version Type",
            message: "This version type will be permanently removed.",
            url: `${BASE}/version-types/${id}`,
        });
    };

    // ══════════════════════════════════════════════
    // ORIGINATORS
    // ══════════════════════════════════════════════
    window.openOriginatorModal = function (id = null) {
        const isEdit = !!id;
        let currentName = "";
        if (isEdit) {
            const row = document.querySelector(`#originatorsTableBody tr[data-id="${id}"] .icon-btn[data-name]`);
            currentName = row ? row.getAttribute("data-name") : "";
        }

        openModal(`
            <div class="st-modal">
                <div class="st-modal-top">
                    <div class="st-modal-icon"><i class="fa-solid fa-user-pen"></i></div>
                    <button class="st-modal-close" onclick="closeSettingsModal()"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="st-modal-title">${isEdit ? "Edit Originator" : "Add Originator"}</div>
                <div class="st-field">
                    <label class="st-label">Originator Name</label>
                    <input type="text" id="originator_nameInput" class="st-input" placeholder="e.g. Juan Dela Cruz" value="${escapeHtml(currentName)}">
                </div>
                <div class="st-actions-row">
                    <button class="st-btn st-btn-ghost" onclick="closeSettingsModal()">Cancel</button>
                    <button class="st-btn st-btn-primary" id="originatorSubmitBtn" onclick="submitOriginator(${id ?? "null"})">
                        <i class="fa-solid fa-check"></i> Save
                    </button>
                </div>
            </div>
        `);
        setTimeout(() => document.getElementById("originator_nameInput")?.focus(), 50);
    };

    window.submitOriginator = async function (id) {
        const name = document.getElementById("originator_nameInput").value.trim();
        if (!name) { showToast("Originator name is required.", "error"); return; }

        const isEdit = id !== null;
        const url = isEdit ? `${BASE}/originators/${id}` : `${BASE}/originators`;
        const method = isEdit ? "PUT" : "POST";

        await handleSubmit("#originatorSubmitBtn", url, method, { originator_name: name });
    };

    window.deleteOriginator = function (id) {
        confirmDelete({
            title: "Delete Originator",
            message: "This originator will be permanently removed. This cannot be undone.",
            url: `${BASE}/originators/${id}`,
        });
    };

    // ══════════════════════════════════════════════
    // FACULTIES
    // ══════════════════════════════════════════════
    window.openFacultyModal = function (id = null) {
        const isEdit = !!id;
        let currentName = "", currentCollegeId = "";
        if (isEdit) {
            const row = document.querySelector(`#facultiesTableBody tr[data-id="${id}"] .icon-btn[data-name]`);
            currentName = row ? row.getAttribute("data-name") : "";
            currentCollegeId = row ? row.getAttribute("data-college") : "";
        }

        // Scrape colleges from the DOM — same pattern as openProgramModal
        const collegeRows = document.querySelectorAll("#collegesTableBody tr[data-id]");
        let collegeOptions = '<option value="">— No College —</option>';
        collegeRows.forEach(r => {
            const cid = r.dataset.id;
            const cname = r.querySelector("td:first-child")?.textContent?.trim() || "";
            const selected = cid == currentCollegeId ? "selected" : "";
            collegeOptions += `<option value="${cid}" ${selected}>${escapeHtml(cname)}</option>`;
        });

        openModal(`
            <div class="st-modal">
                <div class="st-modal-top">
                    <div class="st-modal-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
                    <button class="st-modal-close" onclick="closeSettingsModal()"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="st-modal-title">${isEdit ? "Edit Faculty" : "Add Faculty"}</div>
                <div class="st-field">
                    <label class="st-label">College <span style="opacity:0.5;font-weight:normal;">(optional)</span></label>
                    <select id="faculty_college_idInput" class="st-input">${collegeOptions}</select>
                </div>
                <div class="st-field">
                    <label class="st-label">Faculty Name</label>
                    <input type="text" id="faculty_nameInput" class="st-input" placeholder="e.g. Juan Dela Cruz" value="${escapeHtml(currentName)}">
                </div>
                <div class="st-actions-row">
                    <button class="st-btn st-btn-ghost" onclick="closeSettingsModal()">Cancel</button>
                    <button class="st-btn st-btn-primary" id="facultySubmitBtn" onclick="submitFaculty(${id ?? "null"})">
                        <i class="fa-solid fa-check"></i> Save
                    </button>
                </div>
            </div>
        `);
        setTimeout(() => document.getElementById("faculty_college_idInput")?.focus(), 50);
    };

    window.submitFaculty = async function (id) {
        const selectEl = document.getElementById("faculty_college_idInput");
        console.log("Select has options:", selectEl.options.length, "current value:", selectEl.value);
        const collegeId = selectEl.value || null;
        const name = document.getElementById("faculty_nameInput").value.trim();

        console.log("Sending:", { faculty_name: name, college_id: collegeId }); // ← ADD THIS

        if (!name) { showToast("Faculty name is required.", "error"); return; }

        const isEdit = id !== null;
        const url = isEdit ? `${BASE}/faculties/${id}` : `${BASE}/faculties`;
        const method = isEdit ? "PUT" : "POST";

        await handleSubmit("#facultySubmitBtn", url, method, {
            faculty_name: name,
            college_id: collegeId,
        });
    };

    window.deleteFaculty = function (id) {
        confirmDelete({
            title: "Delete Faculty",
            message: "This faculty member will be permanently removed.",
            url: `${BASE}/faculties/${id}`,
        });
    };

    // ══════════════════════════════════════════════
    // COLLEGES
    // ══════════════════════════════════════════════
    window.openCollegeModal = function (id = null) {
        const isEdit = !!id;
        let currentName = "";
        if (isEdit) {
            const row = document.querySelector(`#collegesTableBody tr[data-id="${id}"] .icon-btn[data-name]`);
            currentName = row ? row.getAttribute("data-name") : "";
        }

        openModal(`
            <div class="st-modal">
                <div class="st-modal-top">
                    <div class="st-modal-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                    <button class="st-modal-close" onclick="closeSettingsModal()"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="st-modal-title">${isEdit ? "Edit College" : "Add College"}</div>
                <div class="st-field">
                    <label class="st-label">College Name</label>
                    <input type="text" id="college_nameInput" class="st-input" placeholder="e.g. College of Computer Studies" value="${escapeHtml(currentName)}">
                </div>
                <div class="st-actions-row">
                    <button class="st-btn st-btn-ghost" onclick="closeSettingsModal()">Cancel</button>
                    <button class="st-btn st-btn-primary" id="collegeSubmitBtn" onclick="submitCollege(${id ?? "null"})">
                        <i class="fa-solid fa-check"></i> Save
                    </button>
                </div>
            </div>
        `);
        setTimeout(() => document.getElementById("college_nameInput")?.focus(), 50);
    };

    window.submitCollege = async function (id) {
        const name = document.getElementById("college_nameInput").value.trim();
        if (!name) { showToast("College name is required.", "error"); return; }

        const isEdit = id !== null;
        const url = isEdit ? `${BASE}/colleges/${id}` : `${BASE}/colleges`;
        const method = isEdit ? "PUT" : "POST";

        await handleSubmit("#collegeSubmitBtn", url, method, { college_name: name });
    };

    window.deleteCollege = function (id) {
        confirmDelete({
            title: "Delete College",
            message: "This college and all its programs will be permanently removed.",
            url: `${BASE}/colleges/${id}`,
        });
    };


    // ══════════════════════════════════════════════
    // PROGRAMS
    // ══════════════════════════════════════════════
    window.openProgramModal = function (id = null) {
        const isEdit = !!id;
        let currentCollegeId = "", currentName = "", currentCode = "";
        if (isEdit) {
            const row = document.querySelector(`#programsTableBody tr[data-id="${id}"] .icon-btn[data-name]`);
            currentCollegeId = row ? row.getAttribute("data-college") : "";
            currentName = row ? row.getAttribute("data-name") : "";
            currentCode = row ? row.getAttribute("data-code") : "";
        }

        const collegeRows = document.querySelectorAll("#collegesTableBody tr[data-id]");
        let collegeOptions = '<option value="">Select College</option>';
        collegeRows.forEach(r => {
            const cid = r.dataset.id;
            const cname = r.querySelector("td:first-child")?.textContent?.trim() || "";
            const selected = cid === currentCollegeId ? "selected" : "";
            collegeOptions += `<option value="${cid}" ${selected}>${escapeHtml(cname)}</option>`;
        });

        openModal(`
            <div class="st-modal">
                <div class="st-modal-top">
                    <div class="st-modal-icon"><i class="fa-solid fa-book-open"></i></div>
                    <button class="st-modal-close" onclick="closeSettingsModal()"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="st-modal-title">${isEdit ? "Edit Program" : "Add Program"}</div>
                <div class="st-field">
                    <label class="st-label">College</label>
                    <select id="college_idInput" class="st-input">${collegeOptions}</select>
                </div>
                <div class="st-field">
                    <label class="st-label">Program Name</label>
                    <input type="text" id="program_nameInput" class="st-input" placeholder="e.g. Bachelor of Library and Information Science" value="${escapeHtml(currentName)}">
                </div>
                <div class="st-field">
                    <label class="st-label">Program Code</label>
                    <input type="text" id="program_codeInput" class="st-input" placeholder="e.g. BLIS" value="${escapeHtml(currentCode)}" style="text-transform:uppercase;">
                </div>
                <div class="st-actions-row">
                    <button class="st-btn st-btn-ghost" onclick="closeSettingsModal()">Cancel</button>
                    <button class="st-btn st-btn-primary" id="programSubmitBtn" onclick="submitProgram(${id ?? "null"})">
                        <i class="fa-solid fa-check"></i> Save
                    </button>
                </div>
            </div>
        `);
        setTimeout(() => document.getElementById("college_idInput")?.focus(), 50);
    };

    window.submitProgram = async function (id) {
        const collegeId = document.getElementById("college_idInput").value;
        const name = document.getElementById("program_nameInput").value.trim();
        const code = document.getElementById("program_codeInput").value.trim().toUpperCase();
        if (!collegeId || !name) { showToast("College and Program name are required.", "error"); return; }

        const isEdit = id !== null;
        const url = isEdit ? `${BASE}/programs/${id}` : `${BASE}/programs`;
        const method = isEdit ? "PUT" : "POST";

        await handleSubmit("#programSubmitBtn", url, method, {
            college_id: collegeId,
            program_name: name,
            program_code: code || null,
        });
    };

    window.deleteProgram = function (id) {
        confirmDelete({
            title: "Delete Program",
            message: "This program will be permanently removed.",
            url: `${BASE}/programs/${id}`,
        });
    };

    // ══════════════════════════════════════════════
    // SEMESTERS
    // ══════════════════════════════════════════════
    window.openSemesterModal = function (id = null) {
        const isEdit = !!id;
        let currentName = "";
        if (isEdit) {
            const row = document.querySelector(`#semestersTableBody tr[data-id="${id}"] .icon-btn[data-name]`);
            currentName = row ? row.getAttribute("data-name") : "";
        }

        openModal(`
            <div class="st-modal">
                <div class="st-modal-top">
                    <div class="st-modal-icon"><i class="fa-solid fa-calendar-week"></i></div>
                    <button class="st-modal-close" onclick="closeSettingsModal()"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="st-modal-title">${isEdit ? "Edit Semester" : "Add Semester"}</div>
                <div class="st-field">
                    <label class="st-label">Semester Name</label>
                    <input type="text" id="semester_nameInput" class="st-input" placeholder="e.g. 1st Semester" value="${escapeHtml(currentName)}">
                </div>
                <div class="st-actions-row">
                    <button class="st-btn st-btn-ghost" onclick="closeSettingsModal()">Cancel</button>
                    <button class="st-btn st-btn-primary" id="semesterSubmitBtn" onclick="submitSemester(${id ?? "null"})">
                        <i class="fa-solid fa-check"></i> Save
                    </button>
                </div>
            </div>
        `);
        setTimeout(() => document.getElementById("semester_nameInput")?.focus(), 50);
    };

    window.submitSemester = async function (id) {
        const name = document.getElementById("semester_nameInput").value.trim();
        if (!name) { showToast("Semester name is required.", "error"); return; }

        const isEdit = id !== null;
        const url = isEdit ? `${BASE}/semesters/${id}` : `${BASE}/semesters`;
        const method = isEdit ? "PUT" : "POST";

        await handleSubmit("#semesterSubmitBtn", url, method, { semester_name: name });
    };

    window.deleteSemester = function (id) {
        confirmDelete({
            title: "Delete Semester",
            message: "This semester will be permanently removed.",
            url: `${BASE}/semesters/${id}`,
        });
    };

    // ══════════════════════════════════════════════
    // SCHOOL YEARS
    // ══════════════════════════════════════════════
    window.openSchoolYearModal = function (id = null) {
        const isEdit = !!id;
        let currentName = "";
        if (isEdit) {
            const row = document.querySelector(`#schoolYearsTableBody tr[data-id="${id}"] .icon-btn[data-name]`);
            currentName = row ? row.getAttribute("data-name") : "";
        }

        openModal(`
            <div class="st-modal">
                <div class="st-modal-top">
                    <div class="st-modal-icon"><i class="fa-solid fa-calendar-days"></i></div>
                    <button class="st-modal-close" onclick="closeSettingsModal()"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="st-modal-title">${isEdit ? "Edit School Year" : "Add School Year"}</div>
                <div class="st-field">
                    <label class="st-label">School Year</label>
                    <input type="text" id="school_yearInput" class="st-input" placeholder="e.g. 2026-2027" value="${escapeHtml(currentName)}">
                </div>
                <div class="st-actions-row">
                    <button class="st-btn st-btn-ghost" onclick="closeSettingsModal()">Cancel</button>
                    <button class="st-btn st-btn-primary" id="schoolYearSubmitBtn" onclick="submitSchoolYear(${id ?? "null"})">
                        <i class="fa-solid fa-check"></i> Save
                    </button>
                </div>
            </div>
        `);
        setTimeout(() => document.getElementById("school_yearInput")?.focus(), 50);
    };

    window.submitSchoolYear = async function (id) {
        const name = document.getElementById("school_yearInput").value.trim();
        if (!name) { showToast("School year is required.", "error"); return; }

        const isEdit = id !== null;
        const url = isEdit ? `${BASE}/school-years/${id}` : `${BASE}/school-years`;
        const method = isEdit ? "PUT" : "POST";

        await handleSubmit("#schoolYearSubmitBtn", url, method, { school_year: name });
    };

    window.deleteSchoolYear = function (id) {
        confirmDelete({
            title: "Delete School Year",
            message: "This school year will be permanently removed.",
            url: `${BASE}/school-years/${id}`,
        });
    };

    // ══════════════════════════════════════════════
    // PROGRAM COURSES (curriculum)
    // ══════════════════════════════════════════════
    window.openProgramCourseModal = function (id = null) {
        const isEdit = !!id;
        let currentProgramId = "", currentSemesterId = "", currentName = "";
        if (isEdit) {
            const row = document.querySelector(`#programCoursesTableBody tr[data-id="${id}"] .icon-btn[data-name]`);
            currentProgramId = row ? row.getAttribute("data-program") : "";
            currentSemesterId = row ? row.getAttribute("data-semester") : "";
            currentName = row ? row.getAttribute("data-name") : "";
        }

        // Programs — same DOM-scraping approach as openProgramModal() uses for Colleges.
        // #programsTableBody rows are: College (td 1), Program (td 2), Actions (td 3).
        const programRows = document.querySelectorAll("#programsTableBody tr[data-id]");
        let programOptions = '<option value="">Select Program</option>';
        programRows.forEach(r => {
            const pid = r.dataset.id;
            const cells = r.querySelectorAll("td");
            const collegeName = cells[0]?.textContent?.trim() || "";
            const programName = cells[1]?.textContent?.trim() || "";
            const selected = pid === currentProgramId ? "selected" : "";
            programOptions += `<option value="${pid}" ${selected}>${escapeHtml(collegeName)} — ${escapeHtml(programName)}</option>`;
        });

        const semesterRows = document.querySelectorAll("#semestersTableBody tr[data-id]");
        let semesterOptions = '<option value="">Select Semester</option>';
        semesterRows.forEach(r => {
            const sid = r.dataset.id;
            const semName = r.querySelector("td:first-child")?.textContent?.trim() || "";
            const selected = sid === currentSemesterId ? "selected" : "";
            semesterOptions += `<option value="${sid}" ${selected}>${escapeHtml(semName)}</option>`;
        });

        openModal(`
            <div class="st-modal">
                <div class="st-modal-top">
                    <div class="st-modal-icon"><i class="fa-solid fa-list-check"></i></div>
                    <button class="st-modal-close" onclick="closeSettingsModal()"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="st-modal-title">${isEdit ? "Edit Course" : "Add Course"}</div>
                <div class="st-field">
                    <label class="st-label">Program</label>
                    <select id="course_program_idInput" class="st-input">${programOptions}</select>
                </div>
                <div class="st-field">
                    <label class="st-label">Semester</label>
                    <select id="course_semester_idInput" class="st-input">${semesterOptions}</select>
                </div>
                <div class="st-field">
                    <label class="st-label">Course Name</label>
                    <input type="text" id="course_nameInput" class="st-input" placeholder="e.g. Data Structures and Algorithms" value="${escapeHtml(currentName)}">
                </div>
                <div class="st-actions-row">
                    <button class="st-btn st-btn-ghost" onclick="closeSettingsModal()">Cancel</button>
                    <button class="st-btn st-btn-primary" id="programCourseSubmitBtn" onclick="submitProgramCourse(${id ?? "null"})">
                        <i class="fa-solid fa-check"></i> Save
                    </button>
                </div>
            </div>
        `);
        setTimeout(() => document.getElementById("course_program_idInput")?.focus(), 50);
    };

    window.submitProgramCourse = async function (id) {
        const programId = document.getElementById("course_program_idInput").value;
        const semesterId = document.getElementById("course_semester_idInput").value;
        const name = document.getElementById("course_nameInput").value.trim();
        if (!programId || !semesterId || !name) { showToast("All fields are required.", "error"); return; }

        const isEdit = id !== null;
        const url = isEdit ? `${BASE}/program-courses/${id}` : `${BASE}/program-courses`;
        const method = isEdit ? "PUT" : "POST";

        await handleSubmit("#programCourseSubmitBtn", url, method, {
            program_id: programId,
            semester_id: semesterId,
            course_name: name,
        });
    };

    window.deleteProgramCourse = function (id) {
        confirmDelete({
            title: "Delete Course",
            message: "This course will be permanently removed from the curriculum list.",
            url: `${BASE}/program-courses/${id}`,
        });
    };

});