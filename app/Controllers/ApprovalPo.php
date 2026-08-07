<?php

namespace App\Controllers;

use App\Services\ApprovalPoService;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;

class ApprovalPo extends BaseController
{
    protected $approvalPoService;

    public function __construct()
    {
        $this->approvalPoService = new ApprovalPoService();
    }

    public function index()
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        $data = [
            'title' => 'Approval Permintaan Order'
        ];

        return $this->render('approval/po/index', $data);
    }

    public function ajax_list()
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        // Tangkap seluruh request (termasuk page, rows, sidx, sord, tgl)
        $params = array_merge($this->request->getGet(), $this->request->getPost());

        // Serahkan logika array slice dan mapping jqGrid ke Service
        $responce = $this->approvalPoService->getGridList($params);

        return $this->response->setJSON($responce);
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

        return $this->response->setJSON($this->approvalPoService->getFilteredKeys($params));
    }

    /**
     * Kode proses yang dipakai ApprovalPoService: 0 menyetel FIsApp jadi 1
     * (approve), 1 menyetel FIsApp jadi 0 (un-approve). Nilainya sengaja
     * disamakan dengan konvensi di ApprovalTop supaya kedua modul approval
     * berbicara dalam bahasa yang sama.
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
     * Jenis proses datang dari route yang dipanggil, BUKAN dari field POST.
     * Dengan satu endpoint gabungan (atau dengan toggle yang membaca status
     * sendiri), user tidak pernah menyatakan MAKSUDNYA -- menekan tombol pada
     * baris yang statusnya keburu berubah di tangan orang lain akan menjalankan
     * aksi yang berlawanan dari yang ia lihat di layar. Dengan arah yang
     * eksplisit, kasus itu jadi "dilewati", bukan aksi kebalikan yang senyap.
     */
    private function jalankanProses(string $prosesdata): ResponseInterface
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        $hasil = $this->approvalPoService->processApproval(
            $this->resolvePostedIds(),
            $prosesdata,
            (string) $this->request->getPost('tgl'),
            session()->get('FUserID')
        );

        return $this->response->setJSON($hasil);
    }

    /**
     * Membaca daftar No Bukti yang dikirim view. Diutamakan field `ids` berisi
     * JSON (satu variabel POST), bukan array `id[]`: PHP membatasi jumlah
     * variabel input lewat max_input_vars (default 1000) dan MEMBUANG
     * kelebihannya tanpa error, sehingga "pilih semua" pada data besar akan
     * terproses sebagian saja. Bentuk `id[]` dan `fntrans` (satu No Bukti,
     * dipakai view CI3) tetap diterima agar pemanggil lama tidak rusak.
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

        if (is_array($legacy)) {
            return $legacy;
        }

        $tunggal = $this->request->getPost('fntrans');

        // urldecode dipertahankan dari CI3: No Bukti di sana dikirim sudah
        // ter-encode sekali oleh sisi klien.
        return is_string($tunggal) && $tunggal !== '' ? [urldecode($tunggal)] : [];
    }

    /**
     * Penjaga hak akses menu untuk SELURUH endpoint modul ini. Di CI3 cukup
     * sekali di __construct() sehingga otomatis menutup semua method (lihat
     * ApprovalPO.php CI3); di CI4 __construct() tidak bisa mengembalikan
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
