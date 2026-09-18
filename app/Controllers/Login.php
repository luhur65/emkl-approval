<?php

namespace App\Controllers;

use App\Libraries\SsoExit;
use App\Libraries\SsoSlo;
use App\Models\AuthModel;

class Login extends BaseController
{
    public function index()
    {
        $session = session();

        // Redirect jika sudah login
        if ($session->get('logged_emkl')) {
            return redirect()->to('/home');
        }

        // Konfigurasi SSO ikut ke view untuk tombol "Masuk dengan SSO" dan
        // penyembunyian form lokal pada mode SSO-only.
        $data = ['error' => '', 'sso' => config(\Config\Sso::class)];

        if ($this->request->getMethod() === 'POST') {
            // Mode SSO-only: ditolak DI SINI, bukan hanya disembunyikan di view.
            // POST langsung dari curl atau tab lama tidak pernah melihat view.
            if ($this->passwordLoginDisabled()) {
                log_message('error', sprintf(
                    'Login lokal ditolak (sso.passwordLoginEnabled=false): pUser=%s ip=%s',
                    (string) $this->request->getPost('pUser'),
                    $this->request->getIPAddress()
                ));

                return redirect()->to(base_url('login?sso=onlysso'));
            }

            // Validasi input
            $rules = [
                'pUser'     => 'required',
                'pPassword' => 'required'
            ];

            if (!$this->validate($rules)) {
                $data['error'] = 'Username dan Password wajib diisi.';
                return view('auth/login', $data);
            }

            $pUser = $this->request->getPost('pUser');
            // Meniru legacy logic yang menggunakan md5
            $pPassword = md5((string)$this->request->getPost('pPassword'));

            $authModel = new AuthModel();
            $user = $authModel->validateUser($pUser, $pPassword);

            if (!empty($user)) {
                $this->setLoggedInSession($user);

                return redirect()->to('/home');
            } else {
                $data['error'] = 'Username atau Password salah.';
            }

            return view('auth/login', $data);
        }

        // Alur SSO berakhir dengan redirect ke halaman ini membawa ?sso=<kode>;
        // sesi lokal saat itu belum tentu ada (baru saja dihancurkan), jadi
        // flashdata bukan jalur yang bisa diandalkan.
        $message = $this->ssoMessage();
        $ssoOnly = $this->ssoOnlyEntryPoint();

        // Mode SSO-only: halaman ini tidak punya apa pun untuk ditawarkan --
        // antar langsung ke SSO. KECUALI saat membawa pesan kegagalan: halaman
        // ini satu-satunya tempat kegagalan SSO bisa muncul ("akun belum
        // terdaftar", "tiket sudah dipakai", "sesi SSO berakhir"). Kalau ikut
        // dialihkan, user gagal, terlempar ke dashboard, mencoba lagi, gagal
        // lagi -- berputar tanpa pernah tahu apa yang salah.
        if ($message === null && $ssoOnly !== null) {
            return redirect()->to($ssoOnly);
        }

        $data['error'] = $message ?? '';

        return view('auth/login', $data);
    }

    public function logout()
    {
        $session = session();

        // Yang diakhiri di sini HANYA sesi aplikasi ini. Mencabut sesi SSO-nya
        // (POST /auth/session/revoke ke auth-sso-api) akan melogout user dari
        // HR, CRM, dan aplikasi lain sekaligus -- itu wewenang dashboard SSO.
        $fromSso = (bool) $session->get('sso_login');
        $sid     = (string) ($session->get('sso_sid') ?? '');

        if ($sid !== '') {
            (new SsoSlo())->forget($sid);
        }

        $session->destroy();

        // Tujuannya ditentukan di satu tempat bersama jalur sesi-berakhir yang
        // lain (AuthFilter), supaya keduanya tidak pelan-pelan berbeda. User SSO
        // pulang ke dashboard SSO, user login lokal ke halaman /login.
        return redirect()->to(SsoExit::target($fromSso));
    }

    /**
     * Endpoint AJAX untuk fitur Lockscreen: verifikasi ulang password user
     * yang sedang login sebelum sesi idle-nya dibuka kembali.
     *
     * Auto-relogin: kalau sesi server sudah hilang (expired/di-GC saat user
     * pergi lama), userid diambil dari field POST -- dikirim client dari
     * localStorage 'lockscreen_userid' -- alih-alih memaksa user balik ke
     * halaman /login dan mengetik ulang username. Selama passwordnya benar,
     * sesi dibangun ulang langsung dari form lockscreen ini.
     */
    public function unlock()
    {
        // Lock screen memverifikasi password FUserList yang sama dengan halaman
        // login, jadi ia ikut mati bersama login lokal. Dijawab dengan penanda
        // `ssoOnly` supaya lockscreen.js mengantar user ke SSO alih-alih
        // menghitungnya sebagai percobaan gagal lalu memaksa logout.
        if ($this->passwordLoginDisabled()) {
            return $this->response->setStatusCode(403)->setJSON([
                'success'  => false,
                'ssoOnly'  => true,
                'redirect' => base_url('sso/login'),
                'message'  => 'Login username/password sudah dinonaktifkan. Membuka kunci lewat SSO...',
            ]);
        }

        $session = session();
        $userId  = $session->get('FUserID');

        if (!$userId) {
            $userId = $this->request->getPost('userid');
        }

        if (!$userId) {
            // Baik sesi server maupun localStorage browser sama-sama sudah
            // tidak punya identitas user -- tidak ada apa pun yang bisa
            // dipakai untuk auto-relogin. Beda dengan password salah, jadi
            // jangan dihitung sebagai percobaan gagal; status 401 + flag
            // session_expired memberi sinyal ke lockscreen.js supaya langsung
            // mengarahkan ke alur logout/login biasa.
            return $this->response->setStatusCode(401)->setJSON([
                'success'         => false,
                'session_expired' => true,
                'message'         => 'Sesi telah berakhir permanen. Silakan muat ulang halaman.',
            ]);
        }

        $password  = md5((string) $this->request->getPost('password'));
        $authModel = new AuthModel();
        $user      = $authModel->validateUser($userId, $password);

        if (empty($user)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Password salah.',
            ]);
        }

        // Sesi server memang sudah hilang (bukan sekadar lama) -- bangun
        // ulang persis seperti login normal, supaya lockscreen berfungsi
        // sebagai re-login penuh tanpa perlu lewat halaman /login.
        if (!$session->get('logged_emkl')) {
            $this->setLoggedInSession($user);
        }

        return $this->response->setJSON(['success' => true]);
    }

    private function setLoggedInSession(array $user): void
    {
        session()->set([
            'FUserID'     => $user['FUserID'],
            'FNamaUser'   => $user['FNamaUser'],
            'logged_emkl' => true,
        ]);
    }

    /**
     * Apakah login lokal (username/password + buka kunci lock screen) sedang
     * dimatikan? Dipanggil di SETIAP endpoint jalur itu, bukan hanya di view.
     */
    private function passwordLoginDisabled(): bool
    {
        return ! config(\Config\Sso::class)->passwordLoginEnabled;
    }

    /**
     * Alamat SSO bila halaman login lokal sudah tidak berguna, atau null bila
     * halaman ini masih perlu ditampilkan.
     *
     * Null saat login lokal masih hidup, dan juga saat SSO belum dikonfigurasi.
     * Yang kedua penting: kalau login lokal dimatikan sementara SSO belum siap,
     * tidak ada satu pun jalan masuk -- user harus melihat halaman ini beserta
     * keterangan "tidak ada metode login yang aktif", bukan diarahkan ke
     * alamat kosong yang menyembunyikan salah konfigurasi.
     */
    private function ssoOnlyEntryPoint(): ?string
    {
        if (! $this->passwordLoginDisabled()) {
            return null;
        }

        $sso = config(\Config\Sso::class);

        if (! $sso->enabled) {
            return null;
        }

        $launch = trim($sso->launchUrl);
        $url    = $launch !== '' ? $launch : rtrim(trim($sso->dashboardUrl), '/');

        return $url !== '' ? $url : null;
    }

    /**
     * Pesan untuk kegagalan SSO, dipilih dari kode pada ?sso=.
     *
     * View login merender $error tanpa escaping, jadi yang lewat URL hanya
     * KODE; teksnya diambil dari daftar tertutup di bawah, tidak pernah dari
     * input. Kode di luar daftar diabaikan (null).
     */
    private function ssoMessage(): ?string
    {
        $messages = [
            'disabled' => 'Login SSO belum diaktifkan pada aplikasi ini.',
            'invalid'  => 'Tiket SSO tidak valid atau sudah kedaluwarsa. Silakan ulangi dari SSO.',
            'replay'   => 'Tiket SSO sudah pernah dipakai. Silakan ulangi dari SSO.',
            // Pemilik masalahnya admin aplikasi ini: FIDKaryawan belum dipetakan.
            'unknown'  => 'Akun Anda belum terhubung ke EMKL Approval cabang ini. Harap hubungi admin EMKL Approval.',
            'account'  => 'Akun yang dipilih tidak ditemukan di EMKL Approval cabang ini. Coba pilih akun lain, atau hubungi admin EMKL Approval.',
            // Pemilik masalahnya admin SSO: tiketnya yang kurang, bukan akunnya.
            'noclaim'  => 'Tiket SSO tidak membawa identitas karyawan yang dibutuhkan aplikasi ini. Harap hubungi admin SSO.',
            'casting'  => 'Login-as (Panel Casting) tidak didukung aplikasi ini.',
            'expired'  => 'Sesi SSO Anda telah berakhir. Silakan login kembali.',
            'server'   => 'Terjadi kesalahan saat memproses login SSO. Coba lagi nanti.',
            'onlysso'  => 'Login username/password sudah dinonaktifkan. Silakan masuk lewat SSO.',
        ];

        $code = (string) ($this->request->getGet('sso') ?? '');

        return $messages[$code] ?? null;
    }
}
