<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
// use App\Models\MmenutopModel;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;
    protected $helpers = ['url', 'form', 'my_helper', 'global_helper', 'asset_helper'];
    protected string $layout = 'home';
    protected $mmenutopModel;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        // $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        // $this->session = service('session');
        // $this->mmenutopModel = new MmenutopModel();
    }

    protected function render(string $page, array $data = [])
    {
        // Bypass menu dari database jika belum di-porting
        $data['sqlmenu'] = []; // $this->mmenutopModel->get_data();

        $output  = view('partials/header', $data);
        $output .= view('partials/navbar', $data);
        $output .= view('partials/sidebar', $data);
        $output .= view($this->getLayout(), ['template' => $page, 'data' => $data]);
        $output .= view('partials/footer', $data);

        return $output;
    }
    /**
     * Balasan JSON seragam saat database tidak bisa dihubungi.
     *
     * Tanpa ini, kegagalan koneksi keluar sebagai halaman error 500 CI4 di
     * dalam respons AJAX. Grid tidak menampilkannya (loader grid hanya menulis
     * ke console), jadi user cuma melihat grid kosong tanpa penjelasan --
     * sementara pesan mentahnya membocorkan nama driver, host, dan SQLSTATE.
     *
     * Dipakai modul yang datanya ada di database terpisah (grup `dbtruck2`:
     * Approval Trip & Approval Absensi), yang servernya bisa saja tidak
     * terjangkau padahal database utama sehat.
     *
     * 503 dipilih, bukan 500: ini kegagalan sementara pada layanan di baliknya,
     * dan berbeda dari 401 (sesi habis) maupun 403 (hak akses) yang sudah punya
     * arti sendiri di sisi klien.
     */
    protected function jsonErrorDatabase(\Throwable $e, string $namaDb): ResponseInterface
    {
        log_message('error', 'Koneksi database "' . $namaDb . '" gagal: ' . $e->getMessage());

        return $this->response
            ->setStatusCode(503)
            ->setJSON([
                'error' => 'Tidak bisa terhubung ke database ' . $namaDb . '. '
                         . 'Server database-nya sedang tidak dapat dihubungi -- '
                         . 'silakan coba lagi, atau hubungi admin bila berlanjut.',
                'msg'   => '',
            ]);
    }

    protected function setLayout(string $layout)
    {
        $this->layout = $layout;
        return $this;
    }

    protected function getLayout(): string
    {
        return 'partials/layouts/' . $this->layout;
    }

    protected function operationAll($filters)
    {
        if (empty($filters)) return " ";

        $filters = str_replace('\"', '"', $filters);
        $filters = str_replace('"[', '[', $filters);
        $filters = str_replace(']"', ']', $filters);
        $filters = json_decode($filters);

        if (!$filters) return " ";

        $where = " ";
        $whereArray = array();
        $rules = $filters->rules;
        $groupOperation = $filters->groupOp;
        $found = 0;

        foreach ($rules as $rule) {
            $fieldName = $rule->field;
            $fieldData = $rule->data; // TODO: Implement proper escaping if not using Query Builder

            $numericColumns = ['FNOMINAL', 'FSISA', 'FSELISIH', 'FTOP', 'FJUMLAHMUATAN', 'FJUMLAHBONGKARAN', 'FJUMLAHEXIM', 'FOMSET', 'FBIAYALAPANGAN', 'FNOMPPH23', 'FPROFIT', 'FMARGIN'];
            if (in_array(strtoupper($fieldName), $numericColumns)) {
                $fieldData = str_replace(',', '', $fieldData);
            }

            // Handle calculated field FNTgl
            if ($fieldName == 'FNTgl') {
                $fieldName = "(ltrim(rtrim(str(FThnJob)))+'-'+(case when FBlnJob>=10 then '' else '0' end)+ltrim(rtrim(str(FBlnJob))))";
            }

            switch ($rule->op) {
                case "eq":
                    $fieldOperation = " = '" . $fieldData . "'";
                    break;
                case "ne":
                    $fieldOperation = " != '" . $fieldData . "'";
                    break;
                case "lt":
                    $fieldOperation = " < '" . $fieldData . "'";
                    break;
                case "gt":
                    $fieldOperation = " > '" . $fieldData . "'";
                    break;
                case "le":
                    $fieldOperation = " <= '" . $fieldData . "'";
                    break;
                case "ge":
                    $fieldOperation = " >= '" . $fieldData . "'";
                    break;
                case "nu":
                    $fieldOperation = " = ''";
                    break;
                case "nn":
                    $fieldOperation = " != ''";
                    break;
                case "in":
                    $fieldOperation = " IN (" . $fieldData . ")";
                    break;
                case "ni":
                    $fieldOperation = " NOT IN '" . $fieldData . "'";
                    break;
                case "bw":
                    $fieldOperation = " LIKE '" . $fieldData . "%'";
                    break;
                case "bn":
                    $fieldOperation = " NOT LIKE '" . $fieldData . "%'";
                    break;
                case "ew":
                    $fieldOperation = " LIKE '%" . $fieldData . "'";
                    break;
                case "en":
                    $fieldOperation = " NOT LIKE '%" . $fieldData . "'";
                    break;
                case "cn":
                    $fieldOperation = " LIKE '%" . $fieldData . "%'";
                    break;
                case "nc":
                    $fieldOperation = " NOT LIKE '%" . $fieldData . "%'";
                    break;
                default:
                    $fieldOperation = "";
                    break;
            }

            if ($fieldOperation != "") {
                $dateColumns = ['FTGL', 'FTGLJT', 'FNTGL'];
                if (in_array(strtoupper($fieldName), $dateColumns)) {
                    $whereArray[] = "FORMAT(CAST(" . $fieldName . " AS DATETIME), 'dd-MMM-yyyy', 'en-US')" . $fieldOperation;
                } else {
                    $whereArray[] = $fieldName . $fieldOperation;
                }
            }
        }

        if (count($whereArray) > 0) {
            $where .= join(" " . $groupOperation . " ", $whereArray);
        }

        return $where;
    }
}
