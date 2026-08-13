<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Login::index');
$routes->match(['get', 'post'], 'login', 'Login::index');
$routes->get('login/logout', 'Login::logout');
$routes->post('login/unlock', 'Login::unlock');
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

// Pengajuan Trip (sisi ENTRI, dipakai mandor). Didaftarkan LEBIH DULU daripada
// route approval di bawahnya supaya `approvaltrip/approval` tidak pernah
// tertangkap sebagai parameter halaman entri.
//
// `hapus` sengaja POST-only: CI3 memakai `approvaltrip/delete/$id` yang juga
// menerima GET, sehingga cukup sebuah <img src="..."> di halaman mana pun untuk
// menghapus data atas nama user yang sedang login.
$routes->get('approvaltrip', 'ApprovalTrip::index');
$routes->match(['get', 'post'], 'approvaltrip/ajax_list_pengajuan', 'ApprovalTrip::ajax_list_pengajuan');
$routes->post('approvaltrip/simpan', 'ApprovalTrip::simpan');
$routes->post('approvaltrip/hapus/(:num)', 'ApprovalTrip::hapus/$1');

// Approval Trip Module
$routes->get('approvaltrip/approval', 'ApprovalTrip::approval');
$routes->match(['get', 'post'], 'approvaltrip/ajax_list', 'ApprovalTrip::ajax_list');
$routes->match(['get', 'post'], 'approvaltrip/select_all_ids', 'ApprovalTrip::select_all_ids');
$routes->post('approvaltrip/approved', 'ApprovalTrip::approved');
$routes->post('approvaltrip/unapproved', 'ApprovalTrip::unapproved');

// Approval Pengajuan Supir Serap Module. Sumbernya tabel TrApprovalAbsensi --
// isian modul "Pengajuan Supir Serap" -- BUKAN TrApprovalAbsensiJam9 milik
// approvalabsensi di bawah, meski namanya mirip. `unapproved` di sini hanya
// mengembalikan FIsApp ke 0; barisnya tetap ada.
$routes->get('approvalpengajuan', 'ApprovalPengajuan::index');
$routes->match(['get', 'post'], 'approvalpengajuan/ajax_list', 'ApprovalPengajuan::ajax_list');
$routes->match(['get', 'post'], 'approvalpengajuan/select_all_ids', 'ApprovalPengajuan::select_all_ids');
$routes->post('approvalpengajuan/approved', 'ApprovalPengajuan::approved');
$routes->post('approvalpengajuan/unapproved', 'ApprovalPengajuan::unapproved');

// Pengajuan Supir Serap (sisi ENTRI, dipakai mandor). Menulis ke tabel yang
// sama dengan approvalpengajuan di atas (TrApprovalAbsensi) tapi hak aksesnya
// berbeda -- lihat Controllers/Pengajuan.php.
//
// `simpan` menggantikan nama CI3 `approved`, yang menyesatkan: isinya INSERT
// pengajuan baru, bukan approval. `hapus` POST-only, alasan sama seperti
// approvaltrip/hapus di atas.
$routes->get('pengajuan', 'Pengajuan::index');
$routes->match(['get', 'post'], 'pengajuan/ajax_list', 'Pengajuan::ajax_list');
$routes->post('pengajuan/simpan', 'Pengajuan::simpan');
$routes->post('pengajuan/hapus/(:num)', 'Pengajuan::hapus/$1');

// Approval Absensi Jam 9 Module. `unapproved` di sini MENGHAPUS penanda
// approval (DELETE), bukan sekadar mengubah kolom status -- lihat
// ApprovalAbsensiModel::batalkanApprove().
$routes->get('approvalabsensi', 'ApprovalAbsensi::index');
$routes->match(['get', 'post'], 'approvalabsensi/ajax_list', 'ApprovalAbsensi::ajax_list');
$routes->match(['get', 'post'], 'approvalabsensi/select_all_ids', 'ApprovalAbsensi::select_all_ids');
$routes->post('approvalabsensi/approved', 'ApprovalAbsensi::approved');
$routes->post('approvalabsensi/unapproved', 'ApprovalAbsensi::unapproved');

// Approval Extra Supir Module. Filternya rentang tanggal (tgldari..tglsampai),
// bukan satu hari seperti modul approval lain, mengikuti CI3. Seluruh
// perubahan status lewat Stored Procedure Net_usp_appExtraSupir /
// Net_usp_UnappExtraSupir -- lihat ApprovalExtraSupirModel.
$routes->get('approvalextrasupir', 'ApprovalExtraSupir::index');
$routes->match(['get', 'post'], 'approvalextrasupir/ajax_list', 'ApprovalExtraSupir::ajax_list');
$routes->match(['get', 'post'], 'approvalextrasupir/select_all_ids', 'ApprovalExtraSupir::select_all_ids');
$routes->post('approvalextrasupir/approved', 'ApprovalExtraSupir::approved');
$routes->post('approvalextrasupir/unapproved', 'ApprovalExtraSupir::unapproved');
// CATATAN: ini TIDAK memberi kompatibilitas URL gaya CI3. Config\Feature::
// $autoRoutesImproved = true, dan AutoRouterImproved menyusun nama method sebagai
// <verb> + <segmen URI> -- jadi `approvaltop/ajax_list` mencarinya sebagai
// getAjax_list(). Tidak ada controller di aplikasi ini yang memakai konvensi itu,
// sehingga auto routing efektif tidak menjangkau apa pun; seluruh URL di atas
// jalan murni lewat route eksplisit. Jangan mematikan $autoRoutesImproved untuk
// "menghidupkan" auto routing: router lama membuka SEMUA method publik controller
// untuk semua HTTP verb, termasuk yang tidak diniatkan jadi endpoint.
$routes->setAutoRoute(true);
