/* sidebar.js */
document.addEventListener("DOMContentLoaded", function () {
    const sideNav = document.getElementById("sideNav");
    const collapseBtn = document.getElementById("collapseBtn");
    const dropdownItems = document.querySelectorAll(".nav-item.dropdown");
    const allNavItems = document.querySelectorAll(".nav-item");
    const pagePath = window.location.pathname.replace(/\/+$/, "") || "/";

    // ── Utility ──
    const normalizePath = (value) => {
        if (!value) return "";
        if (value === "/") return "/";
        return value.replace(/\/+$/, "");
    };

    const linkMatchesPage = (href) => {
        const normalizedHref = normalizePath(href);
        if (!normalizedHref || normalizedHref === "#") return false;
        return normalizedHref === pagePath;
    };

    // ── Active State Detection ──
    allNavItems.forEach((item) => {
        item.classList.remove("active", "soft-active", "open");
    });

    // Direct link matching
    document.querySelectorAll(".nav-item > a").forEach((link) => {
        const href = link.getAttribute("href");
        if (linkMatchesPage(href)) {
            const item = link.closest(".nav-item");
            if (item) item.classList.add("active");

            // Mark sub-dropdown link
            if (link.closest(".sub-dropdown")) {
                link.classList.add("active-sub");
            }
        }
    });

    // Dropdown parent matching
    dropdownItems.forEach((item) => {
        const childLinks = item.querySelectorAll(".sub-dropdown a");
        const hasActiveChild = Array.from(childLinks).some((link) =>
            linkMatchesPage(link.getAttribute("href"))
        );
        if (hasActiveChild) {
            item.classList.add("active", "open");
        }
    });

    // ── Dropdown Toggle ──
    dropdownItems.forEach((item) => {
        const header = item.querySelector(":scope > span");
        if (!header) return;

        header.addEventListener("click", function (e) {
            e.stopPropagation();

            if (sideNav.classList.contains("collapsed")) {
                handleCollapsedDropdownClick(item);
                return;
            }

            const isOpen = item.classList.contains("open");

            // Close others
            dropdownItems.forEach((el) => {
                if (el !== item) {
                    el.classList.remove("open");
                    removeFloatingDropdown(el);
                }
            });

            item.classList.toggle("open", !isOpen);
        });
    });

    // ── Collapsed Dropdown Float ──
    function handleCollapsedDropdownClick(item) {
        const existingFloat = item.querySelector(".dropdown-float");
        if (existingFloat) {
            removeFloatingDropdown(item);
            return;
        }

        dropdownItems.forEach((el) => removeFloatingDropdown(el));

        const subDropdown = item.querySelector(".sub-dropdown");
        if (!subDropdown) return;

        const float = document.createElement("div");
        float.className = "dropdown-float";

        // ── Header showing parent label ──
        const header = document.createElement("div");
        header.className = "dropdown-float-header";

        const icon = item.querySelector(":scope > span i:not(.arrow)");
        const label = item.querySelector(":scope > span > span:not(.tooltip):not(.arrow)");

        if (icon) {
            const headerIcon = icon.cloneNode(true);
            headerIcon.style.marginRight = "0";
            header.appendChild(headerIcon);
        }
        if (label) {
            const headerText = document.createElement("span");
            headerText.textContent = label.textContent;
            header.appendChild(headerText);
        }
        float.appendChild(header);

        // ── Links ──
        const links = subDropdown.querySelectorAll("li a");
        links.forEach((link) => {
            const clonedLink = link.cloneNode(true);
            if (linkMatchesPage(link.getAttribute("href"))) {
                clonedLink.classList.add("active-sub");
            }
            float.appendChild(clonedLink);
        });

        // ── Position ──
        const navRect = sideNav.getBoundingClientRect();
        const itemRect = item.getBoundingClientRect();

        float.style.left = (navRect.right + 6) + "px";
        float.style.top = itemRect.top + "px";

        item.classList.add("expanded-overlay");
        item.appendChild(float);

        // Adjust if it overflows bottom
        requestAnimationFrame(() => {
            const floatRect = float.getBoundingClientRect();
            if (floatRect.bottom > window.innerHeight) {
                float.style.top = (window.innerHeight - floatRect.height - 12) + "px";
            }
        });

        setTimeout(() => {
            document.addEventListener("click", function closeFloat(e) {
                if (!float.contains(e.target) && !item.contains(e.target)) {
                    removeFloatingDropdown(item);
                    document.removeEventListener("click", closeFloat);
                }
            });
        }, 10);
    }

    function removeFloatingDropdown(item) {
        const float = item.querySelector(".dropdown-float");
        if (float) {
            float.style.opacity = "0";
            float.style.transform = "translateX(-6px)";
            setTimeout(() => {
                float.remove();
                item.classList.remove("expanded-overlay");
            }, 180);
        }
    }

    // ── Collapse / Expand ──
    let isAnimating = false;

    const setCollapsedState = (collapsed) => {
        if (isAnimating) return;
        isAnimating = true;

        dropdownItems.forEach((el) => removeFloatingDropdown(el));

        if (collapsed) {
            dropdownItems.forEach((el) => el.classList.remove("open"));
        }

        localStorage.setItem("sidebar-collapsed", collapsed ? "1" : "0");

        sideNav.classList.toggle("collapsed", collapsed);
        collapseBtn.setAttribute("aria-expanded", String(!collapsed));

        updateMainContentMargin(collapsed);

        setTimeout(() => {
            isAnimating = false;
        }, 350);
    };

    function updateMainContentMargin(collapsed) {
        const main = document.querySelector(".dashboard-main, main, .main-content, .content-wrapper");
        if (main) {
            const value = collapsed ? "68px" : "280px";
            main.style.left = value;   // ← use `left`, not marginLeft
        }
    }

    const savedState = localStorage.getItem("sidebar-collapsed");
    if (savedState === "1" && sideNav && collapseBtn) {
        setCollapsedState(true);
    }


    // Toggle click
    collapseBtn.addEventListener("click", () => {
        const willCollapse = !sideNav.classList.contains("collapsed");
        setCollapsedState(willCollapse);
    });

    // Keyboard support
    collapseBtn.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            collapseBtn.click();
        }
    });

    // ── Responsive: auto-collapse on small screens ──
    const mediaQuery = window.matchMedia("(max-width: 768px)");

    function handleResize(e) {
        if (e.matches && !sideNav.classList.contains("collapsed")) {
            setCollapsedState(true);
        }
    }

    mediaQuery.addEventListener("change", handleResize);
    handleResize(mediaQuery);

    // ── Close float on scroll ──
    document.addEventListener("scroll", () => {
        dropdownItems.forEach((el) => removeFloatingDropdown(el));
    }, true);
});


// ── Tooltip Positioning ──
function initTooltips() {
    const navItems = document.querySelectorAll('.nav-item');

    navItems.forEach((item) => {
        const tooltip = item.querySelector('.tooltip');
        if (!tooltip) return;

        item.addEventListener('mouseenter', () => {
            if (!sideNav.classList.contains('collapsed')) return;

            const navRect = sideNav.getBoundingClientRect();
            const itemRect = item.getBoundingClientRect();

            tooltip.style.left = (navRect.right + 6) + 'px';
            tooltip.style.top = (itemRect.top + itemRect.height / 2) + 'px';
            tooltip.style.transform = 'translateY(-50%)';
        });

        item.addEventListener('mouseleave', () => {
            tooltip.style.opacity = '0';
        });
    });
}

initTooltips();
