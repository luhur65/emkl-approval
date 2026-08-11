<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Security extends BaseConfig
{
    /**
     * --------------------------------------------------------------------------
     * CSRF Protection Method
     * --------------------------------------------------------------------------
     *
     * Protection Method for Cross Site Request Forgery protection.
     *
     * @var string 'cookie' or 'session'
     */
    public string $csrfProtection = 'cookie';

    /**
     * --------------------------------------------------------------------------
     * CSRF Token Randomization
     * --------------------------------------------------------------------------
     *
     * Randomize the CSRF Token for added security.
     */
    public bool $tokenRandomize = false;

    /**
     * --------------------------------------------------------------------------
     * CSRF Token Name
     * --------------------------------------------------------------------------
     *
     * Token name for Cross Site Request Forgery protection.
     */
    public string $tokenName = 'csrf_test_name';

    /**
     * --------------------------------------------------------------------------
     * CSRF Header Name
     * --------------------------------------------------------------------------
     *
     * Header name for Cross Site Request Forgery protection.
     */
    public string $headerName = 'X-CSRF-TOKEN';

    /**
     * --------------------------------------------------------------------------
     * CSRF Cookie Name
     * --------------------------------------------------------------------------
     *
     * Cookie name for Cross Site Request Forgery protection.
     */
    public string $cookieName = 'csrf_cookie_name';

    /**
     * --------------------------------------------------------------------------
     * CSRF Expires
     * --------------------------------------------------------------------------
     *
     * Expiration time for Cross Site Request Forgery protection cookie.
     *
     * Defaults to two hours (in seconds).
     */
    public int $expires = 7200;

    /**
     * --------------------------------------------------------------------------
     * CSRF Regenerate
     * --------------------------------------------------------------------------
     *
     * Regenerate CSRF Token on every submission.
     *
     * DIMATIKAN dengan sengaja. Halaman di aplikasi ini menanam hash sekali
     * saat dirender (Views/partials/header.php) lalu memakainya untuk semua
     * request AJAX berikutnya. Bila token diputar tiap kiriman, hash yang
     * tertanam itu langsung basi sesudah POST pertama -- praktisnya: approve
     * pertama berhasil, approve kedua di halaman yang sama ditolak 403 sampai
     * user memuat ulang halaman.
     *
     * Menyalakannya kembali baru aman kalau sisi klien ikut menyegarkan token
     * dari respons tiap kali selesai POST.
     */
    public bool $regenerate = false;

    /**
     * --------------------------------------------------------------------------
     * CSRF Redirect
     * --------------------------------------------------------------------------
     *
     * Redirect to previous page with error on failure.
     *
     * DIPAKSA false, tidak lagi mengikuti ENVIRONMENT. Hampir semua penulisan
     * di aplikasi ini lewat AJAX, dan redirect atas kegagalan CSRF akan diikuti
     * jQuery diam-diam lalu HTML halaman tujuan diserahkan ke handler success
     * -- penolakan jadi terbaca sebagai keberhasilan, persis kelas bug yang
     * sudah diperbaiki di App\Filters\AuthFilter. Dengan false, kegagalan CSRF
     * keluar sebagai 403 yang bisa dibedakan sisi klien.
     *
     * @see https://codeigniter4.github.io/userguide/libraries/security.html#redirection-on-failure
     */
    public bool $redirect = false;
}
