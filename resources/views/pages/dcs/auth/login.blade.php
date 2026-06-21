<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Control - Login</title>
    @vite(['resources/css/dcs/login.css'])
    <link rel="icon" href="/images/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background: url('/images/background.png') center/cover no-repeat;
        }
    </style>
</head>
<body>

    <div class="main-container">
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

            {{-- Rate limit countdown message --}}
            @if ($errors->has('email') && str_contains($errors->first('email'), 'Too many attempts'))
                @php
                    preg_match('/(\d+)\s*seconds/', $errors->first('email'), $matches);
                    $seconds = $matches[1] ?? 60;
                @endphp
                <div class="rate-limit-box">
                    <p>Too many attempts. Try again in <span id="countdown">{{ $seconds }}</span> seconds.</p>
                </div>
            @endif

            <form id="loginForm" action="{{ route('login') }}" method="POST">
                @csrf

                <div class="input-group">
                    <img src="{{ asset('icons/Person.svg') }}" alt="Email Icon" class="email-icon">
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Email"
                        value="{{ old('email') }}"
                        {{ $errors->has('email') && str_contains($errors->first('email'), 'Too many attempts') ? 'disabled' : '' }}
                        required
                        autofocus
                    >
                </div>
                @error('email')
                    @if (!str_contains($message, 'Too many attempts'))
                        <p class="error-message">{{ $message }}</p>
                    @endif
                @enderror

                <div class="input-group password-group">
                    <img src="{{ asset('icons/mdi_password.svg') }}" alt="Password Icon" class="password-icon">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Password"
                        {{ $errors->has('email') && str_contains($errors->first('email'), 'Too many attempts') ? 'disabled' : '' }}
                        required
                    >
                    <i class="fa-solid fa-eye" id="togglePassword"></i>
                </div>
                @error('password')
                    <p class="error-message">{{ $message }}</p>
                @enderror

                <button
                    type="submit"
                    class="login-btn"
                    id="loginBtn"
                    {{ $errors->has('email') && str_contains($errors->first('email'), 'Too many attempts') ? 'disabled' : '' }}
                >
                    Log In
                </button>
            </form>

            <footer class="card-footer">
                RECORDS AND FREEDOM OF INFORMATION OFFICE
            </footer>
        </div>
    </div>

    <script>
        // Password visibility toggle
        document.getElementById('togglePassword').addEventListener('click', function () {
            const passwordInput = document.getElementById('password');
            const eyeIcon = this;

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        });

        // Live countdown timer
        (function () {
            const countdownEl = document.getElementById('countdown');
            if (!countdownEl) return;

            const rateLimitBox = document.querySelector('.rate-limit-box');
            const loginBtn = document.getElementById('loginBtn');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');

            let seconds = parseInt(countdownEl.textContent);

            const timer = setInterval(function () {
                seconds--;
                countdownEl.textContent = seconds;

                if (seconds <= 0) {
                    clearInterval(timer);

                    // Hide the rate limit message
                    if (rateLimitBox) {
                        rateLimitBox.style.display = 'none';
                    }

                    // Re-enable form fields and button
                    loginBtn.disabled = false;
                    loginBtn.classList.remove('btn-disabled');
                    emailInput.disabled = false;
                    passwordInput.disabled = false;
                }
            }, 1000);
        })();
    </script>

</body>
</html>
