<?php

namespace App\Controllers;

use App\Models\ApprovalTripModel;
use App\Services\ApprovalTripService;
use App\Services\PengajuanTripService;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Controller ini melayani DUA halaman dengan penonton berbeda, mengikuti
 * pembagian CI3:
 *
 *   /approvaltrip           -> ENTRI pengajuan trip, dipakai MANDOR.
 *                              Hak aksesnya checkMenuMandor(..., 'TRIP').
 *   /approvaltrip/approval  -> APPROVAL pengajuan, dipakai kantor.
 *                              Hak aksesnya checkMenu().
 *
 * Keduanya bekerja pada tabel yang sama (TrApprovalTripH) tapi tidak boleh
 * berbagi penjaga hak akses: mandor tidak boleh meng-approve pengajuannya
 * sendiri, dan sebaliknya user kantor tidak otomatis boleh membuat pengajuan.
 * Karena itu ada dua method penjaga terpisah di bawah.
 */
class ApprovalTrip extends BaseController
{
    protected $approvalTripModel;
    protected $approvalTripService;
    protected PengajuanTripService $pengajuanTripService;

    public function __construct()
    {
        $this->approvalTripModel = new ApprovalTripModel();
        $this->approvalTripService = new ApprovalTripService();
        $this->pengajuanTripService = new PengajuanTripService();
    }

    // ------------------------------------------------------------------
    // Sisi ENTRI (mandor) -- CI3: ApprovalTrip::index/simpan/delete/ajax_list
    // ------------------------------------------------------------------

    public function index()
    {
        if ($tolak = $this->denyIfNoMandorAccess()) {
            return $tolak;
        }

        try {
            $mandor = $this->pengajuanTripService->daftarMandor();
        } catch (DatabaseException $e) {
            // Halaman tetap dirender dengan dropdown kosong; grid & form akan
            // melaporkan sendiri kalau databasenya memang sedang tak terjangkau.
            log_message('error', 'Daftar mandor gagal dimuat: ' . $e->getMessage());
            $mandor = [];
        }

        return $this->render('approval/trip/pengajuan', [
            'title'  => 'Pengajuan Trip',
            'mandor' => $mandor,
        ]);
    }

    /** Isi grid daftar pengajuan pada satu tanggal (kedua status). */
    public function ajax_list_pengajuan()
    {
        if ($tolak = $this->denyIfNoMandorAccess()) {
            return $tolak;
        }

        $params = array_merge($this->request->getGet(), $this->request->getPost());

        try {
            return $this->response->setJSON($this->pengajuanTripService->getGridList($params));
        } catch (DatabaseException $e) {
            return $this->jsonErrorDatabase($e, self::NAMA_DB);
        }
    }

    public function simpan()
    {
        if ($tolak = $this->denyIfNoMandorAccess()) {
            return $tolak;
        }

        try {
            $hasil = $this->pengajuanTripService->simpan(
                $this->request->getPost(),
                session()->get('FUserID')
            );
        } catch (DatabaseException $e) {
            return $this->jsonErrorDatabase($e, self::NAMA_DB);
        }

        return $this->response->setJSON($hasil);
    }

    /**
     * Menghapus satu pengajuan. Hanya menerima POST (didaftarkan begitu di
     * Routes): CI3 memakai URL `delete/$id` yang juga terbuka lewat GET,
     * sehingga sebuah <img src> di halaman mana pun bisa menghapus data milik
     * user yang sedang login.
     */
    public function hapus($id = '')
    {
        if ($tolak = $this->denyIfNoMandorAccess()) {
            return $tolak;
        }

        try {
            $hasil = $this->pengajuanTripService->hapus(
                (string) $id,
                session()->get('FUserID')
            );
        } catch (DatabaseException $e) {
            return $this->jsonErrorDatabase($e, self::NAMA_DB);
        }

        return $this->response->setJSON($hasil);
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
        return checkMenu(session()->get('FUserID')) ? null : $this->tolak();
    }

    /**
     * Penjaga halaman ENTRI. Parameternya 'TRIP' -- sama persis dengan yang
     * dipakai sidebar untuk memutuskan menu "Trip" ditampilkan atau tidak
     * (Views/partials/sidebar.php), jadi menu yang terlihat dan halaman yang
     * bisa dibuka selalu sepakat.
     *
     * CI3 sama sekali tidak memeriksa ini di controller: ApprovalTrip::index()
     * hanya memeriksa sesi login, sehingga user mana pun yang sudah login bisa
     * membuat pengajuan trip atas nama mandor mana pun hanya dengan mengetik
     * URL-nya, walau menunya tidak muncul untuk dia.
     */
    private function denyIfNoMandorAccess(): ?ResponseInterface
    {
        return checkMenuMandor(session()->get('FUserID'), 'TRIP') ? null : $this->tolak();
    }

    /**
     * Permintaan AJAX dibalas JSON 403 -- BUKAN redirect -- karena jQuery
     * mengikuti redirect diam-diam lalu menyerahkan HTML halaman tujuan ke
     * handler success, sehingga penolakan terbaca sebagai keberhasilan.
     */
    private function tolak(): ResponseInterface
    {
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
