<?php

namespace App\Services;

use App\Models\ApprovalTopModel;

class ApprovalTopService
{
    protected $approvalTopModel;

    public function __construct()
    {
        $this->approvalTopModel = new ApprovalTopModel();
    }

    /**
     * Memproses pengambilan data Grid dengan standar pagination jqGrid.
     * Menerima array parameter GET/POST dari controller.
     */
    public function getGridList(array $params): \stdClass
    {
        // 1. Ekstrak parameter Filter Data
        $tgl = $params['tgl'] ?? date("Y-m-d");
        $bit = $params['bit'] ?? 0;

        // 2. Ekstrak parameter Grid Paging jqGrid
        $page  = (int)($params['page'] ?? 1);
        $limit = (int)($params['rows'] ?? 50);
        $sidx  = $params['sidx'] ?? '1';
        $sord  = $params['sord'] ?? 'asc';

        // 3. Ambil Raw Data dari Model (Stored Procedure)
        $list = $this->approvalTopModel->getData($tgl, $bit);
        $count = count($list);

        // 4. Kalkulasi Paging
        $total_pages = 0;
        if ($count > 0) {
            $total_pages = ceil($count / $limit);
        }

        if ($page > $total_pages && $total_pages > 0) {
            $page = $total_pages;
        }
        
        $start = $limit * $page - $limit;
        if ($start < 0) {
            $start = 0;
        }

        // Potong Data (Slice)
        $slicedList = array_slice($list, $start, $limit);
        
        // 5. Mapping Baris (Rows) menggunakan Associative Array (key-value)
        $data = [];
        foreach ($slicedList as $d) {
            $row = [
                'IdTarget'       => $d['FJurnal'],
                'FJurnal'        => $d['FJurnal'],
                'FNShipper'      => $d['FNShipper'],
                'FTgl'           => date("d-m-Y", strtotime($d['FTgl'])),
                'FNMarketing'    => $d['FNMarketing'],
                'FJumlahInvoice' => $d['FJumlahInvoice'],
                'FJumlahjob'     => $d['FJumlahjob']
            ];
            $data[] = $row;
        }

        // 6. Siapkan Output standar jqGrid
        $responce = new \stdClass();
        $responce->page = $page;
        $responce->total = $total_pages;
        $responce->records = $count;
        $responce->rows = $data;

        return $responce;
    }
}
