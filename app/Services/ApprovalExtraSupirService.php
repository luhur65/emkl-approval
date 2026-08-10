<?php

namespace App\Services;

use App\Models\ApprovalExtraSupirModel;

class ApprovalExtraSupirService
{
    use GridPipeline;

    protected $approvalExtraSupirModel;

    public function __construct()
    {
        $this->approvalExtraSupirModel = new ApprovalExtraSupirModel();
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
     * Seluruh kunci (No Trip) yang lolos filter saat ini — dipakai fitur
     * "pilih semua" pada grid, yang tidak boleh terbatas pada baris yang
     * kebetulan sudah dimuat lazy loading.
     */
    public function getFilteredKeys(array $params): array
    {
        return $this->keysOf($this->rowsApproval($params), $params, 'FNTrans');
    }

    // ------------------------------------------------------------------
    // Aksi
    // ------------------------------------------------------------------

    /**
     * Approve ($prosesdata '0') atau batalkan approve ($prosesdata '1') untuk
     * sekumpulan No Trip.
     *
     * PENTING soal keamanan. Di CI3 klien mengirim SELURUH isi baris
     * (`$_POST['data']`), lalu kolom ke-4 tiap baris dirangkai langsung ke dalam
     * teks pemanggilan SP. Dua akibatnya: nilai apa pun bisa disisipkan sebagai
     * SQL, dan -- bahkan tanpa itu -- user bisa meng-approve No Trip MANA SAJA,
     * termasuk yang tidak pernah muncul di daftarnya.
     *
     * Di sini klien hanya mengirim KUNCI, dan kunci itu harus ada di daftar sisi
     * server untuk rentang tanggal & status yang sedang aktif. Kunci yang tidak
     * ada di daftar dilewati, jadi kiriman yang dikarang tidak pernah sampai ke
     * database.
     */
    public function processApproval(
        array $ids,
        string $prosesdata,
        string $tgldari,
        string $tglsampai,
        ?string $userNama
    ): array {
        // SP-nya menyimpan nilai ini sebagai jejak siapa yang meng-approve, jadi
        // ia tidak boleh kosong.
        if ($userNama === null || trim($userNama) === '') {
            return $this->hasilProses('Sesi Anda telah berakhir. Silakan login ulang.');
        }

        // Hanya 0 (approve) & 1 (batal approve) yang dikenali. Tanpa penjagaan
        // ini, nilai lain lolos tanpa memanggil SP apa pun tapi tetap dilaporkan
        // berhasil -- persis perilaku CI3, yang if/else-nya tidak punya cabang
        // penutup sehingga $sql tinggal memakai nilai dari perulangan sebelumnya.
        if (!in_array($prosesdata, ['0', '1'], true)) {
            return $this->hasilProses('Jenis proses tidak dikenali.');
        }

        // Rentang tanggal WAJIB dikirim & tidak boleh ditebak: ia menentukan
        // himpunan baris yang dianggap layak di bawah. Menggantinya diam-diam
        // dengan hari ini berarti memproses baris yang tidak pernah dilihat user.
        if (trim($tgldari) === '' || trim($tglsampai) === '') {
            return $this->hasilProses('Rentang tanggal tidak terkirim. Muat ulang halaman lalu coba lagi.');
        }

        // is_scalar: isi `ids` berasal dari JSON kiriman klien, jadi elemennya bisa
        // saja array/objek -- strval() atas nilai begitu memicu error, bukan sekadar
        // hasil yang salah. unique: duplikat dibuang supaya satu No Trip tidak
        // diproses dua kali.
        $ids = array_values(array_unique(array_map('strval', array_filter($ids, 'is_scalar'))));

        if ($ids === []) {
            return $this->hasilProses('Tidak ada baris yang dipilih.');
        }

        // Daftar layak = isi grid untuk rentang ini pada status yang sesuai
        // dengan aksinya. Approve mengambil dari daftar "belum approve"
        // (proses 0), batal approve dari daftar "sudah approve" (proses 1) --
        // sama seperti yang dilihat user saat memilih.
        $layak = $this->kunciBarisLayak($tgldari, $tglsampai, $prosesdata === '0' ? 0 : 1);

        $berhasil = 0;
        $gagal    = [];
        $dilewati = [];

        foreach ($ids as $id) {
            if (!isset($layak[$id])) {
                $dilewati[] = $id;
                continue;
            }

            $sukses = $prosesdata === '0'
                ? $this->approvalExtraSupirModel->approve($id, $userNama)
                : $this->approvalExtraSupirModel->unapprove($id, $userNama);

            if (!$sukses) {
                $gagal[] = ['id' => $id, 'pesan' => $this->approvalExtraSupirModel->pesanError()];
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
     * Seluruh baris tampilan pada rentang & status yang diminta. Belum disaring,
     * diurutkan, atau dipotong: pencarian, sorting, & paging dikerjakan
     * GridPipeline di atas himpunan utuh ini.
     */
    private function rowsApproval(array $params): array
    {
        [$tgldari, $tglsampai, $proses] = $this->filter($params);

        return $this->mapRows($this->approvalExtraSupirModel->getData($tgldari, $tglsampai, $proses));
    }

    /**
     * Membaca filter halaman dari parameter request.
     *
     * Rentang yang terbalik (dari > sampai) ditukar, bukan dibiarkan: SP-nya
     * memakai BETWEEN, yang pada rentang terbalik selalu menghasilkan 0 baris --
     * user hanya melihat grid kosong tanpa tahu sebabnya.
     */
    private function filter(array $params): array
    {
        $tgldari   = trim((string)($params['tgldari'] ?? '')) ?: date('Y-m-d');
        $tglsampai = trim((string)($params['tglsampai'] ?? '')) ?: date('Y-m-d');

        if (strtotime($tgldari) > strtotime($tglsampai)) {
            [$tgldari, $tglsampai] = [$tglsampai, $tgldari];
        }

        $proses = (int)($params['proses'] ?? 0);

        // Selain 0/1 tidak punya arti di sini; dibulatkan ke 0 supaya nilai asal
        // dari query string tidak berujung daftar kosong yang membingungkan.
        $proses = in_array($proses, [0, 1], true) ? $proses : 0;

        return [$tgldari, $tglsampai, $proses];
    }

    /**
     * Himpunan No Trip yang boleh diproses, sebagai peta agar pencariannya O(1).
     * Memakai SP yang sama dengan pengisi grid, jadi definisi "boleh diproses"
     * persis sama dengan yang dilihat user.
     */
    private function kunciBarisLayak(string $tgldari, string $tglsampai, int $proses): array
    {
        $peta = [];

        foreach ($this->approvalExtraSupirModel->getData($tgldari, $tglsampai, $proses) as $d) {
            $noTrip = trim((string)($d['FNTrans'] ?? ''));

            if ($noTrip !== '') {
                $peta[$noTrip] = true;
            }
        }

        return $peta;
    }

    /**
     * Memetakan baris mentah hasil Stored Procedure ke struktur kolom jqGrid
     * untuk SELURUH data, bukan hanya 1 halaman, karena pencarian & sorting
     * harus berjalan di atas seluruh data.
     *
     * CATATAN migrasi -- judul kolom di CI3 TIDAK cocok dengan datanya.
     * ApprovalExtraSupir::ajax_list() di CI3 menyusun 22 kolom posisional,
     * sementara nilai yang tersedia untuk itu hanya 21: "No Polisi" tidak pernah
     * diisi, dan kekurangannya ditutup dengan mengirim FCont DUA KALI (indeks 13
     * & 16). Akibatnya seluruh kolom mulai dari "Shipper" (indeks 6) bergeser
     * satu terhadap judulnya -- yang tampil di bawah "Shipper" sebenarnya No Job,
     * di bawah "Dari" sebenarnya kode pelanggan, dan seterusnya.
     *
     * Di sini tiap judul dipasangkan ke kolom SP yang memang sesuai. Bahwa
     * pasangannya pas -- 22 judul untuk 22 kolom, tanpa sisa dan tanpa duplikat,
     * termasuk FKGdg untuk "No Polisi" yang di CI3 tidak terpakai -- menegaskan
     * daftar judul itulah yang benar dan penyusun barisnyalah yang keliru.
     *
     * Kolom uang diformat di SINI lewat GridPipeline::formatUang(), bukan di
     * formatter jqGrid sisi klien. Pencarian & sorting berjalan di server, jadi
     * kalau server hanya memegang angka mentah sementara layar menampilkan
     * "1,250,000.00", kata kunci yang diketik user PERSIS seperti yang ia lihat
     * ("1,25") dicocokkan ke "1250000.00" dan tidak akan pernah ketemu.
     */
    private function mapRows(array $list): array
    {
        $rows = [];

        foreach ($list as $d) {
            $noTrip = trim((string)($d['FNTrans'] ?? ''));

            $rows[] = [
                'id'               => $noTrip, // dipakai lazyLoadingGridMonolith.js sbg row identity
                'IdTarget'         => $noTrip,
                'FKetByExtra'      => (string)($d['FKetByExtra'] ?? ''),
                'FNomSupirByExtra' => $this->formatUang($d['FNomSupirByExtra'] ?? 0),
                'FNomCustByExtra'  => $this->formatUang($d['FNomCustByExtra'] ?? 0),
                'FNTrans'          => $noTrip,
                'FTgl'             => $this->formatTanggal($d['FTgl'] ?? ''),
                // SP menyediakan kode pelanggan (FKPelanggan) sekaligus namanya
                // (NamaRelasi, hasil join ke VRelasi). Yang ditampilkan namanya;
                // kodenya jadi cadangan bila relasinya tidak ketemu di master.
                'FShipper'         => (string)($d['NamaRelasi'] ?? '') !== ''
                                        ? (string)$d['NamaRelasi']
                                        : (string)($d['FKPelanggan'] ?? ''),
                'FDari'            => (string)($d['FDari'] ?? ''),
                'FSampai'          => (string)($d['FSampai'] ?? ''),
                // Kolom FGajiSupir muncul dua kali di SELECT milik SP; yang
                // terbaca adalah yang terakhir, yaitu FGajiSupir + FGajiKenek.
                'FGajiSupir'       => $this->formatUang($d['FGajiSupir'] ?? 0),
                'FJnsOrder'        => (string)($d['FJnsOrder'] ?? ''),
                'FNoJob'           => (string)($d['FNoJob'] ?? ''),
                'FNoCont'          => (string)($d['FNoCont'] ?? ''),
                'FNoSeal'          => (string)($d['FNoSeal'] ?? ''),
                'FStatusCont'      => (string)($d['FStatusCont'] ?? ''),
                'FNamaGudang'      => (string)($d['FNamaGudang'] ?? ''),
                'FKGdg'            => (string)($d['FKGdg'] ?? ''),
                'FKSupir'          => (string)($d['FKSupir'] ?? ''),
                'FEMKL'            => (string)($d['FEMKL'] ?? ''),
                'FCont'            => (string)($d['FCont'] ?? ''),
                'FTujuan'          => (string)($d['FTujuan'] ?? ''),
                'FJobTrucking'     => (string)($d['FJobTrucking'] ?? ''),
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
