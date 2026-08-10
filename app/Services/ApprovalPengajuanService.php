<?php

namespace App\Services;

use App\Models\ApprovalPengajuanModel;

class ApprovalPengajuanService
{
    use GridPipeline;

    protected $approvalPengajuanModel;

    public function __construct()
    {
        $this->approvalPengajuanModel = new ApprovalPengajuanModel();
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
     * sudah dimuat lazy loading.
     */
    public function getFilteredKeys(array $params): array
    {
        return $this->keysOf($this->rowsApproval($params), $params, 'IdTarget');
    }

    // ------------------------------------------------------------------
    // Aksi
    // ------------------------------------------------------------------

    /**
     * Approve ($prosesdata '0') atau batalkan approve ($prosesdata '1') untuk
     * sekumpulan pengajuan.
     *
     * PENTING soal keamanan. Di CI3 status barisnya ditentukan field POST
     * `prosesdata` yang dikirim klien, dan FID yang dikirim di-update tanpa
     * pemeriksaan apa pun -- user bisa meng-approve pengajuan MANA SAJA,
     * termasuk milik tanggal yang tidak pernah muncul di layarnya.
     *
     * Di sini FID yang dikirim harus ada di daftar sisi server untuk tanggal &
     * status yang sedang aktif. Kunci yang tidak ada di daftar dilewati, jadi
     * kiriman yang dikarang tidak pernah sampai ke database.
     */
    public function processApproval(array $ids, string $prosesdata, string $tgl, ?string $userId): array
    {
        // FUserApp diisi nilai ini sebagai jejak siapa yang memproses, jadi ia
        // tidak boleh kosong.
        if ($userId === null || trim($userId) === '') {
            return $this->hasilProses('Sesi Anda telah berakhir. Silakan login ulang.');
        }

        // Hanya 0 (approve) & 1 (batal approve) yang dikenali. Tanpa penjagaan
        // ini, nilai lain lolos tanpa menjalankan apa pun tapi tetap dilaporkan
        // berhasil -- persis perilaku CI3, yang if/else-nya tidak punya cabang
        // penutup sehingga $update tinggal memakai nilai dari perulangan
        // sebelumnya (dan pada baris pertama malah memicu error variabel kosong).
        if (!in_array($prosesdata, ['0', '1'], true)) {
            return $this->hasilProses('Jenis proses tidak dikenali.');
        }

        // Tanggal WAJIB dikirim & tidak boleh ditebak: ia yang menentukan
        // himpunan baris yang dianggap layak di bawah. Menggantinya diam-diam
        // dengan hari ini berarti memproses pengajuan yang tidak pernah dilihat
        // user.
        if (trim($tgl) === '') {
            return $this->hasilProses('Tanggal tidak terkirim. Muat ulang halaman lalu coba lagi.');
        }

        // is_scalar: isi `ids` berasal dari JSON kiriman klien, jadi elemennya bisa
        // saja array/objek -- strval() atas nilai begitu memicu error, bukan sekadar
        // hasil yang salah. unique: duplikat dibuang supaya satu pengajuan tidak
        // diproses dua kali.
        $ids = array_values(array_unique(array_map('strval', array_filter($ids, 'is_scalar'))));

        if ($ids === []) {
            return $this->hasilProses('Tidak ada baris yang dipilih.');
        }

        // Daftar layak = isi grid untuk tanggal ini pada status yang sesuai
        // dengan aksinya. Approve mengambil dari daftar "belum approve" (bit 0),
        // batal approve dari daftar "sudah approve" (bit 1) -- sama seperti
        // yang dilihat user saat memilih.
        $layak = $this->kunciBarisLayak($tgl, $prosesdata === '0' ? 0 : 1);

        $berhasil = 0;
        $gagal    = [];
        $dilewati = [];

        foreach ($ids as $id) {
            if (!isset($layak[$id])) {
                $dilewati[] = $id;
                continue;
            }

            $terpengaruh = $prosesdata === '0'
                ? $this->approvalPengajuanModel->approve($id, $userId)
                : $this->approvalPengajuanModel->unapprove($id, $userId);

            $pesanDb = $this->approvalPengajuanModel->pesanError();

            if ($pesanDb !== '') {
                $gagal[] = ['id' => $id, 'pesan' => $pesanDb];
                continue;
            }

            // 0 baris terpengaruh berarti statusnya sudah tidak seperti yang
            // dilihat user: baris itu keburu diproses orang lain di sela-sela ini.
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

    /**
     * Seluruh baris tampilan pada tanggal & status yang diminta. Belum disaring,
     * diurutkan, atau dipotong: pencarian, sorting, & paging dikerjakan
     * GridPipeline di atas himpunan utuh ini.
     */
    private function rowsApproval(array $params): array
    {
        $tgl = trim((string)($params['tgl'] ?? '')) ?: date('Y-m-d');
        $bit = (int)($params['bit'] ?? 0);

        // Selain 0/1 tidak punya arti di sini; dibulatkan ke 0 supaya nilai asal
        // dari query string tidak berujung daftar kosong yang membingungkan.
        $bit = in_array($bit, [0, 1], true) ? $bit : 0;

        return $this->mapRows($this->approvalPengajuanModel->getData($tgl, $bit));
    }

    /**
     * Himpunan FID yang boleh diproses, sebagai peta agar pencariannya O(1).
     * Memakai query yang sama dengan pengisi grid, jadi definisi "boleh
     * diproses" persis sama dengan yang dilihat user.
     */
    private function kunciBarisLayak(string $tgl, int $bit): array
    {
        $peta = [];

        foreach ($this->approvalPengajuanModel->getData($tgl, $bit) as $d) {
            $fid = trim((string)($d['FID'] ?? ''));

            if ($fid !== '') {
                $peta[$fid] = true;
            }
        }

        return $peta;
    }

    /**
     * Memetakan baris mentah ke struktur kolom jqGrid untuk SELURUH data, bukan
     * hanya 1 halaman, karena pencarian & sorting harus berjalan di atas
     * seluruh data.
     *
     * Kolomnya sama dengan <thead> view CI3: Tanggal, No Polisi, Supir, Supir
     * Serap, Keterangan. FID tidak ikut ditampilkan -- di CI3 pun ia cuma isi
     * kolom checkbox, bukan informasi untuk dibaca.
     */
    private function mapRows(array $list): array
    {
        $rows = [];

        foreach ($list as $d) {
            $fid = trim((string)($d['FID'] ?? ''));

            $rows[] = [
                'id'            => $fid, // dipakai lazyLoadingGridMonolith.js sbg row identity
                'IdTarget'      => $fid,
                'FTgl'          => $this->formatTanggal($d['FTgl'] ?? ''),
                'FKGdg'         => (string)($d['FKGdg'] ?? ''),
                'FKSupir'       => (string)($d['FKSupir'] ?? ''),
                'FKSupir_Serap' => (string)($d['FKSupir_Serap'] ?? ''),
                'FKeterangan'   => (string)($d['FKeterangan'] ?? ''),
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
        $aksi = $prosesdata === '0' ? 'Approve' : 'Batal approve';

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
