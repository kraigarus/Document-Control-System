<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.portal')] #[Title('Document Control - Login')] class extends Component {
    public string $username = '';
    public string $password = '';
    public ?int $lockSeconds = null;

    public function login()
    {
        $key = 'login.' . request()->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->lockSeconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'username' => "Too many attempts. Try again in {$this->lockSeconds} seconds.",
            ]);
        }

        $this->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $failKey = 'login_fails.' . strtolower($this->username);
        $failedAttempts = Cache::get($failKey, 0);

        if ($failedAttempts >= 10) {
            throw ValidationException::withMessages([
                'username' => 'Account temporarily locked. Contact administrator.',
            ]);
        }

        $user = User::where('username', $this->username)->first();

        if (!$user || !Auth::getProvider()->validateCredentials($user, ['password' => $this->password])) {
            RateLimiter::hit($key, 60);
            Cache::put($failKey, $failedAttempts + 1, now()->addMinutes(30));

            throw ValidationException::withMessages([
                'username' => 'The provided credentials do not match our records.',
            ]);
        }

        if (!$user->account_active) {
            throw ValidationException::withMessages([
                'username' => 'This account is inactive. Contact administrator.',
            ]);
        }

        Auth::login($user);
        RateLimiter::clear($key);
        Cache::forget($failKey);
        session()->regenerate();
        session()->put('last_activity_time', now()->timestamp);

        if ($user->details) {
            $user->details->update([
                'is_currently_online' => true,
                'last_online_time' => now(),
            ]);
        }

        return redirect()->route('portal');
    }
}; ?>

@push('styles')
    @vite(['resources/css/login.css'])
@endpush

<div class="main-container" x-data="{ showPassword: false }">
    <header class="top-header">
        <h1 class="gradient-title">CSPC</h1>
        <p>Document Control System</p>
    </header>

    <div class="login-card">
        <div class="logo-container">
            <img src="/images/logo.png" alt="CSPC Logo" class="logo">
        </div>

        <h2>LOGIN</h2>
        <p class="subtitle">Please enter your details to log in your account</p>

        @if ($errors->has('username') && str_contains($errors->first('username'), 'Too many attempts'))
            <div class="rate-limit-box">
                <p>Too many attempts. Try again in <span id="countdown">{{ $lockSeconds ?? 60 }}</span> seconds.</p>
            </div>
        @endif

        <form wire:submit="login">
            <div class="input-group">
                <img src="{{ asset('icons/Person.svg') }}" alt="Username Icon" class="email-icon">
                <input
                    type="text"
                    wire:model="username"
                    placeholder="Username"
                    autocomplete="username"
                    {{ $errors->has('username') && str_contains($errors->first('username'), 'Too many attempts') ? 'disabled' : '' }}
                    required
                    autofocus
                >
            </div>
            @error('username')
                @if (!str_contains($message, 'Too many attempts'))
                    <p class="error-message">{{ $message }}</p>
                @endif
            @enderror

            <div class="input-group password-group">
                <img src="{{ asset('icons/mdi_password.svg') }}" alt="Password Icon" class="password-icon">
                <input
                    :type="showPassword ? 'text' : 'password'"
                    wire:model="password"
                    placeholder="Password"
                    autocomplete="current-password"
                    {{ $errors->has('username') && str_contains($errors->first('username'), 'Too many attempts') ? 'disabled' : '' }}
                    required
                >
                <i class="fa-solid" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" @click="showPassword = !showPassword"></i>
            </div>
            @error('password')
                <p class="error-message">{{ $message }}</p>
            @enderror

            <button
                type="submit"
                class="login-btn"
                {{ $errors->has('username') && str_contains($errors->first('username'), 'Too many attempts') ? 'disabled' : '' }}
            >
                Log In
            </button>
        </form>

        <footer class="card-footer">
            RECORDS AND FREEDOM OF INFORMATION OFFICE
        </footer>
    </div>
</div>

@if ($errors->has('username') && str_contains($errors->first('username'), 'Too many attempts'))
<script>
    (function () {
        const countdownEl = document.getElementById('countdown');
        if (!countdownEl) return;
        let seconds = parseInt(countdownEl.textContent, 10);
        const timer = setInterval(function () {
            seconds--;
            countdownEl.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(timer);
                window.location.reload();
            }
        }, 1000);
    })();
</script>
@endif
