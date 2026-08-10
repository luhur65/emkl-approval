<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Approval Pengajuan Supir Serap.
 *
 * Yang di-approve adalah baris pengajuan supir serap di TrApprovalAbsensi --
 * tabel yang diisi modul "Pengajuan Supir Serap" (CI3: Pengajuan::approved()).
 * Satu baris = satu kendaraan pada satu tanggal, berisi supir aslinya
 * (FKSupir), supir penggantinya (FKSupir_Serap), dan alasannya (FKeterangan).
 *
 * JANGAN tertukar dengan Approval Absensi Jam 9: modul itu bekerja pada tabel
 * TrApprovalAbsensiJam9 (lihat ApprovalAbsensiModel) dan tidak berhubungan
 * dengan supir serap. Namanya memang mirip dan CI3 pun menyimpan keduanya di
 * database yang sama, tapi tabel, kunci, maupun cara menulisnya berbeda.
 *
 * Berbeda dari Approval Absensi Jam 9 yang tidak punya id tunggal, tabel ini
 * punya kolom FID -- jadi kunci barisnya cukup satu kolom.
 *
 * Seperti Approval Trip, Approval Absensi, & Approval Extra Supir, tabelnya ada
 * di database terpisah lewat grup koneksi `dbtruck2` (nama database-nya beda
 * tiap cabang, lihat `database.dbtruck2.database` di .env), sesuai CI3 yang
 * memuat $this->dbtruck2 di ApprovalPengajuan::__construct().
 */
class ApprovalPengajuanModel extends Model
{
    protected $db;

    private string $pesanError = '';

    public function __construct()
    {
        parent::__construct();
        $this->db = \Config\Database::connect('dbtruck2');
    }

    /**
     * Daftar pengajuan pada satu tanggal dengan status tertentu.
     *
     * $bit = 0 -> belum di-approve, 1 -> sudah di-approve. Nilainya dicocokkan
     * ke ISNULL(FIsApp, 0): kolom itu nullable dan baris baru dari modul
     * Pengajuan memang tidak mengisinya, jadi NULL harus dibaca sebagai 0 --
     * tanpa ISNULL, seluruh pengajuan baru tidak akan pernah muncul di daftar
     * "belum approve".
     *
     * INNER JOIN ke Gdg mengikuti CI3, termasuk akibatnya: pengajuan yang
     * FKGdg-nya tidak ada lagi di master kendaraan tidak muncul di daftar mana
     * pun, jadi tidak bisa di-approve maupun dibatalkan. Sengaja tidak diubah
     * jadi LEFT JOIN -- itu akan MENAMBAH baris yang selama ini tidak pernah
     * terlihat user, dan keputusan begitu bukan milik migrasi.
     */
    public function getData(string $tgl, int $bit): array
    {
        $sql = "SELECT A.FID, A.FTgl, ISNULL(B.FNopolSTNK, B.FKGdg) AS FKGdg,
                       A.FKSupir, A.FKSupir_Serap, A.FKeterangan
                FROM TrApprovalAbsensi A
                INNER JOIN Gdg B ON A.FKGdg = B.FKGdg
                WHERE A.FTgl = ? AND ISNULL(A.FIsApp, 0) = ?";

        $query = $this->db->query($sql, [$tgl, $bit]);

        return $query ? $query->getResultArray() : [];
    }

    /**
     * Menandai satu pengajuan sebagai approved.
     *
     * `ISNULL(FIsApp, 0) = 0` pada WHERE membuatnya aman diulang: bila barisnya
     * sudah di-approve orang lain di sela-sela ini, 0 baris berubah dan
     * pemanggil tahu harus melaporkannya sebagai dilewati -- bukan sebagai
     * "berhasil" yang menimpa jejak approval sebelumnya. CI3 meng-update tanpa
     * syarat status sama sekali.
     *
     * @return int Jumlah baris yang benar-benar berubah (0 atau 1).
     */
    public function approve(string $fid, string $userId): int
    {
        return $this->setStatus($fid, 1, $userId);
    }

    /**
     * Membatalkan approve satu pengajuan.
     *
     * FTglApp & FUserApp IKUT ditimpa di sini, tidak dikosongkan -- mengikuti
     * CI3, yang memakai array update yang sama untuk kedua arah proses. Jadi
     * kedua kolom itu berarti "siapa & kapan terakhir memproses baris ini",
     * bukan khusus "siapa yang meng-approve".
     *
     * @return int Jumlah baris yang benar-benar berubah (0 atau 1).
     */
    public function unapprove(string $fid, string $userId): int
    {
        return $this->setStatus($fid, 0, $userId);
    }

    /**
     * Pesan error dari penulisan terakhir. Kosong berarti query sukses --
     * termasuk saat 0 baris terpengaruh, yang bukan error melainkan tanda
     * barisnya sudah tidak berstatus seperti yang diharapkan.
     */
    public function pesanError(): string
    {
        return $this->pesanError;
    }

    /**
     * $isApp adalah nilai tetap dari kode ini sendiri (0/1), bukan dari input.
     * Status asal yang disyaratkan adalah kebalikannya, sehingga satu baris
     * hanya berubah bila ia memang sedang berada di daftar yang dilihat user.
     *
     * FTglApp diisi GETDATE() milik SQL Server, bukan date() milik PHP seperti
     * CI3: jejak waktunya jadi mengikuti jam server database -- sama seperti
     * modul approval CI4 lain -- sehingga tetap konsisten meski jam server
     * aplikasi meleset.
     */
    private function setStatus(string $fid, int $isApp, string $userId): int
    {
        $statusAsal = $isApp === 1 ? 0 : 1;

        $sql = "UPDATE TrApprovalAbsensi
                SET FIsApp = ?, FUserApp = ?, FTglApp = GETDATE()
                WHERE FID = ? AND ISNULL(FIsApp, 0) = ?";

        return $this->jalankanTulis($sql, [$isApp, $userId, $fid, $statusAsal]);
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
