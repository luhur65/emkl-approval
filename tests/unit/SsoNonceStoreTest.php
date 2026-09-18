<?php

namespace Tests\Unit;

use App\Libraries\SsoNonceStore;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Sifat sekali-pakai tiket SSO.
 *
 * Tiket adalah kredensial pembawa yang lewat di query string, jadi ia mudah
 * tertinggal di history browser dan log proxy. Yang membuat jejak itu tidak
 * berbahaya adalah jaminan bahwa tiket yang sudah ditukar tidak bisa ditukar
 * lagi — itulah yang diuji di sini.
 *
 * @internal
 */
final class SsoNonceStoreTest extends CIUnitTestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = WRITEPATH . 'sso_nonce_test' . DIRECTORY_SEPARATOR;
        $this->removeStore();
    }

    protected function tearDown(): void
    {
        $this->removeStore();
        parent::tearDown();
    }

    public function testPenukaranPertamaBerhasilDanPenukaranKeduaDitolak(): void
    {
        $store = new SsoNonceStore($this->path);
        $jti   = bin2hex(random_bytes(16));

        $this->assertTrue($store->burn($jti, 300), 'Penukaran pertama seharusnya diterima.');
        $this->assertFalse($store->burn($jti, 300), 'Replay tiket yang sama seharusnya ditolak.');
    }

    public function testJtiBerbedaTidakSalingMengganggu(): void
    {
        $store = new SsoNonceStore($this->path);

        $this->assertTrue($store->burn(bin2hex(random_bytes(16)), 300));
        $this->assertTrue($store->burn(bin2hex(random_bytes(16)), 300));
    }

    public function testJtiKosongDitolak(): void
    {
        $store = new SsoNonceStore($this->path);

        $this->assertFalse($store->burn('', 300));
        $this->assertFalse($store->burn('   ', 300));
    }

    public function testJtiTidakPernahDipakaiMentahSebagaiNamaBerkas(): void
    {
        // Nilai jti datang dari luar. Kalau dipakai apa adanya, ".." bisa
        // menulis berkas di luar direktori nonce.
        $store = new SsoNonceStore($this->path);

        $this->assertTrue($store->burn('../../keluar', 300));

        $files = glob($this->path . '*.jti');

        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}\.jti$/', basename($files[0]));
    }

    public function testCatatanKedaluwarsaDibersihkanGc(): void
    {
        $store = new SsoNonceStore($this->path);
        $store->burn(bin2hex(random_bytes(16)), 300);

        $files = glob($this->path . '*.jti');
        $this->assertCount(1, $files);

        // Majukan waktu dengan menulis ulang tanggal kedaluwarsanya ke masa lalu.
        file_put_contents($files[0], (string) (time() - 1));

        $this->assertSame(1, $store->gc());
        $this->assertSame([], glob($this->path . '*.jti'));
    }

    public function testCatatanYangMasihBerlakuTidakDibuangGc(): void
    {
        $store = new SsoNonceStore($this->path);
        $jti   = bin2hex(random_bytes(16));

        $store->burn($jti, 300);

        $this->assertSame(0, $store->gc());
        $this->assertFalse($store->burn($jti, 300), 'gc() tidak boleh membuka jalan untuk replay.');
    }

    private function removeStore(): void
    {
        foreach (glob($this->path . '*') ?: [] as $file) {
            @unlink($file);
        }

        if (is_dir($this->path)) {
            @rmdir($this->path);
        }
    }
}
