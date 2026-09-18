<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Konfigurasi klien SSO (auth-sso / auth-sso-api).
 *
 * Aplikasi ini berperan sebagai *aplikasi anggota*, sama seperti sys-modern,
 * HR, CRM, dan trucking per cabang: server SSO (auth-sso-api) yang
 * menerbitkan tiket, aplikasi ini hanya memverifikasinya. Tiket ditandatangani
 * RS256, jadi yang dipegang di sini cukup PUBLIC key — dengannya tiket hanya
 * bisa diperiksa, tidak bisa dibuat. Polanya disalin dari sys-modern
 * (D:\php-project\sys-modern\dokumentasi_sso.md); penjelasan lengkapnya ada di
 * docs/MODUL_SSO.md.
 *
 * Semua nilai diisi lewat .env dengan awalan `sso.`, contoh:
 *   sso.enabled = true
 *   sso.appCode = emkl-approval-surabaya
 */
class Sso extends BaseConfig
{
    /**
     * Saklar utama. Selama false, route callback menolak semua tiket dan tombol
     * "Masuk dengan SSO" tidak dirender — instalasi yang belum dikonfigurasi
     * tidak menyisakan endpoint autentikasi setengah jadi.
     */
    public bool $enabled = false;

    /**
     * Kode aplikasi ini di sisi SSO — PER CABANG, mengikuti pola trucking
     * (`trucking-surabaya`, `trucking-medan`, ...): tiap cabang memasang
     * aplikasi yang sama dengan database FUserList sendiri, jadi tiap cabang
     * adalah aplikasi tersendiri di mata SSO. Nilainya jadi klaim `aud` pada
     * tiket, dan harus sama dengan key di TICKET_RELAY_APPS milik auth-sso-api
     * serta `code` kartu di constants/apps.ts milik auth-sso. Pemeriksaan `aud`
     * inilah yang membuat tiket cabang lain (atau aplikasi lain) tidak bisa
     * dipakai di sini.
     *
     * Sengaja kosong sebagai bawaan: kode cabang yang salah lebih berbahaya
     * daripada yang tidak ada — isi di .env tiap server, mis.
     * `emkl-approval-surabaya`.
     */
    public string $appCode = '';

    /** Klaim `iss` yang wajib ada pada tiket (SSO_TICKET_ISSUER di auth-sso-api). */
    public string $issuer = 'auth-sso';

    /**
     * PUBLIC key RS256 (SPKI). Boleh PEM utuh, boleh badan base64 saja dengan
     * newline ditulis sebagai `\n` — persis format yang dipakai auth-sso-api
     * dan sys-modern, supaya satu nilai bisa disalin apa adanya antar aplikasi.
     * Tulis TANPA tanda kutip di .env (parser .env memproses escape di dalam
     * nilai berkutip dan itu merusak urutan `\n`). JANGAN pernah menaruh
     * private key di sini.
     */
    public string $ticketPublicKey = '';

    /** Toleransi selisih jam (detik) saat memeriksa exp/nbf/iat. */
    public int $leeway = 60;

    /**
     * Umur simpan nonce `jti` (detik). Harus lebih panjang dari TICKET_TTL_SECONDS
     * di auth-sso-api (saat ini 60) ditambah toleransi jam, supaya tiket yang
     * belum kedaluwarsa tidak bisa dipakai dua kali.
     */
    public int $nonceTtl = 300;

    /** URL dashboard auth-sso: tujuan cadangan tombol "Masuk dengan SSO" dan tujuan logout pengguna SSO. */
    public string $dashboardUrl = '';

    /**
     * URL SP-initiated milik auth-sso: `<origin auth-sso>/launch/<appCode>`.
     *
     * Bila diisi, tombol "Masuk dengan SSO" mengantar ke sini: auth-sso langsung
     * menerbitkan tiket untuk aplikasi ini (meminta login SSO dulu bila perlu)
     * dan memantulkan browser ke auth/sso-callback — pengguna tidak perlu
     * mencari kartunya di dashboard. Bila kosong, tombol mengantar ke
     * dashboardUrl dan pengguna menekan kartu cabangnya sendiri.
     */
    public string $launchUrl = '';

    /** Base URL auth-sso-api, dipakai untuk polling Single Logout. */
    public string $apiBaseUrl = '';

    /**
     * Secret bersama untuk endpoint Single Logout (header `x-slo-secret`).
     * Harus sama dengan SLO_INTROSPECT_SECRET di auth-sso-api.
     */
    public string $sloSecret = '';

    /**
     * Jeda (detik) antar pemeriksaan liveness sesi SSO. Hasil pemeriksaan
     * disimpan di cache selama rentang ini, jadi satu request per rentang —
     * bukan satu request per halaman.
     */
    public int $sloPollSeconds = 60;

    /**
     * Klaim tiket yang dipakai mencocokkan pengguna bila tiket TIDAK membawa
     * `sub` berupa FID (jalur cadangan). Jalur utamanya `sub` = FUserList.FID,
     * yang ditandatangani auth-sso-api setelah ia sendiri mencocokkan
     * `karyawan_id` akun SSO ke FIDKaryawan — lihat SsoAuth::callback().
     *
     * `karyawanId` (huruf I besar) adalah id master karyawan HR, identitas yang
     * sama yang dipakai semua aplikasi anggota. `email` sengaja bukan pilihan
     * di sini: FUserList.FEmail kosong di seluruh baris.
     */
    public string $matchClaim = 'karyawanId';

    /**
     * Kolom FUserList yang dicocokkan dengan klaim di atas, DAN yang diperiksa
     * ulang terhadap klaim `karyawanId` pada jalur `sub`. `int NULL`; NULL/0
     * berarti "belum dipetakan" dan ditolak sebagai identitas.
     */
    public string $matchColumn = 'FIDKaryawan';

    /**
     * Saklar login lokal (username/password FUserList + buka kunci lock screen).
     *
     * true  = jalan berdampingan dengan SSO. Default, dan yang benar selama masa
     *         transisi: kalau SSO bermasalah, masih ada jalan masuk untuk
     *         memperbaikinya.
     * false = SSO satu-satunya cara masuk. Form password disembunyikan DAN
     *         endpoint-nya menolak — penyembunyian di view saja tidak menutup
     *         apa pun, POST langsung ke /login tetap akan lolos.
     */
    public bool $passwordLoginEnabled = true;

    /**
     * Ke mana pengguna diantar SETELAH sesi aplikasi ini diakhiri.
     *
     * false = hanya sesi yang LAHIR dari SSO yang dikembalikan ke dashboard SSO;
     *         sesi login lokal pulang ke halaman /login. Default.
     * true  = semua sesi diantar ke sso.dashboardUrl, termasuk yang masuk lewat
     *         username/password. Berlaku untuk SETIAP akhir sesi (tombol logout,
     *         sesi habis sendiri, Single Logout) — App\Libraries\SsoExit yang
     *         memutuskan. Diabaikan selama SSO belum dikonfigurasi.
     *
     * Saklar ini HANYA mengubah tujuan redirect; sesi SSO-nya sendiri tetap
     * TIDAK dicabut — mencabutnya akan melogout pengguna dari HR, CRM, dan
     * aplikasi lain sekaligus, dan itu wewenang dashboard SSO.
     */
    public bool $logoutToSso = false;
}
