<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Sso as SsoConfig;
use Throwable;

/**
 * Diagnosa Single Logout.
 *
 * SLO gagal-terbuka: kalau auth-sso-api tidak bisa dihubungi, sesi lokal
 * DIPERTAHANKAN (lihat App\Libraries\SsoSlo). Itu keputusan yang benar — server
 * SSO yang mati tidak boleh melempar keluar seluruh pengguna — tapi akibatnya
 * SLO yang rusak tidak punya gejala sama sekali di layar. Satu-satunya bedanya
 * dengan SLO yang sehat: logout di dashboard tidak terjadi apa-apa.
 *
 * Perintah ini melakukan panggilan introspeksi yang sama persis, lewat service
 * dan konfigurasi yang sama, lalu menampilkan kegagalannya apa adanya.
 *
 *   php spark sso:slo
 *   php spark sso:slo <sid>
 */
class CheckSsoSlo extends BaseCommand
{
    protected $group       = 'Custom';
    protected $name        = 'sso:slo';
    protected $description = 'Menguji rantai Single Logout: konfigurasi, sertifikat TLS, dan endpoint introspeksi.';
    protected $usage       = 'sso:slo [sid]';

    public function run(array $params)
    {
        $sso = config(SsoConfig::class);

        CLI::write('1. Konfigurasi', 'green');
        CLI::write('   sso.apiBaseUrl     = ' . ($sso->apiBaseUrl === '' ? CLI::color('(KOSONG)', 'red') : $sso->apiBaseUrl));
        CLI::write('   sso.sloSecret      = ' . ($sso->sloSecret === ''
            ? CLI::color('(KOSONG)', 'red')
            : '(terisi, ' . strlen($sso->sloSecret) . ' karakter)'));
        CLI::write('   sso.sloPollSeconds = ' . $sso->sloPollSeconds);

        if (trim($sso->apiBaseUrl) === '' || trim($sso->sloSecret) === '') {
            CLI::newLine();
            CLI::error('Salah satu kosong — SsoSlo akan melewati introspeksi sama sekali (fail open).');

            return EXIT_ERROR;
        }

        // Penyebab paling sering di Windows: php.ini web server menunjuk berkas
        // CA yang tidak ada di server ini, sehingga TLS gagal sebelum request
        // sempat terkirim (curl error 77).
        CLI::newLine();
        CLI::write('2. Sertifikat CA untuk HTTPS', 'green');

        $adaMasalahCa = false;

        foreach (['curl.cainfo', 'openssl.cafile'] as $key) {
            $path = (string) ini_get($key);

            if ($path === '') {
                CLI::write('   ' . $key . ' = ' . CLI::color('(tidak diset)', 'yellow')
                    . ' — curl memakai bawaan sistem');

                continue;
            }

            $ok = is_file($path) && is_readable($path);

            if (! $ok) {
                $adaMasalahCa = true;
            }

            CLI::write('   ' . $key . ' = ' . $path);
            CLI::write('      berkasnya ' . ($ok
                ? CLI::color('ada & terbaca', 'green')
                : CLI::color('TIDAK ADA / TIDAK TERBACA', 'red')));
        }

        if ($adaMasalahCa) {
            CLI::newLine();
            CLI::write('Perbaiki path di php.ini YANG DIPAKAI WEB SERVER (belum tentu sama', 'yellow');
            CLI::write('dengan yang dipakai CLI ini), lalu restart web server-nya.', 'yellow');
            CLI::write('php.ini untuk proses ini: ' . (php_ini_loaded_file() ?: '(tidak diketahui)'), 'yellow');
        }

        // Panggilan yang sama persis dengan SsoSlo::introspect().
        $sid = trim((string) ($params[0] ?? '')) ?: 'uji-coba-sid-yang-tidak-ada';

        CLI::newLine();
        CLI::write('3. Panggilan introspeksi', 'green');
        CLI::write('   POST ' . rtrim($sso->apiBaseUrl, '/') . '/auth/session/introspect');
        CLI::write('   sid  = ' . $sid);

        try {
            $response = service('curlrequest', [
                'timeout'         => 5,
                'connect_timeout' => 3,
                'http_errors'     => false,
            ], null, null, false)->post(rtrim($sso->apiBaseUrl, '/') . '/auth/session/introspect', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-slo-secret' => $sso->sloSecret,
                ],
                'json' => ['sid' => $sid],
            ]);
        } catch (Throwable $e) {
            CLI::newLine();
            CLI::error('GAGAL DIHUBUNGI — ' . $e->getMessage());
            CLI::write('Inilah cabang yang membuat SLO diam-diam mati: SsoSlo menganggap', 'yellow');
            CLI::write('sesi masih hidup (fail open), jadi logout di dashboard tak berefek.', 'yellow');

            return EXIT_ERROR;
        }

        $status = $response->getStatusCode();
        $body   = (string) $response->getBody();

        CLI::write('   HTTP ' . $status . ' | ' . substr($body, 0, 120));
        CLI::newLine();

        if ($status === 401) {
            CLI::error('Secret ditolak. sso.sloSecret harus sama persis dengan');
            CLI::write('SLO_INTROSPECT_SECRET di auth-sso-api.', 'yellow');

            return EXIT_ERROR;
        }

        if ($status < 200 || $status >= 300) {
            CLI::error('Status non-2xx — SsoSlo memperlakukannya sebagai fail open.');

            return EXIT_ERROR;
        }

        $decoded = json_decode($body, true);

        if (! is_array($decoded) || ! array_key_exists('active', $decoded)) {
            CLI::error('Jawaban tidak memuat field "active" — diperlakukan fail open.');

            return EXIT_ERROR;
        }

        CLI::write('Rantai SLO SEHAT.', 'green');
        CLI::write('  active = ' . var_export($decoded['active'], true));
        CLI::newLine();
        CLI::write('Ingat: SLO memakai polling, bukan push. Setelah logout di dashboard,', 'yellow');
        CLI::write('sesi lokal berakhir pada permintaan halaman BERIKUTNYA, paling lama', 'yellow');
        CLI::write($sso->sloPollSeconds . ' detik kemudian. Diam di halaman yang sudah terbuka tidak memicunya.', 'yellow');

        return EXIT_SUCCESS;
    }
}
