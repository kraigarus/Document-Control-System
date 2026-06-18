@vite(['resources/css/header.css', 'resources/js/header.js'])
<header class="top-nav">
    <div class="header-left">
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
                    <div class="notif-list" id="notifList">
                        <!-- Notifications will be injected here -->
                    </div>
                    <div class="notif-empty" id="notifEmpty" style="display: none;">
                        <i class="fa-regular fa-bell-slash"></i>
                        <span>No new notifications</span>
                    </div>
                </div>
            </div>

            @include('components.actions.dropdown')
        </div>
        <p class="office-label">Records and Freedom of Information Office</p>
    </div>
</header>
