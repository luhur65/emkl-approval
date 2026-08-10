<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Approval Extra Supir.
 *
 * Yang di-approve adalah biaya EXTRA pada trip (TrSP_H): baris yang
 * FNomSupirByExtra-nya tidak nol dan belum ditarik ke gaji supir. Statusnya
 * disimpan di kolom FIsApprovalExtraBorongan, dan seluruh perubahannya lewat
 * Stored Procedure -- tidak ada UPDATE langsung dari aplikasi.
 *
 * Seperti Approval Trip & Approval Absensi, tabelnya ada di database terpisah
 * lewat grup koneksi `dbtruck2` (nama database-nya beda tiap cabang, lihat
 * `database.dbtruck2.database` di .env). Di CI3 hal
 * ini tidak terlihat dari controller-nya, melainkan dari helper yang dipakai:
 * MApprovalExtraSupir memanggil Generate_Procedure3(), dan HANYA fungsi itu
 * (dari tiga Generate_Procedure* yang ada) yang menyambung ke `dbtruck2` --
 * dua lainnya memakai koneksi default.
 *
 * Grup `dbtruck2` harus tetap terdaftar sebagai properti di
 * app/Config/Database.php. CodeIgniter\Config\BaseConfig hanya memetakan
 * variabel .env ke properti yang SUDAH terdefinisi di kelas config, jadi bila
 * properti itu dihapus, seluruh baris `database.dbtruck2.*` di .env akan
 * diabaikan diam-diam dan connect() melempar "not a valid database connection
 * group".
 */
class ApprovalExtraSupirModel extends Model
{
    protected $db;

    private string $pesanError = '';

    public function __construct()
    {
        parent::__construct();
        $this->db = \Config\Database::connect('dbtruck2');
    }

    /**
     * Daftar extra supir pada rentang tanggal dengan status tertentu.
     *
     * $proses = 0 -> belum di-approve, 1 -> sudah di-approve. Nilainya masuk ke
     * parameter @pproses (bertipe `bit`) milik SP, yang menyaring
     * ISNULL(FIsApprovalExtraBorongan, 0) = @pproses.
     *
     * CI3 merangkai nilai ini langsung ke string SQL sebagai literal `TRUE` /
     * `FALSE`. Di posisi nilai parameter EXEC, SQL Server memang menerima kedua
     * token itu dan mengubahnya jadi bit 1/0 -- jadi perilakunya sama, bukan
     * berubah. Di sini tetap dipakai 0/1 terikat: itu bentuk yang sah di mana
     * pun (`SET @p = TRUE` justru error "Invalid column name 'TRUE'"), dan
     * nilainya tidak lagi ikut menyusun teks SQL.
     */
    public function getData(string $tgldari, string $tglsampai, int $proses): array
    {
        $sql   = 'EXEC Net_usp_GetListApprovalExtraSupir ?, ?, ?';
        $query = $this->db->query($sql, [$tgldari, $tglsampai, $proses]);

        return $query ? $query->getResultArray() : [];
    }

    /**
     * Meng-approve biaya extra satu No Trip.
     *
     * $user diisi NAMA user (FNamaUser), bukan FUserID -- mengikuti CI3, yang
     * mengoper $_SESSION['FNamaUser'] ke SP ini. Sengaja tidak "diperbaiki" jadi
     * FUserID: nilai itu tersimpan sebagai jejak siapa yang meng-approve, dan
     * menukarnya membuat baris baru tidak sebanding dengan baris lama.
     */
    public function approve(string $fntrans, string $user): bool
    {
        return $this->jalankanSp('EXEC Net_usp_appExtraSupir ?, ?', [$fntrans, $user]);
    }

    /** Membatalkan approve biaya extra satu No Trip. */
    public function unapprove(string $fntrans, string $user): bool
    {
        return $this->jalankanSp('EXEC Net_usp_UnappExtraSupir ?, ?', [$fntrans, $user]);
    }

    /**
     * Pesan error dari pemanggilan SP terakhir. Kosong berarti SP-nya sukses.
     */
    public function pesanError(): string
    {
        return $this->pesanError;
    }

    /**
     * Menjalankan SP penulis (app/unapp) dan menangkap kegagalannya.
     *
     * CI3 memanggil Generate_Procedure3() lalu MEMBUANG hasilnya: variabel
     * $error di ApprovalExtraSupir::approved() tidak pernah diisi dari mana pun,
     * sehingga pesannya selalu "Proses berhasil" -- bahkan ketika SP-nya gagal
     * atau tidak menyentuh satu baris pun.
     *
     * Jumlah baris terpengaruh sengaja TIDAK dipakai sebagai penanda berhasil di
     * sini: yang dipanggil Stored Procedure, dan affectedRows() atasnya
     * memantulkan statemen terakhir di dalam SP (kerap -1 bila ber-SET NOCOUNT
     * ON), bukan jumlah baris yang benar-benar berubah. Kelayakan barisnya sudah
     * disaring lebih dulu di ApprovalExtraSupirService lewat SP daftar yang sama
     * dengan pengisi grid.
     */
    private function jalankanSp(string $sql, array $params): bool
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

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->pesanError = $this->bersihkanPesanSql($e->getMessage());

            return false;
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
