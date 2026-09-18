<?php

namespace App\Libraries;

/**
 * Penjaga sekali-pakai untuk tiket SSO.
 *
 * Tiket SSO adalah kredensial pembawa: siapa pun yang melihatnya — di history
 * browser, di log proxy, di Referer — bisa memakainya sampai kedaluwarsa.
 * Karena itu tiket hanya boleh ditukar SATU kali. Nonce `jti` yang sudah
 * dipakai dicatat di sini, dan penukaran kedua ditolak.
 *
 * hrapi memakai Redis (SET NX EX) untuk ini. Aplikasi ini tidak punya Redis,
 * jadi peran yang sama diambil filesystem: fopen() mode 'x' memakai
 * O_CREAT|O_EXCL, satu-satunya cara membuat file yang atomik di level sistem
 * operasi. Dua request bersamaan dengan jti yang sama hanya bisa menghasilkan
 * satu pemenang, baik di Windows maupun Linux — pemeriksaan "sudah ada?" lalu
 * "tulis" yang terpisah justru punya celah balapan di antaranya.
 *
 * Berkas nonce tinggal di WRITEPATH (di luar public/), jadi tidak pernah bisa
 * diambil lewat web. Disalin dari sys-modern tanpa perubahan logika.
 */
class SsoNonceStore
{
    /** Peluang menjalankan pembersihan pada sebuah penukaran (1 dari sekian). */
    private const GC_DIVISOR = 50;

    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = rtrim($path ?? WRITEPATH . 'sso_nonce', '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Menandai `$jti` terpakai. Mengembalikan true hanya untuk pemanggil
     * PERTAMA; penukaran berikutnya dengan jti yang sama mengembalikan false.
     *
     * @param int $ttl Berapa lama catatan disimpan (detik). Harus melebihi umur
     *                 tiket, kalau tidak replay jadi mungkin lagi setelah TTL.
     */
    public function burn(string $jti, int $ttl): bool
    {
        if (trim($jti) === '') {
            return false;
        }

        if (! $this->ensureDirectory()) {
            // Gagal menyiapkan penyimpanan berarti sifat sekali-pakai tidak bisa
            // dijamin. Fail closed: lebih baik login gagal daripada tiket bisa
            // diputar ulang tanpa ada yang tahu.
            log_message('error', 'SSO: gagal menyiapkan direktori nonce di ' . $this->path);

            return false;
        }

        if (random_int(1, self::GC_DIVISOR) === 1) {
            $this->gc();
        }

        $handle = @fopen($this->file($jti), 'xb');

        if ($handle === false) {
            return false;
        }

        fwrite($handle, (string) (time() + max(1, $ttl)));
        fclose($handle);

        return true;
    }

    /**
     * Membuang catatan yang isinya sudah lewat waktu kedaluwarsa. Dipanggil
     * sesekali dari burn() supaya direktori tidak tumbuh tanpa batas.
     *
     * @return int Jumlah berkas yang dihapus.
     */
    public function gc(): int
    {
        $files = glob($this->path . '*.jti');

        if ($files === false) {
            return 0;
        }

        $now     = time();
        $removed = 0;

        foreach ($files as $file) {
            $expiresAt = (int) @file_get_contents($file);

            // Berkas tanpa isi yang masuk akal (mis. tulisan terpotong) dinilai
            // dari waktu modifikasinya, supaya tetap punya batas umur.
            if ($expiresAt === 0) {
                $expiresAt = (int) @filemtime($file);
            }

            if ($expiresAt !== 0 && $expiresAt <= $now && @unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }

    private function file(string $jti): string
    {
        // jti di-hash, bukan dipakai mentah: nilainya berasal dari luar, dan
        // hash memastikan nama berkas tidak pernah memuat pemisah direktori.
        return $this->path . hash('sha256', $jti) . '.jti';
    }

    private function ensureDirectory(): bool
    {
        if (is_dir($this->path)) {
            return true;
        }

        return @mkdir($this->path, 0700, true) || is_dir($this->path);
    }
}
