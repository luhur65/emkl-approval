<?php

namespace App\Controllers;

use App\Models\ApprovalTopModel;
use App\Services\ApprovalTopService;

class ApprovalTop extends BaseController
{
    protected $approvalTopModel;
    protected $approvalTopService;

    public function __construct()
    {
        $this->approvalTopModel = new ApprovalTopModel();
        $this->approvalTopService = new ApprovalTopService();
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
        // Tangkap seluruh request (termasuk page, rows, sidx, sord, tgl, bit)
        $params = array_merge($this->request->getGet(), $this->request->getPost());

        // Serahkan logika array slice dan mapping jqGrid ke Service
        $responce = $this->approvalTopService->getGridList($params);

        return $this->response->setJSON($responce);
    }

    public function reverse()
    {
        $db = \Config\Database::connect();
        $text = "--- usp_GetListAppPreJob ---\n";
        try {
            $query = $db->query("EXEC sp_helptext 'usp_GetListAppPreJob'");
            foreach($query->getResultArray() as $row){
                $text .= $row['Text'];
            }
        } catch (\Exception $e) { $text .= $e->getMessage(); }
        
        $text .= "\n\n--- usp_AppPreJob ---\n";
        try {
            $query = $db->query("EXEC sp_helptext 'usp_AppPreJob'");
            foreach($query->getResultArray() as $row){
                $text .= $row['Text'];
            }
        } catch (\Exception $e) { $text .= $e->getMessage(); }

        return $this->response->setContentType('text/plain')->setBody($text);
    }
}
