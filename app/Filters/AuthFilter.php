<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    /**
     * Mengecek apakah user sudah login sebelum controller dieksekusi.
     * Jika belum, lempar ke halaman login.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Cek sesi 'logged_emkl'
        if (!session()->get('logged_emkl')) {
            return redirect()->to(base_url('login'));
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Tidak ada logic khusus untuk after-filter saat ini.
    }
}
