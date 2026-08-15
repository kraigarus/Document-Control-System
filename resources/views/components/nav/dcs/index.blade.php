<nav class="side-nav" id="sideNav">
    {{-- Collapse Button --}}
    <div class="collapse-btn" id="collapseBtn" role="button" tabindex="0" aria-expanded="true">
        <div class="collapse-btn-inner">
            <div class="toggle-icon-wrap">
                <svg class="toggle-icon default-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <path d="M3 18H21V16H3V18ZM3 13H21V11H3V13ZM3 6V8H21V6H3Z" fill="currentColor"/>
                </svg>
                <svg class="toggle-icon active-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <path d="M3 18H21V16H3V18ZM3 13H21V11H3V13ZM3 6V8H21V6H3Z" fill="currentColor"/>
                </svg>
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
        {{-- Dashboard --}}
        <li data-page="dashboard" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <a href="{{ route('dashboard') }}">
                <i>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M4 13H10V4H4V13ZM4 20H10V16H4V20ZM12 20H18V11H12V20ZM12 4V9H18V4H12Z" fill="currentColor"/>
                    </svg>
                </i>
                <span>Dashboard</span>
                <span class="tooltip">Dashboard</span>
            </a>
        </li>

        {{-- Document Registration --}}
        <li class="nav-item dropdown {{ request()->is('register*') ? 'active open' : '' }}">
            <span class="dropdown-trigger">
                <i>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3ZM19 19H5V5H19V19ZM17 14H12V17H17V14ZM10 17H7V7H10V17ZM17 11H12V7H17V11Z" fill="currentColor"/>
                    </svg>
                </i>
                <span>Document Registration</span>
                <i class="fas fa-caret-down arrow">
                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none">
                        <path d="M7 10L12 15L17 10H7Z" fill="currentColor"/>
                    </svg>
                </i>
                <span class="tooltip">Document Registration</span>
            </span>
            <ul class="sub-dropdown">
                <li>
                    <a href="{{ route('register') }}" class="{{ request()->routeIs('register') && !request()->routeIs('register.update') ? 'active-sub' : '' }}">
                        <span class="sub-dot"></span>Register
                    </a>
                </li>
                <li>
                    <a href="{{ route('register.update') }}" class="{{ request()->routeIs('register.update') ? 'active-sub' : '' }}">
                        <span class="sub-dot"></span>Update
                    </a>
                </li>
            </ul>
        </li>

        {{-- Generate Report --}}
        <li class="nav-item dropdown {{ request()->is('reports*') ? 'active open' : '' }}">
            <span class="dropdown-trigger">
                <i>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3ZM9 17H7V10H9V17ZM13 17H11V7H13V17ZM17 17H15V13H17V17Z" fill="currentColor"/>
                    </svg>
                </i>
                <span>Generate Report</span>
                <i class="fas fa-caret-down arrow">
                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none">
                        <path d="M7 10L12 15L17 10H7Z" fill="currentColor"/>
                    </svg>
                </i>
                <span class="tooltip">Generate Report</span>
            </span>
            <ul class="sub-dropdown">
                <li>
                    <a href="{{ route('reports.masterlist') }}" class="{{ request()->routeIs('reports.masterlist') ? 'active-sub' : '' }}">
                        <span class="sub-dot"></span>Masterlists
                    </a>
                </li>
                <li>
                    <a href="{{ route('reports.monitoring') }}" class="{{ request()->routeIs('reports.monitoring') ? 'active-sub' : '' }}">
                        <span class="sub-dot"></span>Monitoring Reports
                    </a>
                </li>
                <li>
                    <a href="{{ route('reports.opcr') }}" class="{{ request()->routeIs('reports.opcr') ? 'active-sub' : '' }}">
                        <span class="sub-dot"></span>OPCR Targets
                    </a>
                </li>
                <li>
                    <a href="{{ route('reports.others') }}" class="{{ request()->routeIs('reports.others') ? 'active-sub' : '' }}">
                        <span class="sub-dot"></span>Others
                    </a>
                </li>
            </ul>
        </li>

        {{-- Stamp Document --}}
        <li class="nav-item {{ request()->routeIs('stamping') ? 'active' : '' }}">
            <a href="{{ route('stamping') }}">
                <i>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M16 12H18V16H22V18H2V16H6V12H8C8 9.8 9.8 8 12 8C14.2 8 16 9.8 16 12ZM14 12C14 10.9 13.1 10 12 10C10.9 10 10 10.9 10 12H14ZM4 20H20V22H4V20Z" fill="currentColor"/>
                    </svg>
                </i>
                <span>Stamp Document</span>
                <span class="tooltip">Stamp Document</span>
            </a>
        </li>

        {{-- Database --}}
        <li class="nav-item {{ request()->routeIs('database') ? 'active' : '' }}">
            <a href="{{ route('database') }}">
                <i>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2C6.48 2 2 4.02 2 6.5V17.5C2 19.98 6.48 22 12 22C17.52 22 22 19.98 22 17.5V6.5C22 4.02 17.52 2 12 2ZM12 10C8.77 10 4 8.76 4 6.5C4 4.24 8.77 3 12 3C15.23 3 20 4.24 20 6.5C20 8.76 15.23 10 12 10ZM20 17.5C20 19.76 15.23 21 12 21C8.77 21 4 19.76 4 17.5V14.15C5.71 15.1 8.72 15.75 12 15.75C15.28 15.75 18.29 15.1 20 14.15V17.5ZM12 14.5C8.77 14.5 4 13.26 4 11V9.65C5.71 10.6 8.72 11.25 12 11.25C15.28 11.25 18.29 10.6 20 9.65V11C20 13.26 15.23 14.5 12 14.5Z" fill="currentColor"/>
                    </svg>
                </i>
                <span>Database</span>
                <span class="tooltip">Database</span>
            </a>
        </li>

        {{-- Settings --}}
        <li data-page="settings" class="nav-item {{ request()->routeIs('settings') ? 'active' : '' }}">
            <a href="{{ route('settings') }}">
                <i>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M19.14 12.94C19.18 12.64 19.2 12.33 19.2 12C19.2 11.68 19.18 11.36 19.13 11.06L21.16 9.48C21.34 9.34 21.39 9.07 21.28 8.87L19.36 5.55C19.24 5.33 18.99 5.26 18.77 5.33L16.38 6.29C15.88 5.91 15.35 5.59 14.76 5.35L14.4 2.81C14.36 2.57 14.16 2.4 13.92 2.4H10.08C9.84 2.4 9.65 2.57 9.61 2.81L9.25 5.35C8.66 5.59 8.12 5.92 7.63 6.29L5.24 5.33C5.02 5.25 4.77 5.33 4.65 5.55L2.74 8.87C2.62 9.08 2.66 9.34 2.86 9.48L4.89 11.06C4.84 11.36 4.8 11.69 4.8 12C4.8 12.31 4.82 12.64 4.87 12.94L2.85 14.52C2.67 14.66 2.61 14.93 2.73 15.13L4.65 18.45C4.77 18.67 5.02 18.74 5.24 18.67L7.63 17.71C8.13 18.09 8.66 18.41 9.25 18.65L9.61 21.19C9.65 21.43 9.84 21.6 10.08 21.6H13.92C14.16 21.6 14.36 21.43 14.39 21.19L14.75 18.65C15.34 18.41 15.88 18.09 16.37 17.71L18.76 18.67C18.98 18.75 19.23 18.67 19.35 18.45L21.27 15.13C21.39 14.91 21.34 14.66 21.15 14.52L19.14 12.94ZM12 15.6C10.02 15.6 8.4 13.98 8.4 12C8.4 10.02 10.02 8.4 12 8.4C13.98 8.4 15.6 10.02 15.6 12C15.6 13.98 13.98 15.6 12 15.6Z" fill="currentColor"/>
                    </svg>
                </i>
                <span>Settings</span>
                <span class="tooltip">Settings</span>
            </a>
        </li>
    </ul>
</nav>