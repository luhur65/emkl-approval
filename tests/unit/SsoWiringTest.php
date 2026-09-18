<?php

namespace Tests\Unit;

use App\Libraries\SsoExit;
use App\Libraries\SsoSlo;
use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Filters as FiltersConfig;
use Config\Sso as SsoConfig;

/**
 * Sambungan-sambungan yang membuat SSO benar-benar bisa dipakai.
 *
 * Hal-hal di bawah ini gampang lepas tanpa ada yang sadar, dan gejalanya
 * membingungkan: callback SSO yang tidak dikecualikan dari AuthFilter akan
 * memantulkan pengguna ke halaman login persis saat ia hendak login, dan kode
 * aplikasi yang tidak cocok membuat semua tiket ditolak dengan alasan "aud"
 * yang sulit ditebak dari layar.
 *
 * @internal
 */
final class SsoWiringTest extends CIUnitTestCase
{
    private const CALLBACK_ROUTE = 'auth/sso-callback';
    private const START_ROUTE    = 'sso/login';

    protected function tearDown(): void
    {
        Factories::reset('config');
        parent::tearDown();
    }

    /**
     * Dibaca dari sumber Routes.php, bukan dari RouteCollection: koleksi route
     * tidak terisi di konteks CLI tempat test berjalan.
     */
    public function testRouteCallbackDanStartTerdaftarSebagaiGet(): void
    {
        $source = (string) file_get_contents(APPPATH . 'Config/Routes.php');

        // WAJIB get(): cookie sesi SameSite=Lax tidak terkirim pada POST lintas
        // situs, jadi callback berbentuk POST kehilangan sesi yang baru dibuatnya.
        $this->assertMatchesRegularExpression(
            "/\\\$routes->get\(\s*'" . preg_quote(self::CALLBACK_ROUTE, '/') . "'\s*,\s*'SsoAuth::callback'/",
            $source,
            'Route ' . self::CALLBACK_ROUTE . ' -> SsoAuth::callback hilang dari Routes.php (atau bukan GET).'
        );

        $this->assertMatchesRegularExpression(
            "/\\\$routes->get\(\s*'" . preg_quote(self::START_ROUTE, '/') . "'\s*,\s*'SsoAuth::start'/",
            $source,
            'Route ' . self::START_ROUTE . ' -> SsoAuth::start hilang dari Routes.php.'
        );
    }

    /**
     * Callback dipanggil justru saat pengguna BELUM punya sesi. Kalau 'auth'
     * ikut berjalan di situ, pengguna dilempar kembali ke halaman login dan
     * alur SSO tidak akan pernah selesai.
     */
    public function testRouteSsoDikecualikanDariFilterAuth(): void
    {
        $globals = (new FiltersConfig())->globals['before'];

        $this->assertArrayHasKey('auth', $globals, 'Filter auth hilang dari globals.');

        $except = $globals['auth']['except'] ?? [];
        $except = is_array($except) ? $except : [$except];

        $this->assertContains(self::CALLBACK_ROUTE, $except, self::CALLBACK_ROUTE . ' harus dikecualikan dari filter auth.');
        $this->assertContains(self::START_ROUTE, $except, self::START_ROUTE . ' harus dikecualikan dari filter auth.');
    }

    /**
     * Kode aplikasi dipakai sebagai klaim `aud` tiket dan harus per cabang.
     * Nilainya harus sama persis dengan key di TICKET_RELAY_APPS (auth-sso-api)
     * dan `code` kartu di constants/apps.ts (auth-sso).
     */
    public function testAppCodeDanIssuerTerisiSaatSsoAktif(): void
    {
        $config = new SsoConfig();

        if (! $config->enabled) {
            $this->markTestSkipped('sso.enabled = false di .env — tidak ada yang perlu diperiksa.');
        }

        $this->assertMatchesRegularExpression('/^emkl-approval-[a-z]+$/', $config->appCode, 'sso.appCode harus berbentuk emkl-approval-<cabang>.');
        $this->assertSame('auth-sso', $config->issuer, 'sso.issuer harus auth-sso, sesuai SSO_TICKET_ISSUER di auth-sso-api.');
        $this->assertSame('karyawanId', $config->matchClaim, 'FEmail kosong di seluruh FUserList; hanya karyawanId yang bisa dicocokkan.');
        $this->assertSame('FIDKaryawan', $config->matchColumn);
    }

    /**
     * Nonce harus bertahan lebih lama dari tiketnya sendiri. Kalau tidak, ada
     * jendela waktu ketika catatan "sudah dipakai" sudah dibuang tapi tiketnya
     * masih berlaku — dan replay jadi mungkin lagi.
     */
    public function testUmurNonceMelebihiUmurTiketDitambahToleransiJam(): void
    {
        $config = new SsoConfig();

        // TICKET_TTL_SECONDS di auth-sso-api saat ini 60 detik.
        $ticketTtl = 60;

        $this->assertGreaterThan(
            $ticketTtl + $config->leeway,
            $config->nonceTtl,
            'sso.nonceTtl harus lebih besar dari umur tiket + sso.leeway.'
        );
    }

    /**
     * Tanpa alamat API atau secret, introspeksi mustahil dilakukan. Yang tidak
     * boleh terjadi adalah semua orang ikut terlempar keluar karena itu — SLO
     * yang belum dikonfigurasi harus diam, bukan melogout.
     */
    public function testSloTanpaKonfigurasiTidakMelogoutSiapaPun(): void
    {
        $config = new SsoConfig();

        $config->apiBaseUrl = '';
        $config->sloSecret  = '';

        $slo = new SsoSlo($config);

        $this->assertFalse($slo->isConfigured());
        $this->assertTrue($slo->isSessionActive('sid-apa-saja'));
    }

    public function testSloMengabaikanSidKosong(): void
    {
        $this->assertTrue((new SsoSlo(new SsoConfig()))->isSessionActive(''));
    }

    // ── SsoExit: satu jawaban untuk setiap akhir sesi ───────────────────────

    public function testSesiSsoPulangKeDashboardSesiLokalKeHalamanLogin(): void
    {
        $this->injectSso(['enabled' => true, 'dashboardUrl' => 'https://sso.example/dashboard/', 'logoutToSso' => false]);

        $this->assertSame('https://sso.example/dashboard', SsoExit::target(true));
        $this->assertSame(base_url('login'), SsoExit::target(false));
        $this->assertSame(base_url('login?sso=expired'), SsoExit::target(false, 'expired'));
    }

    public function testSaklarLogoutToSsoMengantarSemuaSesiKeDashboard(): void
    {
        $this->injectSso(['enabled' => true, 'dashboardUrl' => 'https://sso.example/dashboard', 'logoutToSso' => true]);

        $this->assertSame('https://sso.example/dashboard', SsoExit::target(false));
        $this->assertSame('https://sso.example/dashboard', SsoExit::target(false, 'expired'));
    }

    public function testSsoYangBelumDikonfigurasiSelaluPulangKeHalamanLogin(): void
    {
        // Alamat kosong hanya menukar halaman login dengan halaman error.
        $this->injectSso(['enabled' => false, 'dashboardUrl' => 'https://sso.example/dashboard', 'logoutToSso' => true]);
        $this->assertSame(base_url('login'), SsoExit::target(true));

        $this->injectSso(['enabled' => true, 'dashboardUrl' => '', 'logoutToSso' => true]);
        $this->assertSame(base_url('login'), SsoExit::target(true));
    }

    // ── View ────────────────────────────────────────────────────────────────

    /**
     * Tombol SSO harus punya warna teks sendiri untuk KEDUA tema. Tanpa itu ia
     * mewarisi .verdant-btn yang mewarnai teks untuk latar terisi, dan pada
     * tombol berlatar transparan tulisannya hilang sama sekali.
     */
    public function testTombolSsoPunyaWarnaSendiriDiLightDanDarkMode(): void
    {
        $view = (string) file_get_contents(APPPATH . 'Views/auth/login.php');

        $this->assertStringContainsString('id="btnSsoLogin"', $view, 'Tombol SSO kehilangan id yang dipakai aturan warnanya.');
        $this->assertMatchesRegularExpression('/#btnSsoLogin\s*\{[^}]*color\s*:/', $view, 'Tidak ada warna teks light mode untuk tombol SSO.');
        $this->assertMatchesRegularExpression('/body\.dark-mode\s+#btnSsoLogin\s*\{[^}]*color\s*:/', $view, 'Tidak ada warna teks dark mode untuk tombol SSO.');
    }

    /**
     * Lock screen membuka kuncinya dengan password FUserList yang tidak
     * dipunyai pengguna SSO — overlay dan script-nya tidak boleh dirender
     * untuk sesi ber-`sso_login`.
     */
    public function testLockScreenDiFooterDipagariPenandaSesiSso(): void
    {
        $footer = (string) file_get_contents(APPPATH . 'Views/partials/footer.php');

        $this->assertMatchesRegularExpression(
            "/\\\$lockEnabled\s*=\s*session\(\)->get\('logged_emkl'\)\s*&&\s*!\s*session\(\)->get\('sso_login'\)/",
            $footer,
            'Penjaga $lockEnabled (logged_emkl && !sso_login) hilang dari footer.'
        );
        $this->assertSame(2, preg_match_all('/<\?php if \(\$lockEnabled\): \?>/', $footer), 'Overlay DAN script lock screen keduanya harus dipagari $lockEnabled.');
        $this->assertStringNotContainsString("<?php if (session()->get('logged_emkl')): ?>", $footer, 'Masih ada blok lock screen yang hanya memeriksa logged_emkl.');
    }

    /**
     * @param array<string, mixed> $values
     */
    private function injectSso(array $values): void
    {
        $config = new SsoConfig();

        foreach ($values as $key => $value) {
            $config->{$key} = $value;
        }

        Factories::injectMock('config', 'Sso', $config);
    }
}
