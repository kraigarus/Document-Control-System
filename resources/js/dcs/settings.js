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

    window.deleteDocType = async function (id) {
        if (!confirm("Delete this document type? This cannot be undone.")) return;
        const data = await apiCall(`${BASE}/doc-types/${id}`, "DELETE");
        if (data.success) {
            showToast(data.message, "success");
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(data.message || "Delete failed.", "error");
        }
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

    window.deleteOffice = async function (id) {
        if (!confirm("Delete this office? Consider setting it Inactive instead.")) return;
        const data = await apiCall(`${BASE}/offices/${id}`, "DELETE");
        if (data.success) {
            showToast(data.message, "success");
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(data.message || "Delete failed.", "error");
        }
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

    window.deleteVersionType = async function (id) {
        if (!confirm("Delete this version type?")) return;
        const data = await apiCall(`${BASE}/version-types/${id}`, "DELETE");
        if (data.success) {
            showToast(data.message, "success");
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(data.message || "Delete failed.", "error");
        }
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

    window.deleteOriginator = async function (id) {
        if (!confirm("Delete this originator? This cannot be undone.")) return;
        const data = await apiCall(`${BASE}/originators/${id}`, "DELETE");
        if (data.success) {
            showToast(data.message, "success");
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(data.message || "Delete failed.", "error");
        }
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

    window.deleteCollege = async function (id) {
        if (!confirm("Delete this college? All its programs will also be removed.")) return;
        const data = await apiCall(`${BASE}/colleges/${id}`, "DELETE");
        if (data.success) {
            showToast(data.message, "success");
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(data.message || "Delete failed.", "error");
        }
    };

    // ══════════════════════════════════════════════
    // PROGRAMS
    // ══════════════════════════════════════════════
    window.openProgramModal = function (id = null) {
        const isEdit = !!id;
        let currentCollegeId = "", currentName = "";
        if (isEdit) {
            const row = document.querySelector(`#programsTableBody tr[data-id="${id}"] .icon-btn[data-name]`);
            currentCollegeId = row ? row.getAttribute("data-college") : "";
            currentName = row ? row.getAttribute("data-name") : "";
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
                    <input type="text" id="program_nameInput" class="st-input" placeholder="e.g. Bachelor of Science in IT" value="${escapeHtml(currentName)}">
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
        if (!collegeId || !name) { showToast("All fields are required.", "error"); return; }

        const isEdit = id !== null;
        const url = isEdit ? `${BASE}/programs/${id}` : `${BASE}/programs`;
        const method = isEdit ? "PUT" : "POST";

        await handleSubmit("#programSubmitBtn", url, method, { college_id: collegeId, program_name: name });
    };

    window.deleteProgram = async function (id) {
        if (!confirm("Delete this program?")) return;
        const data = await apiCall(`${BASE}/programs/${id}`, "DELETE");
        if (data.success) {
            showToast(data.message, "success");
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(data.message || "Delete failed.", "error");
        }
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

    window.deleteSemester = async function (id) {
        if (!confirm("Delete this semester?")) return;
        const data = await apiCall(`${BASE}/semesters/${id}`, "DELETE");
        if (data.success) {
            showToast(data.message, "success");
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(data.message || "Delete failed.", "error");
        }
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

    window.deleteSchoolYear = async function (id) {
        if (!confirm("Delete this school year?")) return;
        const data = await apiCall(`${BASE}/school-years/${id}`, "DELETE");
        if (data.success) {
            showToast(data.message, "success");
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(data.message || "Delete failed.", "error");
        }
    };

});