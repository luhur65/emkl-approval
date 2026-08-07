<?php

namespace App\Controllers;

use App\Services\ApprovalPhargaService;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Dua halaman, sesuai dua menu di sidebar & dua view di CI3:
 *   index() -> approval penawaran harga
 *   cetak() -> approval permintaan cetak ulang
 */
class ApprovalPharga extends BaseController
{
    protected $approvalPhargaService;

    public function __construct()
    {
        $this->approvalPhargaService = new ApprovalPhargaService();
    }

    /**
     * Kode proses yang dipakai ApprovalPhargaService: 0 menyetel FIsApp jadi 1
     * (approve), 1 menyetel FIsApp jadi 0 (un-approve). Nilainya sama dengan
     * konvensi di ApprovalTop & ApprovalPo -- dan sama pula dengan parameter
     * `bit` yang menyaring isi grid, sehingga untuk baris yang sedang tampil
     * hanya satu aksi yang masuk akal.
     */
    private const PROSES_APPROVE   = '0';
    private const PROSES_UNAPPROVE = '1';

    // ------------------------------------------------------------------
    // Halaman approval penawaran harga
    // ------------------------------------------------------------------

    public function index()
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        return $this->render('approval/pharga/index', [
            'title' => 'Approval Penawaran Harga',
        ]);
    }

    public function ajax_list()
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        return $this->response->setJSON(
            $this->approvalPhargaService->getGridList($this->gridParams())
        );
    }

    public function select_all_ids()
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        return $this->response->setJSON(
            $this->approvalPhargaService->getFilteredKeys($this->gridParams())
        );
    }

    public function approved()
    {
        return $this->jalankanProses(self::PROSES_APPROVE);
    }

    public function unapproved()
    {
        return $this->jalankanProses(self::PROSES_UNAPPROVE);
    }

    // ------------------------------------------------------------------
    // Halaman approval cetak ulang
    // ------------------------------------------------------------------

    public function cetak()
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        return $this->render('approval/pharga/cetak', [
            'title' => 'Approval Cetak Ulang Penawaran Harga',
        ]);
    }

    public function ajax_list_cetak()
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        return $this->response->setJSON(
            $this->approvalPhargaService->getGridListCetak($this->gridParams())
        );
    }

    public function select_all_ids_cetak()
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        return $this->response->setJSON(
            $this->approvalPhargaService->getFilteredKeysCetak($this->gridParams())
        );
    }

    public function approved_cetak()
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        $hasil = $this->approvalPhargaService->processCetak(
            $this->resolvePostedIds(),
            (string) $this->request->getPost('tgl'),
            session()->get('FUserID')
        );

        return $this->response->setJSON($hasil);
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    /** Seluruh request grid (page, rows, sidx, sord, filters, _search, tgl, bit). */
    private function gridParams(): array
    {
        return array_merge($this->request->getGet(), $this->request->getPost());
    }

    /**
     * Jenis proses ditentukan server dari route yang dipanggil, BUKAN dari
     * field POST `prosesdata` seperti CI3. Dengan satu endpoint gabungan, siapa
     * pun yang boleh approve otomatis juga bisa un-approve hanya dengan menukar
     * satu nilai form.
     */
    private function jalankanProses(string $prosesdata): ResponseInterface
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        $hasil = $this->approvalPhargaService->processApproval(
            $this->resolvePostedIds(),
            $prosesdata,
            (string) $this->request->getPost('tgl'),
            session()->get('FUserID')
        );

        return $this->response->setJSON($hasil);
    }

    /**
     * Membaca daftar No Transaksi yang dikirim view. Diutamakan field `ids`
     * berisi JSON (satu variabel POST), bukan array `id[]`: PHP membatasi jumlah
     * variabel input lewat max_input_vars (default 1000) dan MEMBUANG
     * kelebihannya tanpa error, sehingga "pilih semua" pada data besar akan
     * terproses sebagian saja. Bentuk `id[]` milik view CI3 tetap diterima.
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

    /**
     * Penjaga hak akses menu untuk SELURUH endpoint modul ini. Di CI3 cukup
     * sekali di __construct() sehingga otomatis menutup semua method (lihat
     * ApprovalPHarga.php CI3); di CI4 __construct() tidak bisa mengembalikan
     * Response, jadi harus dipanggil eksplisit di tiap method.
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
