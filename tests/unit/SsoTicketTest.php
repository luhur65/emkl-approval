<?php

namespace Tests\Unit;

use App\Libraries\SsoTicket;
use App\Libraries\SsoTicketException;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Sso as SsoConfig;

/**
 * Verifikasi tiket SSO (JWT RS256).
 *
 * Test ini memakai pasangan kunci khusus test dari tests/_support/sso (lihat
 * README di sana), jadi tidak pernah bergantung pada kunci produksi — kecuali
 * satu test terakhir, yang justru memastikan kunci di .env masih bisa dibaca
 * OpenSSL (menangkap kasus nilai yang rusak saat disalin).
 *
 * @internal
 */
final class SsoTicketTest extends CIUnitTestCase
{
    private const APP_CODE = 'emkl-approval-surabaya';

    private string $privateKeyPem;
    private string $publicKeyPem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->privateKeyPem = $this->fixture('test-only-private.pem');
        $this->publicKeyPem  = $this->fixture('test-only-public.pem');
    }

    private function fixture(string $name): string
    {
        $path = SUPPORTPATH . 'sso' . DIRECTORY_SEPARATOR . $name;

        $this->assertFileExists($path, 'Fixture kunci test hilang: ' . $name);

        return (string) file_get_contents($path);
    }

    // ── Jalur normal ────────────────────────────────────────────────────────

    public function testTiketSahMengembalikanKlaimnya(): void
    {
        $claims = $this->verifier()->verify($this->sign($this->claims()));

        $this->assertSame('32', $claims['sub']);
        $this->assertSame(332, $claims['karyawanId']);
        $this->assertSame('sesi-sso-1', $claims['sid']);
    }

    public function testPublicKeyBolehDitulisSebagaiBase64SatuBarisDenganEscapeNewline(): void
    {
        // Format yang dipakai auth-sso-api dan sys-modern di .env mereka: badan
        // base64 tanpa header PEM, newline sebagai dua karakter backslash-n.
        $body    = preg_replace('/-----[A-Z ]+-----|\s+/', '', $this->publicKeyPem) ?? '';
        $escaped = implode('\n', str_split($body, 64));

        $config                  = $this->config();
        $config->ticketPublicKey = $escaped;

        $claims = (new SsoTicket($config))->verify($this->sign($this->claims()));

        $this->assertSame('32', $claims['sub']);
    }

    public function testAudienceBolehBerupaDaftar(): void
    {
        $claims = $this->verifier()->verify($this->sign($this->claims(['aud' => ['hr', self::APP_CODE]])));

        $this->assertSame('32', $claims['sub']);
    }

    // ── Penolakan yang menahan serangan ─────────────────────────────────────

    public function testTandaTanganYangDiubahDitolak(): void
    {
        $ticket = $this->sign($this->claims());

        [$header, $payload, $signature] = explode('.', $ticket);

        // Ganti payload dengan yang mengaku sebagai orang lain, tanda tangan
        // lama dibiarkan — persis yang dilakukan penyerang yang memegang tiket
        // sah milik dirinya sendiri.
        $forged = $this->base64UrlEncode(json_encode(
            ['sub' => '1', 'karyawanId' => 1] + $this->claims()
        ));

        $this->expectException(SsoTicketException::class);
        $this->verifier()->verify($header . '.' . $forged . '.' . $signature);
    }

    public function testTiketDariKunciLainDitolak(): void
    {
        $ticket = $this->sign($this->claims(), 'RS256', $this->fixture('test-only-other-private.pem'));

        $this->expectException(SsoTicketException::class);
        $this->verifier()->verify($ticket);
    }

    public function testAlgNoneDitolak(): void
    {
        $header  = $this->base64UrlEncode(json_encode(['alg' => 'none', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode($this->claims()));

        $this->expectException(SsoTicketException::class);
        $this->expectExceptionMessageMatches('/Algoritma/');
        $this->verifier()->verify($header . '.' . $payload . '.');
    }

    public function testTiketHs256YangDitandatanganiMemakaiPublicKeyDitolak(): void
    {
        // Serangan algorithm confusion: public key itu terbuka, jadi siapa pun
        // bisa memakainya sebagai secret HMAC. Yang menahannya adalah patokan
        // alg ke RS256, bukan kerahasiaan kunci.
        $header  = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode($this->claims()));
        $sig     = $this->base64UrlEncode(hash_hmac('sha256', $header . '.' . $payload, $this->publicKeyPem, true));

        $this->expectException(SsoTicketException::class);
        $this->expectExceptionMessageMatches('/Algoritma/');
        $this->verifier()->verify($header . '.' . $payload . '.' . $sig);
    }

    public function testTiketUntukCabangLainDitolak(): void
    {
        // Tiket sah, tanda tangan benar — tapi diterbitkan untuk cabang Medan.
        // Inilah pemeriksaan `aud` yang membuat kode aplikasi harus per cabang.
        $ticket = $this->sign($this->claims(['aud' => 'emkl-approval-medan']));

        $this->expectException(SsoTicketException::class);
        $this->expectExceptionMessageMatches('/aud/');
        $this->verifier()->verify($ticket);
    }

    public function testIssuerYangSalahDitolak(): void
    {
        $ticket = $this->sign($this->claims(['iss' => 'penerbit-lain']));

        $this->expectException(SsoTicketException::class);
        $this->expectExceptionMessageMatches('/iss/');
        $this->verifier()->verify($ticket);
    }

    public function testTiketKedaluwarsaDitolak(): void
    {
        $config         = $this->config();
        $config->leeway = 0;

        $ticket = $this->sign($this->claims(['exp' => time() - 1]));

        $this->expectException(SsoTicketException::class);
        $this->expectExceptionMessageMatches('/kedaluwarsa/');
        (new SsoTicket($config))->verify($ticket);
    }

    public function testTiketTanpaExpDitolak(): void
    {
        $claims = $this->claims();
        unset($claims['exp']);

        $this->expectException(SsoTicketException::class);
        $this->expectExceptionMessageMatches('/exp/');
        $this->verifier()->verify($this->sign($claims));
    }

    public function testTiketTanpaJtiDitolak(): void
    {
        // Tanpa jti tidak ada yang bisa dibakar SsoNonceStore, artinya tiket
        // bisa ditukar berkali-kali.
        $claims = $this->claims();
        unset($claims['jti']);

        $this->expectException(SsoTicketException::class);
        $this->expectExceptionMessageMatches('/jti/');
        $this->verifier()->verify($this->sign($claims));
    }

    public function testTiketTanpaSubDitolak(): void
    {
        $claims = $this->claims();
        unset($claims['sub']);

        $this->expectException(SsoTicketException::class);
        $this->expectExceptionMessageMatches('/sub/');
        $this->verifier()->verify($this->sign($claims));
    }

    public function testBentukSelainTigaBagianDitolak(): void
    {
        $this->expectException(SsoTicketException::class);
        $this->verifier()->verify('bukan-jwt');
    }

    public function testPublicKeyKosongDitolak(): void
    {
        $config                  = $this->config();
        $config->ticketPublicKey = '';

        $this->expectException(SsoTicketException::class);
        (new SsoTicket($config))->verify($this->sign($this->claims()));
    }

    // ── Konfigurasi nyata ───────────────────────────────────────────────────

    public function testPublicKeyDiEnvBisaDibacaOpensslDanBerukuranMinimal2048Bit(): void
    {
        $configured = trim((new SsoConfig())->ticketPublicKey);

        if ($configured === '') {
            $this->markTestSkipped('sso.ticketPublicKey belum diisi di .env.');
        }

        $normalised = str_replace(['\r\n', '\n', '\r'], "\n", $configured);

        if (! str_contains($normalised, '-----BEGIN')) {
            $body       = preg_replace('/\s+/', '', $normalised) ?? '';
            $normalised = "-----BEGIN PUBLIC KEY-----\n" . chunk_split($body, 64, "\n") . "-----END PUBLIC KEY-----\n";
        }

        $key = openssl_pkey_get_public($normalised);

        $this->assertNotFalse($key, 'sso.ticketPublicKey di .env tidak bisa dibaca OpenSSL — kemungkinan rusak saat disalin.');

        $details = openssl_pkey_get_details($key);

        $this->assertSame(OPENSSL_KEYTYPE_RSA, $details['type'], 'sso.ticketPublicKey bukan kunci RSA.');
        $this->assertGreaterThanOrEqual(2048, $details['bits'], 'Public key SSO lebih pendek dari 2048 bit.');
    }

    // ── Perkakas ────────────────────────────────────────────────────────────

    private function config(): SsoConfig
    {
        $config = new SsoConfig();

        $config->enabled         = true;
        $config->appCode         = self::APP_CODE;
        $config->issuer          = 'auth-sso';
        $config->ticketPublicKey = $this->publicKeyPem;
        $config->leeway          = 60;

        return $config;
    }

    private function verifier(): SsoTicket
    {
        return new SsoTicket($this->config());
    }

    /**
     * Klaim yang bentuknya sama dengan terbitan auth-sso-api generateTicket():
     * `sub` = FID baris FUserList yang dipilih, `karyawanId` = id master
     * karyawan, `sid` = sesi SSO untuk Single Logout.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function claims(array $overrides = []): array
    {
        return $overrides + [
            'email'      => 'citra@transporindo.com',
            'name'       => 'Citra',
            'karyawanId' => 332,
            'sid'        => 'sesi-sso-1',
            'iat'        => time(),
            'exp'        => time() + 45,
            'sub'        => '32',
            'aud'        => self::APP_CODE,
            'iss'        => 'auth-sso',
            'jti'        => bin2hex(random_bytes(16)),
        ];
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function sign(array $claims, string $alg = 'RS256', ?string $privateKey = null): string
    {
        $header  = $this->base64UrlEncode(json_encode(['alg' => $alg, 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode($claims));

        $signature = '';
        openssl_sign($header . '.' . $payload, $signature, $privateKey ?? $this->privateKeyPem, OPENSSL_ALGO_SHA256);

        return $header . '.' . $payload . '.' . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
