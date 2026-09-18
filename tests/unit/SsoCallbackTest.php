<?php

namespace Tests\Unit;

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\Mock\MockSession;
use Config\Sso as SsoConfig;

/**
 * Penukaran tiket SSO menjadi sesi, lewat request HTTP sungguhan ke
 * auth/sso-callback — filter, route, controller, model, dan sesi bekerja
 * bersama, bukan satu per satu.
 *
 * Tabel FUserList dibuat di grup database `tests` (SQLite in-memory) dengan
 * bentuk yang sama seperti di SQL Server untuk kolom yang dipakai SSO, jadi
 * jalur sukses ikut teruji tanpa menyentuh database sungguhan. Tiket
 * ditandatangani dengan kunci khusus test (tests/_support/sso).
 *
 * @internal
 */
final class SsoCallbackTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const APP_CODE = 'emkl-approval-surabaya';
    private const CALLBACK = 'auth/sso-callback';

    private string $privateKeyPem;
    private MockSession $mockSession;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('sqlite3')) {
            $this->markTestSkipped('Butuh ekstensi sqlite3 untuk grup database tests.');
        }

        $this->privateKeyPem = (string) file_get_contents(SUPPORTPATH . 'sso/test-only-private.pem');

        // csrf dibuang (tidak berlaku untuk GET, tapi menghalangi POST di test
        // lain); filter auth dipertahankan APA ADANYA beserta daftar except-nya —
        // callback yang tidak dikecualikan adalah salah satu bug yang dicari.
        $filters                    = config('Filters');
        $filters->globals['before'] = ['auth' => $filters->globals['before']['auth']];
        $filters->globals['after']  = [];

        // Session bawaan mengosongkan $_SESSION saat start() di lingkungan test.
        // MockSession tidak, dan mencatat regenerate() tanpa menyentuh PHP session.
        $this->mockSession();
        $this->mockSession = service('session');

        $this->bootSso();
        $this->siapkanFUserList();
    }

    protected function tearDown(): void
    {
        Factories::reset('config');
        parent::tearDown();
    }

    // ── Jalur sukses ────────────────────────────────────────────────────────

    public function testTiketSahMembuatSesiSepertiLoginBiasa(): void
    {
        $result = $this->withSession([])->get(self::CALLBACK, ['ticket' => $this->sign($this->claims())]);

        $result->assertRedirectTo(base_url('home'));

        $this->assertSame('citra', $_SESSION['FUserID'] ?? null, 'Sesi harus milik baris FID=32 (citra).');
        $this->assertSame('Citra Indrawati', $_SESSION['FNamaUser'] ?? null);
        $this->assertTrue($_SESSION['logged_emkl'] ?? false, 'Penanda logged_emkl (dibaca AuthFilter & checkMenu) hilang.');
        $this->assertSame(1, $_SESSION['sso_login'] ?? null, 'Penanda sso_login (mematikan lock screen, tujuan logout) hilang.');
        $this->assertSame('sesi-sso-1', $_SESSION['sso_sid'] ?? null, 'sso_sid hilang — Single Logout tidak akan menjangkau sesi ini.');
        $this->assertTrue($this->mockSession->didRegenerate, 'Session ID harus diregenerasi sebelum penanda login ditulis (session fixation).');
    }

    public function testJalurCadanganMencocokkanFidKaryawanSaatSubBukanFid(): void
    {
        // Tiket format lama: `sub` bukan FID. Dicari lewat sso.matchClaim ->
        // sso.matchColumn dan harus tepat satu baris.
        $result = $this->withSession([])->get(self::CALLBACK, ['ticket' => $this->sign($this->claims(['sub' => 'akun-sso-152']))]);

        $result->assertRedirectTo(base_url('home'));
        $this->assertSame('citra', $_SESSION['FUserID'] ?? null);
    }

    // ── Penolakan ───────────────────────────────────────────────────────────

    public function testSsoNonaktifMenolakSemuaTiket(): void
    {
        $this->bootSso(['enabled' => false]);

        $result = $this->withSession([])->get(self::CALLBACK, ['ticket' => $this->sign($this->claims())]);

        $result->assertRedirectTo(base_url('login?sso=disabled'));
        $this->assertArrayNotHasKey('logged_emkl', $_SESSION);
    }

    public function testTanpaParameterTiketDitolak(): void
    {
        $this->withSession([])->get(self::CALLBACK)->assertRedirectTo(base_url('login?sso=invalid'));
    }

    public function testTiketDariKunciLainDitolak(): void
    {
        $lain   = (string) file_get_contents(SUPPORTPATH . 'sso/test-only-other-private.pem');
        $result = $this->withSession([])->get(self::CALLBACK, ['ticket' => $this->sign($this->claims(), $lain)]);

        $result->assertRedirectTo(base_url('login?sso=invalid'));
        $this->assertArrayNotHasKey('logged_emkl', $_SESSION);
    }

    public function testTiketCabangLainDitolak(): void
    {
        $result = $this->withSession([])->get(self::CALLBACK, ['ticket' => $this->sign($this->claims(['aud' => 'emkl-approval-medan']))]);

        $result->assertRedirectTo(base_url('login?sso=invalid'));
    }

    public function testTiketYangSamaTidakBisaDipakaiDuaKali(): void
    {
        $ticket = $this->sign($this->claims());

        $this->withSession([])->get(self::CALLBACK, ['ticket' => $ticket])->assertRedirectTo(base_url('home'));

        // Tombol back + muat ulang url callback yang sama.
        $result = $this->withSession([])->get(self::CALLBACK, ['ticket' => $ticket]);

        $result->assertRedirectTo(base_url('login?sso=replay'));
        $this->assertArrayNotHasKey('logged_emkl', $_SESSION);
    }

    public function testSubYangFidKaryawanNyaTidakCocokDitolak(): void
    {
        // `sub` menunjuk FID=32 (citra, FIDKaryawan 332) tapi tiketnya milik
        // karyawan lain: kemungkinan `sub` dari format lama yang kebetulan sama
        // dengan sebuah FID. Wajib ditolak, bukan mendarat di akun orang lain.
        $result = $this->withSession([])->get(self::CALLBACK, ['ticket' => $this->sign($this->claims(['karyawanId' => 5091]))]);

        $result->assertRedirectTo(base_url('login?sso=account'));
        $this->assertArrayNotHasKey('logged_emkl', $_SESSION);
    }

    public function testSubYangTidakAdaDiFUserListDitolak(): void
    {
        $this->withSession([])
            ->get(self::CALLBACK, ['ticket' => $this->sign($this->claims(['sub' => '9999']))])
            ->assertRedirectTo(base_url('login?sso=account'));
    }

    public function testSubYangMenunjukUserBelumDipetakanDitolak(): void
    {
        // FID=122 (ryan) FIDKaryawan-nya masih NULL: tiket apa pun yang
        // menunjuk ke sana ditolak sampai barisnya dipetakan (php spark sso:map).
        $this->withSession([])
            ->get(self::CALLBACK, ['ticket' => $this->sign($this->claims(['sub' => '122']))])
            ->assertRedirectTo(base_url('login?sso=account'));
    }

    public function testTiketTanpaKaryawanIdDitolakDenganKodeNoclaim(): void
    {
        // Beda pemilik masalah dari `unknown`/`account`: yang harus bertindak
        // admin SSO (akun SSO belum terhubung ke master karyawan), bukan admin
        // aplikasi ini.
        $claims = $this->claims();
        unset($claims['karyawanId']);

        $this->withSession([])
            ->get(self::CALLBACK, ['ticket' => $this->sign($claims)])
            ->assertRedirectTo(base_url('login?sso=noclaim'));
    }

    public function testKaryawanIdNolDitolakSebagaiIdentitas(): void
    {
        // Di master karyawan, 0 berarti "tidak punya karyawan".
        $this->withSession([])
            ->get(self::CALLBACK, ['ticket' => $this->sign($this->claims(['karyawanId' => 0]))])
            ->assertRedirectTo(base_url('login?sso=noclaim'));
    }

    public function testTiketPanelCastingDitolak(): void
    {
        $this->withSession([])
            ->get(self::CALLBACK, ['ticket' => $this->sign($this->claims(['impersonated' => true]))])
            ->assertRedirectTo(base_url('login?sso=casting'));
        $this->assertArrayNotHasKey('logged_emkl', $_SESSION);
    }

    public function testJalurCadanganMenolakKaryawanYangTidakDipetakan(): void
    {
        $this->withSession([])
            ->get(self::CALLBACK, ['ticket' => $this->sign($this->claims(['sub' => 'akun-sso-x', 'karyawanId' => 7777]))])
            ->assertRedirectTo(base_url('login?sso=unknown'));
    }

    // ── Halaman login sebagai tempat pendaratan kegagalan ───────────────────

    public function testHalamanLoginMenerjemahkanKodeKegagalanDariDaftarTertutup(): void
    {
        $result = $this->withSession([])->get('login?sso=replay');

        $result->assertOK();
        $result->assertSee('Tiket SSO sudah pernah dipakai');
        $result->assertSee('Masuk dengan SSO');
    }

    public function testKodeDiLuarDaftarDiabaikanBukanDirender(): void
    {
        // Parameter ?sso= datang dari URL dan view merender $error tanpa
        // escaping: hanya KODE dari daftar tertutup yang boleh jadi teks.
        $result = $this->withSession([])->get('login?sso=%3Cscript%3Ealert(1)%3C%2Fscript%3E');

        $result->assertOK();
        $result->assertDontSee('<script>alert(1)</script>', 'body');
    }

    public function testModeSsoOnlyMenolakPostLoginDiServer(): void
    {
        $this->bootSso(['passwordLoginEnabled' => false]);

        $result = $this->withSession([])->post('login', ['pUser' => 'citra', 'pPassword' => 'rahasia']);

        $result->assertRedirectTo(base_url('login?sso=onlysso'));
        $this->assertArrayNotHasKey('logged_emkl', $_SESSION);
    }

    public function testModeSsoOnlyMengalihkanHalamanLoginKeSso(): void
    {
        $this->bootSso(['passwordLoginEnabled' => false, 'launchUrl' => 'https://sso.example/launch/' . self::APP_CODE]);

        $this->withSession([])->get('login')->assertRedirectTo('https://sso.example/launch/' . self::APP_CODE);

        // KECUALI saat membawa pesan kegagalan — satu-satunya tempat pesan itu
        // bisa dibaca; kalau ikut dialihkan, user berputar tanpa tahu sebabnya.
        $result = $this->withSession([])->get('login?sso=unknown');

        $result->assertOK();
        $result->assertSee('belum terhubung ke EMKL Approval');
    }

    public function testModeSsoOnlyMenolakUnlockDenganPenandaSsoOnly(): void
    {
        $this->bootSso(['passwordLoginEnabled' => false]);

        $result = $this->withSession(['FUserID' => 'citra', 'logged_emkl' => true])
            ->post('login/unlock', ['password' => 'rahasia']);

        $this->assertSame(403, $result->response()->getStatusCode());
        $this->assertTrue($this->json($result)['ssoOnly'] ?? false);
    }

    // ── Alat bantu ──────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $overrides
     */
    private function bootSso(array $overrides = []): void
    {
        $sso = new SsoConfig();

        $sso->enabled         = true;
        $sso->appCode         = self::APP_CODE;
        $sso->issuer          = 'auth-sso';
        $sso->ticketPublicKey = (string) file_get_contents(SUPPORTPATH . 'sso/test-only-public.pem');
        $sso->leeway          = 60;
        $sso->nonceTtl        = 300;
        $sso->dashboardUrl    = 'https://sso.example/dashboard';
        $sso->launchUrl       = '';
        $sso->matchClaim      = 'karyawanId';
        $sso->matchColumn     = 'FIDKaryawan';

        foreach ($overrides as $key => $value) {
            $sso->{$key} = $value;
        }

        Factories::injectMock('config', 'Sso', $sso);
    }

    /**
     * FUserList tiruan di SQLite: kolom yang dipakai jalur login & SSO saja.
     * Dua baris: citra sudah dipetakan (FIDKaryawan 332), ryan belum (NULL).
     */
    private function siapkanFUserList(): void
    {
        $db    = \Config\Database::connect();
        $forge = \Config\Database::forge();

        $forge->dropTable('FUserList', true);
        $forge->addField([
            'FID'         => ['type' => 'INTEGER'],
            'FUserID'     => ['type' => 'TEXT'],
            'FNamaUser'   => ['type' => 'TEXT', 'null' => true],
            'FKode'       => ['type' => 'TEXT', 'null' => true],
            'FIDKaryawan' => ['type' => 'INTEGER', 'null' => true],
        ]);
        $forge->addKey('FID', true);
        $forge->createTable('FUserList');

        $db->table('FUserList')->insertBatch([
            ['FID' => 32, 'FUserID' => 'citra', 'FNamaUser' => 'Citra Indrawati', 'FKode' => md5('rahasia'), 'FIDKaryawan' => 332],
            ['FID' => 122, 'FUserID' => 'ryan', 'FNamaUser' => 'Ryan', 'FKode' => md5('rahasia'), 'FIDKaryawan' => null],
            ['FID' => 139, 'FUserID' => 'varyan', 'FNamaUser' => 'Varyan Aghni', 'FKode' => md5('rahasia'), 'FIDKaryawan' => 5091],
        ]);
    }

    /**
     * Klaim yang bentuknya sama dengan terbitan auth-sso-api: `sub` = FID baris
     * FUserList yang dipilih, `karyawanId` = id master karyawan.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function claims(array $overrides = []): array
    {
        return $overrides + [
            'name'       => 'Citra Indrawati',
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
    private function sign(array $claims, ?string $privateKey = null): string
    {
        $encode  = static fn (string $data): string => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        $header  = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $encode(json_encode($claims));

        $signature = '';
        openssl_sign($header . '.' . $payload, $signature, $privateKey ?? $this->privateKeyPem, OPENSSL_ALGO_SHA256);

        return $header . '.' . $payload . '.' . $encode($signature);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(\CodeIgniter\Test\TestResponse $result): array
    {
        // FeatureTestTrait bisa membungkus body; JSON-nya diambil dari antara
        // kurung kurawal terluar.
        $body  = (string) $result->getBody();
        $start = strpos($body, '{');
        $end   = strrpos($body, '}');
        $json  = $start !== false && $end !== false ? substr($body, $start, $end - $start + 1) : $body;

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
