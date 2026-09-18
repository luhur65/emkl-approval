<?php

namespace App\Libraries;

use Config\Sso as SsoConfig;
use Throwable;

/**
 * Klien Single Logout (SLO).
 *
 * Setiap tiket SSO membawa klaim `sid` — identitas sesi SSO yang melahirkannya.
 * Aplikasi ini menyimpan `sid` itu di sesinya sendiri lalu menanyakan kabarnya
 * secara berkala ke auth-sso-api. Begitu sesi SSO dicabut (pengguna menekan
 * logout di dashboard SSO), jawabannya berubah jadi `active:false` dan sesi
 * lokal di sini ikut diakhiri. Tanpa ini, logout di dashboard hanya menutup
 * dashboard — aplikasi ini tetap terbuka dengan sesinya sendiri.
 *
 * Endpointnya dijaga secret bersama (`x-slo-secret`), karena `sid` saja bukan
 * kredensial: tanpa penjagaan itu, siapa pun yang pernah melihat sebuah `sid`
 * bisa menguji atau mencabut sesi orang lain.
 *
 * Kebijakan kegagalan:
 *  - auth-sso-api menjawab `active:false`  -> sesi lokal diakhiri (fail closed).
 *  - auth-sso-api tidak bisa dihubungi     -> sesi lokal dipertahankan
 *    (fail open). Server SSO yang sedang mati tidak boleh membuat seluruh
 *    pengguna terlempar keluar; kalau tidak, satu gangguan jaringan berubah
 *    jadi pemadaman untuk semua aplikasi sekaligus.
 *
 * Konsekuensinya: SLO yang rusak tidak punya gejala apa pun di layar.
 * Periksa dengan `php spark sso:slo`.
 */
class SsoSlo
{
    /** Awalan kunci cache hasil introspeksi. */
    private const CACHE_PREFIX = 'sso_slo_';

    private SsoConfig $config;

    public function __construct(?SsoConfig $config = null)
    {
        $this->config = $config ?? config(SsoConfig::class);
    }

    /**
     * Apakah sesi SSO `$sid` masih hidup?
     *
     * Hasilnya disimpan di cache selama sso.sloPollSeconds, jadi biaya SLO
     * adalah satu permintaan per rentang itu — bukan satu per halaman.
     */
    public function isSessionActive(string $sid): bool
    {
        if (trim($sid) === '') {
            return true;
        }

        if (! $this->isConfigured()) {
            // Tanpa alamat API atau secret, introspeksi mustahil dilakukan.
            // Diperlakukan sama seperti API tidak bisa dihubungi: fail open,
            // tapi dicatat supaya salah konfigurasi tidak diam-diam berlalu.
            log_message('error', 'SSO: sso.apiBaseUrl / sso.sloSecret belum diisi, Single Logout tidak aktif.');

            return true;
        }

        $key    = self::CACHE_PREFIX . hash('sha256', $sid);
        $cached = cache($key);

        if ($cached !== null) {
            return (bool) $cached;
        }

        $active = $this->introspect($sid);

        // Jawaban negatif ikut di-cache: sekali sesi SSO dicabut ia tidak akan
        // hidup lagi, jadi menanyakannya berulang kali hanya menambah beban.
        cache()->save($key, $active ? 1 : 0, max(5, $this->config->sloPollSeconds));

        return $active;
    }

    /** Membuang hasil cache untuk `$sid` — dipakai saat sesi lokal diakhiri. */
    public function forget(string $sid): void
    {
        if (trim($sid) !== '') {
            cache()->delete(self::CACHE_PREFIX . hash('sha256', $sid));
        }
    }

    public function isConfigured(): bool
    {
        return trim($this->config->apiBaseUrl) !== '' && trim($this->config->sloSecret) !== '';
    }

    private function introspect(string $sid): bool
    {
        $url = rtrim($this->config->apiBaseUrl, '/') . '/auth/session/introspect';

        try {
            // Panggilan ini terjadi di dalam filter, jadi batas waktunya harus
            // pendek: server SSO yang menggantung tidak boleh ikut menggantungkan
            // setiap halaman. Hasilnya di-cache, jadi paling banyak satu jeda
            // seperti ini per sso.sloPollSeconds.
            $response = service('curlrequest', [
                'timeout'         => 3,
                'connect_timeout' => 2,
                // Status non-2xx dikembalikan sebagai respons biasa, bukan
                // exception, supaya dibedakan dari kegagalan transport di bawah.
                'http_errors'     => false,
            ], null, null, false)->post($url, [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'x-slo-secret'  => $this->config->sloSecret,
                ],
                'json' => ['sid' => $sid],
            ]);
        } catch (Throwable $e) {
            log_message('error', 'SSO: introspeksi sesi gagal dihubungi — ' . $e->getMessage());

            return true;
        }

        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            // 401 di sini berarti secret salah, bukan sesi mati. Melogout semua
            // orang karena salah konfigurasi jauh lebih merusak daripada
            // membiarkan sesi berjalan sampai kedaluwarsa sendiri.
            log_message('error', 'SSO: introspeksi sesi menjawab HTTP ' . $status . '.');

            return true;
        }

        $body = json_decode((string) $response->getBody(), true);

        if (! is_array($body) || ! array_key_exists('active', $body)) {
            log_message('error', 'SSO: jawaban introspeksi sesi tidak memuat field active.');

            return true;
        }

        return (bool) $body['active'];
    }
}
