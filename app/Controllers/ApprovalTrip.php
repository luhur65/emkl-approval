<?php

namespace App\Controllers;

use App\Models\ApprovalTripModel;
use App\Services\ApprovalTripService;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;

class ApprovalTrip extends BaseController
{
    protected $approvalTripModel;
    protected $approvalTripService;

    public function __construct()
    {
        $this->approvalTripModel = new ApprovalTripModel();
        $this->approvalTripService = new ApprovalTripService();
    }

    public function approval()
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        $data = [
            'title' => 'Approval Trip'
        ];
        return $this->render('approval/trip/index', $data);
    }

    public function select_all_ids()
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        $params = array_merge($this->request->getGet(), $this->request->getPost());

        try {
            return $this->response->setJSON($this->approvalTripService->getFilteredKeys($params));
        } catch (DatabaseException $e) {
            return $this->jsonErrorDatabase($e, self::NAMA_DB);
        }
    }

    private const PROSES_APPROVE   = '0';
    private const PROSES_UNAPPROVE = '1';

    /**
     * Dipakai pada pesan kegagalan koneksi. Data modul ini ada di database
     * terpisah (grup `dbtruck2`), yang bisa saja tidak terjangkau padahal
     * database utama sehat -- jadi pesannya perlu menyebut yang mana.
     */
    private const NAMA_DB = 'Trucking (dbtruck2)';

    public function approved()
    {
        return $this->jalankanProses(self::PROSES_APPROVE);
    }

    public function unapproved()
    {
        return $this->jalankanProses(self::PROSES_UNAPPROVE);
    }

    private function jalankanProses(string $prosesdata): ResponseInterface
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        try {
            $hasil = $this->approvalTripService->processApproval(
                $this->resolvePostedIds(),
                $prosesdata,
                (string) $this->request->getPost('tgl'),
                session()->get('FUserID')
            );
        } catch (DatabaseException $e) {
            return $this->jsonErrorDatabase($e, self::NAMA_DB);
        }

        return $this->response->setJSON($hasil);
    }

    private function resolvePostedIds(): array
    {
        $raw = $this->request->getPost('ids');

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $legacy = $this->request->getPost('id');

        return is_array($legacy) ? $legacy : [];
    }

    public function ajax_list()
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        $params = array_merge($this->request->getGet(), $this->request->getPost());

        try {
            return $this->response->setJSON($this->approvalTripService->getGridList($params));
        } catch (DatabaseException $e) {
            return $this->jsonErrorDatabase($e, self::NAMA_DB);
        }
    }

    private function denyIfNoAccess(): ?ResponseInterface
    {
        if (checkMenu(session()->get('FUserID'))) {
            return null;
        }

        if ($this->request instanceof IncomingRequest && $this->request->isAJAX()) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON([
                    'error' => 'Anda tidak memiliki hak akses untuk menu ini.',
                    'msg'   => '',
                ]);
        }

        return redirect()->to('home');
    }
}
