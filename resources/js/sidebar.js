/* sidebar.js */
function initSideNav() {
    const sideNav = document.getElementById("sideNav");
    if (!sideNav || sideNav.dataset.ready === "1") return;
    sideNav.dataset.ready = "1";
    const collapseBtn = document.getElementById("collapseBtn");
    const dropdownItems = document.querySelectorAll(".nav-item.dropdown");
    const allNavItems = document.querySelectorAll(".nav-item");
    const pagePath = window.location.pathname.replace(/\/+$/, "") || "/";

    const hrefToPath = (href) => {
        if (!href || href === "#") return "";
        try {
            return new URL(href, window.location.origin).pathname.replace(/\/+$/, "") || "/";
        } catch {
            return href.replace(/\/+$/, "") || "/";
        }
    };

    // ── State ──
    const BREAKPOINT_TABLET = 900;
    const isMobile = () => window.innerWidth <= BREAKPOINT_TABLET;

    // ── Create mobile elements ──
    let backdrop = document.querySelector(".nav-backdrop");
    if (!backdrop) {
        backdrop = document.createElement("div");
        backdrop.className = "nav-backdrop";
        document.body.appendChild(backdrop);
    }

    let mobileToggle = document.querySelector(".mobile-nav-toggle");

    const linkMatchesPage = (href) => {
        const normalizedHref = hrefToPath(href);
        if (!normalizedHref || normalizedHref === "#") return false;
        return normalizedHref === pagePath;
    };

    // ── Active State Detection ──
    allNavItems.forEach((item) => {
        item.classList.remove("active", "soft-active", "open");
    });

    document.querySelectorAll(".nav-item > a").forEach((link) => {
        const href = link.getAttribute("href");
        if (linkMatchesPage(href)) {
            const item = link.closest(".nav-item");
            if (item) item.classList.add("active");
            if (link.closest(".sub-dropdown")) {
                link.classList.add("active-sub");
            }
        }
    });

    dropdownItems.forEach((item) => {
        const childLinks = item.querySelectorAll(".sub-dropdown a");
        const hasActiveChild = Array.from(childLinks).some((link) =>
            linkMatchesPage(link.getAttribute("href"))
        );
        if (hasActiveChild) {
            item.classList.add("active", "open");
            childLinks.forEach((link) => {
                link.classList.toggle("active-sub", linkMatchesPage(link.getAttribute("href")));
            });
        }
    });

    // ── Dropdown Toggle ──
    dropdownItems.forEach((item) => {
        const header = item.querySelector(":scope > span");
        if (!header) return;

        header.addEventListener("click", function (e) {
            e.stopPropagation();

            // On mobile, never use collapsed float behavior
            if (!isMobile() && sideNav.classList.contains("collapsed")) {
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

    // ── Collapsed Dropdown Float (desktop only) ──
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

        // Header with parent label
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

        // Links
        const links = subDropdown.querySelectorAll("li a");
        links.forEach((link) => {
            const clonedLink = link.cloneNode(true);
            if (linkMatchesPage(link.getAttribute("href"))) {
                clonedLink.classList.add("active-sub");
            }
            float.appendChild(clonedLink);
        });

        // Position
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

    // ══════════════════════════════════
    //  MOBILE: open / close drawer
    // ══════════════════════════════════
    function openMobileNav() {
        sideNav.classList.add("mobile-open");
        backdrop.classList.add("visible");
        document.body.style.overflow = "hidden"; // lock scroll

        // Update hamburger if present
        if (mobileToggle) {
            mobileToggle.setAttribute("aria-expanded", "true");
        }
    }

    function closeMobileNav() {
        sideNav.classList.remove("mobile-open");
        backdrop.classList.remove("visible");
        document.body.style.overflow = "";

        dropdownItems.forEach((el) => {
            el.classList.remove("open");
            removeFloatingDropdown(el);
        });

        if (mobileToggle) {
            mobileToggle.setAttribute("aria-expanded", "false");
        }
    }

    // Backdrop click closes drawer
    backdrop.addEventListener("click", closeMobileNav);

    // Mobile toggle button
    if (mobileToggle) {
        mobileToggle.addEventListener("click", () => {
            if (sideNav.classList.contains("mobile-open")) {
                closeMobileNav();
            } else {
                openMobileNav();
            }
        });
    }

    // Close on Escape
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && sideNav.classList.contains("mobile-open")) {
            closeMobileNav();
        }
    });

    // Close when a nav link is clicked on mobile (navigate away)
    sideNav.querySelectorAll(".nav-item > a, .sub-dropdown a").forEach((link) => {
        link.addEventListener("click", () => {
            if (isMobile() && sideNav.classList.contains("mobile-open")) {
                // Small delay so navigation can start
                setTimeout(closeMobileNav, 50);
            }
        });
    });

    // ══════════════════════════════════
    //  DESKTOP: collapse / expand
    // ══════════════════════════════════
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
        document.querySelectorAll(".tooltip").forEach((tooltip) => {
            tooltip.classList.remove("is-visible");
            tooltip.style.opacity = "";
            tooltip.style.visibility = "";
        });

        setTimeout(() => {
            isAnimating = false;
        }, 350);
    };

    // Restore saved state (desktop only)
    const savedState = localStorage.getItem("sidebar-collapsed");
    if (savedState === "1" && !isMobile()) {
        setCollapsedState(true);
    }

    // Toggle click (desktop collapse button)
    collapseBtn.addEventListener("click", () => {
        if (isMobile()) {
            closeMobileNav();
            return;
        }
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

    // ══════════════════════════════════
    //  RESPONSIVE: handle breakpoint
    // ══════════════════════════════════
    function handleBreakpointChange() {
        if (isMobile()) {
            // Entering mobile: hide sidebar, ensure off-canvas
            sideNav.classList.remove("mobile-open");
            backdrop.classList.remove("visible");
            document.body.style.overflow = "";
        } else {
            // Entering desktop: ensure sidebar is visible, restore collapse state
            sideNav.classList.remove("mobile-open");
            backdrop.classList.remove("visible");
            document.body.style.overflow = "";

            const isCollapsed = localStorage.getItem("sidebar-collapsed") === "1";
            setCollapsedState(isCollapsed);
        }
    }

    // Use matchMedia for efficient listener
    const mediaQuery = window.matchMedia(`(max-width: ${BREAKPOINT_TABLET}px)`);
    mediaQuery.addEventListener("change", handleBreakpointChange);

    // Run on load
    handleBreakpointChange();

    // ── Close float on scroll ──
    document.addEventListener("scroll", () => {
        dropdownItems.forEach((el) => removeFloatingDropdown(el));
    }, true);

    // ── Tooltip Positioning ──
    function hideTooltip(tooltip) {
        if (!tooltip) return;
        tooltip.classList.remove("is-visible");
        tooltip.style.opacity = "";
        tooltip.style.visibility = "";
    }

    function showCollapsedTooltip(item, tooltip) {
        if (isMobile() || !sideNav.classList.contains("collapsed")) {
            hideTooltip(tooltip);
            return;
        }

        const navRect = sideNav.getBoundingClientRect();
        const itemRect = item.getBoundingClientRect();

        tooltip.style.left = (navRect.right + 10) + "px";
        tooltip.style.top = (itemRect.top + itemRect.height / 2) + "px";
        tooltip.style.transform = "translateY(-50%)";
        tooltip.classList.add("is-visible");
    }

    function initTooltips() {
        const navItems = document.querySelectorAll(".nav-item");

        navItems.forEach((item) => {
            const tooltip = item.querySelector(":scope .tooltip");
            if (!tooltip) return;

            item.addEventListener("mouseenter", () => showCollapsedTooltip(item, tooltip));
            item.addEventListener("mouseleave", () => hideTooltip(tooltip));
        });
    }

    initTooltips();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initSideNav);
} else {
    initSideNav();
}