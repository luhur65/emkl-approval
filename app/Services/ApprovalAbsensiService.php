<?php

namespace App\Services;

use App\Models\ApprovalAbsensiModel;

class ApprovalAbsensiService
{
    use GridPipeline;

    /**
     * Pemisah kunci gabungan FKGdg + FKSupir.
     *
     * Modul ini tidak punya kolom id tunggal, jadi identitas barisnya dirangkai
     * dari kedua kolom itu (tanggalnya sudah tertentu dari filter halaman).
     * Kunci ini HANYA dipakai untuk mencocokkan kembali ke daftar sisi server;
     * ia tidak pernah dipecah lalu ditulis ke database. Jadi seandainya sebuah
     * nilai memuat pemisah ini, akibat terburuknya baris itu tidak cocok dengan
     * apa pun lalu dilaporkan "dilewati" -- bukan salah tulis.
     */
    private const PEMISAH_KUNCI = '|';

    protected $approvalAbsensiModel;

    public function __construct()
    {
        $this->approvalAbsensiModel = new ApprovalAbsensiModel();
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
     * Seluruh kunci yang lolos filter saat ini — dipakai fitur "pilih semua"
     * pada grid, yang tidak boleh terbatas pada baris yang kebetulan sudah
     * dimuat lazy loading.
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
     * sekumpulan baris.
     *
     * PENTING soal keamanan. Di CI3 klien mengirim SELURUH isi baris
     * (`$_POST['data']`), lalu FKGdg/FKSupir/FTgl darinya langsung dirangkai ke
     * dalam INSERT/DELETE. Dua akibatnya: nilai apa pun bisa disisipkan sebagai
     * SQL, dan -- bahkan tanpa itu -- user bisa meng-approve kombinasi
     * kendaraan/supir/tanggal MANA SAJA, termasuk yang tidak pernah muncul di
     * daftarnya.
     *
     * Di sini klien hanya mengirim KUNCI. Nilai FKGdg & FKSupir yang benar-benar
     * ditulis selalu diambil ulang dari daftar sisi server untuk tanggal &
     * status yang sedang aktif. Kunci yang tidak ada di daftar itu dilewati,
     * jadi kiriman yang dikarang tidak pernah sampai ke database.
     */
    public function processApproval(array $ids, string $prosesdata, string $tgl, ?string $userId): array
    {
        if ($userId === null || trim($userId) === '') {
            return $this->hasilProses('Sesi Anda telah berakhir. Silakan login ulang.');
        }

        // Hanya 0 (approve) & 1 (batal approve) yang dikenali. Tanpa penjagaan
        // ini, nilai lain lolos tanpa menjalankan apa pun tapi tetap dilaporkan
        // berhasil -- persis perilaku CI3, yang if/else-nya tidak punya cabang
        // penutup sehingga $sql tinggal memakai nilai dari perulangan sebelumnya.
        if (!in_array($prosesdata, ['0', '1'], true)) {
            return $this->hasilProses('Jenis proses tidak dikenali.');
        }

        // Tanggal WAJIB dikirim & tidak boleh ditebak: ia bagian dari kunci baris
        // yang ditulis, jadi menggantinya diam-diam dengan hari ini berarti
        // menandai absensi pada tanggal yang tidak pernah dilihat user.
        if (trim($tgl) === '') {
            return $this->hasilProses('Tanggal tidak terkirim. Muat ulang halaman lalu coba lagi.');
        }

        // is_scalar: isi `ids` berasal dari JSON kiriman klien, jadi elemennya bisa
        // saja array/objek -- strval() atas nilai begitu memicu error, bukan sekadar
        // hasil yang salah. unique: duplikat dibuang supaya satu baris tidak
        // diproses dua kali.
        $ids = array_values(array_unique(array_map('strval', array_filter($ids, 'is_scalar'))));

        if ($ids === []) {
            return $this->hasilProses('Tidak ada baris yang dipilih.');
        }

        // Daftar layak = isi grid untuk tanggal ini pada status yang sesuai
        // dengan aksinya. Approve mengambil dari daftar "belum approve" (bit 0),
        // batal approve dari daftar "sudah approve" (bit 1) -- sama seperti
        // yang dilihat user saat memilih.
        $layak = $this->petaBarisLayak($tgl, $prosesdata === '0' ? 0 : 1);

        $berhasil = 0;
        $gagal    = [];
        $dilewati = [];

        foreach ($ids as $id) {
            if (!isset($layak[$id])) {
                $dilewati[] = $id;
                continue;
            }

            ['FKGdg' => $fkgdg, 'FKSupir' => $fksupir] = $layak[$id];

            $terpengaruh = $prosesdata === '0'
                ? $this->approvalAbsensiModel->tandaiApprove($fkgdg, $fksupir, $tgl, $userId)
                : $this->approvalAbsensiModel->batalkanApprove($fkgdg, $fksupir, $tgl);

            $pesanDb = $this->approvalAbsensiModel->pesanError();

            if ($pesanDb !== '') {
                $gagal[] = ['id' => $id, 'pesan' => $pesanDb];
                continue;
            }

            // 0 baris terpengaruh berarti barisnya sudah tidak memenuhi syarat:
            // penandanya keburu dibuat/dihapus user lain di sela-sela ini.
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

        // Selain 0/1 tidak punya arti di sini; dibulatkan ke 0 supaya nilai asal
        // dari query string tidak berujung daftar kosong yang membingungkan.
        $bit = in_array($bit, [0, 1], true) ? $bit : 0;

        return $this->mapRows($this->approvalAbsensiModel->getData($tgl, $bit), $tgl);
    }

    /**
     * Peta kunci => (FKGdg, FKSupir) untuk baris yang boleh diproses. Memakai
     * query yang sama dengan pengisi grid, jadi definisi "boleh diproses" persis
     * sama dengan yang dilihat user.
     */
    private function petaBarisLayak(string $tgl, int $bit): array
    {
        $peta = [];

        foreach ($this->approvalAbsensiModel->getData($tgl, $bit) as $d) {
            $fkgdg   = trim((string)($d['FKGdg'] ?? ''));
            $fksupir = trim((string)($d['FKSupir'] ?? ''));

            if ($fkgdg === '' && $fksupir === '') {
                continue;
            }

            $peta[$this->kunci($fkgdg, $fksupir)] = ['FKGdg' => $fkgdg, 'FKSupir' => $fksupir];
        }

        return $peta;
    }

    private function kunci(string $fkgdg, string $fksupir): string
    {
        return $fkgdg . self::PEMISAH_KUNCI . $fksupir;
    }

    /**
     * Memetakan baris mentah ke struktur kolom jqGrid untuk SELURUH data, bukan
     * hanya 1 halaman, karena pencarian & sorting harus berjalan di atas
     * seluruh data.
     *
     * Kolomnya sama dengan CI3 (Tanggal, No Polisi, Supir). $tgl dipakai sebagai
     * cadangan bila FTgl baris kosong -- cabang "belum approve" memang tidak
     * membacanya dari tabel, melainkan memantulkan balik tanggal yang diminta.
     */
    private function mapRows(array $list, string $tgl): array
    {
        $rows = [];

        foreach ($list as $d) {
            $fkgdg   = trim((string)($d['FKGdg'] ?? ''));
            $fksupir = trim((string)($d['FKSupir'] ?? ''));
            $kunci   = $this->kunci($fkgdg, $fksupir);

            $rows[] = [
                'id'       => $kunci, // dipakai lazyLoadingGridMonolith.js sbg row identity
                'IdTarget' => $kunci,
                'FTgl'     => $this->formatTanggal($d['FTgl'] ?? $tgl),
                'FKGdg'    => $fkgdg,
                'FKSupir'  => $fksupir,
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
