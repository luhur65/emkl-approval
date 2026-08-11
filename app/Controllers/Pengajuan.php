<?php

namespace App\Controllers;

use App\Services\PengajuanSupirSerapService;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Pengajuan Supir Serap -- sisi ENTRI, dipakai MANDOR. Port dari CI3
 * application/controllers/Pengajuan.php.
 *
 * Jangan tertukar dengan ApprovalPengajuan: itu sisi APPROVAL atas tabel yang
 * sama (TrApprovalAbsensi) dan dipakai user kantor dengan hak akses checkMenu().
 * Di sini penjaganya checkMenuMandor(..., 'PENGAJUANSUPIRSERAP') -- sama dengan
 * yang dipakai sidebar untuk menampilkan menunya.
 *
 * CI3 sama sekali tidak memeriksa hak akses menu di controller ini, hanya sesi
 * login: user mana pun yang sudah login bisa membuat maupun menghapus pengajuan
 * lewat URL langsung, walau menunya tidak muncul untuknya.
 */
class Pengajuan extends BaseController
{
    /**
     * Dipakai pada pesan kegagalan koneksi. Data modul ini ada di database
     * terpisah (grup `dbtruck2`), yang bisa saja tidak terjangkau padahal
     * database utama sehat -- jadi pesannya perlu menyebut yang mana.
     */
    private const NAMA_DB = 'Trucking (dbtruck2)';

    protected PengajuanSupirSerapService $service;

    public function __construct()
    {
        $this->service = new PengajuanSupirSerapService();
    }

    public function index()
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        // Dropdown-nya dimuat saat render, bukan lewat AJAX: isinya master yang
        // jarang berubah & tidak banyak (36 kendaraan, 34 supir pada data
        // Surabaya). Bila databasenya sedang tak terjangkau, halaman tetap
        // dirender dengan dropdown kosong -- grid akan melaporkan sendiri.
        try {
            $kendaraan = $this->service->daftarKendaraan();
            $supir     = $this->service->daftarSupir();
        } catch (DatabaseException $e) {
            log_message('error', 'Master pengajuan supir serap gagal dimuat: ' . $e->getMessage());
            $kendaraan = [];
            $supir     = [];
        }

        return $this->render('pengajuan/index', [
            'title'     => 'Pengajuan Supir Serap',
            'kendaraan' => $kendaraan,
            'supir'     => $supir,
        ]);
    }

    public function ajax_list()
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        $params = array_merge($this->request->getGet(), $this->request->getPost());

        try {
            return $this->response->setJSON($this->service->getGridList($params));
        } catch (DatabaseException $e) {
            return $this->jsonErrorDatabase($e, self::NAMA_DB);
        }
    }

    /**
     * CI3 menamai method ini `approved()` padahal isinya INSERT pengajuan baru,
     * sama sekali bukan approval. Dinamai ulang di sini supaya tidak tertukar
     * dengan approve yang sesungguhnya di ApprovalPengajuan::approved().
     */
    public function simpan()
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        try {
            $hasil = $this->service->simpan(
                $this->request->getPost(),
                session()->get('FUserID')
            );
        } catch (DatabaseException $e) {
            return $this->jsonErrorDatabase($e, self::NAMA_DB);
        }

        return $this->response->setJSON($hasil);
    }

    /**
     * Hanya menerima POST (didaftarkan begitu di Routes). CI3 memakai
     * `pengajuan/delete/$id` yang juga terbuka lewat GET, sehingga sebuah
     * <img src> di halaman mana pun bisa menghapus data milik user yang sedang
     * login.
     */
    public function hapus($id = '')
    {
        if ($tolak = $this->denyIfNoAccess()) {
            return $tolak;
        }

        try {
            $hasil = $this->service->hapus((string) $id, session()->get('FUserID'));
        } catch (DatabaseException $e) {
            return $this->jsonErrorDatabase($e, self::NAMA_DB);
        }

        return $this->response->setJSON($hasil);
    }

    /**
     * Permintaan AJAX dibalas JSON 403 -- BUKAN redirect -- karena jQuery
     * mengikuti redirect diam-diam lalu menyerahkan HTML halaman tujuan ke
     * handler success, sehingga penolakan terbaca sebagai keberhasilan.
     */
    private function denyIfNoAccess(): ?ResponseInterface
    {
        if (checkMenuMandor(session()->get('FUserID'), 'PENGAJUANSUPIRSERAP')) {
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
