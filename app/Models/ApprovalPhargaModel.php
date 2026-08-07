<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Berbeda dari modul approval lain di aplikasi ini, penawaran harga TIDAK
 * punya Stored Procedure sendiri: CI3 merangkai SELECT & UPDATE langsung ke
 * TPenawaranHarga (lihat MPenawaranHarga & ApprovalPHarga::approved).
 *
 * Rangkaian string itu tidak ikut dipindahkan. Di CI3 nilai dari $_POST['id']
 * dan $_SESSION['FUserID'] disisipkan mentah ke dalam SQL, sehingga satu No
 * Transaksi berisi tanda kutip sudah cukup untuk menyisipkan perintah lain.
 * Semua query di sini memakai parameter terikat.
 */
class ApprovalPhargaModel extends Model
{
    protected $table      = 'TPenawaranHarga';
    protected $primaryKey = 'FNTrans';

    /** Kolom yang dibutuhkan grid. SELECT * di CI3 menarik 23 kolom untuk 5 yang dipakai. */
    private const KOLOM_GRID = 'FNTrans, FTgl, FNShipper, FNamamarketing, FUp';

    /**
     * Menyaring satu hari pada kolom datetime.
     *
     * CI3 memakai `FTgl = '$tgl'`, yang hanya kebetulan bekerja selama komponen
     * jam seluruh baris 00:00:00 -- satu baris dengan jam bukan nol langsung
     * hilang dari daftar tanpa jejak. Bentuk rentang di bawah benar untuk nilai
     * jam apa pun DAN tetap sargable (indeks FTgl masih terpakai), tidak seperti
     * CAST(FTgl AS DATE) = ?.
     */
    private const FILTER_SATU_HARI = "FTgl >= ? AND FTgl < DATEADD(day, 1, ?)";

    private string $pesanError = '';

    /**
     * Daftar penawaran harga pada satu tanggal dengan status approval tertentu
     * ($isApp: 0 belum di-approve, 1 sudah).
     */
    public function getData(string $tgl, int $isApp): array
    {
        $sql = "SELECT " . self::KOLOM_GRID . "
                FROM TPenawaranHarga WITH (READUNCOMMITTED)
                WHERE FIsApp = ? AND " . self::FILTER_SATU_HARI;

        $query = $this->db->query($sql, [$isApp, $tgl, $tgl]);

        return $query ? $query->getResultArray() : [];
    }

    /**
     * Daftar penawaran harga yang MEMINTA cetak ulang (FIsCetak = 1) pada satu
     * tanggal. Meng-approve permintaan itu berarti mengembalikan FIsCetak ke 0.
     */
    public function getDataCetak(string $tgl): array
    {
        $sql = "SELECT " . self::KOLOM_GRID . "
                FROM TPenawaranHarga WITH (READUNCOMMITTED)
                WHERE FIsCetak = 1 AND " . self::FILTER_SATU_HARI;

        $query = $this->db->query($sql, [$tgl, $tgl]);

        return $query ? $query->getResultArray() : [];
    }

    /**
     * Menyetel status approval satu No Transaksi.
     *
     * WHERE-nya sekaligus jadi syarat: status lama harus masih seperti yang
     * dilihat user, dan barisnya harus benar dari tanggal yang sedang dibuka.
     * Dengan begitu "boleh diproses" dan "diproses" terjadi dalam SATU statemen
     * -- tidak ada celah antara pengecekan dan penulisan, dan hasilnya terbaca
     * pasti dari jumlah baris terpengaruh (bukan ditebak seperti di CI3, yang
     * selalu melaporkan "Proses berhasil" walau tidak ada baris yang berubah).
     *
     * FTglApp diisi GETDATE() milik SQL Server, bukan date() PHP. Selain bebas
     * dari selisih jam antara web server & database, ini memperbaiki bug CI3
     * yang memakai format "Y-m-d h:i:s" -- huruf `h` kecil = jam 12-jam tanpa
     * penanda AM/PM, sehingga approval pukul 15:30 tercatat sebagai 03:30.
     *
     * @return int Jumlah baris yang benar-benar berubah (0 atau 1).
     */
    public function setApproval(string $fntrans, string $userId, int $statusBaru, string $tgl): int
    {
        $statusLama = $statusBaru === 1 ? 0 : 1;

        $sql = "UPDATE TPenawaranHarga
                SET FIsApp = ?, FTglApp = GETDATE(), FUserApp = ?
                WHERE FNTrans = ? AND FIsApp = ? AND " . self::FILTER_SATU_HARI;

        return $this->jalankanUpdate($sql, [$statusBaru, $userId, $fntrans, $statusLama, $tgl, $tgl]);
    }

    /**
     * Meng-approve permintaan cetak ulang: FIsCetak kembali ke 0.
     *
     * FTglApp/FUserApp sengaja TIDAK ikut ditulis, mengikuti CI3
     * (ApprovalPHarga::approved_cetak) -- keduanya milik jejak approval harga,
     * dan menimpanya di sini akan menghapus catatan siapa yang meng-approve
     * harganya.
     *
     * @return int Jumlah baris yang benar-benar berubah (0 atau 1).
     */
    public function setCetak(string $fntrans, string $tgl): int
    {
        $sql = "UPDATE TPenawaranHarga
                SET FIsCetak = 0
                WHERE FNTrans = ? AND FIsCetak = 1 AND " . self::FILTER_SATU_HARI;

        return $this->jalankanUpdate($sql, [$fntrans, $tgl, $tgl]);
    }

    /**
     * Pesan error dari pemanggilan setApproval()/setCetak() terakhir. Kosong
     * berarti query sukses -- termasuk saat 0 baris terpengaruh, yang bukan
     * error melainkan tanda barisnya sudah tidak memenuhi syarat.
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
            if ($query === false) {
                $error            = $this->db->error();
                $this->pesanError = $this->bersihkanPesanSql((string)($error['message'] ?? ''));

                return 0;
            }

            $terpengaruh = $this->db->affectedRows();

            // sqlsrv_rows_affected() memberi -1 bila jumlahnya tidak tersedia
            // (mis. bila tabel ini suatu saat diberi trigger ber-SET NOCOUNT ON;
            // saat ini TPenawaranHarga tidak punya trigger sama sekali).
            // Query-nya sendiri sudah sukses, jadi diperlakukan sebagai 1 baris:
            // lebih baik melaporkan "berhasil" yang mungkin meleset daripada
            // "dilewati" yang pasti salah.
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
