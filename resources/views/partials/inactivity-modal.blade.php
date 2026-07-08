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
            inset: 0; background: rgba(0,0,0,0.6);
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
        const TIMEOUT_SECONDS = 15 * 60; // 15 minutes
        const WARNING_SECONDS = 60;
        const WARNING_AT = TIMEOUT_SECONDS - WARNING_SECONDS; // show warning at 14 min

        const modal = document.getElementById('inactivityModal');
        const countdownEl = document.getElementById('inactivityCountdown');
        const stayBtn = document.getElementById('stayLoggedIn');

        let idleSeconds = 0;
        let countdownInterval;
        let tickInterval;

        // Tick every second — simpler and more reliable than setTimeout chains
        function startIdleCounter() {
            tickInterval = setInterval(function () {
                idleSeconds++;

                if (idleSeconds === WARNING_AT) {
                    showModal();
                }

                if (idleSeconds >= TIMEOUT_SECONDS) {
                    clearInterval(tickInterval);
                    clearInterval(countdownInterval);
                    window.location.href = '{{ route("login") }}';
                }
            }, 1000);
        }

        function showModal() {
            let remaining = WARNING_SECONDS;
            countdownEl.textContent = remaining;
            modal.style.display = 'block';

            countdownInterval = setInterval(function () {
                remaining--;
                countdownEl.textContent = remaining;
                if (remaining <= 0) clearInterval(countdownInterval);
            }, 1000);
        }

        function resetIdle() {
            idleSeconds = 0;
            modal.style.display = 'none';
            clearInterval(countdownInterval);
        }

        // Only track meaningful interaction events (not scroll/mousemove)
        ['mousedown', 'keydown', 'touchstart', 'click'].forEach(function (evt) {
            document.addEventListener(evt, resetIdle, { passive: true });
        });

        stayBtn.addEventListener('click', function () {
            // Ping server to refresh session
            fetch('{{ route("keep-alive") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            }).then(function (res) {
                if (res.ok) {
                    resetIdle();
                } else {
                    window.location.href = '{{ route("login") }}';
                }
            }).catch(function () {
                resetIdle(); // network error — assume fine
            });
        });

        startIdleCounter();
    })();
    </script>
@endif