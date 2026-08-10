<?php

namespace App\Controllers;

use App\Services\ApprovalPengajuanService;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * CATATAN migrasi: index() di CI3 mengambil SELURUH isi TrApprovalAbsensi
 * (`$this->dbtruck2->get('TrApprovalAbsensi')`) lalu mengopernya ke view
 * sebagai $data['data'] -- padahal view-nya tidak pernah menyentuh variabel
 * itu; isinya dimuat AJAX. Query itu tidak ikut dipindahkan: ia menarik seluruh
 * tabel di setiap kali halaman dibuka tanpa dipakai sama sekali.
 */
class ApprovalPengajuan extends BaseController
{
    protected $approvalPengajuanService;

    public function __construct()
    {
        $this->approvalPengajuanService = new ApprovalPengajuanService();
    }

    /**
     * Kode proses yang dipakai ApprovalPengajuanService: 0 meng-approve,
     * 1 membatalkannya. Nilai yang sama juga jadi parameter `bit` pengisi grid,
     * sehingga untuk baris yang sedang tampil hanya satu aksi yang masuk akal.
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

        return $this->render('approval/pengajuan/index', [
            'title' => 'Approval Pengajuan Supir Serap',
        ]);
    }

    public function ajax_list()
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        try {
            return $this->response->setJSON(
                $this->approvalPengajuanService->getGridList($this->gridParams())
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
                $this->approvalPengajuanService->getFilteredKeys($this->gridParams())
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

    /** Seluruh request grid (page, rows, sidx, sord, filters, _search, tgl, bit). */
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
            $hasil = $this->approvalPengajuanService->processApproval(
                $this->resolvePostedIds(),
                $prosesdata,
                (string) $this->request->getPost('tgl'),
                // Kolom FUserApp menyimpan FUserID -- bukan nama user seperti
                // Approval Extra Supir. Mengikuti CI3, yang mengoper
                // $_SESSION['FUserID'] ke array update.
                session()->get('FUserID')
            );
        } catch (DatabaseException $e) {
            return $this->jsonErrorDatabase($e, self::NAMA_DB);
        }

        return $this->response->setJSON($hasil);
    }

    /**
     * Membaca daftar FID yang dikirim view.
     *
     * Field `ids` berisi JSON (satu variabel POST) diutamakan: PHP membatasi
     * jumlah variabel input lewat max_input_vars (default 1000) dan MEMBUANG
     * kelebihannya tanpa error, sehingga "pilih semua" pada data besar akan
     * terproses sebagian saja -- persis bentuk `id[]` yang dipakai CI3.
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
     * Penjaga hak akses menu untuk SELURUH endpoint modul ini. Di CI3 modul ini
     * hanya memeriksa sesi login -- pemeriksaan checkMenu() yang sudah dipakai
     * modul approval lain (mis. ApprovalExtraSupir) tidak ada di sini, padahal
     * menunya berada di grup Approval yang sama. Disamakan supaya satu menu
     * tidak menjadi celah masuk ke seluruh grupnya.
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
