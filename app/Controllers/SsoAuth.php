<?php

namespace App\Controllers;

use App\Libraries\SsoNonceStore;
use App\Libraries\SsoTicket;
use App\Libraries\SsoTicketException;
use App\Models\AuthModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Sso as SsoConfig;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Sisi aplikasi ini dari alur Single Sign-On. Polanya disalin dari sys-modern
 * (app/Controllers/SsoAuth.php), disesuaikan ke tabel FUserList.
 *
 * Urutan lengkapnya:
 *   1. Pengguna login di dashboard auth-sso lalu menekan kartu EMKL APPROVAL
 *      cabangnya — atau menekan "Masuk dengan SSO" di halaman login sini, yang
 *      mengantarnya ke `<auth-sso>/launch/<appCode>` (jalur SP-initiated).
 *   2. auth-sso meminta tiket ke auth-sso-api (POST /auth/generate-ticket).
 *      Di sanalah hak akses diperiksa: aplikasi terdaftar, `access_menu`
 *      pengguna memuat nama aplikasi cabang ini, sesi SSO-nya masih hidup, DAN
 *      FUserList cabang ini punya baris aktif dengan FIDKaryawan = karyawan_id
 *      akun SSO. FID baris itulah yang ditandatangani sebagai klaim `sub`.
 *   3. Browser diarahkan ke sini: GET auth/sso-callback?ticket=<JWT RS256>.
 *   4. callback() memverifikasi tiket secara lokal dengan public key SSO,
 *      membakar `jti` supaya tiket tidak bisa dipakai dua kali, menukar `sub`
 *      menjadi baris FUserList (dan mencocokkan ulang FIDKaryawan-nya), lalu
 *      membuat sesi persis seperti login password biasa.
 *
 * Aplikasi ini TIDAK PERNAH membuat akun sendiri dari tiket. Tiket membuktikan
 * "orang ini sudah diautentikasi SSO", bukan "orang ini berhak masuk EMKL
 * Approval" — yang kedua tetap ditentukan oleh ada tidaknya barisnya di
 * FUserList beserta menu approval-nya (checkMenu()).
 */
class SsoAuth extends BaseController
{
    private SsoConfig $sso;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->sso = config(SsoConfig::class);
    }

    /**
     * Pintu masuk dari halaman login ke SSO ("Masuk dengan SSO").
     *
     * Kalau sso.launchUrl diisi, pengguna diantar ke jalur SP-initiated milik
     * auth-sso yang langsung menerbitkan tiket untuk aplikasi ini. Kalau tidak,
     * ia diantar ke dashboard dan menekan kartu cabangnya sendiri.
     */
    public function start(): RedirectResponse
    {
        if (! $this->sso->enabled || trim($this->sso->dashboardUrl) === '') {
            return $this->fail('disabled', 'start: SSO belum diaktifkan atau sso.dashboardUrl kosong.');
        }

        $launch = trim($this->sso->launchUrl);

        return redirect()->to($launch !== '' ? $launch : rtrim($this->sso->dashboardUrl, '/'));
    }

    /**
     * Menukar tiket SSO menjadi sesi aplikasi.
     */
    public function callback()
    {
        if (! $this->sso->enabled) {
            return $this->fail('disabled', 'callback: SSO belum diaktifkan.');
        }

        $ticket = (string) ($this->request->getGet('ticket') ?? '');

        if (trim($ticket) === '') {
            return $this->fail('invalid', 'callback: parameter ticket kosong.');
        }

        try {
            $claims = (new SsoTicket($this->sso))->verify($ticket);
        } catch (SsoTicketException $e) {
            // Alasan sesungguhnya hanya masuk log. Pesan ke pengguna dibuat
            // seragam supaya respons tidak bisa dipakai menebak bentuk tiket
            // yang akan diterima.
            return $this->fail('invalid', 'callback: tiket ditolak — ' . $e->getMessage());
        }

        $jti = (string) $claims['jti'];

        if (! (new SsoNonceStore())->burn($jti, $this->sso->nonceTtl)) {
            return $this->fail('replay', 'callback: tiket dengan jti ' . substr($jti, 0, 6) . '… sudah pernah dipakai.');
        }

        // Tiket Panel Casting (login-as oleh admin IT) sengaja TIDAK diterima:
        // aplikasi ini tidak didaftarkan ke PANEL_CASTING_APP_CODES di auth-sso,
        // jadi tiket seperti ini tidak seharusnya pernah sampai ke sini.
        // Perbandingannya `=== true`: nilainya datang dari tiket bertanda tangan,
        // dan "1"/"false" tidak boleh diam-diam mengubah jalur.
        if (($claims['impersonated'] ?? null) === true) {
            return $this->fail('casting', sprintf(
                'callback: tiket Panel Casting (sub=%s) ditolak, aplikasi ini tidak mendukung login-as.',
                (string) $claims['sub']
            ));
        }

        // Klaim karyawanId wajib: ia yang mengikat tiket ke baris FUserList,
        // baik di jalur `sub` (pencocokan ulang) maupun jalur cadangan. Tiket
        // tanpa klaim ini adalah masalah di sisi PENERBIT (akun SSO-nya tidak
        // terhubung ke master karyawan), bukan di daftar akun aplikasi ini —
        // kodenya dibedakan supaya layar menunjuk pihak yang bisa memperbaikinya.
        $karyawanId = $this->klaimKaryawanId($claims);

        if ($karyawanId === null) {
            return $this->fail('noclaim', sprintf(
                'callback: tiket tidak membawa klaim karyawanId yang sah (sub=%s). Klaim yang ada: %s.',
                (string) $claims['sub'],
                implode(', ', array_keys($claims))
            ));
        }

        $sub = trim((string) $claims['sub']);

        try {
            if (ctype_digit($sub)) {
                // Jalur utama: `sub` = FID baris yang dipilih auth-sso-api dari
                // FUserList cabang ini (satu karyawan bisa punya beberapa akun;
                // `sub` yang membedakannya, karyawanId tidak).
                $user = $this->resolveByFid((int) $sub, $karyawanId);
                $kode = 'account';
            } else {
                // Jalur cadangan untuk tiket yang `sub`-nya bukan FID: cari lewat
                // sso.matchClaim/sso.matchColumn, harus tepat satu baris.
                $user = $this->resolveByClaim($claims);
                $kode = 'unknown';
            }
        } catch (Throwable $e) {
            log_message('error', 'SSO callback: gagal mencari pengguna — ' . $e->getMessage());

            return $this->fail('server', null);
        }

        if ($user === null) {
            return $this->fail($kode, sprintf(
                'callback: tidak ada baris FUserList untuk sub=%s dengan %s=%d.',
                $sub,
                $this->sso->matchColumn,
                $karyawanId
            ));
        }

        // Tiket tanpa `sid` menghasilkan sesi yang TIDAK bisa dijangkau Single
        // Logout: AuthFilter melewatinya, dan logout di dashboard SSO tidak akan
        // mengakhiri sesi ini. Kegagalan yang diam-diam — dicatat sejak awal.
        $sid = isset($claims['sid']) && is_string($claims['sid']) ? trim($claims['sid']) : '';

        if ($sid === '') {
            log_message('error', sprintf(
                'SSO callback: tiket untuk FUserID=%s tidak membawa klaim sid — '
                . 'sesi ini TIDAK akan ikut berakhir saat logout di dashboard SSO.',
                (string) $user['FUserID']
            ));
        }

        // Cegah session fixation: naik level privilese (anonim -> terautentikasi)
        // harus memakai session ID baru, sama seperti yang seharusnya di jalur
        // login password.
        session()->regenerate(true);

        // Kunci sesi sama persis dengan Login::setLoggedInSession() supaya
        // seluruh aplikasi (AuthFilter, sidebar, checkMenu) tidak membedakan
        // asal sesi. `sso_login`/`sso_sid` adalah penanda tambahannya: AuthFilter
        // memakai `sso_sid` untuk Single Logout, footer memakai `sso_login`
        // untuk mematikan lock screen, dan Login::logout memakainya untuk
        // memulangkan pengguna ke dashboard SSO.
        session()->set([
            'FUserID'     => $user['FUserID'],
            'FNamaUser'   => $user['FNamaUser'],
            'logged_emkl' => true,
            'sso_login'   => 1,
            'sso_sid'     => $sid !== '' ? $sid : null,
        ]);

        log_message('info', sprintf(
            'SSO callback: login berhasil FUserID=%s (FID=%s, karyawanId=%d) ip=%s',
            (string) $user['FUserID'],
            (string) $user['FID'],
            $karyawanId,
            $this->request->getIPAddress()
        ));

        return redirect()->to(base_url('home'));
    }

    /**
     * Klaim `karyawanId` sebagai int positif, atau null bila absen / bukan
     * angka / tidak positif. Di master karyawan 0 (juga 9999/99999) berarti
     * "tidak punya karyawan"; auth-sso-api sudah menyaringnya sebelum
     * menandatangani tiket, ini lapis kedua.
     *
     * @param array<string, mixed> $claims
     */
    private function klaimKaryawanId(array $claims): ?int
    {
        $nilai = $claims['karyawanId'] ?? null;

        if (is_string($nilai) && ctype_digit(trim($nilai))) {
            $nilai = (int) trim($nilai);
        }

        return is_int($nilai) && $nilai > 0 ? $nilai : null;
    }

    /**
     * Baris FUserList ber-FID `$fid`, HANYA bila FIDKaryawan-nya (kolom
     * sso.matchColumn) sama dengan klaim karyawanId pada tiket yang sama.
     *
     * Pencocokan ulang ini wajib: `sub` yang berasal dari format tiket lama
     * (id internal ssoapi, bukan FID) bisa kebetulan sama dengan FID baris
     * lain yang sama sekali tidak terkait. Baris yang ketemu tapi
     * FIDKaryawan-nya tidak cocok berarti `sub` itu bukan FID yang dimaksud.
     *
     * @return array<string, mixed>|null
     */
    private function resolveByFid(int $fid, int $karyawanId): ?array
    {
        $column = $this->sso->matchColumn;
        $model  = new AuthModel();

        if ($column === '' || ! $model->hasColumn($column)) {
            log_message('error', 'SSO: kolom FUserList.' . $column . ' tidak ada — periksa sso.matchColumn.');

            return null;
        }

        $row = $model->findByFid($fid);

        if ($row === null) {
            return null;
        }

        if ((int) ($row[$column] ?? 0) !== $karyawanId) {
            log_message('error', sprintf(
                'SSO: FID=%d ditemukan (FUserID=%s) tapi %s=%s tidak cocok dengan klaim karyawanId=%d — ditolak.',
                $fid,
                (string) $row['FUserID'],
                $column,
                var_export($row[$column] ?? null, true),
                $karyawanId
            ));

            return null;
        }

        return $row;
    }

    /**
     * Jalur cadangan: satu baris FUserList yang kolom sso.matchColumn-nya sama
     * dengan klaim sso.matchClaim. Nol baris berarti belum dipetakan; lebih
     * dari satu berarti tidak jelas sesi siapa yang harus dibuat. Keduanya
     * ditolak dengan cara yang sama, supaya jawaban endpoint ini tidak bisa
     * dipakai memetakan identitas mana yang terdaftar.
     *
     * @param array<string, mixed> $claims
     *
     * @return array<string, mixed>|null
     */
    private function resolveByClaim(array $claims): ?array
    {
        $claimName = $this->sso->matchClaim;
        $column    = $this->sso->matchColumn;

        if ($claimName === '' || $column === '') {
            log_message('error', 'SSO: sso.matchClaim / sso.matchColumn belum diisi.');

            return null;
        }

        $value = $claims[$claimName] ?? null;

        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || (is_numeric($value) && (float) $value <= 0)) {
            return null;
        }

        $model = new AuthModel();

        if (! $model->hasColumn($column)) {
            log_message('error', 'SSO: kolom FUserList.' . $column . ' tidak ada — periksa sso.matchColumn.');

            return null;
        }

        $rows = $model->findByColumn($column, $value, 2);

        return count($rows) === 1 ? $rows[0] : null;
    }

    /**
     * Mengakhiri percakapan SSO yang gagal: catat alasan teknisnya, kembalikan
     * pengguna ke halaman login dengan penanda yang sudah ditentukan.
     *
     * Penanda dikirim sebagai kode pendek, bukan sebagai pesan bebas, karena
     * view login merender pesannya tanpa escaping — kode dari daftar tertutup
     * (Login::ssoMessage()) membuat parameter URL ini mustahil jadi jalur XSS.
     *
     * Level `error`, bukan `warning`: logger.threshold = 4 di production, jadi
     * warning tidak pernah sampai ke berkas — persis di environment tempat
     * alasan penolakan SSO paling perlu dilacak.
     */
    private function fail(string $code, ?string $logMessage): RedirectResponse
    {
        if ($logMessage !== null) {
            log_message('error', 'SSO ' . $logMessage . ' ip=' . $this->request->getIPAddress());
        }

        return redirect()->to(base_url('login?sso=' . $code));
    }
}
