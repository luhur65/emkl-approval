<?php

namespace App\Services;

use App\Models\ApprovalTopModel;

class ApprovalTopService
{
    use GridPipeline;

    protected $approvalTopModel;

    public function __construct()
    {
        $this->approvalTopModel = new ApprovalTopModel();
    }

    // ------------------------------------------------------------------
    // Grid
    // ------------------------------------------------------------------

    /**
     * Memproses pengambilan data Grid dengan standar pagination, sorting,
     * dan pencarian (toolbar per-kolom & pencarian global) jqGrid.
     * Menerima array parameter GET/POST dari controller.
     */
    public function getGridList(array $params): \stdClass
    {
        return $this->buildGrid($this->rowsApproval($params), $params);
    }

    /**
     * Seluruh kunci (FJurnal) yang lolos filter saat ini — dipakai fitur
     * "pilih semua" pada grid, yang tidak boleh terbatas pada baris yang
     * kebetulan sudah dimuat lazy loading. Memakai pipeline filter yang sama
     * dengan getGridList(), jadi isinya dijamin sama dengan yang tampil.
     */
    public function getFilteredKeys(array $params): array
    {
        return $this->keysOf($this->rowsApproval($params), $params, 'FJurnal');
    }

    /**
     * Detail invoice per FJurnal untuk subgrid, dalam bentuk respons standar
     * subgrid jqGrid ({ rows: [...] }, sesuai jsonReader.subgrid.root bawaan).
     * Isinya dirender grid.subgrid.js sendiri lewat subGridModel di view, jadi
     * urutan & judul kolom TIDAK ditentukan di sini -- hanya nama kuncinya yang
     * harus cocok dengan `mapping` di sana.
     *
     * Sama seperti kolom grid utama, tanggal & uang diformat di SINI, bukan di
     * klien: satu-satunya tempat yang tahu bentuk mentah kiriman SP adalah
     * server, dan menyebar aturan formatnya ke view membuat dua modul yang
     * menampilkan angka sama jadi mudah berbeda.
     */
    public function getSubGridList(string $jurnal): \stdClass
    {
        $rows = [];
        foreach ($this->approvalTopModel->getDetail($jurnal) as $d) {
            $rows[] = [
                'FNInvoice'       => $d['FNInvoice'] ?? '',
                'FNPiutg'         => $d['FNPiutg'] ?? '',
                'FTglInvoice'     => $this->formatTanggal($d['FTglInvoice'] ?? ''),
                'FNominalInvoice' => $this->formatUang($d['FNominalInvoice'] ?? 0),
                // Sengaja string, bukan angka: renderer subgrid bawaan menulis
                // sel dengan `nilai || '&#160;'`, sehingga angka 0 tampil sebagai
                // sel kosong sedangkan string "0" tetap tercetak.
                'FJumlahHari'     => (string) ($d['FJumlahHari'] ?? ''),
                'FTop'            => (string) ($d['FTop'] ?? ''),
            ];
        }

        $responce       = new \stdClass();
        $responce->rows = $rows;

        return $responce;
    }

    // ------------------------------------------------------------------
    // Aksi
    // ------------------------------------------------------------------

    /**
     * Menjalankan approve/un-approve untuk sekumpulan FJurnal.
     *
     * Mengembalikan ringkasan per-baris, bukan sekadar satu string error
     * gabungan seperti versi CI3. Alasannya: usp_AppPreJob & usp_UnAppPreJob
     * sama sekali tidak melaporkan berapa baris yang benar-benar terpengaruh
     * (tanpa TRY/CATCH, tanpa RAISERROR, tanpa @@ROWCOUNT) -- jadi FJurnal yang
     * sudah tidak memenuhi syarat, entah keburu diproses user lain atau sisa
     * seleksi dari kombinasi tanggal/status sebelumnya, akan meng-update 0 baris
     * namun tetap terlaporkan "Proses berhasil".
     */
    public function processApproval(array $ids, string $prosesdata, string $tgl, ?string $userId): array
    {
        if ($userId === null || trim($userId) === '') {
            return $this->hasilProses('Sesi Anda telah berakhir. Silakan login ulang.');
        }

        // Hanya 0 (approve) & 1 (un-approve) yang dikenali. Tanpa penjagaan ini,
        // nilai lain lolos tanpa memanggil SP apa pun tapi tetap dilaporkan
        // berhasil -- persis perilaku versi sebelumnya.
        if (!in_array($prosesdata, ['0', '1'], true)) {
            return $this->hasilProses('Jenis proses tidak dikenali.');
        }

        // Tanggal WAJIB dikirim & tidak boleh ditebak: ia menentukan himpunan baris
        // mana yang dianggap layak di bawah. Menggantinya diam-diam dengan hari ini
        // berisiko memproses baris yang tidak pernah dilihat user.
        if (trim($tgl) === '') {
            return $this->hasilProses('Tanggal tidak terkirim. Muat ulang halaman lalu coba lagi.');
        }

        // is_scalar: isi `ids` berasal dari JSON kiriman klien, jadi elemennya bisa
        // saja array/objek -- strval() atas nilai begitu memicu error, bukan sekadar
        // hasil yang salah. strval: value checkbox HTML selalu string. unique:
        // duplikat harus dibuang supaya satu baris tidak diproses dua kali -- pada
        // un-approve itu berarti dua kali notifikasi WhatsApp, karena
        // usp_UnAppPreJob memanggil usp_GetReminderAppPreJob.
        $ids = array_values(array_unique(array_map('strval', array_filter($ids, 'is_scalar'))));

        if ($ids === []) {
            return $this->hasilProses('Tidak ada baris yang dipilih.');
        }

        $layak = array_flip($this->getEligibleKeys($tgl, $prosesdata));

        $berhasil = 0;
        $gagal    = [];
        $dilewati = [];

        foreach ($ids as $id) {
            if (!isset($layak[$id])) {
                $dilewati[] = $id;
                continue;
            }

            $hasil   = $this->approvalTopModel->processApproval($id, $userId, $prosesdata);
            $pesanDb = trim($hasil->error_sql ?? '');

            if ($pesanDb !== '') {
                $gagal[] = ['id' => $id, 'pesan' => $pesanDb];
                continue;
            }

            $berhasil++;
        }

        return $this->rangkumProses($prosesdata, count($ids), $berhasil, $gagal, $dilewati);
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    /**
     * FJurnal yang saat ini benar-benar berstatus sesuai `$prosesdata` pada
     * tanggal `$tgl`. Sengaja memakai Stored Procedure yang sama dengan pengisi
     * grid, bukan query tabel langsung: definisi "memenuhi syarat" jadi persis
     * sama dengan yang dilihat user, tanpa menambah ketergantungan baru ke skema
     * tabel MShipperApprovalValidasiTOPHeader.
     */
    private function getEligibleKeys(string $tgl, string $prosesdata): array
    {
        $rows = $this->approvalTopModel->getData($tgl, $prosesdata);

        return array_map('strval', array_column($rows, 'FJurnal'));
    }

    /**
     * Balasan seragam saat proses ditolak sebelum satu baris pun dijalankan.
     * Field `error` & `msg` dipertahankan supaya pemanggil lama yang hanya
     * membaca keduanya tetap berfungsi.
     */
    private function hasilProses(string $pesan): array
    {
        return [
            'error'    => $pesan,
            'msg'      => $pesan,
            'total'    => 0,
            'berhasil' => 0,
            'gagal'    => [],
            'dilewati' => [],
        ];
    }

    /**
     * Menyusun pesan akhir dari hitungan berhasil / gagal / dilewati.
     */
    private function rangkumProses(string $prosesdata, int $total, int $berhasil, array $gagal, array $dilewati): array
    {
        $aksi = $prosesdata === '0' ? 'Approve' : 'Un Approve';

        if ($berhasil === $total) {
            $msg = $aksi . ' berhasil untuk ' . $berhasil . ' data.';
        } elseif ($berhasil === 0) {
            $msg = $aksi . ' tidak memproses satu data pun dari ' . $total . ' data yang dipilih.';
        } else {
            $msg = $aksi . ' berhasil untuk ' . $berhasil . ' dari ' . $total . ' data.';
        }

        return [
            'error'    => implode("\n", array_column($gagal, 'pesan')),
            'msg'      => $msg,
            'total'    => $total,
            'berhasil' => $berhasil,
            'gagal'    => $gagal,
            'dilewati' => $dilewati,
        ];
    }

    /**
     * Seluruh baris tampilan untuk kombinasi tanggal/status yang diminta.
     * Belum disaring, diurutkan, atau dipotong: pencarian, sorting, & paging
     * dikerjakan GridPipeline di atas himpunan utuh ini.
     */
    private function rowsApproval(array $params): array
    {
        $tgl = $params['tgl'] ?? date("Y-m-d");
        $bit = $params['bit'] ?? 0;

        return $this->mapRows($this->approvalTopModel->getData($tgl, $bit));
    }

    /**
     * Memetakan baris mentah hasil Stored Procedure ke struktur kolom jqGrid
     * (associative array key-value) untuk seluruh data, bukan hanya 1 halaman,
     * karena pencarian & sorting harus berjalan di atas seluruh data.
     */
    private function mapRows(array $list): array
    {
        $rows = [];
        foreach ($list as $d) {
            $rows[] = [
                'id'             => $d['FJurnal'], // dipakai lazyLoadingGridMonolith.js sbg row identity
                'IdTarget'       => $d['FJurnal'],
                'FJurnal'        => $d['FJurnal'],
                'FNShipper'      => $d['FNShipper'],
                'FTgl'           => date("d-m-Y", strtotime($d['FTgl'])),
                'FNMarketing'    => $d['FNMarketing'],
                'FJumlahInvoice' => $d['FJumlahInvoice'],
                'FJumlahjob'     => $d['FJumlahjob'],
            ];
        }

        return $rows;
    }
}
