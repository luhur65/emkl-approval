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
        //             'FJurnal' => 990000 + $i, // Harus Integer karena SP menerima tipe data INT
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

    public function processApproval($id, $userId, $prosesdata)
    {
        $hasil = new \stdClass();
        $hasil->error_sql = '';
        
        try {
            if ($prosesdata == 0) {
                // Parameter binding
                $sql = "EXEC usp_AppPreJob ?, ?, ?";
                $this->db->query($sql, [$id, $userId, date("Y-m-d H:i:s")]);
            } else if ($prosesdata == 1) {
                // Parameter binding
                $sql = "EXEC usp_UnAppPreJob ?, ?";
                $this->db->query($sql, [$id, $userId]);
            }
        } catch (\Exception $e) {
            $hasil->error_sql = $e->getMessage();
        }
        
        return $hasil;
    }
}
