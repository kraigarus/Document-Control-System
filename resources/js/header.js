/* header.js */
document.addEventListener("DOMContentLoaded", function () {
    const notifBtn = document.getElementById("notifBtn");
    const notifDropdown = document.getElementById("notifDropdown");
    const notifList = document.getElementById("notifList");
    const notifEmpty = document.getElementById("notifEmpty");
    const notifDot = document.getElementById("notifDot");
    const notifClear = document.getElementById("notifClear");

    const BREAKPOINT = 900;
    const isMobile = () => window.innerWidth <= BREAKPOINT;

    // ── Sample notifications (replace with real data) ──
    let notifications = [
        {
            id: 1,
            type: "info",
            icon: "fa-solid fa-file-circle-plus",
            text: "<strong>New document</strong> submitted for review",
            time: "2 minutes ago",
            unread: true,
        },
        {
            id: 2,
            type: "success",
            icon: "fa-solid fa-circle-check",
            text: "<strong>Document #2048</strong> has been approved",
            time: "1 hour ago",
            unread: true,
        },
    ];

    function renderNotifications() {
        notifList.innerHTML = "";

        if (notifications.length === 0) {
            notifList.style.display = "none";
            notifEmpty.style.display = "flex";
            notifDot.style.display = "none";
            notifBtn.classList.remove("has-notif");
            return;
        }

        notifList.style.display = "block";
        notifEmpty.style.display = "none";

        const hasUnread = notifications.some((n) => n.unread);
        if (hasUnread) {
            notifDot.style.display = "block";
            notifBtn.classList.add("has-notif");
        } else {
            notifDot.style.display = "none";
            notifBtn.classList.remove("has-notif");
        }

        notifications.forEach((notif) => {
            const item = document.createElement("div");
            item.className = "notif-item" + (notif.unread ? " unread" : "");
            item.innerHTML = `
                <div class="notif-icon ${notif.type}">
                    <i class="${notif.icon}"></i>
                </div>
                <div class="notif-body">
                    <div class="notif-text">${notif.text}</div>
                    <div class="notif-time">${notif.time}</div>
                </div>
                <button class="notif-dismiss" data-id="${notif.id}" title="Dismiss">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            `;
            notifList.appendChild(item);
        });

        // Dismiss individual
        notifList.querySelectorAll(".notif-dismiss").forEach((btn) => {
            btn.addEventListener("click", function (e) {
                e.stopPropagation();
                const id = parseInt(this.getAttribute("data-id"));
                notifications = notifications.filter((n) => n.id !== id);
                renderNotifications();
            });
        });

        // Mark as read
        notifList.querySelectorAll(".notif-item").forEach((item, index) => {
            item.addEventListener("click", function () {
                if (notifications[index]) {
                    notifications[index].unread = false;
                    renderNotifications();
                }
            });
        });
    }

    renderNotifications();

    // ── Toggle notification dropdown ──
    notifBtn.addEventListener("click", function (e) {
        e.stopPropagation();

        // Close actions dropdown (both old and new)
        closeActionsDropdown();

        notifDropdown.classList.toggle("show");
    });

    // ── Clear all ──
    notifClear.addEventListener("click", function () {
        notifications = [];
        renderNotifications();
    });

    // ── Close actions when notifications open ──
    function closeActionsDropdown() {
        const dropdown = document.getElementById("dropdown");
        const icon = document.getElementById("dropdown-icon");
        if (dropdown) {
            dropdown.classList.remove("show");
            if (icon) {
                icon.classList.remove("rotate");
                icon.classList.add("revert");
            }
        }

        const actionsContainer = document.querySelector(".actions-container");
        if (actionsContainer) {
            actionsContainer.classList.remove("open");
            const content = actionsContainer.querySelector(".dropdown-content");
            if (content) content.classList.remove("show");
        }
    }

    // Actions button closes notifs
    const actionsBtn =
        document.getElementById("actionsBtn") ||
        document.querySelector(".action_button") ||
        document.querySelector(".actions-btn");

    if (actionsBtn) {
        actionsBtn.addEventListener("click", function () {
            notifDropdown.classList.remove("show");
        });
    }

    // ── Close on outside click ──
    document.addEventListener("click", function (e) {
        if (
            !notifDropdown.contains(e.target) &&
            !notifBtn.contains(e.target)
        ) {
            notifDropdown.classList.remove("show");
        }
    });

    // ── Close on Escape ──
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            notifDropdown.classList.remove("show");
            closeActionsDropdown();
        }
    });

    // ── Close dropdowns on viewport resize ──
    let resizeTimer;
    window.addEventListener("resize", function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            notifDropdown.classList.remove("show");
            closeActionsDropdown();
        }, 150);
    });
});