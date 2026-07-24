<?php

namespace App\Controllers;

use App\Models\ApprovalTopModel;

class ApprovalTop extends BaseController
{
    protected $approvalTopModel;

    public function __construct()
    {
        // AuthFilter otomatis memblokir controller ini jika belum login,
        // sehingga logic session_start() & empty(cek) CI3 sudah tak diperlukan.
        $this->approvalTopModel = new ApprovalTopModel();
    }

    public function index()
    {
        if (!checkMenu(session()->get('FUserID'))) {
            return redirect()->to('home');
        }

        $data = [
            'title' => 'Approval TOP Pre Orderan'
        ];
        return $this->render('approval/top/index', $data);
    }

    public function get_detail($jurnal)
    {
        $data = $this->approvalTopModel->getDetail($jurnal);
        return $this->response->setJSON($data);
    }

    public function approved()
    {
        $error = '';
        $msg = '';
        $ids = $this->request->getPost('id');
        $prosesdata = $this->request->getPost('prosesdata');
        $userId = session()->get('FUserID');

        if ($ids && is_array($ids)) {
            foreach ($ids as $id) {
                $hasil = $this->approvalTopModel->processApproval($id, $userId, $prosesdata);
                if (isset($hasil->error_sql) && $hasil->error_sql != '') {
                    $error .= $hasil->error_sql;
                }
            }
        }

        if (trim($error) != '') {
            $msg = 'Terjadi kesalahan tidak semua proses berhasil';
        } else {
            $msg = 'Proses berhasil';
        }

        return $this->response->setJSON([
            'error' => $error,
            'msg'   => $msg
        ]);
    }

    public function ajax_list()
    {
        $tgl = $this->request->getGet('tgl') ?? date("Y-m-d");
        $bit = $this->request->getGet('bit') ?? 0;

        $list = $this->approvalTopModel->getData($tgl, $bit);
        
        $data = [];
        foreach ($list as $d) {
            $row = [];
            $row[] = $d['FJurnal']; // Checkbox / Expand icon target (sesuai dt CI3)
            $row[] = $d['FJurnal'];
            $row[] = $d['FNShipper'];
            $date = strtotime($d['FTgl']);
            $row[] = date("d-m-Y", $date);
            $row[] = $d['FNMarketing'];
            $row[] = $d['FJumlahInvoice'];
            $row[] = $d['FJumlahjob'];
            $data[] = $row;
        }

        return $this->response->setJSON(["data" => $data]);
    }
}
