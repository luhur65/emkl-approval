<?php

namespace Tests\Unit;

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\Mock\MockCache;
use Config\Sso as SsoConfig;

/**
 * Apa yang terjadi saat sesi berakhir SENDIRI — bukan lewat tombol logout —
 * dan bagaimana AuthFilter menjawabnya.
 *
 *   - Sesi sudah tidak ada saat request datang (habis, cookie hilang, belum
 *     pernah login): halaman berpindah ke tujuan SsoExit; AJAX dijawab 401
 *     ber-JSON yang memuat `sessionExpired` + `redirect` (dibaca penangan
 *     global di partials/header.php) tanpa menghilangkan kunci `error`/`msg`
 *     yang dibaca seluruh view approval.
 *   - Sesi SSO-nya dicabut di dashboard (Single Logout): sesi lokal diakhiri
 *     dan pengguna diantar ke halaman login beserta kode `expired`.
 *   - Sesi login lokal dan sesi SSO yang masih hidup tidak diganggu.
 *
 * Status introspeksi SSO ditanam langsung di cache — persis tempat SsoSlo
 * membacanya — jadi tidak ada permintaan jaringan selama test.
 *
 * @internal
 */
final class SsoSessionEndTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const DASHBOARD_URL = 'https://sso.example/dashboard';
    private const ROUTE_TERJAGA = 'uji-sesi';

    private MockCache $cache;

    protected function setUp(): void
    {
        parent::setUp();

        // Hanya filter auth yang diuji; daftar except-nya tidak berpengaruh
        // pada route uji ini.
        $filters                    = config('Filters');
        $filters->globals['before'] = ['auth'];
        $filters->globals['after']  = [];

        $this->mockSession();

        $this->cache = new MockCache();
        $this->cache->initialize();
        \Config\Services::injectMock('cache', $this->cache);

        // Route sendiri: pada kasus "sesi masih hidup" filter meloloskan
        // request, dan controller sungguhan mana pun akan menyentuh database
        // yang tidak ada di lingkungan test.
        $this->withRoutes([['GET', self::ROUTE_TERJAGA, static fn () => 'ok']]);
    }

    protected function tearDown(): void
    {
        Factories::reset('config');
        parent::tearDown();
    }

    // ── Sesi sudah tidak ada saat request datang ────────────────────────────

    public function testSesiKosongDiantarKeHalamanLogin(): void
    {
        $this->bootSso(false);

        $this->withSession([])->get(self::ROUTE_TERJAGA)->assertRedirectTo(base_url('login'));
    }

    public function testSesiKosongDiantarKeSsoSaatSaklarLogoutToSsoMenyala(): void
    {
        $this->bootSso(true);

        $this->withSession([])->get(self::ROUTE_TERJAGA)->assertRedirectTo(self::DASHBOARD_URL);
    }

    public function testJawabanAjaxUntukSesiKosongMembawaPenandaDanTujuan(): void
    {
        $this->bootSso(false);

        $body = $this->jawabanAjax();

        // Kunci lama yang dibaca pesanGagalAjax() di setiap view approval.
        $this->assertArrayHasKey('error', $body);
        $this->assertArrayHasKey('msg', $body);
        // Kunci baru yang dibaca penangan global di partials/header.php.
        $this->assertTrue($body['sessionExpired'] ?? false, 'Penanda sessionExpired hilang — penangan global tidak akan mengenalinya.');
        $this->assertSame(base_url('login'), $body['redirect'] ?? '');
    }

    public function testJawabanAjaxMengikutiSaklarLogoutToSso(): void
    {
        $this->bootSso(true);

        $this->assertSame(self::DASHBOARD_URL, $this->jawabanAjax()['redirect'] ?? '');
    }

    // ── Sesi yang masih hidup ───────────────────────────────────────────────

    public function testSesiLoginLokalDiloloskanTanpaMenyentuhSlo(): void
    {
        $this->bootSso(false);

        $result = $this->withSession(['FUserID' => 'citra', 'logged_emkl' => true])->get(self::ROUTE_TERJAGA);

        $result->assertOK();
        $result->assertSee('ok');
    }

    public function testSesiSsoYangMasihHidupTidakDiganggu(): void
    {
        $this->bootSso(false);

        $this->sesiSso(true)->get(self::ROUTE_TERJAGA)->assertOK();
    }

    // ── Single Logout ───────────────────────────────────────────────────────

    public function testSesiSsoYangDicabutDiakhiriDanDiantarKeLoginDenganKodeExpired(): void
    {
        $this->bootSso(false);

        $this->sesiSso(false)->get(self::ROUTE_TERJAGA)->assertRedirectTo(base_url('login?sso=expired'));
    }

    public function testSesiSsoYangDicabutDiantarKeSsoSaatSaklarMenyala(): void
    {
        $this->bootSso(true);

        $this->sesiSso(false)->get(self::ROUTE_TERJAGA)->assertRedirectTo(self::DASHBOARD_URL);
    }

    public function testJawabanAjaxUntukSesiSsoYangDicabutMembawaKodeExpired(): void
    {
        $this->bootSso(false);

        $result = $this->sesiSso(false)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(self::ROUTE_TERJAGA);

        $this->assertSame(401, $result->response()->getStatusCode());
        $this->assertSame(base_url('login?sso=expired'), $this->json($result)['redirect'] ?? '');
    }

    public function testSloYangBelumDikonfigurasiTidakMelogoutSesiSso(): void
    {
        // Fail open: tanpa apiBaseUrl/sloSecret introspeksi mustahil, dan itu
        // tidak boleh melempar keluar semua pengguna SSO.
        $this->bootSso(false, ['apiBaseUrl' => '', 'sloSecret' => '']);

        $this->sesiSso(false)->get(self::ROUTE_TERJAGA)->assertOK();
    }

    // ── Alat bantu ──────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $overrides
     */
    private function bootSso(bool $logoutToSso, array $overrides = []): void
    {
        $sso               = new SsoConfig();
        $sso->enabled      = true;
        $sso->appCode      = 'emkl-approval-surabaya';
        $sso->dashboardUrl = self::DASHBOARD_URL;
        $sso->logoutToSso  = $logoutToSso;
        // Harus terisi keduanya, kalau tidak SsoSlo menganggap SLO belum
        // dikonfigurasi dan membiarkan setiap sesi hidup (fail open).
        $sso->apiBaseUrl = 'https://ssoapi.example';
        $sso->sloSecret  = 'rahasia-test';

        foreach ($overrides as $key => $value) {
            $sso->{$key} = $value;
        }

        Factories::injectMock('config', 'Sso', $sso);
    }

    /**
     * Sesi yang lahir dari SSO, dengan status introspeksi yang sudah ditanam di
     * cache — persis tempat SsoSlo membacanya.
     */
    private function sesiSso(bool $masihHidup): self
    {
        $sid = 'sid-uji-coba';

        $this->cache->save('sso_slo_' . hash('sha256', $sid), $masihHidup ? 1 : 0, 60);

        return $this->withSession([
            'FUserID'     => 'citra',
            'FNamaUser'   => 'Citra Indrawati',
            'logged_emkl' => true,
            'sso_login'   => 1,
            'sso_sid'     => $sid,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function jawabanAjax(): array
    {
        $result = $this->withSession([])
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(self::ROUTE_TERJAGA);

        $this->assertSame(401, $result->response()->getStatusCode());

        return $this->json($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(\CodeIgniter\Test\TestResponse $result): array
    {
        $body  = (string) $result->getBody();
        $start = strpos($body, '{');
        $end   = strrpos($body, '}');
        $json  = $start !== false && $end !== false ? substr($body, $start, $end - $start + 1) : $body;

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
