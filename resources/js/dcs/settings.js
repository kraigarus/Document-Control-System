document.addEventListener("DOMContentLoaded", function () {

    const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute("content");
    const overlay = document.getElementById("settingsOverlay");
    const modalBox = document.getElementById("settingsModalBox");
    const toast = document.getElementById("settingsToast");

    // ── Route base — adjust if your app isn't served at root ──
    const BASE = "/settings";

    function escapeHtml(value) {
        const str = String(value ?? "");
        return str
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#39;");
    }

    function showToast(message, type = "success") {
        toast.textContent = message;
        toast.className = "settings-toast show " + type;
        setTimeout(() => { toast.className = "settings-toast"; }, 3000);
    }

    function openModal(html) {
        modalBox.innerHTML = html;
        overlay.style.display = "flex";
    }

    function closeModal() {
        overlay.style.display = "none";
        modalBox.innerHTML = "";
    }
    window.closeSettingsModal = closeModal;

    overlay.addEventListener("click", (e) => {
        if (e.target === overlay) closeModal();
    });

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

        const res = await fetch(url, options);
        let data;
        try {
            data = await res.json();
        } catch (e) {
            data = { success: false, message: "Unexpected server response." };
        }
        if (!res.ok && !data.message) {
            data.message = "Request failed (HTTP " + res.status + ").";
        }
        return data;
    }

    // ══════════════════════════════════════════════
    // TABS
    // ══════════════════════════════════════════════
    document.querySelectorAll(".tab-btn").forEach((btn) => {
        btn.addEventListener("click", () => {
            document.querySelectorAll(".tab-btn").forEach((b) => b.classList.remove("active"));
            document.querySelectorAll(".tab-panel").forEach((p) => p.classList.remove("active"));
            btn.classList.add("active");
            document.getElementById("panel-" + btn.dataset.tab).classList.add("active");
        });
    });

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

        const title = isEdit
            ? "Edit " + (currentName ? "" : "")
            : (parentId ? "Add Sub-type" : "Add Document Type");

        openModal(`
            <div class="st-modal">
                <div class="st-modal-top">
                    <div class="st-modal-icon"><i class="fa-solid fa-tags"></i></div>
                    <button class="st-modal-close" onclick="closeSettingsModal()"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="st-modal-title">${isEdit ? "Edit Document Type" : (parentId ? "Add Sub-type" : "Add Document Type")}</div>
                <div class="st-field">
                    <label class="st-label">Name</label>
                    <input type="text" id="docTypeNameInput" class="st-input" placeholder="e.g. Internal, Syllabi" value="${escapeHtml(currentName)}">
                </div>
                <div class="st-actions-row">
                    <button class="st-btn st-btn-ghost" onclick="closeSettingsModal()">Cancel</button>
                    <button class="st-btn st-btn-primary" onclick="submitDocType(${id ?? "null"}, ${parentId ?? "null"})">
                        <i class="fa-solid fa-check"></i> Save
                    </button>
                </div>
            </div>
        `);
        setTimeout(() => document.getElementById("docTypeNameInput")?.focus(), 50);
    };

    window.submitDocType = async function (id, parentId) {
        const name = document.getElementById("docTypeNameInput").value.trim();
        if (!name) { showToast("Name is required.", "error"); return; }

        const isEdit = id !== null;
        const url = isEdit ? `${BASE}/doc-types/${id}` : `${BASE}/doc-types`;
        const method = isEdit ? "PUT" : "POST";
        const body = { doc_type_name: name };
        if (!isEdit) body.parent_id = parentId;

        const data = await apiCall(url, method, body);
        if (data.success) {
            showToast(data.message, "success");
            closeModal();
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(data.message || "Something went wrong.", "error");
        }
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
                    <input type="text" id="officeNameInput" class="st-input" placeholder="e.g. Registrar's Office" value="${escapeHtml(currentName)}">
                </div>
                <div class="st-actions-row">
                    <button class="st-btn st-btn-ghost" onclick="closeSettingsModal()">Cancel</button>
                    <button class="st-btn st-btn-primary" onclick="submitOffice(${id ?? "null"})">
                        <i class="fa-solid fa-check"></i> Save
                    </button>
                </div>
            </div>
        `);
        setTimeout(() => document.getElementById("officeNameInput")?.focus(), 50);
    };

    window.submitOffice = async function (id) {
        const name = document.getElementById("officeNameInput").value.trim();
        if (!name) { showToast("Office name is required.", "error"); return; }

        const isEdit = id !== null;
        const url = isEdit ? `${BASE}/offices/${id}` : `${BASE}/offices`;
        const method = isEdit ? "PUT" : "POST";

        const data = await apiCall(url, method, { office_name: name });
        if (data.success) {
            showToast(data.message, "success");
            closeModal();
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(data.message || "Something went wrong.", "error");
        }
    };

    window.toggleOfficeStatus = async function (id, checkbox) {
        const data = await apiCall(`${BASE}/offices/${id}/toggle-status`, "POST");
        if (data.success) {
            const label = checkbox.closest(".status-toggle").querySelector(".toggle-label");
            label.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
            showToast(data.message, "success");
        } else {
            checkbox.checked = !checkbox.checked; // revert
            showToast(data.message || "Failed to update status.", "error");
        }
    };

    window.deleteOffice = async function (id) {
        if (!confirm("Delete this office? Consider setting it Inactive instead if it may have historical records.")) return;
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
                    <input type="text" id="versionTypeNameInput" class="st-input" placeholder="e.g. Original, Revised" value="${escapeHtml(currentName)}">
                </div>
                <div class="st-actions-row">
                    <button class="st-btn st-btn-ghost" onclick="closeSettingsModal()">Cancel</button>
                    <button class="st-btn st-btn-primary" onclick="submitVersionType(${id ?? "null"})">
                        <i class="fa-solid fa-check"></i> Save
                    </button>
                </div>
            </div>
        `);
        setTimeout(() => document.getElementById("versionTypeNameInput")?.focus(), 50);
    };

    window.submitVersionType = async function (id) {
        const name = document.getElementById("versionTypeNameInput").value.trim();
        if (!name) { showToast("Version name is required.", "error"); return; }

        const isEdit = id !== null;
        const url = isEdit ? `${BASE}/version-types/${id}` : `${BASE}/version-types`;
        const method = isEdit ? "PUT" : "POST";

        const data = await apiCall(url, method, { version_name: name });
        if (data.success) {
            showToast(data.message, "success");
            closeModal();
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(data.message || "Something went wrong.", "error");
        }
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

});
