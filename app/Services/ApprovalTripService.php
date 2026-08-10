<?php

namespace App\Services;

use App\Models\ApprovalTripModel;

class ApprovalTripService
{
    use GridPipeline;

    protected $approvalTripModel;

    public function __construct()
    {
        $this->approvalTripModel = new ApprovalTripModel();
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
     * Seluruh kunci (FID) yang lolos filter saat ini — dipakai fitur "pilih
     * semua" pada grid, yang tidak boleh terbatas pada baris yang kebetulan
     * sudah dimuat lazy loading. Memakai pipeline filter yang sama dengan
     * getGridList(), jadi isinya dijamin sama dengan yang tampil.
     */
    public function getFilteredKeys(array $params): array
    {
        return $this->keysOf($this->rowsApproval($params), $params, 'FID');
    }

    // ------------------------------------------------------------------
    // Aksi
    // ------------------------------------------------------------------

    /**
     * Menjalankan approve ($prosesdata '0') atau un-approve ($prosesdata '1')
     * untuk sekumpulan FID. Arahnya datang dari route yang dipanggil, bukan
     * dari field POST seperti CI3 -- di sana `prosesdata` dikirim bersama
     * daftar id, sehingga siapa pun yang boleh approve otomatis juga bisa
     * un-approve hanya dengan menukar satu nilai form.
     *
     * Kelayakan tiap baris TIDAK lagi diperiksa lewat query terpisah. Syaratnya
     * kini menyatu di WHERE milik UPDATE (lihat ApprovalTripModel::setApproval),
     * jadi pemeriksaan dan penulisan terjadi bersamaan -- tidak ada celah bagi
     * user lain untuk mengubah status di antara keduanya.
     */
    public function processApproval(array $ids, string $prosesdata, string $tgl, ?string $userId): array
    {
        if ($userId === null || trim($userId) === '') {
            return $this->hasilProses('Sesi Anda telah berakhir. Silakan login ulang.');
        }

        // Hanya 0 (approve) & 1 (un-approve) yang dikenali. Tanpa penjagaan ini,
        // nilai lain lolos tanpa menjalankan UPDATE apa pun tapi tetap dilaporkan
        // berhasil -- persis perilaku CI3, yang if/else-nya tidak punya cabang
        // penutup sehingga $update tinggal memakai nilai dari perulangan sebelumnya.
        if (!in_array($prosesdata, ['0', '1'], true)) {
            return $this->hasilProses('Jenis proses tidak dikenali.');
        }

        // Tanggal WAJIB dikirim & tidak boleh ditebak: ia ikut jadi syarat WHERE
        // pada UPDATE, jadi menggantinya diam-diam dengan hari ini berisiko
        // memproses baris yang tidak pernah dilihat user.
        if (trim($tgl) === '') {
            return $this->hasilProses('Tanggal tidak terkirim. Muat ulang halaman lalu coba lagi.');
        }

        // is_scalar: isi `ids` berasal dari JSON kiriman klien, jadi elemennya bisa
        // saja array/objek -- strval() atas nilai begitu memicu error, bukan sekadar
        // hasil yang salah. strval: value checkbox HTML selalu string. unique:
        // duplikat dibuang supaya satu baris tidak diproses dua kali.
        $ids = array_values(array_unique(array_map('strval', array_filter($ids, 'is_scalar'))));

        if ($ids === []) {
            return $this->hasilProses('Tidak ada baris yang dipilih.');
        }

        // Status yang dituju: approve -> FIsApp 1, un-approve -> FIsApp 0.
        $statusTujuan = $prosesdata === '0' ? 1 : 0;

        $berhasil = 0;
        $gagal    = [];
        $dilewati = [];

        foreach ($ids as $id) {
            $terpengaruh = $this->approvalTripModel->setApproval($id, $userId, $statusTujuan, $tgl);
            $pesanDb     = $this->approvalTripModel->pesanError();

            if ($pesanDb !== '') {
                $gagal[] = ['id' => $id, 'pesan' => $pesanDb];
                continue;
            }

            // 0 baris terpengaruh berarti barisnya sudah tidak memenuhi syarat:
            // status keburu diubah user lain, atau bukan dari tanggal ini.
            if ($terpengaruh < 1) {
                $dilewati[] = $id;
                continue;
            }

            $berhasil++;
        }

        return $this->rangkumProses($prosesdata, count($ids), $berhasil, $gagal, $dilewati);
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    private function rowsApproval(array $params): array
    {
        $tgl = (string)($params['tgl'] ?? date('Y-m-d'));
        $bit = (int)($params['bit'] ?? 0);

        // Selain 0/1 tidak punya arti untuk kolom bit; dibulatkan ke 0 supaya
        // nilai asal dari query string tidak berujung daftar kosong yang
        // membingungkan.
        $bit = in_array($bit, [0, 1], true) ? $bit : 0;

        return $this->mapRows($this->approvalTripModel->getData($tgl, $bit));
    }

    /**
     * Memetakan baris mentah ke struktur kolom jqGrid (associative array
     * key-value) untuk SELURUH data, bukan hanya 1 halaman, karena pencarian
     * & sorting harus berjalan di atas seluruh data.
     *
     * Kolomnya sama persis dengan CI3 (ApprovalTrip::ajax_approved): Tanggal,
     * Jumlah Trip, Mandor, User.
     */
    private function mapRows(array $list): array
    {
        $rows = [];

        foreach ($list as $d) {
            $id = (string)($d['FID'] ?? '');

            // Nama mandor digabung ke kodenya, mengikuti CI3. JOIN-nya LEFT,
            // jadi FNMandor bisa NULL untuk kode yang tidak ada di MMandor --
            // tanpa penjagaan ini barisnya tampil sebagai "MDR01 ()".
            $namaMandor = trim((string)($d['FNMandor'] ?? ''));
            $kodeMandor = trim((string)($d['FMandor'] ?? ''));

            $rows[] = [
                'id'       => $id, // dipakai lazyLoadingGridMonolith.js sbg row identity
                'IdTarget' => $id,
                'FID'      => $id,
                'FTgl'     => $this->formatTanggal($d['FTgl'] ?? ''),
                'FJlhTrip' => $d['FJlhTrip'] ?? '',
                'FMandor'  => $namaMandor !== '' ? $kodeMandor . ' (' . $namaMandor . ')' : $kodeMandor,
                'FUserID'  => $d['FUserID'] ?? '',
            ];
        }

        return $rows;
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
}
