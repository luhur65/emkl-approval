/* =========================================================
   CONFIGURATION
========================================================= */
const SESSION_LIFETIME_MS = sessionLifetime * 60 * 1000;

const WARNING_BEFORE_EXPIRE_MS = 60 * 1000;//15 detik //  //60 * 1000; // warning 60 detik
const PING_INTERVAL_MS = 2 * 60 * 1000; //5 detik //     //2 * 60 * 1000;     // ping tiap 2 menit
const IDLE_LIMIT_MS = 3 * 60 * 1000; //10 detik //   //3 * 60 * 1000;        // idle 3 menit → stop ping

/* =========================================================
   STATE
========================================================= */
let lastActivityTime = Date.now();
let pingTimer = null;
let warningTimer = null;
let warningShown = false;

/* =========================================================
   USER ACTIVITY DETECTION
========================================================= */
function recordActivity() {

    if (!warningShown) {
        lastActivityTime = Date.now();
    }

    // if (warningShown) {
    //     hideWarning();
    // }
}

['mousemove', 'keydown', 'click', 'scroll', 'touchstart']
    .forEach(event => window.addEventListener(event, recordActivity));

/* =========================================================
   KEEPALIVE PING (ONLY WHEN USER ACTIVE)
========================================================= */
function startPing() {
    stopPing();

    pingTimer = setInterval(() => {
        const idleTime = Date.now() - lastActivityTime;

        if (idleTime < IDLE_LIMIT_MS) {
            fetch( `${appUrl}/keepalive`, {
                credentials: 'include'
            });
        }
    }, PING_INTERVAL_MS);
}

function stopPing() {
    if (pingTimer) clearInterval(pingTimer);
}

startPing();

/* =========================================================
   SESSION EXPIRE MONITOR
========================================================= */
setInterval(() => {
    const now = Date.now();
    const idleDuration = now - lastActivityTime; // durasi idle
    const timeLeft = SESSION_LIFETIME_MS - idleDuration; // waktu tersisa sebelum session habis

    if (timeLeft <= WARNING_BEFORE_EXPIRE_MS && timeLeft > 0 && !warningShown) { // tampilkan peringatan
        showWarning(Math.ceil(timeLeft / 1000));
    }

    if (timeLeft <= 0) {
        forceLogout();
    }
}, 1000);

// /* =========================================================
//    LOGOUT
// ========================================================= */
function forceLogout() {
    window.location.href = `${appUrl}/logout`;
}

/* =========================================================
   WARNING POPUP UI
========================================================= */
function showWarning(secondsLeft) {
    warningShown = true;

    // Set countdown awal
    document.getElementById('session-expire-seconds').innerText = secondsLeft;

    // Tampilkan modal (static backdrop supaya tidak bisa klik luar)
    $('#sessionExpireModal').modal({
        backdrop: 'static',
        keyboard: false
    });

    // Countdown tiap detik
    countdownTimer = setInterval(() => {
        secondsLeft--;
        document.getElementById('session-expire-seconds').innerText = secondsLeft;

        if (secondsLeft <= 0) {
            forceLogout();
        }
    }, 1000);
}

document.getElementById('stay-login').addEventListener('click', () => {
    console.log($('#stay-login').data('url'));
    let urlAlive = $('#stay-login').data('url');
    fetch(urlAlive+'/keepalive', { credentials: 'include' });
    recordActivity(); // reset lastActivityTime
    hideWarning();
});

document.getElementById('logout-now').addEventListener('click', () => {
    forceLogout();
});

function hideWarning() {
    warningShown = false;
    clearInterval(countdownTimer);
    $('#sessionExpireModal').modal('hide');
}
