<?php

namespace App\Controllers;

use App\Models\ApprovalPoModel;
use App\Services\ApprovalPoService;

class ApprovalPo extends BaseController
{
    protected $approvalPoModel;
    protected $approvalPoService;

    public function __construct()
    {
        $this->approvalPoModel = new ApprovalPoModel();
        $this->approvalPoService = new ApprovalPoService();
    }

    public function index()
    {
        if (!checkMenu(session()->get('FUserID'))) {
            return redirect()->to('home');
        }

        $data = [
            'title' => 'Approval Permintaan Order'
        ];
        return $this->render('approval/po/index', $data);
    }

    public function ajax_list()
    {
        // Tangkap seluruh request (termasuk page, rows, sidx, sord, tgl)
        $params = array_merge($this->request->getGet(), $this->request->getPost());

        // Serahkan logika array slice dan mapping jqGrid ke Service
        $responce = $this->approvalPoService->getGridList($params);

        return $this->response->setJSON($responce);
    }

    public function approved()
    {
        $error = '';
        $msg = '';
        $fntrans = $this->request->getPost('fntrans');
        $fntrans = urldecode($fntrans);

        // Check Validation from SP
        $check = $this->approvalPoModel->getValidasi($fntrans);
        if (isset($check->error_sql) && $check->error_sql != "") {
            return $this->response->setJSON([
                'error' => $check->error_sql,
                'msg'   => ''
            ]);
        }

        // Get Status App
        $poData = $this->approvalPoModel->getPermintaanApprovalOrder($fntrans);
        $app = (isset($poData[0]['FIsApp'])) ? $poData[0]['FIsApp'] : 0;
        
        // Execute Update SP
        $newApp = $app == 1 ? 0 : 1;
        $updateResult = $this->approvalPoModel->processApproval(session()->get('FUserID'), $fntrans, $newApp);

        if (isset($updateResult->error_sql) && $updateResult->error_sql != "") {
             return $this->response->setJSON([
                'error' => $updateResult->error_sql,
                'msg'   => ''
            ]);
        }

        if ($app == 1) {
            $msg = 'No Bukti Berhasil Di Un Approval Permintaan Approval Order';
        } else {
            $msg = 'No Bukti Berhasil Di Approval Permintaan Approval Order';
        }

        return $this->response->setJSON([
            'error' => '',
            'msg'   => $msg
        ]);
    }
}
