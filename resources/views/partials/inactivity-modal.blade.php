{{-- resources/views/partials/inactivity-modal.blade.php --}}
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
        background: rgba(0, 0, 0, 0.6);
        z-index: 9998;
    }

    .inactivity-box {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 30px 40px;
        border-radius: 12px;
        text-align: center;
        z-index: 9999;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
        animation: fadeIn 0.3s ease;
    }

    .inactivity-box h3 {
        margin-bottom: 10px;
        font-size: 1.3rem;
        color: #1a1a1a;
    }

    .inactivity-box p {
        margin-bottom: 20px;
        color: #555;
        font-size: 0.95rem;
    }

    .inactivity-box #inactivityCountdown {
        font-weight: bold;
        color: #e74c3c;
        font-size: 1.1rem;
    }

    .btn-stay {
        background: #FFB800;
        color: #0d2a7a;
        border: none;
        padding: 10px 30px;
        border-radius: 8px;
        font-weight: bold;
        font-size: 1rem;
        cursor: pointer;
        transition: background 0.2s;
    }

    .btn-stay:hover {
        background: #e6a600;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translate(-50%, -50%) scale(0.95); }
        to   { opacity: 1; transform: translate(-50%, -50%) scale(1); }
    }
</style>

<script>
(function () {
    const WARNING_BEFORE = 60;
    const INACTIVITY_LIMIT = {{ $session_remaining ?? 60 }};
    const WARNING_TIME = INACTIVITY_LIMIT - WARNING_BEFORE;

    let inactivityTimer;
    let countdownTimer;
    let countdownSeconds = WARNING_BEFORE;

    const modal = document.getElementById('inactivityModal');
    const countdownEl = document.getElementById('inactivityCountdown');
    const stayBtn = document.getElementById('stayLoggedIn');

    // Debounce: only reset timer if user pauses for 1 second
    let debounceTimer;
    function handleActivity() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(resetTimer, 1000);
    }

    function resetTimer() {
        clearTimeout(inactivityTimer);
        clearInterval(countdownTimer);
        modal.style.display = 'none';
        countdownSeconds = WARNING_BEFORE;
        inactivityTimer = setTimeout(showWarning, WARNING_TIME * 1000);
    }

    function showWarning() {
        modal.style.display = 'block';
        countdownEl.textContent = countdownSeconds;

        countdownTimer = setInterval(function () {
            countdownSeconds--;
            countdownEl.textContent = countdownSeconds;

            if (countdownSeconds <= 0) {
                clearInterval(countdownTimer);
                window.location.href = "/logout";
            }
        }, 1000);
    }

    stayBtn.addEventListener('click', function () {
        resetTimer();

        // Ping server to reset last_activity_time
        fetch(window.location.href, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
    });

    // Track activity with debounce (fixes constant reset issue)
    const events = ['mousedown', 'keydown', 'scroll', 'touchstart', 'click'];
    events.forEach(function (event) {
        document.addEventListener(event, handleActivity, { passive: true });
    });

    // Start timer on page load
    resetTimer();
})();
</script>
