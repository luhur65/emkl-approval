<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Pengajuan Supir Serap -- sisi ENTRI, dipakai mandor (CI3: Pengajuan::index,
 * ::approved, ::delete, ::ajax_list).
 *
 * Satu baris = satu kendaraan pada satu tanggal yang supir tetapnya digantikan
 * supir serap, berikut alasannya. Yang menyetujuinya adalah modul Approval
 * Pengajuan Supir Serap (ApprovalPengajuanModel) di atas tabel yang sama.
 *
 * CATATAN NAMA: method CI3-nya bernama `approved()` padahal isinya INSERT
 * pengajuan baru, sama sekali bukan approval. Di sini dinamai simpan() supaya
 * tidak tertukar dengan approve yang sesungguhnya.
 *
 * Bentuk tabel yang jadi dasar kode ini (diperiksa dari sys.columns):
 *   TrApprovalAbsensi : FID int IDENTITY & primary key, FTgl bertipe DATE
 *                       (jadi pembandingan tanggal cukup '=', tidak perlu
 *                       rentang seperti TrApprovalTripH yang DATETIME),
 *                       FIsApp bit NULL.
 *                       Kolom FisSudahSimpan, FIsAppPusat, FUserAppPusat,
 *                       FTglAppPusat & FKeteranganEdit NOT NULL tapi semuanya
 *                       punya DEFAULT -- karena itu tidak ikut disebut di
 *                       INSERT, persis seperti CI3.
 */
class PengajuanSupirSerapModel extends Model
{
    protected $db;

    private string $pesanError = '';

    public function __construct()
    {
        parent::__construct();
        $this->db = \Config\Database::connect('dbtruck2');
    }

    // ------------------------------------------------------------------
    // Master untuk form
    // ------------------------------------------------------------------

    /**
     * Kendaraan aktif untuk dropdown "No Polisi".
     *
     * FKGdg adalah kunci yang disimpan, FNopolSTNK yang ditampilkan -- keduanya
     * bisa berbeda (mis. FKGdg "B 9411 VS (HEAD)" vs FNopolSTNK "B 9411 VS"),
     * jadi keduanya ikut diambil. FMilikSupir adalah supir tetap kendaraan itu;
     * dipakai mengisi kolom "Supir" otomatis di form.
     */
    public function daftarKendaraan(): array
    {
        $sql = "SELECT FKGdg, ISNULL(FNopolSTNK, FKGdg) AS FNopolSTNK, ISNULL(FMilikSupir, '') AS FMilikSupir
                FROM Gdg
                WHERE FAktif = 1 AND ISNULL(FIsKendaraan, 0) = 1
                ORDER BY ISNULL(FNopolSTNK, FKGdg)";

        $query = $this->db->query($sql);

        return $query ? $query->getResultArray() : [];
    }

    /**
     * Supir non-karyawan yang aktif, untuk dropdown "Supir Serap" -- syarat
     * yang sama dengan CI3 (FAktif = 1 AND ISNULL(FIsKaryawan,0) = 0).
     */
    public function daftarSupir(): array
    {
        $sql = "SELECT FKSupir
                FROM MSupir
                WHERE FAktif = 1 AND ISNULL(FIsKaryawan, 0) = 0
                ORDER BY FKSupir";

        $query = $this->db->query($sql);

        return $query ? $query->getResultArray() : [];
    }

    /**
     * Supir tetap milik satu kendaraan, DARI DATABASE.
     *
     * Dipakai menggantikan nilai kiriman form: di CI3 kolom "Supir" adalah
     * input readonly yang isinya ikut dikirim POST, jadi siapa pun bisa
     * menyimpan pasangan kendaraan-supir yang tidak sesuai master hanya dengan
     * menyusun request sendiri -- `readonly` tidak mengikat apa pun di server.
     *
     * @return string|null null bila kendaraannya tidak ada / tidak aktif.
     */
    public function supirPemilik(string $fkgdg): ?string
    {
        $sql = "SELECT ISNULL(FMilikSupir, '') AS FMilikSupir
                FROM Gdg
                WHERE FKGdg = ? AND FAktif = 1 AND ISNULL(FIsKendaraan, 0) = 1";

        $baris = $this->db->query($sql, [$fkgdg])->getRow();

        return $baris === null ? null : (string) $baris->FMilikSupir;
    }

    /** Apakah kode supir ada di daftar supir yang boleh jadi supir serap. */
    public function supirSerapValid(string $fksupir): bool
    {
        $sql = "SELECT 1 AS ada
                FROM MSupir
                WHERE FKSupir = ? AND FAktif = 1 AND ISNULL(FIsKaryawan, 0) = 0";

        return $this->db->query($sql, [$fksupir])->getRow() !== null;
    }

    // ------------------------------------------------------------------
    // Grid
    // ------------------------------------------------------------------

    /**
     * Seluruh pengajuan pada satu tanggal, kedua status sekaligus -- mandor
     * perlu memantau punyanya sendiri baik yang sudah maupun belum di-approve.
     *
     * INNER JOIN ke Gdg dipertahankan dari CI3, termasuk akibatnya: pengajuan
     * yang kendaraannya sudah dihapus dari master tidak muncul di daftar. Sama
     * seperti ApprovalPengajuanModel -- sengaja tidak diubah jadi LEFT JOIN,
     * karena itu akan MENAMBAH baris yang selama ini tak pernah terlihat user.
     */
    public function getData(string $tgl): array
    {
        $sql = "SELECT TA.FID, TA.FTgl,
                       ISNULL(G.FNopolSTNK, G.FKGdg) AS FKGdg,
                       TA.FKSupir, TA.FKSupir_Serap, TA.FKeterangan,
                       ISNULL(TA.FIsApp, 0) AS FIsApp,
                       TA.FTglInput, TA.FTglApp, TA.FUserID
                FROM TrApprovalAbsensi TA
                INNER JOIN Gdg G ON TA.FKGdg = G.FKGdg
                WHERE TA.FTgl = ?";

        $query = $this->db->query($sql, [$tgl]);

        return $query ? $query->getResultArray() : [];
    }

    // ------------------------------------------------------------------
    // Tulis
    // ------------------------------------------------------------------

    /**
     * Menyimpan satu pengajuan supir serap.
     *
     * Penjagaan duplikat menyatu di dalam INSERT lewat WHERE NOT EXISTS, bukan
     * SELECT terpisah lalu INSERT seperti CI3. Pada CI3 ada jeda antara
     * pemeriksaan dan penulisan: dua kiriman yang sama persis dalam waktu
     * berdekatan sama-sama lolos pemeriksaan lalu sama-sama tersimpan.
     *
     * Jujur soal batasnya: tanpa unique index pada (FTgl, FKGdg, FKSupir,
     * FKSupir_Serap), bentuk satu statemen ini masih bisa kebobolan pada
     * isolation level READ COMMITTED -- jauh lebih sempit dari CI3, tapi bukan
     * jaminan mutlak. Jaminannya hanya bisa datang dari unique index di sisi
     * database, dan menambah index bukan wewenang migrasi ini.
     *
     * @return int|null FID pengajuan baru; null bila duplikat atau gagal
     *                  (bedakan lewat pesanError(): kosong berarti duplikat).
     */
    public function simpan(
        string $tgl,
        string $fkgdg,
        string $fksupir,
        string $fksupirSerap,
        string $keterangan,
        string $userId
    ): ?int {
        $this->pesanError = '';

        try {
            $sql = "INSERT INTO TrApprovalAbsensi
                        (FTgl, FKGdg, FKSupir, FKSupir_Serap, FKeterangan, FTglInput, FUserID)
                    SELECT ?, ?, ?, ?, ?, GETDATE(), ?
                    WHERE NOT EXISTS (
                        SELECT 1 FROM TrApprovalAbsensi
                        WHERE FTgl = ? AND FKGdg = ? AND FKSupir = ? AND FKSupir_Serap = ?
                    )";

            $hasil = $this->db->query($sql, [
                $tgl, $fkgdg, $fksupir, $fksupirSerap, $keterangan, $userId,
                $tgl, $fkgdg, $fksupir, $fksupirSerap,
            ]);

            if ($hasil === false) {
                $error            = $this->db->error();
                $this->pesanError = $this->bersihkanPesanSql((string) ($error['message'] ?? ''));

                return null;
            }

            // 0 baris = NOT EXISTS-nya tidak terpenuhi, artinya duplikat.
            // pesanError sengaja dibiarkan kosong supaya pemanggil bisa
            // membedakannya dari kegagalan teknis.
            if ($this->db->affectedRows() < 1) {
                return null;
            }

            $baris = $this->db->query("SELECT CAST(SCOPE_IDENTITY() AS BIGINT) AS FID")->getRow();

            return isset($baris->FID) ? (int) $baris->FID : null;
        } catch (\Throwable $e) {
            $this->pesanError = $this->bersihkanPesanSql($e->getMessage());

            return null;
        }
    }

    /**
     * Menghapus satu pengajuan, hanya bila belum di-approve.
     *
     * Syaratnya menyatu di WHERE, tidak dicek lewat SELECT terpisah seperti
     * CI3 -- lihat alasan yang sama di PengajuanTripModel::hapus(). Tabel ini
     * tidak punya tabel rincian, jadi cukup satu statemen tanpa transaksi.
     *
     * @return int 1 bila terhapus, 0 bila tidak memenuhi syarat / tidak ada.
     */
    public function hapus(string $fid): int
    {
        $this->pesanError = '';

        try {
            $hasil = $this->db->query(
                "DELETE FROM TrApprovalAbsensi WHERE FID = ? AND ISNULL(FIsApp, 0) = 0",
                [$fid]
            );

            if ($hasil === false) {
                $error            = $this->db->error();
                $this->pesanError = $this->bersihkanPesanSql((string) ($error['message'] ?? ''));

                return 0;
            }

            $terhapus = $this->db->affectedRows();

            return $terhapus < 0 ? 1 : $terhapus;
        } catch (\Throwable $e) {
            $this->pesanError = $this->bersihkanPesanSql($e->getMessage());

            return 0;
        }
    }

    /**
     * Pesan error dari penulisan terakhir. Kosong berarti query sukses --
     * termasuk saat 0 baris terpengaruh, yang bukan error melainkan tanda
     * barisnya duplikat (simpan) atau tidak memenuhi syarat (hapus).
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
