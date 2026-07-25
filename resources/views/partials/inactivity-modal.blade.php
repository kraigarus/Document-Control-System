@if(auth()->check())
    <div id="inactivityModal" style="display: none;">
        <div class="inactivity-overlay"></div>
        <div class="inactivity-box">
            <h3>Are you still there?</h3>
            <p>You will be logged out in <span id="inactivityCountdown">60</span> seconds due to inactivity.</p>
            <button id="stayLoggedIn" class="btn-stay">Stay Logged In</button>
        </div>
    </div>

    <style>
        .inactivity-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: 9998;
        }
        .inactivity-box {
            position: fixed;
            top: 50%; left: 50%;
            transform: translate(-50%,-50%);
            background: white;
            padding: 30px 40px;
            border-radius: 12px;
            text-align: center;
            z-index: 9999;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            animation: fadeIn 0.3s ease;
        }
        .inactivity-box h3 {
            margin-bottom: 10px;
            font-size: 1.3rem;
            color: #1a1a1a;
        }
        .inactivity-box p { margin-bottom: 20px; color: #555; font-size: 0.95rem; }
        .inactivity-box #inactivityCountdown { font-weight: bold; color: #e74c3c; font-size: 1.1rem; }
        .btn-stay { background: #FFB800; color: #0d2a7a; border: none; padding: 10px 30px;
            border-radius: 8px; font-weight: bold; font-size: 1rem; cursor: pointer; }
        .btn-stay:hover { background: #e6a600; }
        @keyframes fadeIn { from { opacity:0; transform:translate(-50%,-50%) scale(0.95); }
            to { opacity:1; transform:translate(-50%,-50%) scale(1); } }
    </style>

    <script>
    (function () {
        const TIMEOUT_MS     = 15 * 60 * 1000; // 15 minutes in milliseconds
        const WARNING_MS     = 60  * 1000;      // 60 seconds warning
        const WARNING_AT_MS  = TIMEOUT_MS - WARNING_MS;
        const CHECK_INTERVAL = 2000;            // check every 2 seconds

        const modal        = document.getElementById('inactivityModal');
        const countdownEl  = document.getElementById('inactivityCountdown');
        const stayBtn      = document.getElementById('stayLoggedIn');

        let lastActivityAt = Date.now();
        let checking       = null;
        let warningShown   = false;

        function startChecker() {
            checking = setInterval(function () {
                var elapsed = Date.now() - lastActivityAt;

                // Show warning when approaching timeout
                if (elapsed >= WARNING_AT_MS && !warningShown) {
                    warningShown = true;
                    modal.style.display = 'block';
                }

                // Update countdown while warning is visible
                if (warningShown) {
                    var remaining = Math.max(0, Math.ceil((TIMEOUT_MS - elapsed) / 1000));
                    countdownEl.textContent = remaining;
                }

                // Time's up — force logout
                if (elapsed >= TIMEOUT_MS) {
                    clearInterval(checking);
                    window.location.href = '{{ route("login") }}';
                }
            }, CHECK_INTERVAL);
        }

        function resetActivity() {
            lastActivityAt = Date.now();
            warningShown   = false;
            modal.style.display = 'none';
        }

        // Track meaningful interactions only
        ['mousedown', 'keydown', 'touchstart', 'click'].forEach(function (evt) {
            document.addEventListener(evt, resetActivity, { passive: true });
        });

        // Also reset when user comes BACK to the tab — but only if they
        // interact. Just switching back alone doesn't count as activity.
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                // Tab is active again — the checker will immediately see
                // the real elapsed time and decide if we need to logout.
                // Force one check right away so it's instant:
                var elapsed = Date.now() - lastActivityAt;
                if (elapsed >= TIMEOUT_MS) {
                    clearInterval(checking);
                    window.location.href = '{{ route("login") }}';
                } else if (elapsed >= WARNING_AT_MS && !warningShown) {
                    warningShown = true;
                    modal.style.display = 'block';
                    var remaining = Math.max(0, Math.ceil((TIMEOUT_MS - elapsed) / 1000));
                    countdownEl.textContent = remaining;
                }
            }
        });

        stayBtn.addEventListener('click', function () {
            fetch('{{ route("keep-alive") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            }).then(function (res) {
                if (res.ok) {
                    resetActivity();
                } else {
                    window.location.href = '{{ route("login") }}';
                }
            }).catch(function () {
                resetActivity();
            });
        });

        startChecker();
    })();
    </script>
@endif