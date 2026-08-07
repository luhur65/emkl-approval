<?php

namespace App\Services;

use App\Models\ApprovalPhargaModel;

/**
 * Melayani DUA grid sekaligus, sesuai dua halaman di CI3:
 *   - approval penawaran harga (ApprovalPHarga::index)
 *   - approval cetak ulang     (ApprovalPHarga::cetak)
 *
 * Keduanya berbagi bentuk kolom & pipeline yang sama; yang berbeda hanya
 * sumber datanya dan aksi yang dijalankan.
 */
class ApprovalPhargaService
{
    use GridPipeline;

    protected $approvalPhargaModel;

    public function __construct()
    {
        $this->approvalPhargaModel = new ApprovalPhargaModel();
    }

    // ------------------------------------------------------------------
    // Grid: approval penawaran harga
    // ------------------------------------------------------------------

    public function getGridList(array $params): \stdClass
    {
        return $this->buildGrid($this->rowsApproval($params), $params);
    }

    public function getFilteredKeys(array $params): array
    {
        return $this->keysOf($this->rowsApproval($params), $params, 'FNTrans');
    }

    // ------------------------------------------------------------------
    // Grid: approval cetak ulang
    // ------------------------------------------------------------------

    public function getGridListCetak(array $params): \stdClass
    {
        return $this->buildGrid($this->rowsCetak($params), $params);
    }

    public function getFilteredKeysCetak(array $params): array
    {
        return $this->keysOf($this->rowsCetak($params), $params, 'FNTrans');
    }

    // ------------------------------------------------------------------
    // Aksi
    // ------------------------------------------------------------------

    /**
     * Approve ($prosesdata '0') atau un-approve ($prosesdata '1') sekumpulan
     * No Transaksi. Arahnya datang dari route yang dipanggil, bukan dari field
     * POST -- lihat ApprovalPharga::jalankanProses().
     *
     * Di CI3 arah proses dikirim sebagai field `prosesdata` bersama daftar id,
     * sehingga siapa pun yang boleh approve otomatis juga bisa un-approve hanya
     * dengan menukar satu nilai form.
     */
    public function processApproval(array $ids, string $prosesdata, string $tgl, ?string $userId): array
    {
        $penjaga = $this->periksaPermintaan($ids, $tgl, $userId);

        if ($penjaga !== null) {
            return $penjaga;
        }

        // Hanya 0 (approve) & 1 (un-approve) yang dikenali. Tanpa penjagaan ini,
        // nilai lain lolos tanpa menjalankan UPDATE apa pun tapi tetap dilaporkan
        // berhasil -- persis perilaku CI3, yang if/else-nya tidak punya cabang
        // penutup sehingga $sql tinggal memakai nilai dari perulangan sebelumnya.
        if (!in_array($prosesdata, ['0', '1'], true)) {
            return $this->hasilProses('Jenis proses tidak dikenali.');
        }

        $statusTujuan = $prosesdata === '0' ? 1 : 0;
        $aksi         = $prosesdata === '0' ? 'Approve' : 'Un Approve';

        return $this->jalankanPerBaris(
            $this->normalkanIds($ids),
            $aksi,
            fn (string $id) => $this->approvalPhargaModel->setApproval($id, (string) $userId, $statusTujuan, $tgl)
        );
    }

    /**
     * Meng-approve permintaan cetak ulang: FIsCetak kembali ke 0. Satu arah
     * saja, jadi tidak ada parameter jenis proses.
     */
    public function processCetak(array $ids, string $tgl, ?string $userId): array
    {
        $penjaga = $this->periksaPermintaan($ids, $tgl, $userId);

        if ($penjaga !== null) {
            return $penjaga;
        }

        return $this->jalankanPerBaris(
            $this->normalkanIds($ids),
            'Approve cetak ulang',
            fn (string $id) => $this->approvalPhargaModel->setCetak($id, $tgl)
        );
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

        return $this->mapRows($this->approvalPhargaModel->getData($tgl, $bit));
    }

    private function rowsCetak(array $params): array
    {
        $tgl = (string)($params['tgl'] ?? date('Y-m-d'));

        return $this->mapRows($this->approvalPhargaModel->getDataCetak($tgl));
    }

    /**
     * Memetakan baris mentah ke struktur kolom jqGrid (associative array
     * key-value) untuk SELURUH data, bukan hanya 1 halaman, karena pencarian
     * & sorting harus berjalan di atas seluruh data.
     *
     * Kolomnya sama persis dengan CI3 (No Transaksi, Shipper, Tanggal,
     * Marketing, UP) -- dipakai bersama oleh kedua grid.
     */
    private function mapRows(array $list): array
    {
        $rows = [];

        foreach ($list as $d) {
            $noTrans = (string)($d['FNTrans'] ?? '');

            $rows[] = [
                'id'             => $noTrans, // dipakai lazyLoadingGridMonolith.js sbg row identity
                'IdTarget'       => $noTrans,
                'FNTrans'        => $noTrans,
                'FNShipper'      => $d['FNShipper'] ?? '',
                'FTgl'           => $this->formatTanggal($d['FTgl'] ?? ''),
                'FNamamarketing' => $d['FNamamarketing'] ?? '',
                'FUp'            => $d['FUp'] ?? '',
            ];
        }

        return $rows;
    }

    /**
     * Penjagaan yang sama untuk kedua aksi. Mengembalikan null bila permintaan
     * layak diteruskan.
     */
    private function periksaPermintaan(array $ids, string $tgl, ?string $userId): ?array
    {
        if ($userId === null || trim($userId) === '') {
            return $this->hasilProses('Sesi Anda telah berakhir. Silakan login ulang.');
        }

        // Tanggal WAJIB dikirim & tidak boleh ditebak: ia ikut jadi syarat WHERE
        // pada UPDATE, jadi menggantinya diam-diam dengan hari ini berisiko
        // memproses baris yang tidak pernah dilihat user.
        if (trim($tgl) === '') {
            return $this->hasilProses('Tanggal tidak terkirim. Muat ulang halaman lalu coba lagi.');
        }

        if ($this->normalkanIds($ids) === []) {
            return $this->hasilProses('Tidak ada baris yang dipilih.');
        }

        return null;
    }

    /**
     * is_scalar: isi `ids` berasal dari JSON kiriman klien, jadi elemennya bisa
     * saja array/objek -- strval() atas nilai begitu memicu error, bukan sekadar
     * hasil yang salah. strval: value checkbox HTML selalu string. unique:
     * duplikat dibuang supaya satu No Transaksi tidak diproses dua kali.
     */
    private function normalkanIds(array $ids): array
    {
        return array_values(array_unique(array_map('strval', array_filter($ids, 'is_scalar'))));
    }

    /**
     * Menjalankan satu UPDATE per No Transaksi lalu merangkum hasilnya.
     *
     * $jalankan mengembalikan jumlah baris terpengaruh: 0 berarti barisnya sudah
     * tidak memenuhi syarat (status keburu berubah, atau bukan dari tanggal ini)
     * -- itu DILEWATI, bukan gagal, dan bukan pula "berhasil" seperti laporan
     * CI3 yang hanya melihat ada-tidaknya pesan error.
     */
    private function jalankanPerBaris(array $ids, string $aksi, callable $jalankan): array
    {
        $berhasil = 0;
        $gagal    = [];
        $dilewati = [];

        foreach ($ids as $id) {
            $terpengaruh = $jalankan($id);
            $pesanDb     = $this->approvalPhargaModel->pesanError();

            if ($pesanDb !== '') {
                $gagal[] = ['id' => $id, 'pesan' => $pesanDb];
                continue;
            }

            if ($terpengaruh < 1) {
                $dilewati[] = $id;
                continue;
            }

            $berhasil++;
        }

        return $this->rangkumProses($aksi, count($ids), $berhasil, $gagal, $dilewati);
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
    private function rangkumProses(string $aksi, int $total, int $berhasil, array $gagal, array $dilewati): array
    {
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
