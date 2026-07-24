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
                // Set session
                $ses_data = [
                    'FUserID'     => $user['FUserID'],
                    'FNamaUser'   => $user['FNamaUser'],
                    'logged_emkl' => true,
                ];
                $session->set($ses_data);
                
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
}
