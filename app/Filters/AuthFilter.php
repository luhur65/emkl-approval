<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
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
        if (session()->get('logged_emkl')) {
            return null;
        }

        // Permintaan AJAX TIDAK boleh dibalas redirect: jQuery mengikuti redirect
        // itu diam-diam lalu menyerahkan HTML halaman login ke handler success.
        // Akibatnya sesi yang habis terlihat persis seperti proses yang berhasil --
        // pada endpoint approve, user diberi tahu "berhasil" padahal tidak ada satu
        // baris pun yang diproses. Balas 401 + JSON supaya sisi klien bisa
        // membedakannya dan menyuruh user login ulang.
        if ($request instanceof IncomingRequest && $request->isAJAX()) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'error' => 'Sesi Anda telah berakhir. Silakan login ulang.',
                    'msg'   => '',
                ]);
        }

        return redirect()->to(base_url('login'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Tidak ada logic khusus untuk after-filter saat ini.
    }
}
