<?php

namespace App\Controllers;

use App\Services\ApprovalExtraSupirService;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;

class ApprovalExtraSupir extends BaseController
{
    protected $approvalExtraSupirService;

    public function __construct()
    {
        $this->approvalExtraSupirService = new ApprovalExtraSupirService();
    }

    /**
     * Kode proses yang dipakai ApprovalExtraSupirService: 0 meng-approve,
     * 1 membatalkannya. Nilai yang sama juga jadi parameter `proses` pengisi
     * grid, sehingga untuk baris yang sedang tampil hanya satu aksi yang masuk
     * akal.
     *
     * CI3 memakai literal SQL 'TRUE'/'FALSE' untuk hal yang sama. Angka 0/1
     * dipilih di sini agar sebahasa dengan modul approval lain (Top, PO,
     * Absensi); pemetaannya ke parameter `bit` milik SP dikerjakan model.
     */
    private const PROSES_APPROVE   = '0';
    private const PROSES_UNAPPROVE = '1';

    /**
     * Dipakai pada pesan kegagalan koneksi. Data modul ini ada di database
     * terpisah (grup `dbtruck2`), yang bisa saja tidak terjangkau padahal
     * database utama sehat -- jadi pesannya perlu menyebut yang mana.
     */
    private const NAMA_DB = 'Trucking (dbtruck2)';

    public function index()
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        return $this->render('approval/extrasupir/index', [
            'title' => 'Approval Extra Supir',
        ]);
    }

    public function ajax_list()
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        try {
            return $this->response->setJSON(
                $this->approvalExtraSupirService->getGridList($this->gridParams())
            );
        } catch (DatabaseException $e) {
            return $this->jsonErrorDatabase($e, self::NAMA_DB);
        }
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

        try {
            return $this->response->setJSON(
                $this->approvalExtraSupirService->getFilteredKeys($this->gridParams())
            );
        } catch (DatabaseException $e) {
            return $this->jsonErrorDatabase($e, self::NAMA_DB);
        }
    }

    public function approved()
    {
        return $this->jalankanProses(self::PROSES_APPROVE);
    }

    public function unapproved()
    {
        return $this->jalankanProses(self::PROSES_UNAPPROVE);
    }

    /** Seluruh request grid (page, rows, sidx, sord, filters, _search, tanggal, proses). */
    private function gridParams(): array
    {
        return array_merge($this->request->getGet(), $this->request->getPost());
    }

    /**
     * Jenis proses ditentukan server dari route yang dipanggil, BUKAN dari
     * field POST `prosesdata` seperti CI3. Dengan satu endpoint gabungan, siapa
     * pun yang boleh approve otomatis juga bisa membatalkannya hanya dengan
     * menukar satu nilai form.
     */
    private function jalankanProses(string $prosesdata): ResponseInterface
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        try {
            $hasil = $this->approvalExtraSupirService->processApproval(
                $this->resolvePostedIds(),
                $prosesdata,
                (string) $this->request->getPost('tgldari'),
                (string) $this->request->getPost('tglsampai'),
                // SP-nya menyimpan NAMA user sebagai jejak approval, bukan
                // FUserID -- lihat ApprovalExtraSupirModel::approve().
                session()->get('FNamaUser')
            );
        } catch (DatabaseException $e) {
            return $this->jsonErrorDatabase($e, self::NAMA_DB);
        }

        return $this->response->setJSON($hasil);
    }

    /**
     * Membaca daftar No Trip yang dikirim view.
     *
     * Berbeda dari CI3, yang dikirim HANYA kunci -- bukan seluruh isi baris
     * (`$_POST['data']`), yang di sana kolom ke-4-nya dirangkai langsung ke
     * dalam teks pemanggilan Stored Procedure.
     *
     * Field `ids` berisi JSON (satu variabel POST) diutamakan: PHP membatasi
     * jumlah variabel input lewat max_input_vars (default 1000) dan MEMBUANG
     * kelebihannya tanpa error, sehingga "pilih semua" pada data besar akan
     * terproses sebagian saja.
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
     * sekali di __construct() sehingga otomatis menutup semua method; di CI4
     * __construct() tidak bisa mengembalikan Response, jadi harus dipanggil
     * eksplisit di tiap method.
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
