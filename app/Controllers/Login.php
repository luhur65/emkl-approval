<?php

namespace App\Controllers;

use App\Models\AuthModel;

class Login extends BaseController
{
    public function index()
    {
        $session = session();
        
        // Redirect jika sudah login
        if ($session->get('logged_emkl')) {
            return redirect()->to('/home');
        }

        $data = ['error' => ''];

        if ($this->request->getMethod() === 'POST') {
            // Validasi input
            $rules = [
                'pUser'     => 'required',
                'pPassword' => 'required'
            ];

            if (!$this->validate($rules)) {
                $data['error'] = 'Username dan Password wajib diisi.';
                return view('auth/login', $data);
            }

            $pUser = $this->request->getPost('pUser');
            // Meniru legacy logic yang menggunakan md5
            $pPassword = md5((string)$this->request->getPost('pPassword'));

            $authModel = new AuthModel();
            $user = $authModel->validateUser($pUser, $pPassword);

            if (!empty($user)) {
                $this->setLoggedInSession($user);

                return redirect()->to('/home');
            } else {
                $data['error'] = 'Username atau Password salah.';
            }
        }

        return view('auth/login', $data);
    }

    public function logout()
    {
        $session = session();
        $session->destroy();
        return redirect()->to('/login');
    }

    /**
     * Endpoint AJAX untuk fitur Lockscreen: verifikasi ulang password user
     * yang sedang login sebelum sesi idle-nya dibuka kembali.
     *
     * Auto-relogin: kalau sesi server sudah hilang (expired/di-GC saat user
     * pergi lama), userid diambil dari field POST -- dikirim client dari
     * localStorage 'lockscreen_userid' -- alih-alih memaksa user balik ke
     * halaman /login dan mengetik ulang username. Selama passwordnya benar,
     * sesi dibangun ulang langsung dari form lockscreen ini.
     */
    public function unlock()
    {
        $session = session();
        $userId  = $session->get('FUserID');

        if (!$userId) {
            $userId = $this->request->getPost('userid');
        }

        if (!$userId) {
            // Baik sesi server maupun localStorage browser sama-sama sudah
            // tidak punya identitas user -- tidak ada apa pun yang bisa
            // dipakai untuk auto-relogin. Beda dengan password salah, jadi
            // jangan dihitung sebagai percobaan gagal; status 401 + flag
            // session_expired memberi sinyal ke lockscreen.js supaya langsung
            // mengarahkan ke alur logout/login biasa.
            return $this->response->setStatusCode(401)->setJSON([
                'success'         => false,
                'session_expired' => true,
                'message'         => 'Sesi telah berakhir permanen. Silakan muat ulang halaman.',
            ]);
        }

        $password  = md5((string) $this->request->getPost('password'));
        $authModel = new AuthModel();
        $user      = $authModel->validateUser($userId, $password);

        if (empty($user)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Password salah.',
            ]);
        }

        // Sesi server memang sudah hilang (bukan sekadar lama) -- bangun
        // ulang persis seperti login normal, supaya lockscreen berfungsi
        // sebagai re-login penuh tanpa perlu lewat halaman /login.
        if (!$session->get('logged_emkl')) {
            $this->setLoggedInSession($user);
        }

        return $this->response->setJSON(['success' => true]);
    }

    private function setLoggedInSession(array $user): void
    {
        session()->set([
            'FUserID'     => $user['FUserID'],
            'FNamaUser'   => $user['FNamaUser'],
            'logged_emkl' => true,
        ]);
    }
}
