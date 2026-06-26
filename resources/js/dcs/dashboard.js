document.addEventListener("DOMContentLoaded", function () {

    function escapeHtml(value) {
        const str = String(value ?? "");
        return str
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#39;");
    }

    function formatTime12(time24) {
        if (!time24) return "";
        const [h, m] = time24.split(":").map(Number);
        const period = h >= 12 ? "PM" : "AM";
        const hour = h % 12 || 12;
        return `${hour}:${String(m).padStart(2, "0")} ${period}`;
    }

    function getISO(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, "0");
        const d = String(date.getDate()).padStart(2, "0");
        return `${y}-${m}-${d}`;
    }

    // ── Header date ──
    const headerDate = document.getElementById("headerDate");
    if (headerDate) {
        const now = new Date();
        const options = { weekday: "long", year: "numeric", month: "long", day: "numeric" };
        headerDate.textContent = now.toLocaleDateString("en-US", options);
    }

    // ── Fetch Dashboard Stats ──
    loadDashboardStats();

    async function loadDashboardStats() {
        try {
            const res = await fetch("/api/dashboard-stats");
            if (!res.ok) throw new Error("HTTP " + res.status);
            const stats = await res.json();

            animateCount("internalCount", stats.internalCount || 0);
            animateCount("internalFormsCount", stats.internalFormsCount || 0);
            animateCount("externalCount", stats.externalCount || 0);
            animateCount("formsCount", stats.formsCount || 0);
            animateCount("logbooksCount", stats.logbooksCount || 0);

            updateTrends(stats);

        } catch (err) {
            console.error("Failed to load dashboard stats:", err);
        }
    }

    function animateCount(elementId, target) {
        const el = document.getElementById(elementId);
        if (!el) return;

        const duration = 1000;
        const startTime = performance.now();

        function step(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            // Ease out cubic
            const eased = 1 - Math.pow(1 - progress, 3);
            const current = Math.round(target * eased);
            el.textContent = current.toLocaleString();
            if (progress < 1) {
                requestAnimationFrame(step);
            }
        }

        requestAnimationFrame(step);
    }

    function updateTrends(stats) {
        const boxes = document.querySelectorAll(".stat-box");
        boxes.forEach(box => {
            const valueEl = box.querySelector(".stat-value");
            const trendEl = box.querySelector(".stat-trend");
            if (!valueEl || !trendEl) return;

            const count = parseInt(valueEl.textContent.replace(/,/g, "")) || 0;

            if (count > 0) {
                trendEl.className = "stat-trend up";
                trendEl.innerHTML = '<i class="fa-solid fa-arrow-trend-up"></i>';
            } else {
                trendEl.className = "stat-trend neutral";
                trendEl.innerHTML = '<i class="fa-solid fa-minus"></i>';
            }
        });
    }

    // ── Upcoming Events Widget ──
    const upcomingList = document.getElementById("upcomingList");
    const upcomingCount = document.getElementById("upcomingCount");

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);

    const todayISO = getISO(today);
    const tomorrowISO = getISO(tomorrow);

    const todayLabel = today.toLocaleDateString("en-US", { weekday: "long", month: "long", day: "numeric" });
    const tomorrowLabel = tomorrow.toLocaleDateString("en-US", { weekday: "long", month: "long", day: "numeric" });

    function renderUpcomingEvents() {
        const allEvents = JSON.parse(localStorage.getItem("calendarEvents")) || [];

        const todayEvents = allEvents
            .filter((ev) => ev.date === todayISO)
            .sort((a, b) => (a.startTime || "").localeCompare(b.startTime || ""));

        const tomorrowEvents = allEvents
            .filter((ev) => ev.date === tomorrowISO)
            .sort((a, b) => (a.startTime || "").localeCompare(b.startTime || ""));

        const total = todayEvents.length + tomorrowEvents.length;

        if (upcomingCount) {
            upcomingCount.textContent = total;
        }

        if (!upcomingList) return;

        if (total === 0) {
            upcomingList.innerHTML = `
                <div class="upcoming-empty">
                    <i class="fa-regular fa-calendar-check"></i>
                    <span>No events today or tomorrow</span>
                </div>
            `;
            return;
        }

        let html = "";

        if (todayEvents.length > 0) {
            html += `
                <div class="upcoming-section">
                    <div class="upcoming-section-label">
                        <span class="section-dot today-dot"></span>
                        Today
                        <span class="section-date">${todayLabel}</span>
                    </div>
            `;
            todayEvents.forEach((ev) => {
                html += `
                    <div class="upcoming-item" onclick="openDayModal('${escapeHtml(ev.date)}')">
                        <div class="upcoming-event-dot today-accent"></div>
                        <div class="upcoming-info">
                            <div class="title">${escapeHtml(ev.title)}</div>
                            <div class="time">${formatTime12(ev.startTime)} — ${formatTime12(ev.endTime)}</div>
                        </div>
                    </div>
                `;
            });
            html += `</div>`;
        }

        if (tomorrowEvents.length > 0) {
            html += `
                <div class="upcoming-section">
                    <div class="upcoming-section-label">
                        <span class="section-dot tomorrow-dot"></span>
                        Tomorrow
                        <span class="section-date">${tomorrowLabel}</span>
                    </div>
            `;
            tomorrowEvents.forEach((ev) => {
                html += `
                    <div class="upcoming-item" onclick="openDayModal('${escapeHtml(ev.date)}')">
                        <div class="upcoming-event-dot"></div>
                        <div class="upcoming-info">
                            <div class="title">${escapeHtml(ev.title)}</div>
                            <div class="time">${formatTime12(ev.startTime)} — ${formatTime12(ev.endTime)}</div>
                        </div>
                    </div>
                `;
            });
            html += `</div>`;
        }

        upcomingList.innerHTML = html;
    }

    renderUpcomingEvents();

    window.addEventListener("storage", renderUpcomingEvents);

    const observer = new MutationObserver(() => {
        const overlay = document.getElementById("overlay");
        if (overlay && overlay.style.display === "none") {
            renderUpcomingEvents();
        }
    });

    const overlay = document.getElementById("overlay");
    if (overlay) {
        observer.observe(overlay, { attributes: true, attributeFilter: ["style"] });
    }
});