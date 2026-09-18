<?php

namespace App\Filters;

use App\Libraries\SsoExit;
use App\Libraries\SsoSlo;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    /**
     * Mengecek apakah user sudah login sebelum controller dieksekusi.
     * Jika belum, lempar ke halaman login (atau dashboard SSO -- lihat SsoExit).
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Cek sesi 'logged_emkl'
        if (! session()->get('logged_emkl')) {
            // Sesi yang sudah tidak ada tidak bisa ditanya lagi apakah ia lahir
            // dari SSO, jadi tujuannya ditentukan sepenuhnya oleh
            // sso.logoutToSso. Halaman /login sendiri ada di daftar `except`
            // filter ini, jadi login lokal tetap bisa dibuka langsung.
            return $this->sessionEnded($request, SsoExit::target());
        }

        return $this->enforceSingleLogout($request);
    }

    /**
     * Satu jawaban untuk setiap sesi yang berakhir, dalam dua rupa: halaman
     * berpindah untuk navigasi biasa, 401 ber-JSON untuk AJAX. Alamat tujuannya
     * sama persis -- yang memutuskan alamat itu App\Libraries\SsoExit.
     */
    private function sessionEnded(RequestInterface $request, string $target)
    {
        // Permintaan AJAX TIDAK boleh dibalas redirect: jQuery mengikuti redirect
        // itu diam-diam lalu menyerahkan HTML halaman login ke handler success.
        // Akibatnya sesi yang habis terlihat persis seperti proses yang berhasil --
        // pada endpoint approve, user diberi tahu "berhasil" padahal tidak ada satu
        // baris pun yang diproses. Balas 401 + JSON supaya sisi klien bisa
        // membedakannya dan menyuruh user login ulang.
        if ($request instanceof IncomingRequest && $request->isAJAX()) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    // Dua kunci pertama dipertahankan apa adanya: seluruh view
                    // approval membacanya (pesanGagalAjax).
                    'error'          => 'Sesi Anda telah berakhir. Silakan login ulang.',
                    'msg'            => '',
                    // Penanda untuk penangan ajaxError global di partials/header.php:
                    // dikenali lewat penanda ini, BUKAN status 401 saja, karena
                    // login/unlock juga menjawab 401 untuk keadaan lain. `redirect`
                    // ikut dikirim supaya klien tidak menebak tujuan -- untuk sesi
                    // SSO yang dicabut alamatnya membawa ?sso=expired.
                    'sessionExpired' => true,
                    'redirect'       => $target,
                ]);
        }

        return redirect()->to($target);
    }

    /**
     * Single Logout: sesi yang lahir dari SSO ikut berakhir saat sesi SSO-nya
     * dicabut di dashboard. Sesi login lokal (tanpa `sso_sid`) tidak tersentuh
     * sama sekali; sesi SSO hanya ditanyakan sekali per sso.sloPollSeconds,
     * sisanya dijawab dari cache (lihat SsoSlo).
     */
    private function enforceSingleLogout(RequestInterface $request)
    {
        $sid = (string) (session()->get('sso_sid') ?? '');

        if ($sid === '') {
            return null;
        }

        $slo = new SsoSlo();

        if ($slo->isSessionActive($sid)) {
            return null;
        }

        log_message('error', sprintf(
            'SSO SLO: sesi SSO %s… sudah dicabut, sesi lokal FUserID=%s diakhiri.',
            substr($sid, 0, 8),
            session()->get('FUserID') ?: '-'
        ));

        $slo->forget($sid);
        session()->destroy();

        // Alasannya dititipkan lewat query string, bukan flashdata: sesi baru
        // saja dihancurkan, jadi tidak ada tempat menyimpan flashdata. Login
        // controller menerjemahkan kode ini jadi kalimat (ssoMessage()).
        return $this->sessionEnded($request, SsoExit::target(false, 'expired'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Tidak ada logic khusus untuk after-filter saat ini.
    }
}
