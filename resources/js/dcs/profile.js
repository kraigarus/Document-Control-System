document.addEventListener("DOMContentLoaded", function () {

    const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute("content");
    const toast = document.getElementById("settingsToast");

    const BASE = "/profile";

    function showToast(message, type = "success") {
        toast.textContent = message;
        toast.className = "settings-toast show " + type;
        setTimeout(() => { toast.className = "settings-toast"; }, 3000);
    }

    async function apiCall(url, method, body = null, isFormData = false) {
        const options = {
            method,
            headers: { "X-CSRF-TOKEN": CSRF, "Accept": "application/json" },
        };
        if (body) {
            if (isFormData) {
                options.body = body; // browser sets multipart headers
            } else {
                options.headers["Content-Type"] = "application/json";
                options.body = JSON.stringify(body);
            }
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
    // TABS (same behavior as settings.js)
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
    // PROFILE INFO
    // ══════════════════════════════════════════════
    const infoForm = document.getElementById("infoForm");
    infoForm?.addEventListener("submit", async (e) => {
        e.preventDefault();
        const name = document.getElementById("nameInput").value.trim();
        const email = document.getElementById("emailInput").value.trim();

        if (!name || !email) { showToast("Name and email are required.", "error"); return; }

        const data = await apiCall(`${BASE}/info`, "PUT", { name, email });
        if (data.success) {
            showToast(data.message, "success");
            document.querySelector(".profile-identity h2").textContent = name;
            document.querySelector(".profile-identity p").textContent = email;
        } else {
            showToast(data.message || "Something went wrong.", "error");
        }
    });

    // ══════════════════════════════════════════════
    // PASSWORD
    // ══════════════════════════════════════════════
    const passwordForm = document.getElementById("passwordForm");
    passwordForm?.addEventListener("submit", async (e) => {
        e.preventDefault();
        const current_password = document.getElementById("currentPasswordInput").value;
        const password = document.getElementById("newPasswordInput").value;
        const password_confirmation = document.getElementById("newPasswordConfirmInput").value;

        if (!current_password || !password) { showToast("All password fields are required.", "error"); return; }
        if (password !== password_confirmation) { showToast("New passwords do not match.", "error"); return; }
        if (password.length < 8) { showToast("New password must be at least 8 characters.", "error"); return; }

        const data = await apiCall(`${BASE}/password`, "PUT", { current_password, password, password_confirmation });
        if (data.success) {
            showToast(data.message, "success");
            passwordForm.reset();
        } else {
            showToast(data.message || "Something went wrong.", "error");
        }
    });

    // ══════════════════════════════════════════════
    // PHOTO
    // ══════════════════════════════════════════════
    const photoInput = document.getElementById("photoInput");
    const avatarPreview = document.getElementById("profileAvatarPreview");

    photoInput?.addEventListener("change", async () => {
        const file = photoInput.files[0];
        if (!file) return;

        if (file.size > 2 * 1024 * 1024) {
            showToast("Photo must be under 2MB.", "error");
            return;
        }

        const formData = new FormData();
        formData.append("photo", file);

        const data = await apiCall(`${BASE}/photo`, "POST", formData, true);
        if (data.success) {
            showToast(data.message, "success");
            avatarPreview.style.backgroundImage = `url(${data.photo_url})`;
            avatarPreview.innerHTML = "";
            setTimeout(() => window.location.reload(), 600); // refresh to show "Remove Photo" button
        } else {
            showToast(data.message || "Upload failed.", "error");
        }
    });

    const removePhotoBtn = document.getElementById("removePhotoBtn");
    removePhotoBtn?.addEventListener("click", async () => {
        if (!confirm("Remove your profile photo?")) return;
        const data = await apiCall(`${BASE}/photo`, "DELETE");
        if (data.success) {
            showToast(data.message, "success");
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(data.message || "Failed to remove photo.", "error");
        }
    });

});