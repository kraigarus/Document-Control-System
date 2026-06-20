// register.js
document.addEventListener("DOMContentLoaded", function () {

    // ── Add revision table row ──
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
            <td>
                <button type="button" class="reg-row-del" onclick="this.closest('tr').remove()">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    };

    // ── Show/hide sections based on version type ──
    window.handleVersionChange = function () {
        const versionType = document.getElementById("versionType").value;
        const section1 = document.getElementById("section-1");
        const section2 = document.getElementById("section-2");
        const section3 = document.getElementById("section-3");
        const section4 = document.getElementById("section-4");
        const section5 = document.getElementById("section-5");
        const approval = document.getElementById("section-approval");

        // Hide all first
        [section1, section2, section3, section4, section5, approval].forEach(s => {
            if (s) s.style.display = "none";
        });

        // Enable doc type dropdown
        document.getElementById("docType").disabled = false;

        // Show relevant sections based on version
        if (versionType === "new") {
            if (section1) section1.style.display = "block";
            if (section3) section3.style.display = "block";
        } else if (versionType === "revised") {
            if (section2) section2.style.display = "block";
            if (section3) section3.style.display = "block";
        }
    };

    window.handleDocTypeChange = function () {
        document.getElementById("subType").disabled = false;
    };

    window.validateChecklistState = function () {
        // Enable checkboxes and approval radios
        document.querySelectorAll("#dynamicCheckboxes input").forEach(cb => cb.disabled = false);
        document.querySelectorAll('input[name="approval_status"]').forEach(r => r.disabled = false);
    };

    window.handleApprovalToggle = function (applicable) {
        const approval = document.getElementById("section-approval");
        if (approval) {
            approval.style.display = applicable ? "block" : "none";
        }
    };

    // ── Search for offices ──
    window.handleSearch = function (input, resultsId, bodyId, totalId) {
        const query = input.value.trim().toLowerCase();
        const dropdown = document.getElementById(resultsId);
        if (!dropdown) return;

        if (query.length < 1) {
            dropdown.style.display = "none";
            return;
        }

        // Sample offices — replace with API call
        const offices = [
            "Office of the President",
            "Office of the VP Academic Affairs",
            "Office of the VP Administration",
            "Registrar's Office",
            "Human Resource Management Office",
            "Planning and Development Office",
            "Finance Office",
            "Research and Development Office",
            "Extension Services Office",
            "Library Services",
            "Guidance and Counseling Office",
            "Health Services Office",
            "Student Affairs Office",
            "Supply Office",
            "ICT Office"
        ];

        const filtered = offices.filter(o => o.toLowerCase().includes(query));

        if (filtered.length === 0) {
            dropdown.style.display = "none";
            return;
        }

        dropdown.innerHTML = filtered
            .map(o => `<div onclick="addOffice('${o}', '${bodyId}', '${totalId}', '${resultsId}')">${o}</div>`)
            .join("");
        dropdown.style.display = "block";
    };

    window.addOffice = function (office, bodyId, totalId, resultsId) {
        const tbody = document.getElementById(bodyId);
        const dropdown = document.getElementById(resultsId);
        if (!tbody) return;

        // Check duplicate
        const existing = tbody.querySelectorAll("td:first-child");
        for (const td of existing) {
            if (td.textContent === office) {
                dropdown.style.display = "none";
                return;
            }
        }

        const tr = document.createElement("tr");
        tr.innerHTML = `
            <td>${office}</td>
            <td><input type="number" name="copies_${bodyId}[]" value="1" min="1" style="width:60px; height:32px; border:1px solid #e2e8f0; border-radius:4px; padding:0 8px; font-size:0.82rem;"></td>
            <td><button type="button" class="btn-remove" onclick="removeOffice(this, '${totalId}')"><i class="fa-solid fa-xmark"></i></button></td>
        `;
        tbody.appendChild(tr);

        updateTotal(totalId);
        dropdown.style.display = "none";
        document.querySelector(`#${resultsId}`).previousElementSibling.value = "";
    };

    window.removeOffice = function (btn, totalId) {
        btn.closest("tr").remove();
        updateTotal(totalId);
    };

    function updateTotal(totalId) {
        const totalEl = document.getElementById(totalId);
        if (!totalEl) return;
        const row = totalEl.closest("tbody").querySelectorAll("input[type='number']");
        let sum = 0;
        row.forEach(input => sum += parseInt(input.value) || 0);
        totalEl.textContent = sum;
    }

    // ── Close dropdowns on outside click ──
    document.addEventListener("click", function (e) {
        document.querySelectorAll(".reg-search-dropdown").forEach(dd => {
            if (!dd.parentElement.contains(e.target)) {
                dd.style.display = "none";
            }
        });
    });
});