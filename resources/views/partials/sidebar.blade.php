<!-- sidebar.blade.php -->
@vite(['resources/css/sidebar.css', 'resources/js/sidebar.js'])

<nav class="side-nav" id="sideNav">
    <div class="collapse-btn" id="collapseBtn" role="button" tabindex="0" aria-expanded="true">
        <div class="collapse-btn-inner">
            <div class="toggle-icon-wrap">
                <img src="{{ asset('icons/toggle-nav-default.svg') }}" alt="" class="toggle-icon default-icon">
                <img src="{{ asset('icons/toggle-nav-section.svg') }}" alt="" class="toggle-icon active-icon">
            </div>
            <span class="collapse-label">Document Control</span>
        </div>
        <div class="collapse-indicator">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <path class="chevron-path" d="M10 4L6 8L10 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
    </div>

    <div class="nav-divider"></div>

    <ul class="nav-links">
        <li data-page="dashboard" class="nav-item">
            <a href="/dashboard">
                <i class="fa-regular fa-square"></i>
                <span>Dashboard</span>
                <span class="tooltip">Dashboard</span>
            </a>
        </li>
        <li class="nav-item dropdown">
            <span class="dropdown-trigger">
                <i class="fa-regular fa-pen-to-square"></i>
                <span>Document Registration</span>
                <i class="fas fa-caret-down arrow"></i>
                <span class="tooltip">Document Registration</span>
            </span>
            <ul class="sub-dropdown">
                <li><a href="/register"><span class="sub-dot"></span>Register</a></li>
                <li><a href="/register/update"><span class="sub-dot"></span>Update</a></li>
            </ul>
        </li>
        <li class="nav-item">
            <a href="/report">
                <i class="fa-regular fa-file-lines"></i>
                <span>Generate Report</span>
                <span class="tooltip">Generate Report</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="/stamp-document">
                <i class="fa-solid fa-stamp"></i>
                <span>Stamp Document</span>
                <span class="tooltip">Stamp Document</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="/dashboard/inventory">
                <i class="fa-solid fa-database"></i>
                <span>Database</span>
                <span class="tooltip">Database</span>
            </a>
        </li>
        <li data-page="settings" class="nav-item">
            <a href="/settings">
                <i class="fa-solid fa-gear"></i>
                <span>Settings</span>
                <span class="tooltip">Settings</span>
            </a>
        </li>
    </ul>

    <div class="nav-footer">
        <div class="nav-divider"></div>
        <div class="nav-item footer-item">
            <a href="/profile">
                <i class="fa-regular fa-circle-user"></i>
                <span>My Profile</span>
                <span class="tooltip">My Profile</span>
            </a>
        </div>
    </div>
</nav>
