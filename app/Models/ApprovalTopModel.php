<?php

namespace App\Models;

use CodeIgniter\Model;

class ApprovalTopModel extends Model
{
    public function getData($tgl, $bit)
    {
        // Menggunakan binding parameters (?) untuk mencegah SQL Injection
        $sql = "EXEC usp_GetListAppPreJob ?, ?";
        $query = $this->db->query($sql, [$tgl, $bit]);
        return $query->getResultArray();
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
