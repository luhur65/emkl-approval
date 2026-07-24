<?php

namespace App\Models;

use CodeIgniter\Model;

class HomeModel extends Model
{
    public function getJumlahBelumAppTop()
    {
        $sql = "SELECT COUNT(FJurnal) AS jumlah FROM MShipperApprovalValidasiTOPHeader WHERE ISNULL(FIsApp, 0) = 0";
        $query = $this->db->query($sql);
        $result = $query->getRow();
        
        return $result ? $result->jumlah : 0;
    }
}
