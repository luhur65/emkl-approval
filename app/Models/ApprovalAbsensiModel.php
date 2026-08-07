<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Approval Absensi Jam 9.
 *
 * Yang di-approve BUKAN sebuah baris transaksi yang sudah ada, melainkan
 * ketiadaannya: daftar "belum approve" berisi kendaraan/supir yang pada tanggal
 * tsb belum tercatat di AbsensiSupir_R. Meng-approve berarti MENYISIPKAN baris
 * penanda ke TrApprovalAbsensiJam9; membatalkannya berarti MENGHAPUS baris itu.
 * Karena itu tidak ada satu kolom id pun -- kuncinya gabungan
 * (FKGdg, FKSupir, FTgl).
 *
 * Seperti Approval Trip, tabelnya ada di database terpisah
 * TVPTTransporindoAgungSejahteraSby0001 (grup koneksi `dbtruck2`), sesuai CI3
 * yang memuat $this->dbtruck2 di ApprovalAbsensi::__construct().
 */
class ApprovalAbsensiModel extends Model
{
    protected $db;

    private string $pesanError = '';

    public function __construct()
    {
        parent::__construct();
        $this->db = \Config\Database::connect('dbtruck2');
    }

    /**
     * Syarat "data kendaraan lengkap" milik CI3, disalin apa adanya.
     *
     * Ini aturan bisnis, bukan kode teknis: hanya kendaraan aktif yang seluruh
     * dokumennya terisi yang wajib diabsen. Sengaja TIDAK disederhanakan --
     * setiap kolom di sini menentukan siapa yang muncul di daftar approval,
     * dan tidak ada cara memverifikasinya tanpa data aslinya.
     */
    private const SYARAT_KENDARAAN_LENGKAP = "
        ISNULL(FIsKendaraan, 0) <> 0
        AND ISNULL(FAktif, 0) <> 0
        AND YEAR(ISNULL(FTglAsuransiMati, '1900/1/1')) <> 1900
        AND YEAR(ISNULL(FTglSpeksiMati, '1900/1/1')) <> 1900
        AND ISNULL(FNama, '') <> ''
        AND ISNULL(FNoStnk, '') <> ''
        AND ISNULL(FAlamatStnk, '') <> ''
        AND YEAR(ISNULL(FTglStnkMati, '1900/1/1')) <> 1900
        AND YEAR(ISNULL(FTglPajakSTNK, '1900/1/1')) <> 1900
        AND ISNULL(FNoBpkb, '') <> ''
        AND ISNULL(FMerek, '') <> ''
        AND ISNULL(FNoRangka, '') <> ''
        AND ISNULL(FNoMesin, '') <> ''
        AND ISNULL(FTipe, '') <> ''
        AND ISNULL(FJenis, '') <> ''
        AND ISNULL(FIsiSilinder, '') <> ''
        AND ISNULL(FWarna, '') <> ''
        AND ISNULL(FBahanBakar, '') <> ''
        AND ISNULL(FJlhRoda, 0) <> 0
        AND ISNULL(FModel, '') <> ''
        AND ISNULL(FMilikSupir, '') <> ''";

    /**
     * Daftar untuk grid.
     *
     * $bit = 0 -> belum di-approve: kendaraan lengkap yang belum tercatat di
     *             absensi hari itu dan belum punya penanda approval.
     * $bit = 1 -> sudah di-approve: isi TrApprovalAbsensiJam9 hari itu.
     */
    public function getData(string $tgl, int $bit): array
    {
        return $bit === 0 ? $this->getBelumApprove($tgl) : $this->getSudahApprove($tgl, $bit);
    }

    /**
     * Dua cabang dengan syarat kendaraan yang sama persis; bedanya hanya kolom
     * yang dipakai memeriksa "belum tercatat" -- cabang pertama lewat kode
     * kendaraan (FKGdg), cabang kedua lewat kode supirnya (FMilikSupir).
     *
     * CI3 memakai UNION ALL, sehingga kendaraan yang KEDUA-duanya belum
     * tercatat muncul dua kali. Di DataTables itu sekadar baris kembar; di grid
     * baru hal itu merusak, karena kedua baris berbagi identitas yang sama dan
     * jqGrid memakainya sebagai id elemen DOM. Diganti UNION (distinct): kedua
     * cabang memproyeksikan kolom yang identik, jadi yang hilang hanya duplikat
     * persis -- tidak ada baris yang seharusnya tampil jadi ikut terbuang.
     */
    private function getBelumApprove(string $tgl): array
    {
        $sql = $this->cabangBelumApprove('FKGdg', 'FKGdg')
             . ' UNION '
             . $this->cabangBelumApprove('FMilikSupir', 'FKSupir');

        // Tiap cabang memakai tanggal 3x: kolom FTgl hasil, saringan
        // AbsensiSupir_R, dan saringan TrApprovalAbsensiJam9.
        $query = $this->db->query($sql, [$tgl, $tgl, $tgl, $tgl, $tgl, $tgl]);

        return $query ? $query->getResultArray() : [];
    }

    /**
     * $kolomLuar & $kolomDalam adalah nama kolom tetap yang ditulis di kode ini
     * sendiri, bukan dari input -- aman disisipkan ke SQL. Seluruh NILAI tetap
     * lewat parameter terikat.
     */
    private function cabangBelumApprove(string $kolomLuar, string $kolomDalam): string
    {
        return "SELECT ? AS FTgl, ISNULL(FNopolStnk, FKGdg) AS FKGdg, FMilikSupir AS FKSupir
                FROM GDg
                WHERE " . self::SYARAT_KENDARAAN_LENGKAP . "
                  AND {$kolomLuar} NOT IN (
                        SELECT {$kolomDalam} FROM AbsensiSupir_R WHERE FTgl = ?
                  )
                  AND {$kolomLuar} NOT IN (
                        SELECT {$kolomDalam} FROM TrApprovalAbsensiJam9
                        WHERE FTgl = ? AND ISNULL(FIsApp, 0) = 1
                  )";
    }

    private function getSudahApprove(string $tgl, int $bit): array
    {
        $sql = "SELECT FTgl, FKGdg, FKSupir
                FROM TrApprovalAbsensiJam9
                WHERE FTgl = ? AND ISNULL(FIsApp, 0) = ?";

        $query = $this->db->query($sql, [$tgl, $bit]);

        return $query ? $query->getResultArray() : [];
    }

    /**
     * Menyisipkan penanda approval untuk satu (FKGdg, FKSupir, FTgl).
     *
     * NOT EXISTS membuatnya aman diulang: bila penandanya sudah ada, 0 baris
     * tersisip dan pemanggil tahu baris itu perlu dilaporkan sebagai dilewati.
     * CI3 menyisipkan tanpa syarat apa pun, jadi satu klik ganda meninggalkan
     * dua penanda untuk kombinasi yang sama.
     *
     * FTglInput & FTglApp diisi GETDATE() milik SQL Server, mengikuti CI3.
     *
     * @return int Jumlah baris yang benar-benar tersisip (0 atau 1).
     */
    public function tandaiApprove(string $fkgdg, string $fksupir, string $tgl, string $userId): int
    {
        $sql = "INSERT INTO TrApprovalAbsensiJam9 (FKGdg, FKSupir, FTgl, FTglInput, FIsApp, FUserApp, FTglApp)
                SELECT ?, ?, ?, GETDATE(), 1, ?, GETDATE()
                WHERE NOT EXISTS (
                    SELECT 1 FROM TrApprovalAbsensiJam9
                    WHERE FKGdg = ? AND FKSupir = ? AND FTgl = ?
                )";

        return $this->jalankanTulis($sql, [$fkgdg, $fksupir, $tgl, $userId, $fkgdg, $fksupir, $tgl]);
    }

    /**
     * Menghapus penanda approval satu (FKGdg, FKSupir, FTgl).
     *
     * ISNULL(FIsApp,0) = 1 ditambahkan supaya hanya penanda yang memang
     * ber-status approve yang terhapus -- sejalan dengan daftar yang dilihat
     * user, yang juga disaring dengan syarat itu.
     *
     * @return int Jumlah baris yang benar-benar terhapus (0 atau 1).
     */
    public function batalkanApprove(string $fkgdg, string $fksupir, string $tgl): int
    {
        $sql = "DELETE FROM TrApprovalAbsensiJam9
                WHERE FKGdg = ? AND FKSupir = ? AND FTgl = ? AND ISNULL(FIsApp, 0) = 1";

        return $this->jalankanTulis($sql, [$fkgdg, $fksupir, $tgl]);
    }

    /**
     * Pesan error dari penulisan terakhir. Kosong berarti query sukses --
     * termasuk saat 0 baris terpengaruh, yang bukan error melainkan tanda
     * barisnya sudah tidak memenuhi syarat.
     */
    public function pesanError(): string
    {
        return $this->pesanError;
    }

    private function jalankanTulis(string $sql, array $params): int
    {
        $this->pesanError = '';

        try {
            $query = $this->db->query($sql, $params);

            // query() hanya MELEMPAR exception selama DBDebug menyala. Bila suatu
            // saat dimatikan untuk produksi, ia diam-diam mengembalikan false dan
            // blok catch di bawah jadi kode mati -- setiap kegagalan akan
            // terlaporkan sebagai "berhasil". Karena itu hasilnya diperiksa juga.
            // Catatan: db_debug grup dbtruck2 di CI3 memang FALSE, jadi jalur
            // inilah yang dulu aktif -- dan tidak ada yang memeriksanya.
            if ($query === false) {
                $error            = $this->db->error();
                $this->pesanError = $this->bersihkanPesanSql((string)($error['message'] ?? ''));

                return 0;
            }

            $terpengaruh = $this->db->affectedRows();

            // sqlsrv_rows_affected() memberi -1 bila jumlahnya tidak tersedia
            // (mis. bila tabelnya diberi trigger ber-SET NOCOUNT ON). Query-nya
            // sendiri sudah sukses, jadi diperlakukan sebagai 1 baris: lebih baik
            // melaporkan "berhasil" yang mungkin meleset daripada "dilewati" yang
            // pasti salah.
            return $terpengaruh < 0 ? 1 : $terpengaruh;
        } catch (\Throwable $e) {
            $this->pesanError = $this->bersihkanPesanSql($e->getMessage());

            return 0;
        }
    }

    /**
     * Membuang awalan driver dari pesan SQL Server sebelum ditampilkan ke user.
     * Bentuk mentahnya "[Microsoft][ODBC Driver 17 for SQL Server][SQL Server]
     * <pesan asli>"; deretan "[...]" di awal dibuang secara umum supaya tidak
     * terikat ke satu nama driver tertentu.
     */
    private function bersihkanPesanSql(string $pesan): string
    {
        $pesan = trim(preg_replace('/^(\[[^\]]*\])+/', '', trim($pesan)));

        return $pesan !== '' ? $pesan : 'Query gagal dijalankan.';
    }
}
