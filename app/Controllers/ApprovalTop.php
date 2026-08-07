<?php

namespace App\Controllers;

use App\Models\ApprovalTopModel;
use App\Services\ApprovalTopService;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;

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
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        $data = [
            'title' => 'Approval TOP Pre Orderan'
        ];
        return $this->render('approval/top/index', $data);
    }

    public function get_detail($jurnal)
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        $data = $this->approvalTopModel->getDetail($jurnal);
        return $this->response->setJSON($data);
    }

    /**
     * Seluruh kunci baris yang lolos filter saat ini, untuk fitur "pilih semua"
     * di grid. Dipisah dari ajax_list karena yang dibutuhkan hanya kuncinya,
     * bukan satu halaman data.
     */
    public function select_all_ids()
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        $params = array_merge($this->request->getGet(), $this->request->getPost());

        return $this->response->setJSON($this->approvalTopService->getFilteredKeys($params));
    }

    /**
     * Kode proses yang dipakai ApprovalTopService & ApprovalTopModel: 0 memanggil
     * usp_AppPreJob, 1 memanggil usp_UnAppPreJob. Nilai yang sama juga jadi
     * parameter `bit` usp_GetListAppPreJob, sehingga menentukan pula baris mana
     * yang dianggap layak diproses.
     */
    private const PROSES_APPROVE   = '0';
    private const PROSES_UNAPPROVE = '1';

    public function approved()
    {
        return $this->jalankanProses(self::PROSES_APPROVE);
    }

    public function unapproved()
    {
        return $this->jalankanProses(self::PROSES_UNAPPROVE);
    }

    /**
     * Jenis proses sengaja datang dari route yang dipanggil, BUKAN dari field POST
     * `prosesdata` seperti sebelumnya. Dengan satu endpoint gabungan, siapa pun
     * yang boleh approve otomatis juga bisa un-approve hanya dengan menukar satu
     * nilai form -- dan un-approve bukan aksi setara: usp_UnAppPreJob memicu
     * render PDF via xp_cmdshell lalu MENGIRIM notifikasi WhatsApp ke manajemen.
     */
    private function jalankanProses(string $prosesdata): ResponseInterface
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        $hasil = $this->approvalTopService->processApproval(
            $this->resolvePostedIds(),
            $prosesdata,
            (string) $this->request->getPost('tgl'),
            session()->get('FUserID')
        );

        return $this->response->setJSON($hasil);
    }

    /**
     * Membaca daftar id yang dikirim view. Diutamakan field `ids` berisi JSON
     * (satu variabel POST), bukan array `id[]`: PHP membatasi jumlah variabel
     * input lewat max_input_vars (default 1000) dan MEMBUANG kelebihannya tanpa
     * error, sehingga "pilih semua" pada data besar akan terproses sebagian saja.
     * Bentuk lama `id[]` tetap diterima agar pemanggil lama tidak rusak.
     */
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

        // Tangkap seluruh request (termasuk page, rows, sidx, sord, tgl, bit)
        $params = array_merge($this->request->getGet(), $this->request->getPost());

        // Serahkan logika array slice dan mapping jqGrid ke Service
        $responce = $this->approvalTopService->getGridList($params);

        return $this->response->setJSON($responce);
    }

    /**
     * Penjaga hak akses menu untuk SELURUH endpoint modul ini. Di CI3 cukup
     * sekali di __construct() sehingga otomatis menutup semua method (lihat
     * ApprovalTOP.php CI3); di CI4 __construct() tidak bisa mengembalikan
     * Response, jadi harus dipanggil eksplisit di tiap method. Sebelumnya hanya
     * index() yang diperiksa, sehingga endpoint approve/list bisa dipanggil
     * langsung oleh user mana pun yang sekadar berhasil login.
     *
     * Permintaan AJAX dibalas JSON 403 -- BUKAN redirect -- karena jQuery
     * mengikuti redirect diam-diam lalu menyerahkan HTML halaman tujuan ke
     * handler success, sehingga penolakan akan terbaca sebagai keberhasilan.
     */
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
