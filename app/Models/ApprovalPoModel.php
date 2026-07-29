<?php

namespace App\Models;

use CodeIgniter\Model;

class ApprovalPoModel extends Model
{
    public function getData($tgl)
    {
        $sql = "EXEC usp_GetListPermintaanApprovalorder ?, ?";
        $query = $this->db->query($sql, [$tgl, $tgl]);
        $data = $query->getResultArray();
        
        // [TESTING MODE] - Mock data for lazy loading test
        if (isset($_GET['test']) && count($data) == 0) {
            for ($i = 1; $i <= 100; $i++) {
                $data[] = [
                    'FNTrans' => 'PO-TEST-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                    'FTgl' => $tgl . ' 00:00:00.000',
                    'FNShipper' => 'PT. SHP TEST ' . $i,
                    'FSaldoPiutang' => rand(1000000, 50000000),
                    'FSisaPiutang' => rand(500000, 20000000),
                    'FKelebihanPiutang' => rand(0, 1000000),
                    'FJumlahOrder' => rand(1, 10),
                    'FIsApp' => rand(0, 1),
                    'FDateApp' => '',
                    'FUserApp' => '',
                    'FTglInput' => date('Y-m-d H:i:s'),
                    'FUserId' => 'TESTUSER'
                ];
            }
        }
        return $data;
    }

    public function getValidasi($fntrans)
    {
        $hasil = new \stdClass();
        $hasil->error_sql = '';
        try {
            $sql = "EXEC usp_GetdataValidasiListPermintaanApprovalOrder ?";
            $query = $this->db->query($sql, [$fntrans]);
            if ($query) {
                $row = $query->getRowObject();
                // SQL Server Validation SP usually returns ErrorMessage or error_sql
                if ($row && isset($row->error_sql)) {
                    $hasil->error_sql = $row->error_sql;
                } elseif ($row && isset($row->ErrorMessage)) {
                    $hasil->error_sql = $row->ErrorMessage;
                }
            }
        } catch (\Exception $e) {
            $hasil->error_sql = $e->getMessage();
        }
        return $hasil;
    }

    public function getPermintaanApprovalOrder($fntrans)
    {
        $sql = "EXEC usp_GetDataTPermintaanApprovalOrder ?";
        $query = $this->db->query($sql, [$fntrans]);
        return $query ? $query->getResultArray() : [];
    }

    public function processApproval($user, $fntrans, $isapp)
    {
        $hasil = new \stdClass();
        $hasil->error_sql = '';
        try {
            $sql = "EXEC usp_UpdTPermintaanApprovalOrder ?, ?, ?";
            $this->db->query($sql, [$user, $fntrans, $isapp]);
        } catch (\Exception $e) {
            $hasil->error_sql = $e->getMessage();
        }
        return $hasil;
    }
}
