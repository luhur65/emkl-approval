<?php

namespace App\Models;

use CodeIgniter\Model;

class ApprovalPoModel extends Model
{
    /**
     * Daftar permintaan order pada SATU tanggal. Parameter awal & akhir SP
     * sengaja diisi tanggal yang sama, persis seperti MapprovalPO::get_data()
     * di CI3 -- filternya memang per-hari, bukan rentang.
     */
    public function getData($tgl)
    {
        $sql   = "EXEC usp_GetListPermintaanApprovalorder ?, ?";
        $query = $this->db->query($sql, [$tgl, $tgl]);

        // [TESTING MODE] Hasilkan 100 baris data bayangan untuk tes Lazy Loading jqGrid
        // if (isset($_GET['test'])) {
        //     for ($i = 1; $i <= 100; $i++) {
        //         $data[] = [
        //             'FNTrans' => 'PO-TEST-' . str_pad($i, 4, '0', STR_PAD_LEFT),
        //             'FTgl' => $tgl . ' 00:00:00.000',
        //             'FNShipper' => 'PT. SHP TEST ' . $i,
        //             'FSaldoPiutang' => rand(1000000, 50000000),
        //             'FSisaPiutang' => rand(500000, 20000000),
        //             'FKelebihanPiutang' => rand(0, 1000000),
        //             'FJumlahOrder' => rand(1, 10),
        //             'FIsApp' => rand(0, 1),
        //             'FDateApp' => '',
        //             'FUserApp' => '',
        //             'FTglInput' => date('Y-m-d H:i:s'),
        //             'FUserId' => 'TESTUSER'
        //         ];
        //     }
        // }

        return $query ? $query->getResultArray() : [];
    }

    /**
     * Validasi bisnis sebelum satu No Bukti boleh di-approve/un-approve.
     *
     * usp_GetdataValidasiListPermintaanApprovalOrder melaporkan penolakannya
     * sebagai ERROR SQL Server -- RAISERROR(@pMsg, 16, 10) dengan pesan yang
     * memang ditujukan ke user ("Bukti Tidak Bisa Approval karena sudah ada
     * digunakan di job") -- bukan sebagai baris hasil. SP-nya sama sekali tidak
     * mengandung SELECT. Itulah sebabnya CI3 hanya membaca `error_sql` milik
     * Generate_Procedure(), yang isinya murni kumpulan pesan sqlsrv_errors().
     * Jalur setara di CI4: query() melempar DatabaseException selama DBDebug
     * menyala.
     *
     * Pemeriksaan kolom pesan pada baris hasil tetap dipertahankan sebagai
     * jaring pengaman bila SP suatu saat diubah agar mengembalikan pesan
     * (bukan me-raise) -- tanpa itu penolakan model baru akan lolos diam-diam.
     */
    public function getValidasi($fntrans)
    {
        $hasil            = new \stdClass();
        $hasil->error_sql = '';

        try {
            $sql   = "EXEC usp_GetdataValidasiListPermintaanApprovalOrder ?";
            $query = $this->db->query($sql, [$fntrans]);

            if ($query === false) {
                $hasil->error_sql = $this->pesanErrorDb();

                return $hasil;
            }

            $row = $query->getRowArray();

            if ($row !== null) {
                foreach (['error_sql', 'ErrorMessage', 'Pesan', 'Message'] as $kolom) {
                    if (isset($row[$kolom]) && trim((string) $row[$kolom]) !== '') {
                        $hasil->error_sql = trim((string) $row[$kolom]);
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            $hasil->error_sql = $this->bersihkanPesanSql($e->getMessage());
        }

        return $hasil;
    }

    /**
     * Status approval terkini satu No Bukti. Dibaca ulang dari SP (bukan dari
     * baris grid) tepat sebelum di-toggle, sama seperti CI3 -- baris grid bisa
     * saja sudah basi bila user lain memproses No Bukti yang sama lebih dulu.
     */
    public function getPermintaanApprovalOrder($fntrans)
    {
        $sql   = "EXEC usp_GetDataTPermintaanApprovalOrder ?";
        $query = $this->db->query($sql, [$fntrans]);

        return $query ? $query->getResultArray() : [];
    }

    /**
     * Menyetel status approval satu No Bukti ($isapp: 1 approve, 0 un-approve).
     */
    public function processApproval($user, $fntrans, $isapp)
    {
        $hasil            = new \stdClass();
        $hasil->error_sql = '';

        try {
            $sql   = "EXEC usp_UpdTPermintaanApprovalOrder ?, ?, ?";
            $query = $this->db->query($sql, [$user, $fntrans, $isapp]);

            // query() hanya MELEMPAR exception selama DBDebug menyala. Bila suatu
            // saat dimatikan untuk produksi, ia diam-diam mengembalikan false dan
            // blok catch di bawah jadi kode mati -- setiap kegagalan akan
            // terlaporkan sebagai "berhasil". Karena itu hasilnya diperiksa juga.
            if ($query === false) {
                $hasil->error_sql = $this->pesanErrorDb();
            }
        } catch (\Throwable $e) {
            $hasil->error_sql = $this->bersihkanPesanSql($e->getMessage());
        }

        return $hasil;
    }

    private function pesanErrorDb(): string
    {
        $error = $this->db->error();
        $pesan = $this->bersihkanPesanSql((string) ($error['message'] ?? ''));

        return $pesan !== '' ? $pesan : 'Query gagal dijalankan.';
    }

    /**
     * Membuang awalan driver dari pesan SQL Server sebelum ditampilkan ke user.
     *
     * Pesan mentahnya berbentuk
     *   [Microsoft][ODBC Driver 17 for SQL Server][SQL Server]<pesan asli>
     * sedangkan yang berguna bagi user hanya bagian terakhir -- di modul ini
     * pesan itu memang ditulis untuk dibaca user (lihat RAISERROR di
     * usp_GetdataValidasiListPermintaanApprovalOrder).
     *
     * Deretan "[...]" di awal dibuang secara umum, bukan dicocokkan ke satu
     * nama driver tertentu seperti helper CI3 (yang hanya mengenali "SQL Server
     * Native Client" sehingga tidak lagi kena pada driver ODBC 17 yang dipakai
     * sekarang, dan awalannya ikut terbaca user).
     */
    private function bersihkanPesanSql(string $pesan): string
    {
        return trim(preg_replace('/^(\[[^\]]*\])+/', '', trim($pesan)));
    }
}
