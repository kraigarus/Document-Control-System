<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.portal')] #[Title('CSPC Portal')] class extends Component
{
    public string $userNameDisplay = '';

    public ?int $currentRoleId = null;

    public array $currentPermissions = [];

    public function mount(): mixed
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        if ($user && $user->details) {
            $this->userNameDisplay = trim($user->details->first_name . ' ' . $user->details->last_name)
                ?: $user->username;
        } else {
            $this->userNameDisplay = $user?->username ?? '';
        }

        $this->checkRoleUpdate();

        return null;
    }

    public function checkRoleUpdate(): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }
        $user = $user->fresh();

        $perms = $user->permissions;
        $permValues = $perms ? [
            $perms->is_sadm,
            $perms->can_access_dts,
            $perms->can_access_rdp,
            $perms->can_access_dcs,
        ] : [];

        if ($this->currentRoleId === null) {
            $this->currentRoleId = $user->account_role;
            $this->currentPermissions = $permValues;

            return;
        }

        if ($user->account_role !== $this->currentRoleId || $permValues !== $this->currentPermissions) {
            $this->js('window.location.reload();');
        }
    }

    public function logout(): void
    {
        $user = Auth::user();
        if ($user && $user->details) {
            $user->details->update([
                'is_currently_online' => false,
                'last_online_time' => now(),
            ]);
        }

        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        $this->redirect(route('login'));
    }

    public function with(): array
    {
        $activeSubsystems = DB::table('subsystems')
            ->where('is_active', true)
            ->pluck('subsystem_name')
            ->all();

        $perms = Auth::user()?->permissions;
        $dcsActive = in_array('Document Control System', $activeSubsystems, true);

        $mainItems = [];
        if (($perms?->is_sadm || $perms?->can_access_dcs) && $dcsActive) {
            $mainItems[] = [
                'id' => 'dcs',
                'route' => route('dcs'),
                'label' => 'Document Control System',
                'target' => '_self',
                'is_desktop_only' => false,
            ];
        }

        $desktopCount = count($mainItems);
        if ($desktopCount <= 3) {
            $containerClass = 'cols-1';
        } elseif ($desktopCount <= 6) {
            $containerClass = 'cols-2';
        } else {
            $containerClass = 'cols-3';
        }

        return [
            'desktopItems' => $mainItems,
            'containerClass' => $containerClass,
        ];
    }
}; ?>

@push('styles')
    @vite(['resources/css/accesspoint.css'])
    <style data-navigate-track>
        body {
            background-image: url('{{ asset('images/background.png') }}');
            background-repeat: no-repeat !important;
            background-size: cover !important;
            background-attachment: fixed !important;
            background-position: center !important;
            min-height: 100vh !important;
        }
        @media (max-width: 768px) {
            .desktop-only { display: none !important; }
        }
        @media (min-width: 769px) {
            .mobile-only { display: none !important; }
        }
    </style>
@endpush

<div class="livewire-root" wire:poll.5s="checkRoleUpdate">
    <header>
        <span class="office-name">{{ auth()->user()?->details?->office?->office_name ?? 'Records and Freedom of Information Office' }}</span>
    </header>
    <section>
        <div class="logout-con">
            <button type="button" title="Logout" class="logout" wire:click="logout">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" /><line x1="21" y1="12" x2="9" y2="12" />
                </svg>
            </button>
        </div>
        <div class="portal_header">
            <div class="ico_con">
                <img class="ico" src="{{ asset('images/logo.png') }}" alt="CSPC">
            </div>
            <span>Welcome, {{ $userNameDisplay }}</span>
        </div>
        <div class="systems-container {{ $containerClass }}">
            @forelse($desktopItems as $item)
                <a href="{{ $item['route'] }}"
                   @if(($item['target'] ?? '_self') === '_blank') target="_blank" rel="noopener noreferrer" @endif
                   class="system-con @if($item['is_desktop_only']) desktop-only @endif"
                   id="{{ $item['id'] }}">
                    <div class="display-box">
                        <span>{{ $item['label'] }}</span>
                    </div>
                </a>
            @empty
                <div class="system-con">
                    <div class="display-box">
                        <span>No subsystems available for your role.</span>
                    </div>
                </div>
            @endforelse
        </div>
    </section>
    <footer>
        <div class="copy-right">Copyright 2026. All Rights Reserved.</div>
    </footer>
</div>
