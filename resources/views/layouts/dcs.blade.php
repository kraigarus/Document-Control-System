<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('images/logo.png') }}" type="image/png">
    <title>{{ $title ?? 'CSPC - Document Control System' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body, button, input, select, textarea, a, span, div, h1, h2, h3, h4, h5, h6, label {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite([
        'resources/css/dashboard.css',
        'resources/js/dashboard.js',
    ])
    @if(request()->routeIs('dcs', 'dcs.dashboard'))
        @vite(['resources/css/dcs/dashboard.css'])
    @elseif(request()->routeIs('dcs.settings.index'))
        @vite(['resources/css/dcs/settings.css'])
    @elseif(request()->routeIs('dcs.register.create') || request()->routeIs('dcs.register.revised'))
        @vite(['resources/css/dcs/register.css'])
    @elseif(request()->routeIs('dcs.register.update'))
        @vite(['resources/css/dcs/update.css'])
    @elseif(request()->routeIs('dcs.register.edit'))
        @vite(['resources/css/dcs/edit.css', 'resources/css/dcs/register.css'])
    @elseif(request()->routeIs('dcs.register.history'))
        @vite(['resources/css/dcs/history.css', 'resources/css/dcs/register.css'])
    @elseif(request()->routeIs('dcs.reports.*'))
        @vite(['resources/css/dcs/reports.css'])
    @elseif(request()->routeIs('dcs.stamping.index'))
        @vite(['resources/css/dcs/stamping.css'])
    @elseif(request()->routeIs('dcs.database.index'))
        @vite(['resources/css/dcs/database.css'])
    @endif
    @stack('styles')
    @livewireStyles
</head>
<body>
    <header class="top-nav">
        <div class="header-left">
            <button class="mobile-nav-toggle" id="mobileNavToggle"
                    aria-label="Open navigation" aria-expanded="false">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="logo-container">
                <img src="/images/logo.png" alt="CSPC Logo" class="logo">
            </div>
            <div class="title-text">
                <p class="sub-title">Camarines Sur Polytechnic Colleges</p>
                <h1 class="main-title">Records Management System</h1>
            </div>
        </div>

        <div class="header-right">
            <div class="top-row">
                <div class="notif-container" id="notifContainer">
                    <div class="notif-wrapper" id="notifBtn">
                        <i class="fa-regular fa-bell"></i>
                        <span class="red-dot" id="notifDot"></span>
                    </div>
                    <div class="notif-dropdown" id="notifDropdown">
                        <div class="notif-header">
                            <span class="notif-title">Notifications</span>
                            <button class="notif-clear" id="notifClear">Clear All</button>
                        </div>
                        <div class="notif-list" id="notifList"></div>
                        <div class="notif-empty" id="notifEmpty" style="display: none;">
                            <i class="fa-regular fa-bell-slash"></i>
                            <span>No new notifications</span>
                        </div>
                    </div>
                </div>

                <x-actions.dropdown />
            </div>
            <p class="office-label">{{ auth()->user()?->details?->office?->office_name ?? 'Records and Freedom of Information Office' }}</p>
        </div>
    </header>

    <x-nav.dcs />

    @auth
        <form id="inactivityLogoutForm" action="{{ route('logout') }}" method="POST" hidden>
            @csrf
        </form>
        <div id="inactivityModal" style="display: none;">
            <div class="inactivity-overlay"></div>
            <div class="inactivity-box">
                <h3>Are you still there?</h3>
                <p>You will be logged out in <span id="inactivityCountdown">60</span> seconds due to inactivity.</p>
                <button id="stayLoggedIn" class="btn-stay">Stay Logged In</button>
            </div>
        </div>
        <style>
            .inactivity-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9998; }
            .inactivity-box { position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%); background: white; padding: 30px 40px; border-radius: 12px; text-align: center; z-index: 9999; box-shadow: 0 20px 60px rgba(0,0,0,0.4); }
            .inactivity-box h3 { margin-bottom: 10px; font-size: 1.3rem; color: #1a1a1a; }
            .inactivity-box p { margin-bottom: 20px; color: #555; font-size: 0.95rem; }
            .inactivity-box #inactivityCountdown { font-weight: bold; color: #e74c3c; font-size: 1.1rem; }
            .btn-stay { background: #FFB800; color: #0d2a7a; border: none; padding: 10px 30px; border-radius: 8px; font-weight: bold; font-size: 1rem; cursor: pointer; }
            .btn-stay:hover { background: #e6a600; }
        </style>
        <script>
        (function () {
            const TIMEOUT_MS = 15 * 60 * 1000;
            const WARNING_MS = 60 * 1000;
            const WARNING_AT_MS = TIMEOUT_MS - WARNING_MS;
            const modal = document.getElementById('inactivityModal');
            const countdownEl = document.getElementById('inactivityCountdown');
            const stayBtn = document.getElementById('stayLoggedIn');
            const logoutForm = document.getElementById('inactivityLogoutForm');
            let lastActivityAt = Date.now();
            let checking = null;
            let warningShown = false;

            function forceLogout() {
                if (logoutForm) logoutForm.submit();
            }

            function startChecker() {
                checking = setInterval(function () {
                    var elapsed = Date.now() - lastActivityAt;
                    if (elapsed >= WARNING_AT_MS && !warningShown) {
                        warningShown = true;
                        modal.style.display = 'block';
                    }
                    if (warningShown) {
                        countdownEl.textContent = Math.max(0, Math.ceil((TIMEOUT_MS - elapsed) / 1000));
                    }
                    if (elapsed >= TIMEOUT_MS) {
                        clearInterval(checking);
                        forceLogout();
                    }
                }, 2000);
            }

            function resetActivity() {
                lastActivityAt = Date.now();
                warningShown = false;
                modal.style.display = 'none';
            }

            ['mousedown', 'keydown', 'touchstart', 'click'].forEach(function (evt) {
                document.addEventListener(evt, resetActivity, { passive: true });
            });

            stayBtn.addEventListener('click', function () {
                fetch('{{ url('/api/session/ping') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                }).then(function (res) {
                    if (res.ok) resetActivity();
                    else forceLogout();
                }).catch(resetActivity);
            });

            startChecker();
        })();
        </script>
        <x-session-heartbeat />
    @endauth

    {{ $slot }}

    @stack('scripts')
    @livewireScripts
</body>
</html>
