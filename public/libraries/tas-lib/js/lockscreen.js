const IDLE_TIMEOUT = 15 * 60 * 1000; // 15 menit
// const IDLE_TIMEOUT = 5 * 1000; // 5 detik

// Prefix namespace -- WAJIB unik per-project. emkl-approval-sby-ci4 dan
// sys-modern bisa diakses dari origin browser yang sama (mis. keduanya lewat
// http://localhost/...), dan localStorage/BroadcastChannel di-scope per-origin
// oleh browser, BUKAN per-folder/path. Tanpa prefix ini, dua project saling
// menimpa localStorage & broadcast satu sama lain (lihat: user di-unlock pakai
// userid project lain yang kebetulan terakhir login di origin yang sama).
const APP_NS = 'emklapproval_';
const CHANNEL_NAME = APP_NS + 'idle-lock-channel';
const LAST_ACTIVITY_KEY = APP_NS + 'idle-last-activity';
const LOCKED_KEY = APP_NS + 'idle-locked';
const FAILED_ATTEMPTS_KEY = APP_NS + 'idle-failed-attempts';
const USERID_KEY = APP_NS + 'lockscreen_userid';
const MAX_ATTEMPTS = 3;

let lockscreenInterval = null;
let lastStorageWriteTime = 0;
let broadcastChannel = null;
let lastActivityLocal = Date.now();
let elementToRefocus = null; // Menyimpan elemen terakhir yang fokus

$(document).ready(function () {
    // Abaikan jika modal lockscreen belum di-render (misal di halaman login)
    if ($('#lockscreen-overlay').length === 0) return;

    if (window.BroadcastChannel) {
        broadcastChannel = new BroadcastChannel(CHANNEL_NAME);
        broadcastChannel.onmessage = function (event) {
            const data = event.data;
            if (data.type === 'unlock') {
                unlockScreenLocal();
            } else if (data.type === 'logout') {
                window.location.href = appUrl + 'login/logout';
            }
        };
    }

    // Cek status saat baru muat halaman
    if (localStorage.getItem(LOCKED_KEY) === 'true') {
        showLockscreen();
    } else {
        registerActivity(); // Inisialisasi aktivitas pertama
    }

    // Tampilkan tombol Quick Login hanya jika perangkat adalah Mobile dan mendukung biometrik
    let isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    if (window.PublicKeyCredential && isMobile) {
        PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable().then(function(available) {
            if (available) {
                $('#lockscreen-biometric-btn').show();
            }
        }).catch(function() {});
    }

    // Pasang listener aktivitas
    const events = ['mousemove', 'mousedown', 'keydown', 'wheel', 'touchstart', 'scroll'];
    events.forEach(function (evt) {
        window.addEventListener(evt, registerActivity, { passive: true });
    });

    // Hapus interval jika ada
    if (lockscreenInterval) {
        clearInterval(lockscreenInterval);
    }
    lockscreenInterval = setInterval(checkIdleStatus, 1000);

    // UX: Tangkap tombol Enter pada kolom password (karena mungkin dibajak oleh jqGrid/mains.js)
    $('#lockscreen-password').on('keydown', function(e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            e.stopPropagation(); // Cegah event bocor ke grid di belakang layar
            $('#lockscreen-btn').click(); // Gunakan klik tombol untuk trigger form
        }
    });

    // Form submit listener
    $('#lockscreen-form').on('submit', function (e) {
        e.preventDefault();
        let password = $('#lockscreen-password').val();
        if (!password) {
            $('#lockscreen-error').text('PASSWORD WAJIB DIISI').show();
            return;
        }

        let $btn = $('#lockscreen-btn');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memverifikasi...');
        $('#lockscreen-error').hide();

        $.ajax({
            url: appUrl + 'login/unlock',
            type: 'POST',
            data: { 
                password: password,
                userid: localStorage.getItem(USERID_KEY)
            },
            success: function (res) {
                $btn.prop('disabled', false).text('Buka Kunci');
                if (res.success) {
                    unlockScreenGlobal();
                } else {
                    handleFailedUnlock(res.message);
                }
            },
            error: function (jqXHR) {
                $btn.prop('disabled', false).text('Buka Kunci');
                // Sesi server sudah benar-benar habis (bukan sekadar salah ketik
                // password) -- password apapun yang dimasukkan tidak akan pernah
                // bisa diverifikasi, jadi jangan hitung sebagai percobaan gagal.
                // Langsung arahkan ke alur logout/login biasa, sama seperti kalau
                // sesi habis saat sedang memakai aplikasi (bukan sedang terkunci).
                if (jqXHR.status === 401 && jqXHR.responseJSON && jqXHR.responseJSON.session_expired) {
                    forceSessionExpired();
                    return;
                }
                // 403 + ssoOnly = login lokal sedang dimatikan (sso.passwordLoginEnabled
                // = false), bukan password salah. TIDAK boleh lewat handleFailedUnlock:
                // menghitungnya sebagai percobaan gagal akan memaksa logout atas sesuatu
                // yang bukan kesalahan user. Antar langsung ke SSO.
                if (jqXHR.status === 403 && jqXHR.responseJSON && jqXHR.responseJSON.ssoOnly) {
                    $('#lockscreen-error')
                        .text(jqXHR.responseJSON.message || 'Membuka kunci lewat SSO...')
                        .show();
                    window.location.href = jqXHR.responseJSON.redirect || (appUrl + 'sso/login');
                    return;
                }
                handleFailedUnlock('Terjadi kesalahan koneksi.');
            }
        });
    });
});

function getSharedLastActivity(localValue) {
    try {
        const stored = localStorage.getItem(LAST_ACTIVITY_KEY);
        if (stored) return Math.max(localValue, Number(stored));
    } catch (e) { }
    return localValue;
}

function registerActivity() {
    if (localStorage.getItem(LOCKED_KEY) === 'true') return;
    const now = Date.now();
    lastActivityLocal = now;

    // Throttle: simpan ke localStorage maksimum 1x per detik
    if (now - lastStorageWriteTime > 1000) {
        lastStorageWriteTime = now;
        try {
            localStorage.setItem(LAST_ACTIVITY_KEY, String(now));
        } catch (e) { }
    }
}

function checkIdleStatus() {
    if (localStorage.getItem(LOCKED_KEY) === 'true') return;
    const now = Date.now();
    const lastActivity = getSharedLastActivity(lastActivityLocal);

    if (now - lastActivity > IDLE_TIMEOUT) {
        lockGlobal();
    }
}

function lockGlobal() {
    try {
        localStorage.setItem(LOCKED_KEY, 'true');
    } catch (e) { }
    showLockscreen();
}

function showLockscreen() {
    // Simpan elemen input/form terakhir yang sedang aktif sebelum lockscreen muncul
    if (document.activeElement && document.activeElement !== document.body) {
        elementToRefocus = document.activeElement;
    }

    $('#lockscreen-overlay').css('display', 'flex').hide().fadeIn('fast');
    $('#lockscreen-password').val('');
    
    // PEMBUNUH FOCUS TRAP ABSOLUT
    // Matikan SEMUA event 'focusin' di level document (termasuk milik Bootstrap & jQuery UI)
    // Ini menjamin 100% modal background tidak akan bisa menarik paksa fokus kursor
    $(document).off('focusin');

    let currentAttempts = parseInt(localStorage.getItem(FAILED_ATTEMPTS_KEY) || '0', 10);
    if (currentAttempts > 0) {
        let remaining = MAX_ATTEMPTS - currentAttempts;
        $('#lockscreen-error').text(`Password salah (${remaining} percobaan tersisa sebelum logout)`).show();
    } else {
        $('#lockscreen-error').hide();
    }
    
    // Blur out focus from background elements
    if (document.activeElement) {
        document.activeElement.blur();
    }
    // Set focus to password field after modal is shown
    setTimeout(() => { $('#lockscreen-password').focus(); }, 100);
}

function unlockScreenLocal() {
    $('#lockscreen-overlay').fadeOut('fast', function() {
        // Kembalikan kursor ke elemen semula setelah layar terbuka
        if (elementToRefocus) {
            try {
                $(elementToRefocus).focus();
            } catch(e) {}
            elementToRefocus = null;
        }
    });
    const now = Date.now();
    lastActivityLocal = now;
}

function unlockScreenGlobal() {
    const now = Date.now();
    lastActivityLocal = now;
    try {
        localStorage.setItem(LAST_ACTIVITY_KEY, String(now));
        localStorage.setItem(LOCKED_KEY, 'false');
        localStorage.removeItem(FAILED_ATTEMPTS_KEY);
    } catch(e) {}
    unlockScreenLocal();
    if (broadcastChannel) {
        broadcastChannel.postMessage({ type: 'unlock' });
    }
}

function forceSessionExpired() {
    try {
        localStorage.removeItem(LOCKED_KEY);
        localStorage.removeItem(LAST_ACTIVITY_KEY);
        localStorage.removeItem(FAILED_ATTEMPTS_KEY);
    } catch (e) { }
    if (broadcastChannel) {
        broadcastChannel.postMessage({ type: 'logout' });
    }
    window.location.href = appUrl + 'login/logout';
}

function handleFailedUnlock(customMsg) {
    let currentAttempts = parseInt(localStorage.getItem(FAILED_ATTEMPTS_KEY) || '0', 10);
    currentAttempts++;
    try {
        localStorage.setItem(FAILED_ATTEMPTS_KEY, String(currentAttempts));
    } catch(e) {}

    if (currentAttempts >= MAX_ATTEMPTS) {
        try {
            localStorage.removeItem(LOCKED_KEY);
            localStorage.removeItem(LAST_ACTIVITY_KEY);
            localStorage.removeItem(FAILED_ATTEMPTS_KEY);
        } catch(e) {}
        if (broadcastChannel) {
            broadcastChannel.postMessage({ type: 'logout' });
        }
        window.location.href = appUrl + 'login/logout';
        return;
    }
    
    let remaining = MAX_ATTEMPTS - currentAttempts;
    let msg = customMsg || 'Password salah';
    $('#lockscreen-error').text(`${msg} (${remaining} percobaan tersisa sebelum logout)`).show();
    $('#lockscreen-password').val('').focus();
}

// Fungsi bantu untuk tombol show/hide password
function toggleLockscreenPassword() {
    let x = document.getElementById("lockscreen-password");
    let eye = document.getElementById("lockscreen-eye");
    if (x.type === "password") {
        x.type = "text";
        eye.classList.remove("fa-eye");
        eye.classList.add("fa-eye-slash");
    } else {
        x.type = "password";
        eye.classList.remove("fa-eye-slash");
        eye.classList.add("fa-eye");
    }
}

// Fungsi bantu untuk trigger Quick Login Biometrik
function triggerLockscreenBiometric() {
    if (typeof startWebAuthnLogin === 'function') {
        let loginArgsUrl = appUrl + 'webauthn/getLoginArgs';
        let processLoginUrl = appUrl + 'webauthn/processLogin';
        
        // Sembunyikan error lama
        $('#lockscreen-error').hide();

        // Parameter ketiga adalah callback sukses, parameter keempat adalah callback error
        startWebAuthnLogin(loginArgsUrl, processLoginUrl, function() {
            unlockScreenGlobal();
        }, function(errMsg, cancelled) {
            if (cancelled) {
                // User membatalkan prompt / waktu habis — bukan kegagalan autentikasi,
                // jadi jangan mengurangi jatah percobaan unlock
                $('#lockscreen-error').text(errMsg).show();
            } else {
                // Tampilkan error menggunakan fungsi standar lockscreen agar seragam dengan error password
                handleFailedUnlock(errMsg);
            }
        });
    } else {
        alert("Library WebAuthn belum dimuat.");
    }
}
