<?php

namespace App\Services;

use App\Models\ApprovalPoModel;

class ApprovalPoService
{
    use GridPipeline;

    protected $approvalPoModel;

    public function __construct()
    {
        $this->approvalPoModel = new ApprovalPoModel();
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
     * Seluruh kunci (FNTrans) yang lolos filter saat ini — dipakai fitur
     * "pilih semua" pada grid, yang tidak boleh terbatas pada baris yang
     * kebetulan sudah dimuat lazy loading. Memakai pipeline filter yang sama
     * dengan getGridList(), jadi isinya dijamin sama dengan yang tampil.
     */
    public function getFilteredKeys(array $params): array
    {
        return $this->keysOf($this->rowsApproval($params), $params, 'FNTrans');
    }

    // ------------------------------------------------------------------
    // Aksi
    // ------------------------------------------------------------------

    /**
     * Menjalankan approve ($prosesdata '0') atau un-approve ($prosesdata '1')
     * untuk sekumpulan No Bukti. Arahnya datang dari route yang dipanggil,
     * bukan dari status baris -- lihat ApprovalPo::jalankanProses().
     *
     * Berbeda dari ApprovalTop, grid modul ini menampilkan kedua status
     * sekaligus (usp_GetListPermintaanApprovalorder tidak punya parameter bit),
     * jadi user memang bisa memilih baris yang tidak cocok dengan tombol yang
     * ia tekan. Baris seperti itu DILEWATI, bukan dibalik diam-diam.
     *
     * Mengembalikan ringkasan per-baris, bukan satu string error gabungan:
     * usp_UpdTPermintaanApprovalOrder hanya UPDATE polos -- tanpa TRY/CATCH,
     * tanpa RAISERROR, tanpa @@ROWCOUNT -- jadi tanpa rincian ini No Bukti yang
     * meng-update 0 baris akan tetap terlaporkan "berhasil".
     */
    public function processApproval(array $ids, string $prosesdata, string $tgl, ?string $userId): array
    {
        if ($userId === null || trim($userId) === '') {
            return $this->hasilProses('Sesi Anda telah berakhir. Silakan login ulang.');
        }

        // Hanya 0 (approve) & 1 (un-approve) yang dikenali. Tanpa penjagaan ini,
        // nilai lain lolos tanpa memanggil SP apa pun tapi tetap dilaporkan berhasil.
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
        // duplikat dibuang supaya satu No Bukti tidak diproses dua kali.
        $ids = array_values(array_unique(array_map('strval', array_filter($ids, 'is_scalar'))));

        if ($ids === []) {
            return $this->hasilProses('Tidak ada baris yang dipilih.');
        }

        // Status yang dituju: approve -> FIsApp 1, un-approve -> FIsApp 0.
        $statusTujuan = $prosesdata === '0' ? 1 : 0;

        $layak = $this->getEligibleStatuses($tgl);

        $berhasil = 0;
        $gagal    = [];
        $dilewati = [];

        foreach ($ids as $id) {
            if (!array_key_exists($id, $layak)) {
                $dilewati[] = $id;
                continue;
            }

            // Sudah berada di status yang diminta -> tidak ada yang perlu dikerjakan.
            // Menjalankannya tetap tidak akan error (UPDATE-nya idempoten), tapi akan
            // ikut terhitung "berhasil" & menimpa FUserApp/FDateApp milik orang lain
            // dengan data user ini.
            if ($this->statusTerkini($id, $layak[$id]) === $statusTujuan) {
                $dilewati[] = $id;
                continue;
            }

            // Validasi bisnis dijalankan per No Bukti, sama seperti CI3: SP-nya
            // me-raise error bila baris ybs tidak boleh diproses.
            $pesanValidasi = trim($this->approvalPoModel->getValidasi($id)->error_sql ?? '');

            if ($pesanValidasi !== '') {
                $gagal[] = ['id' => $id, 'pesan' => $pesanValidasi];
                continue;
            }

            $pesanDb = trim($this->approvalPoModel->processApproval($userId, $id, $statusTujuan)->error_sql ?? '');

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
     * Peta FNTrans => FIsApp untuk seluruh baris pada tanggal tsb. Sengaja
     * memakai Stored Procedure yang sama dengan pengisi grid, bukan query tabel
     * langsung: definisi "boleh diproses" jadi persis sama dengan yang dilihat
     * user, tanpa menambah ketergantungan baru ke skema tabel.
     */
    private function getEligibleStatuses(string $tgl): array
    {
        $peta = [];

        foreach ($this->approvalPoModel->getData($tgl) as $row) {
            if (!isset($row['FNTrans'])) {
                continue;
            }

            $peta[(string) $row['FNTrans']] = (int)($row['FIsApp'] ?? 0);
        }

        return $peta;
    }

    /**
     * Status approval terkini, dipakai untuk menentukan apakah baris ini masih
     * perlu diproses. Dibaca ulang lewat SP (seperti CI3) supaya perubahan yang
     * terjadi setelah grid dimuat ikut terbaca; nilai dari grid hanya dipakai
     * bila SP tidak mengembalikan baris.
     */
    private function statusTerkini(string $fntrans, int $bawaan): int
    {
        $data = $this->approvalPoModel->getPermintaanApprovalOrder($fntrans);

        return isset($data[0]['FIsApp']) ? (int) $data[0]['FIsApp'] : $bawaan;
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
     * Seluruh baris tampilan pada tanggal yang diminta. Belum disaring,
     * diurutkan, atau dipotong: pencarian, sorting, & paging dikerjakan
     * GridPipeline di atas himpunan utuh ini.
     */
    private function rowsApproval(array $params): array
    {
        $tgl = $params['tgl'] ?? date("Y-m-d");

        return $this->mapRows($this->approvalPoModel->getData($tgl));
    }

    /**
     * Memetakan baris mentah hasil Stored Procedure ke struktur kolom jqGrid
     * (associative array key-value) untuk seluruh data, bukan hanya 1 halaman,
     * karena pencarian & sorting harus berjalan di atas seluruh data.
     *
     * Kolom uang sudah diformat di SINI lewat GridPipeline::formatUang(), bukan
     * di formatter jqGrid sisi klien. Pencarian & sorting berjalan di server,
     * jadi kalau server hanya memegang angka mentah sementara layar menampilkan
     * "2,736,213,726.00", kata kunci yang diketik user PERSIS seperti yang ia
     * lihat ("2,7") dicocokkan ke "2736213726.00" dan tidak akan pernah ketemu.
     * Dengan format ditentukan di satu tempat, apa yang dicari = apa yang
     * tampil. Urutan angkanya tetap benar karena compareForSort() membaca bentuk
     * berkoma ini sebagai angka.
     */
    private function mapRows(array $list): array
    {
        $rows = [];

        foreach ($list as $d) {
            $noBukti = (string)($d['FNTrans'] ?? '');

            $rows[] = [
                'id'                => $noBukti, // dipakai lazyLoadingGridMonolith.js sbg row identity
                'IdTarget'          => $noBukti,
                'FNTrans'           => $noBukti,
                'FTgl'              => $this->formatTanggal($d['FTgl'] ?? ''),
                'FNShipper'         => $d['FNShipper'] ?? '',
                'FSaldoPiutang'     => $this->formatUang($d['FSaldoPiutang'] ?? 0),
                'FSisaPiutang'      => $this->formatUang($d['FSisaPiutang'] ?? 0),
                'FKelebihanPiutang' => $this->formatUang($d['FKelebihanPiutang'] ?? 0),
                'FJumlahOrder'      => $this->formatUang($d['FJumlahOrder'] ?? 0),
                'FIsApp'            => (int)($d['FIsApp'] ?? 0),
                'FDateApp'          => $this->formatTanggal($d['FDateApp'] ?? '', true),
                'FUserApp'          => $d['FUserApp'] ?? '',
                'FTglInput'         => $this->formatTanggal($d['FTglInput'] ?? '', true),
                'FUserId'           => $d['FUserId'] ?? '',
            ];
        }

        return $rows;
    }
}
