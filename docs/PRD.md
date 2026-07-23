# Product Requirements Document (PRD)

## 1. Pendahuluan
Proyek **emkl-approval-sby** merupakan sistem web internal untuk mengelola berbagai alur persetujuan (approval) terkait operasional Ekspedisi Muatan Kapal Laut (EMKL). Aplikasi awalnya dibangun dengan CodeIgniter 3 dan menggunakan database SQL Server. Proyek ini direfactor menggunakan CodeIgniter 4 untuk meningkatkan keamanan, performa, dan maintenanceabilitas, serta menerapkan desain UI bergaya `sys-modern`.

## 2. Tujuan Refactoring
- **Upgrade Framework**: Dari CodeIgniter 3 ke CodeIgniter 4 (PHP 8.x support, Namespaces, Object-Oriented Routing).
- **Modernisasi UI/UX**: Menerapkan gaya antarmuka `sys-modern` (clean, minimalist, responsive, dark/light mode jika memungkinkan).
- **Standarisasi Database**: Mempertahankan penggunaan SQL Server (sqlsrv) namun mengadaptasi helper legacy (seperti `Generate_Procedure`) ke arsitektur Models yang lebih modern pada CI4.
- **Peningkatan Keamanan**: Menerapkan validasi input bawaan CI4, perbaikan manajemen session, dan CSRF protection.

## 3. Scope Fitur Utama
1. **Autentikasi (Login/Logout)**
   - Login menggunakan username dan password terenkripsi MD5.
   - Pengecekan autentikasi session tersentralisasi di BaseController atau menggunakan Filter bawaan CI4.
2. **Dashboard**
   - Menampilkan ringkasan data approval yang belum dan sudah diproses.
3. **Modul Approval**
   - **Approval Trip**: Persetujuan jumlah perjalanan truk (Trip) berdasarkan mandor dan tanggal. Terdiri dari header dan detail record.
   - **Approval PO (Purchase Order)**: Persetujuan pesanan pembelian barang.
   - **Approval Pengajuan**: Persetujuan pengajuan operasional lainnya.
   - **Approval Absensi**: Validasi kehadiran supir/karyawan.
   - **Approval Penawaran Harga**: Persetujuan harga yang ditawarkan pelanggan.
   - **Approval Extra Supir**: Tambahan ongkos atau lembur supir.
   - **Approval TOP**.
4. **Modul Operasional**
   - Trucking, Trip, dan Pengelolaan Stock.

## 4. Analisis Kode CI3 yang Direfactor
- **Controller/MY_Controller.php** direfactor menjadi `BaseController` di CI 4 beserta Filter Auth.
- **Helper/MY_Helper.php** (`Generate_Procedure`) dipindahkan ke Library atau Base Model di CI 4 agar selaras dengan DB CI4.
- **Datatables Ajax (Server-side)**: Controllers seperti `ApprovalTrip::ajax_list` direfactor dengan format standard JSON CI4 (`$this->response->setJSON()`).
- **Database Transaction**: Bawaan CI4 Database Transaction menggantikan method `$this->db->trans_begin()`.

## 5. Kebutuhan UI (Design sys-modern)
- **Komponen**: Sidebar navigasi, Header pencarian/profil, Card berbayang halus untuk tabel data.
- **Warna & Tipografi**: Skema warna netral dengan aksen warna primer korporat, font sans-serif modern (Inter atau Roboto).
- **Interaktivitas**: Menggunakan AJAX penuh untuk Approve/Reject, modal modern, dan notifikasi Toast / SweetAlert.
