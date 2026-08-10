<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modul Approval Trip memakai DATABASE TERPISAH, bukan database utama
 * aplikasi: TrApprovalTripH & MMandor ada di grup koneksi `dbtruck2`, persis
 * seperti CI3 yang memuat $this->dbtruck2 di ApprovalTrip::__construct().
 * Nama database-nya berbeda tiap cabang (Surabaya, Medan, Jakarta, dst.),
 * jadi jangan ditulis di sini -- isinya ditentukan `database.dbtruck2.database`
 * di .env milik server masing-masing.
 *
 * Grup `dbtruck2` harus tetap terdaftar sebagai properti di
 * app/Config/Database.php. CodeIgniter\Config\BaseConfig hanya memetakan
 * variabel .env ke properti yang SUDAH terdefinisi di kelas config, jadi bila
 * properti itu dihapus, seluruh baris `database.dbtruck2.*` di .env akan
 * diabaikan diam-diam dan connect() melempar "not a valid database connection
 * group".
 */
class ApprovalTripModel extends Model
{
    protected $db;

    /**
     * Menyaring satu hari pada kolom tanggal.
     *
     * CI3 memakai `A.FTgl='$tgl'`, yang hanya benar selama komponen jam seluruh
     * baris 00:00:00. Bentuk rentang di bawah benar untuk nilai jam apa pun DAN
     * tetap sargable (indeks FTgl masih terpakai), tidak seperti
     * CONVERT(varchar(10), A.FTgl, 120) = ? yang memaksa scan.
     */
    private const FILTER_SATU_HARI = "FTgl >= ? AND FTgl < DATEADD(day, 1, ?)";

    private string $pesanError = '';

    public function __construct()
    {
        parent::__construct();
        $this->db = \Config\Database::connect('dbtruck2');
    }

    /**
     * Daftar approval trip pada satu tanggal dengan status tertentu
     * ($bit: 0 belum di-approve, 1 sudah).
     *
     * LEFT OUTER JOIN dipertahankan dari CI3: baris dengan FMandor yang tidak
     * ada di MMandor tetap harus muncul, jangan sampai hilang dari daftar
     * approval hanya karena master mandornya belum lengkap.
     */
    public function getData($tgl, $bit): array
    {
        $sql = "SELECT A.FTgl, A.FJlhTrip, A.FMandor, A.FUserID, A.FID, B.FNMandor
                FROM TrApprovalTripH A
                LEFT OUTER JOIN MMandor B ON A.FMandor = B.FKMandor
                WHERE ISNULL(A.FIsApp, 0) = ? AND A." . self::FILTER_SATU_HARI;

        $query = $this->db->query($sql, [$bit, $tgl, $tgl]);

        return $query ? $query->getResultArray() : [];
    }

    /**
     * Menyetel status approval satu FID.
     *
     * WHERE-nya sekaligus jadi syarat: status lama harus masih seperti yang
     * dilihat user, dan barisnya harus benar dari tanggal yang sedang dibuka.
     * Dengan begitu "boleh diproses" dan "diproses" terjadi dalam SATU statemen
     * -- tidak ada celah antara pengecekan dan penulisan, dan hasilnya terbaca
     * pasti dari jumlah baris terpengaruh. CI3 hanya `where('fid',$id)->update()`
     * tanpa memeriksa apa pun, lalu selalu melaporkan "Proses berhasil".
     *
     * ISNULL(FIsApp, 0) dipakai, bukan FIsApp langsung, karena kolomnya bisa
     * NULL -- getData() pun menyaringnya dengan cara yang sama.
     *
     * FTglApp diisi dari PHP, bukan GETDATE(), mengikuti CI3. Formatnya di sana
     * sudah benar ("Y-m-d H:i:s", 24 jam), jadi tidak ada yang perlu diperbaiki,
     * dan cara ini tidak bergantung pada tipe kolomnya.
     *
     * @return int Jumlah baris yang benar-benar berubah (0 atau 1).
     */
    public function setApproval($id, string $userId, int $statusBaru, string $tgl): int
    {
        $statusLama = $statusBaru === 1 ? 0 : 1;

        $sql = "UPDATE TrApprovalTripH
                SET FIsApp = ?, FTglApp = ?, FUserApp = ?
                WHERE FID = ? AND ISNULL(FIsApp, 0) = ? AND " . self::FILTER_SATU_HARI;

        return $this->jalankanUpdate($sql, [
            $statusBaru,
            date('Y-m-d H:i:s'),
            $userId,
            $id,
            $statusLama,
            $tgl,
            $tgl,
        ]);
    }

    /**
     * Pesan error dari pemanggilan setApproval() terakhir. Kosong berarti query
     * sukses -- termasuk saat 0 baris terpengaruh, yang bukan error melainkan
     * tanda barisnya sudah tidak memenuhi syarat.
     */
    public function pesanError(): string
    {
        return $this->pesanError;
    }

    private function jalankanUpdate(string $sql, array $params): int
    {
        $this->pesanError = '';

        try {
            $query = $this->db->query($sql, $params);

            // query() hanya MELEMPAR exception selama DBDebug menyala. Bila suatu
            // saat dimatikan untuk produksi, ia diam-diam mengembalikan false dan
            // blok catch di bawah jadi kode mati -- setiap kegagalan akan
            // terlaporkan sebagai "berhasil". Karena itu hasilnya diperiksa juga.
            //
            // Catatan: db_debug grup dbtruck2 di CI3 memang FALSE, jadi jalur
            // inilah yang dulu aktif -- dan tidak ada yang memeriksanya.
            if ($query === false) {
                $error            = $this->db->error();
                $this->pesanError = $this->bersihkanPesanSql((string)($error['message'] ?? ''));

                return 0;
            }

            $terpengaruh = $this->db->affectedRows();

            // sqlsrv_rows_affected() memberi -1 bila jumlahnya tidak tersedia
            // (mis. bila tabel diberi trigger ber-SET NOCOUNT ON). Query-nya
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
