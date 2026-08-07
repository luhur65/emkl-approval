<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Login::index');
$routes->match(['get', 'post'], 'login', 'Login::index');
$routes->get('login/logout', 'Login::logout');
$routes->get('home', 'Home::index');
$routes->get('approvaltop', 'ApprovalTop::index');
$routes->match(['get', 'post'], 'approvaltop/ajax_list', 'ApprovalTop::ajax_list');
$routes->match(['get', 'post'], 'approvaltop/select_all_ids', 'ApprovalTop::select_all_ids');
$routes->get('approvaltop/get_detail/(:any)', 'ApprovalTop::get_detail/$1');
// Aksi dipisah per URL, bukan lagi satu endpoint + field POST `prosesdata`:
// jenis proses jadi ditentukan server dari route yang dipanggil, sehingga klien
// tidak bisa lagi menukar approve <-> un-approve lewat isi form.
$routes->post('approvaltop/approved', 'ApprovalTop::approved');
$routes->post('approvaltop/unapproved', 'ApprovalTop::unapproved');

// Approval PO Module
$routes->get('approvalpo', 'ApprovalPo::index');
$routes->match(['get', 'post'], 'approvalpo/ajax_list', 'ApprovalPo::ajax_list');
$routes->match(['get', 'post'], 'approvalpo/select_all_ids', 'ApprovalPo::select_all_ids');
// Aksi dipisah per URL, sama seperti approvaltop di atas: jenis proses
// ditentukan server dari route yang dipanggil, sehingga klien tidak bisa
// menukar approve <-> un-approve lewat isi form. Bedanya dengan approvaltop,
// grid di sini menampilkan kedua status sekaligus (SP-nya tanpa parameter bit),
// jadi baris yang statusnya tidak cocok dengan aksi akan dilewati server.
$routes->post('approvalpo/approved', 'ApprovalPo::approved');
$routes->post('approvalpo/unapproved', 'ApprovalPo::unapproved');

// Approval Penawaran Harga Module -- dua halaman: approval harga & cetak ulang.
// `approvalpharga/cetak` didaftarkan lebih dulu agar tidak tertutup route lain
// yang berbagi awalan yang sama.
$routes->get('approvalpharga', 'ApprovalPharga::index');
$routes->match(['get', 'post'], 'approvalpharga/ajax_list', 'ApprovalPharga::ajax_list');
$routes->match(['get', 'post'], 'approvalpharga/select_all_ids', 'ApprovalPharga::select_all_ids');
$routes->post('approvalpharga/approved', 'ApprovalPharga::approved');
$routes->post('approvalpharga/unapproved', 'ApprovalPharga::unapproved');

$routes->get('approvalpharga/cetak', 'ApprovalPharga::cetak');
$routes->match(['get', 'post'], 'approvalpharga/ajax_list_cetak', 'ApprovalPharga::ajax_list_cetak');
$routes->match(['get', 'post'], 'approvalpharga/select_all_ids_cetak', 'ApprovalPharga::select_all_ids_cetak');
$routes->post('approvalpharga/approved_cetak', 'ApprovalPharga::approved_cetak');

$routes->get('harilibur', 'Harilibur::index');

// Approval Trip Module
$routes->get('approvaltrip/approval', 'ApprovalTrip::approval');
$routes->match(['get', 'post'], 'approvaltrip/ajax_list', 'ApprovalTrip::ajax_list');
$routes->match(['get', 'post'], 'approvaltrip/select_all_ids', 'ApprovalTrip::select_all_ids');
$routes->post('approvaltrip/approved', 'ApprovalTrip::approved');
$routes->post('approvaltrip/unapproved', 'ApprovalTrip::unapproved');

// Approval Absensi Jam 9 Module. `unapproved` di sini MENGHAPUS penanda
// approval (DELETE), bukan sekadar mengubah kolom status -- lihat
// ApprovalAbsensiModel::batalkanApprove().
$routes->get('approvalabsensi', 'ApprovalAbsensi::index');
$routes->match(['get', 'post'], 'approvalabsensi/ajax_list', 'ApprovalAbsensi::ajax_list');
$routes->match(['get', 'post'], 'approvalabsensi/select_all_ids', 'ApprovalAbsensi::select_all_ids');
$routes->post('approvalabsensi/approved', 'ApprovalAbsensi::approved');
$routes->post('approvalabsensi/unapproved', 'ApprovalAbsensi::unapproved');
// CATATAN: ini TIDAK memberi kompatibilitas URL gaya CI3. Config\Feature::
// $autoRoutesImproved = true, dan AutoRouterImproved menyusun nama method sebagai
// <verb> + <segmen URI> -- jadi `approvaltop/ajax_list` mencarinya sebagai
// getAjax_list(). Tidak ada controller di aplikasi ini yang memakai konvensi itu,
// sehingga auto routing efektif tidak menjangkau apa pun; seluruh URL di atas
// jalan murni lewat route eksplisit. Jangan mematikan $autoRoutesImproved untuk
// "menghidupkan" auto routing: router lama membuka SEMUA method publik controller
// untuk semua HTTP verb, termasuk yang tidak diniatkan jadi endpoint.
$routes->setAutoRoute(true);
