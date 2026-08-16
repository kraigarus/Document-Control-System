<div class="actions-container" id="actionsContainer">
    <button class="action_button" id="actionsBtn" onclick="toggleDropdown(event)">
        <span>ACTIONS</span>
        <img id="dropdown-icon" src="{{ asset('icons/dropdown-icon.svg') }}" alt="Dropdown Icon">
    </button>
    <div class="drop_down-container" id="dropdown">
        <span class="menu-label">Move To</span>
        @unless(request()->routeIs('portal'))
        <button class="subSystem" onclick="window.location.href='{{ route('portal', absolute: false) }}'">
            <img src="{{ asset('icons/portal.svg') }}" alt="Portal Icon">
            <span>Portal</span>
        </button>
        @endunless
        @if((auth()->user()?->permissions?->is_sadm || auth()->user()?->permissions?->can_access_dcs) && !request()->is('dcs*'))
        <button class="subSystem" onclick="window.location.href='{{ route('dcs', absolute: false) }}'">
            <img src="{{ asset('icons/dts.svg') }}" alt="Document Control Icon">
            <span>Document Control</span>
        </button>
        @endif
        <hr>
        <form id="logoutForm" action="{{ route('logout') }}" method="POST" style="display: none;">
            @csrf
        </form>
        <button class="subSystem" onclick="document.getElementById('logoutForm').submit();">
            <img src="{{ asset('icons/logout.svg') }}" alt="Logout Icon">
            <span>LOGOUT</span>
        </button>
    </div>
</div>

<script>
    function toggleDropdown(event) {
        event.stopPropagation();
        const dropdown = document.getElementById('dropdown');
        const icon = document.getElementById('dropdown-icon');

        if (dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
            icon.classList.remove('rotate');
            icon.classList.add('revert');
        } else {
            dropdown.classList.add('show');
            icon.classList.remove('revert');
            icon.classList.add('rotate');
        }
    }

    document.addEventListener('click', function (event) {
        const container = document.querySelector('.actions-container');
        const dropdown = document.getElementById('dropdown');
        const icon = document.getElementById('dropdown-icon');

        if (!container.contains(event.target)) {
            dropdown.classList.remove('show');
            icon.classList.remove('rotate');
            icon.classList.add('revert');
        }
    });
</script>
