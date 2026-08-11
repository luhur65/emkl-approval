<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Pengajuan Trip -- sisi ENTRI, dipakai mandor (CI3: ApprovalTrip::index,
 * ::simpan, ::delete, ::ajax_list).
 *
 * Jangan tertukar dengan ApprovalTripModel: itu sisi APPROVAL atas tabel yang
 * sama (TrApprovalTripH), dipakai user kantor. Di sini barisnya DIBUAT dan
 * DIHAPUS; di sana hanya kolom statusnya yang diubah.
 *
 * Satu pengajuan = satu baris header TrApprovalTripH + sebanyak FJlhTrip baris
 * rincian TrApprovalTripR (FUrut 1..n, FStatus 0). Rincian itu yang nantinya
 * diisi realisasi trip; jumlahnya ditetapkan saat pengajuan dibuat.
 *
 * Tabelnya ada di database terpisah lewat grup koneksi `dbtruck2` (nama
 * database-nya beda tiap cabang, lihat `database.dbtruck2.database` di .env).
 *
 * Bentuk tabel yang jadi dasar kode ini (diperiksa langsung dari sys.columns):
 *   TrApprovalTripH : FID int IDENTITY & primary key, FTgl DATETIME (jadi
 *                     penyaringan tanggal harus berupa rentang, lihat
 *                     FILTER_SATU_HARI), FIsApp bit NULL.
 *   TrApprovalTripR : primary key gabungan (FID, Furut) -- karena itu Furut
 *                     wajib unik per FID. Kolom FSeqTime bertipe `timestamp`
 *                     (rowversion): diisi sendiri oleh SQL Server dan TIDAK
 *                     BOLEH ikut disebut di INSERT.
 */
class PengajuanTripModel extends Model
{
    protected $db;

    /**
     * Menyaring satu hari pada kolom tanggal -- disalin dari ApprovalTripModel
     * supaya kedua sisi (entri & approval) menyaring baris yang sama persis.
     * Bentuk rentang ini benar untuk nilai jam apa pun dan tetap sargable,
     * tidak seperti CONVERT(varchar(10), FTgl, 120) = ? yang memaksa scan.
     */
    private const FILTER_SATU_HARI = "FTgl >= ? AND FTgl < DATEADD(day, 1, ?)";

    private string $pesanError = '';

    public function __construct()
    {
        parent::__construct();
        $this->db = \Config\Database::connect('dbtruck2');
    }

    /**
     * Daftar mandor aktif untuk dropdown form entri.
     *
     * ISNULL(FAktif, 0) = 1 dipakai, bukan FAktif = 1 seperti CI3: kolom bit
     * yang NULL tidak pernah cocok dengan '= 1' MAUPUN '= 0', jadi tanpa ISNULL
     * mandor yang FAktif-nya belum pernah diisi hilang dari dropdown tanpa
     * jejak. Diurutkan supaya isi dropdown tidak berubah-ubah urutannya.
     */
    public function daftarMandor(): array
    {
        $sql = "SELECT FKMandor, FNMandor
                FROM MMandor
                WHERE ISNULL(FAktif, 0) = 1
                ORDER BY FKMandor";

        $query = $this->db->query($sql);

        return $query ? $query->getResultArray() : [];
    }

    /**
     * Seluruh pengajuan pada satu tanggal, kedua status sekaligus.
     *
     * Berbeda dari ApprovalTripModel::getData() yang menyaring per status:
     * mandor perlu melihat pengajuannya sendiri baik yang belum maupun sudah
     * di-approve -- justru statusnya itu yang ingin ia pantau.
     *
     * LEFT OUTER JOIN ke MMandor dipertahankan dari CI3: baris dengan FMandor
     * yang tidak ada di master tetap harus terlihat pemiliknya.
     */
    public function getData(string $tgl): array
    {
        $sql = "SELECT A.FID, A.FTgl, A.FJlhTrip, A.FMandor, B.FNMandor,
                       ISNULL(A.FIsApp, 0) AS FIsApp, A.FUserID, A.FTglInput
                FROM TrApprovalTripH A
                LEFT OUTER JOIN MMandor B ON A.FMandor = B.FKMandor
                WHERE A." . self::FILTER_SATU_HARI;

        $query = $this->db->query($sql, [$tgl, $tgl]);

        return $query ? $query->getResultArray() : [];
    }

    /**
     * Membuat satu pengajuan: header + FJlhTrip baris rincian, dalam satu
     * transaksi. Bila salah satu gagal, tidak ada yang tersisa.
     *
     * Dua perbaikan atas CI3:
     *
     * 1. FID hasil INSERT diambil lewat SCOPE_IDENTITY(), bukan
     *    `SELECT fid FROM TrApprovalTripH ORDER BY fid DESC`. Query CI3 itu
     *    membaca baris TERBARU DI SELURUH TABEL -- bila dua mandor menyimpan
     *    hampir bersamaan, yang belakangan menempelkan seluruh baris rinciannya
     *    ke pengajuan milik yang lebih dulu. SCOPE_IDENTITY() terbatas pada
     *    koneksi & scope ini sendiri, jadi tidak bisa tertukar.
     *
     * 2. Rincian ditulis satu statemen (INSERT ... SELECT atas deret angka),
     *    bukan perulangan INSERT sebanyak FJlhTrip kali. Server cabang diakses
     *    lewat jaringan antar-kota; 25 kali bolak-balik di dalam transaksi
     *    berarti transaksinya menahan kunci selama itu juga.
     *
     * @return int|null FID pengajuan yang terbentuk, atau null bila gagal
     *                  (alasannya di pesanError()).
     */
    public function simpan(string $tgl, int $jlh, string $mandor, string $userId): ?int
    {
        $this->pesanError = '';

        try {
            $this->db->transBegin();

            $ok = $this->jalankanTulis(
                "INSERT INTO TrApprovalTripH (FTgl, FJlhTrip, FMandor, FTglInput, FUserID)
                 VALUES (?, ?, ?, GETDATE(), ?)",
                [$tgl, $jlh, $mandor, $userId]
            );

            if (!$ok) {
                $this->db->transRollback();

                return null;
            }

            // Dijalankan sebagai query terpisah, bukan digabung ke batch INSERT
            // di atas: driver sqlsrv menyerahkan result set pertama saja, dan
            // pada batch itu yang pertama adalah jumlah baris INSERT -- bukan
            // SELECT-nya. SCOPE_IDENTITY() tetap sah di sini karena keduanya
            // berjalan pada koneksi yang sama.
            $baris = $this->db->query("SELECT CAST(SCOPE_IDENTITY() AS BIGINT) AS FID")->getRow();
            $fid   = $baris->FID ?? null;

            if ($fid === null) {
                // Tidak diharapkan terjadi -- FID sudah dipastikan IDENTITY.
                // Tetap dijaga karena satu-satunya alternatifnya adalah menebak
                // nomor pengajuan, dan tebakan yang meleset membuat seluruh
                // baris rincian menempel ke pengajuan milik mandor lain.
                $this->db->transRollback();
                $this->pesanError = 'Nomor pengajuan tidak terbaca setelah disimpan. '
                                  . 'Data tidak jadi disimpan -- hubungi admin.';

                return null;
            }

            $fid = (int) $fid;

            // Deret 1..$jlh dibangkitkan dari sys.all_objects -- tabel sistem
            // yang selalu ada dan jauh lebih panjang daripada jumlah trip
            // sehari, jadi TOP (?) tidak akan kehabisan baris. ROW_NUMBER()
            // menjamin Furut unik 1..n, sesuai primary key (FID, Furut).
            // FSeqTime sengaja tidak disebut: kolom rowversion diisi server.
            $ok = $this->jalankanTulis(
                "INSERT INTO TrApprovalTripR (FID, Furut, FStatus)
                 SELECT ?, x.n, 0
                 FROM (
                     SELECT TOP (?) ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS n
                     FROM sys.all_objects
                 ) x",
                [$fid, $jlh]
            );

            if (!$ok) {
                $this->db->transRollback();

                return null;
            }

            $this->db->transCommit();

            return $fid;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->pesanError = $this->bersihkanPesanSql($e->getMessage());

            return null;
        }
    }

    /**
     * Menghapus satu pengajuan beserta rinciannya, hanya bila belum di-approve.
     *
     * URUTANNYA WAJIB rincian dulu, baru header. Ada foreign key FKFID dari
     * TrApprovalTripR.FID ke TrApprovalTripH.FID dengan ON DELETE NO_ACTION,
     * jadi menghapus header lebih dulu ditolak SQL Server dengan error 547
     * selama rinciannya masih ada.
     *
     * Syarat "belum di-approve" tetap menyatu di WHERE kedua DELETE, tidak
     * dicek lewat SELECT terpisah seperti CI3. Pada CI3 ada jeda antara
     * pengecekan dan penghapusan: bila baris di-approve orang lain tepat di
     * sela itu, penghapusan tetap jalan dan pengajuan yang sudah disetujui ikut
     * lenyap. Di sini, bila barisnya sudah di-approve, DELETE rincian mengenai
     * 0 baris (syaratnya ikut lewat JOIN ke header) dan DELETE header juga 0 --
     * jadi tidak ada rincian yatim yang tertinggal.
     *
     * @return int 1 bila terhapus, 0 bila tidak memenuhi syarat / tidak ada.
     */
    public function hapus(string $fid): int
    {
        $this->pesanError = '';

        try {
            $this->db->transBegin();

            // Syarat status diambil dari header lewat JOIN, supaya rincian
            // hanya ikut terhapus bila headernya memang boleh dihapus.
            $ok = $this->jalankanTulis(
                "DELETE R
                 FROM TrApprovalTripR R
                 INNER JOIN TrApprovalTripH H ON R.FID = H.FID
                 WHERE H.FID = ? AND ISNULL(H.FIsApp, 0) = 0",
                [$fid]
            );

            if (!$ok) {
                $this->db->transRollback();

                return 0;
            }

            $ok = $this->jalankanTulis(
                "DELETE FROM TrApprovalTripH WHERE FID = ? AND ISNULL(FIsApp, 0) = 0",
                [$fid]
            );

            if (!$ok) {
                $this->db->transRollback();

                return 0;
            }

            // Baru di sini jumlah baris headernya berarti: 0 = tidak memenuhi
            // syarat (sudah di-approve / tidak ada), 1 = benar-benar terhapus.
            $terhapus = $this->db->affectedRows();

            if ($terhapus < 1) {
                $this->db->transRollback();

                return 0;
            }

            $this->db->transCommit();

            return 1;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->pesanError = $this->bersihkanPesanSql($e->getMessage());

            return 0;
        }
    }

    /**
     * Menjalankan satu statemen tulis dan MEMERIKSA hasilnya sendiri.
     *
     * Perlu dipisah karena query yang gagal di dalam transaksi TIDAK selalu
     * melempar exception meski DBDebug menyala -- pada driver SQLSRV ia
     * mengembalikan false dan hanya menurunkan transStatus. Tanpa pemeriksaan
     * ini, pelanggaran foreign key terbaca sekadar sebagai "0 baris
     * terpengaruh", dan penghapusan yang gagal dilaporkan seolah barisnya
     * memang tidak memenuhi syarat.
     *
     * @return bool true bila statemennya sukses (berapa pun baris terpengaruh).
     */
    private function jalankanTulis(string $sql, array $params): bool
    {
        $hasil = $this->db->query($sql, $params);

        if ($hasil === false || $this->db->transStatus() === false) {
            $error            = $this->db->error();
            $this->pesanError = $this->bersihkanPesanSql((string) ($error['message'] ?? ''));

            return false;
        }

        return true;
    }

    /**
     * Pesan error dari penulisan terakhir. Kosong berarti query sukses --
     * termasuk saat 0 baris terpengaruh, yang bukan error melainkan tanda
     * barisnya tidak memenuhi syarat.
     */
    public function pesanError(): string
    {
        return $this->pesanError;
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
