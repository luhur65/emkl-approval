<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Site extends BaseConfig
{
    /**
     * Nama aplikasi TANPA kode cabang. Kode cabangnya ditambahkan terpisah saat
     * ditampilkan (lihat Views/partials/navbar.php), supaya satu basis kode ini
     * bisa dipasang di Medan, Jakarta, Semarang, Bitung, Makassar, dsb. tanpa
     * mengubah file apa pun -- cukup isi `site.branchCode` di .env milik server
     * masing-masing.
     */
    public $siteTitle = 'EMKL Approval';

    /**
     * Kode cabang tempat instance ini dipasang, mis. 'SBY', 'MDN', 'JKT'.
     * Diisi lewat `site.branchCode` di .env; setiap cabang punya .env sendiri
     * karena database SQL Server-nya juga terpisah per cabang.
     *
     * Sengaja dibiarkan kosong sebagai bawaan: label cabang yang salah lebih
     * berbahaya daripada label yang tidak ada -- user bisa menyangka sedang
     * meng-approve data cabang lain.
     */
    public $branchCode = '';

    /**
     * Nama panjang cabang, mis. 'Surabaya'. Dipakai di tempat yang muat teks
     * lebih panjang (judul cetakan, tooltip). Kosong = tidak ditampilkan.
     */
    public $branchName = '';

    public $siteTheme = 'light';

    /**
     * Nama aplikasi lengkap dengan kode cabangnya, mis. "EMKL Approval SBY".
     * Dipakai di <title> dan navbar. Bila `site.branchCode` belum diisi, yang
     * keluar hanya nama aplikasinya -- tanpa spasi menggantung di belakang.
     *
     * $override dipakai pemanggil yang punya kode cabang lebih spesifik
     * (mis. kode cabang milik user yang sedang login).
     */
    public function fullTitle(?string $override = null): string
    {
        $kode = trim((string) ($override ?? '')) ?: trim((string) $this->branchCode);

        return $kode === '' ? $this->siteTitle : $this->siteTitle . ' ' . $kode;
    }
}
