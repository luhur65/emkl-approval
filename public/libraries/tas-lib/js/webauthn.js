/**
 * WebAuthn Helper Functions
 */

function arrayBufferToBase64(buffer) {
    let binary = '';
    let bytes = new Uint8Array(buffer);
    let len = bytes.byteLength;
    for (let i = 0; i < len; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return window.btoa(binary);
}

function base64ToArrayBuffer(base64) {
    base64 = base64.replace(/-/g, '+').replace(/_/g, '/');
    let padLen = (4 - (base64.length % 4)) % 4;
    base64 += '='.repeat(padLen);
    let binary_string = window.atob(base64);
    let len = binary_string.length;
    let bytes = new Uint8Array(len);
    for (let i = 0; i < len; i++) {
        bytes[i] = binary_string.charCodeAt(i);
    }
    return bytes.buffer;
}

function recursiveBase64ToArrayBuffer(obj) {
    for (let key in obj) {
        if (typeof obj[key] === 'object' && obj[key] !== null) {
            recursiveBase64ToArrayBuffer(obj[key]);
        } else if (typeof obj[key] === 'string') {
            // lbuchs/WebAuthn encodes binary properties as =?BINARY?B?{base64}?=
            if (obj[key].startsWith('=?BINARY?B?') && obj[key].endsWith('?=')) {
                let base64 = obj[key].substring(11, obj[key].length - 2);
                obj[key] = base64ToArrayBuffer(base64);
            }
        }
    }
}

/**
 * Petakan DOMException dari navigator.credentials ke pesan bahasa Indonesia.
 * Mengembalikan { message, cancelled, alreadyRegistered } — cancelled = true
 * jika user membatalkan atau waktu habis (bukan kegagalan autentikasi
 * sungguhan); alreadyRegistered = true jika perangkat sudah punya credential
 * untuk user ini (ditolak lewat excludeCredentials).
 */
function friendlyWebAuthnError(err) {
    let name = err && err.name ? err.name : '';
    switch (name) {
        case 'NotAllowedError':
            return { message: 'Proses dibatalkan atau waktu habis. Silakan coba lagi.', cancelled: true };
        case 'AbortError':
            return { message: 'Proses dihentikan. Silakan coba lagi.', cancelled: true };
        case 'InvalidStateError':
            return { message: 'Perangkat ini sudah pernah didaftarkan untuk akun Anda.', cancelled: false, alreadyRegistered: true };
        case 'SecurityError':
            return { message: 'Koneksi tidak aman. Fitur biometrik hanya dapat digunakan melalui HTTPS.', cancelled: false };
        case 'NotSupportedError':
            return { message: 'Perangkat Anda tidak mendukung fitur biometrik ini.', cancelled: false };
        case 'ConstraintError':
            return { message: 'Perangkat tidak memenuhi persyaratan verifikasi biometrik.', cancelled: false };
        default:
            return { message: 'Verifikasi biometrik gagal. Silakan coba lagi.', cancelled: false };
    }
}

/**
 * Ambil pesan ramah dari respons AJAX yang gagal.
 */
function friendlyAjaxError(err) {
    if (err && err.responseJSON) {
        let res = err.responseJSON;
        if (typeof res.message === 'string' && res.message) return res.message;
        if (typeof res.error === 'string' && res.error) return res.error;
    }
    if (err && err.status === 0) {
        return 'Tidak dapat terhubung ke server. Periksa koneksi internet Anda.';
    }
    return 'Terjadi kesalahan pada server. Silakan coba lagi.';
}

// Function to handle login via WebAuthn
function startWebAuthnLogin(loginUrl, processUrl, redirectUrlOrCallback, errorCallback) {
    if (!window.PublicKeyCredential) {
        showDialog("Browser Anda tidak mendukung Login Biometrik.");
        return;
    }

    // Sertakan userid yang tersimpan di perangkat (di-set setiap kali user
    // login) agar server mengirim daftar credentialId milik user tersebut.
    // Wajib untuk perangkat lama yang tidak mendukung discoverable credential
    // (mis. Android 8) — tanpa ini navigator.credentials.get selalu gagal
    // dengan NotAllowedError di perangkat tersebut.
    let storedUserid = null;
    try { storedUserid = localStorage.getItem('lockscreen_userid'); } catch (e) {}

    $.ajax({
        url: loginUrl,
        type: 'GET',
        data: storedUserid ? { userid: storedUserid } : null,
        dataType: 'json',
        success: function(options) {
            if (options.error) {
                if (errorCallback) errorCallback(options.message || options.error);
                else showDialog(options.message || options.error);
                return;
            }

            // Convert base64 fields to ArrayBuffers
            recursiveBase64ToArrayBuffer(options);

            navigator.credentials.get(options)
                .then(function(assertion) {
                    let authData = {
                        id: arrayBufferToBase64(assertion.rawId),
                        clientDataJSON: arrayBufferToBase64(assertion.response.clientDataJSON),
                        authenticatorData: arrayBufferToBase64(assertion.response.authenticatorData),
                        signature: arrayBufferToBase64(assertion.response.signature),
                        userHandle: assertion.response.userHandle ? arrayBufferToBase64(assertion.response.userHandle) : null
                    };

                    $.ajax({
                        url: processUrl,
                        type: 'POST',
                        data: authData,
                        dataType: 'json',
                        success: function(res) {
                            if (res.success) {
                                if (typeof redirectUrlOrCallback === 'function') {
                                    redirectUrlOrCallback();
                                } else if (redirectUrlOrCallback) {
                                    window.location.href = redirectUrlOrCallback;
                                }
                            } else {
                                let errMsg = res.message || res.error || 'Login biometrik gagal. Silakan coba lagi.';
                                if (errorCallback) errorCallback(errMsg);
                                else showDialog(errMsg);
                            }
                        },
                        error: function(err) {
                            let errMsg = friendlyAjaxError(err);
                            if (errorCallback) errorCallback(errMsg);
                            else showDialog(errMsg);
                        }
                    });
                })
                .catch(function(err) {
                    console.error(err);
                    let friendly = friendlyWebAuthnError(err);
                    if (errorCallback) errorCallback(friendly.message, friendly.cancelled);
                    else showDialog(friendly.message);
                });
        },
        error: function(err) {
            console.error(err);
            let errMsg = friendlyAjaxError(err);
            if (errorCallback) errorCallback(errMsg);
            else showDialog(errMsg);
        }
    });
}

// Function to handle registration via WebAuthn
// errorCallback opsional menerima (message, info) — info adalah hasil
// friendlyWebAuthnError (berisi flag cancelled / alreadyRegistered) atau null
// untuk kegagalan non-WebAuthn (jaringan/server).
function startWebAuthnRegister(registerUrl, processUrl, successCallback, errorCallback) {
    if (!window.PublicKeyCredential) {
        showDialog("Browser Anda tidak mendukung Biometrik.");
        return;
    }

    $.ajax({
        url: registerUrl,
        type: 'GET',
        dataType: 'json',
        success: function(options) {
            if (options.error) {
                let errMsg = options.message || options.error;
                if (errorCallback) errorCallback(errMsg, null);
                else showDialog(errMsg);
                return;
            }

            // Convert base64 fields to ArrayBuffers
            recursiveBase64ToArrayBuffer(options);

            if (options.user && options.user.id) {
                // User ID must be Uint8Array
                if (typeof options.user.id === 'string') {
                     options.user.id = base64ToArrayBuffer(options.user.id);
                }
            }

            navigator.credentials.create(options)
                .then(function(credential) {
                    let attestationData = {
                        clientDataJSON: arrayBufferToBase64(credential.response.clientDataJSON),
                        attestationObject: arrayBufferToBase64(credential.response.attestationObject)
                    };

                    $.ajax({
                        url: processUrl,
                        type: 'POST',
                        data: attestationData,
                        dataType: 'json',
                        success: function(res) {
                            if (res.success) {
                                if (successCallback) successCallback(res);
                                else showDialog("Pendaftaran biometrik berhasil!");
                            } else {
                                let errMsg = res.message || res.error || 'Pendaftaran biometrik gagal. Silakan coba lagi.';
                                if (errorCallback) errorCallback(errMsg, null);
                                else showDialog(errMsg);
                            }
                        },
                        error: function(err) {
                            let errMsg = friendlyAjaxError(err);
                            if (errorCallback) errorCallback(errMsg, null);
                            else showDialog(errMsg);
                        }
                    });
                })
                .catch(function(err) {
                    console.error(err);
                    let friendly = friendlyWebAuthnError(err);
                    if (errorCallback) errorCallback(friendly.message, friendly);
                    else showDialog(friendly.message);
                });
        },
        error: function(err) {
            console.error(err);
            let errMsg = friendlyAjaxError(err);
            if (errorCallback) errorCallback(errMsg, null);
            else showDialog(errMsg);
        }
    });
}
