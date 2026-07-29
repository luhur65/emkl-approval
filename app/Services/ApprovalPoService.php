<?php

namespace App\Services;

use App\Models\ApprovalPoModel;

class ApprovalPoService
{
    protected $approvalPoModel;

    public function __construct()
    {
        $this->approvalPoModel = new ApprovalPoModel();
    }

    /**
     * Memproses pengambilan data Grid dengan standar pagination jqGrid.
     * Menerima array parameter GET/POST dari controller.
     */
    public function getGridList(array $params): \stdClass
    {
        // 1. Ekstrak parameter Filter Data
        $tgl = $params['tgl'] ?? date("Y-m-d");

        // 2. Ekstrak parameter Grid Paging jqGrid
        $page  = (int)($params['page'] ?? 1);
        $limit = (int)($params['rows'] ?? 50);

        // 3. Ambil Raw Data dari Model (Stored Procedure)
        $list = $this->approvalPoModel->getData($tgl);
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
        
        // 5. Mapping Baris (Rows) sesuai struktur kolom jqGrid
        $data = [];
        foreach ($slicedList as $d) {
            $check = $d['FIsApp'] ?? 0;
            $checkboxHtml = "<input type='checkbox' disabled " . ($check == 1 ? "checked" : "") . ">";

            $row = [
                'IdTarget'          => $d['FNTrans'],
                'FTgl'              => date("d-m-Y", strtotime($d['FTgl'])),
                'FNShipper'         => $d['FNShipper'],
                'FSaldoPiutang'     => number_format($d['FSaldoPiutang'] ?? 0, 2, '.', ','),
                'FSisaPiutang'      => number_format($d['FSisaPiutang'] ?? 0, 2, '.', ','),
                'FKelebihanPiutang' => number_format($d['FKelebihanPiutang'] ?? 0, 2, '.', ','),
                'FJumlahOrder'      => number_format($d['FJumlahOrder'] ?? 0, 2, '.', ','),
                'FIsApp'            => $checkboxHtml,
                'FDateApp'          => $d['FDateApp'] ?? '',
                'FUserApp'          => $d['FUserApp'] ?? '',
                'FTglInput'         => $d['FTglInput'] ?? '',
                'FUserId'           => $d['FUserId'] ?? ''
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
