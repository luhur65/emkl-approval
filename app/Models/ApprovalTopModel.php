<?php

namespace App\Models;

use CodeIgniter\Model;

class ApprovalTopModel extends Model
{
    public function getData($tgl, $bit)
    {
        $sql = "EXEC usp_GetListAppPreJob ?, ?";
        $query = $this->db->query($sql, [$tgl, $bit]);
        $data = $query->getResultArray();

        // [TESTING MODE] Hasilkan 100 baris data bayangan untuk tes Lazy Loading jqGrid
        // if (isset($_GET['test'])) {
        //     for ($i = 1; $i <= 100; $i++) {
        //         $data[] = [
        //             'FJurnal' => 990000 + $i, // Harus numerik: parameter SP-nya varchar(30), tapi kolom FJurnal bertipe int
        //             'FNShipper' => 'PT. SIMULASI KAPAL LAUT ' . $i,
        //             'FTgl' => $tgl . ' 00:00:00.000',
        //             'FNMarketing' => 'MARKETING ' . rand(1, 9),
        //             'FJumlahInvoice' => rand(1000000, 99000000),
        //             'FJumlahjob' => rand(1, 5)
        //         ];
        //     }
        // }

        return $data;
    }


    public function getDetail($jurnal)
    {
        $sql = "EXEC usp_GetListAppDetailPreJob ?";
        $query = $this->db->query($sql, [$jurnal]);
        return $query->getResultArray();
    }

    /**
     * Menjalankan satu SP approve/un-approve.
     *
     * CATATAN penting soal usp_UnAppPreJob: SP itu memanggil
     * usp_GetReminderAppPreJob, yang merender PDF lewat xp_cmdshell lalu MENGIRIM
     * PESAN WHATSAPP ke manajemen. Jadi satu pemanggilan un-approve bukan operasi
     * data murni dan tidak murah -- penyaring di ApprovalTopService memastikan
     * hanya baris yang benar-benar layak yang sampai ke sini.
     */
    public function processApproval($id, $userId, $prosesdata)
    {
        $hasil = new \stdClass();
        $hasil->error_sql = '';

        try {
            if ((string) $prosesdata === '0') {
                // Parameter binding
                $sql = "EXEC usp_AppPreJob ?, ?, ?";
                $query = $this->db->query($sql, [$id, $userId, date("Y-m-d H:i:s")]);
            } elseif ((string) $prosesdata === '1') {
                // Parameter binding
                $sql = "EXEC usp_UnAppPreJob ?, ?";
                $query = $this->db->query($sql, [$id, $userId]);
            } else {
                $hasil->error_sql = 'Jenis proses tidak dikenali: ' . $prosesdata;

                return $hasil;
            }

            // query() hanya MELEMPAR exception selama DBDebug menyala. Bila suatu
            // saat dimatikan untuk produksi, ia diam-diam mengembalikan false dan
            // blok catch di bawah jadi kode mati -- setiap kegagalan akan
            // terlaporkan sebagai "berhasil". Karena itu hasilnya diperiksa juga.
            if ($query === false) {
                $error = $this->db->error();
                $pesan = trim((string) ($error['message'] ?? ''));
                $hasil->error_sql = $pesan !== '' ? $pesan : 'Query gagal dijalankan.';
            }
        } catch (\Throwable $e) {
            $hasil->error_sql = $e->getMessage();
        }

        return $hasil;
    }
}
