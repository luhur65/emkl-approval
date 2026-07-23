# Skema Flow Aplikasi emkl-approval-sby (CI4)

## 1. Flow Otentikasi (Login)
1. **User** membuka halaman utama (`/` atau `/login`).
2. **Sistem** menampilkan halaman form login.
3. **User** memasukkan `username` dan `password`.
4. **Auth Controller** memproses form (POST).
5. **Auth Model** melakukan query ke SQL Server (`FUserList`) membandingkan `FUserID` dan `FKode` (MD5 Password).
6. Jika **Valid**: Sistem menyimpan `FUserID` dan `FNamaUser` ke Session, menandai status `logged_emkl`, lalu redirect ke halaman `/dashboard`.
7. Jika **Tidak Valid**: Sistem mengembalikan ke halaman login dengan pesan error.

## 2. Global Request Flow (Filter CI4)
1. Setiap request menuju rute operasional atau approval (contoh: `/approval-trip/*`) akan dicegat oleh **AuthFilter**.
2. **AuthFilter** mengecek keberadaan session `logged_emkl`.
3. Jika session **kosong**, pengguna langsung dialihkan ke `/login`.
4. Jika session **ada**, request dilanjutkan ke Controller tujuan.

## 3. Flow Modul Approval (Contoh: Approval Trip)
**A. Melihat Daftar Approval**
1. **User** mengakses menu `Approval > Trip` (`/approval-trip`).
2. **ApprovalTrip Controller** memanggil method `index()`.
3. Controller memuat view utama yang berisi template `Datatables` untuk daftar approval.
4. **View (AJAX)** menembak rute `/approval-trip/ajax-list` dengan parameter tanggal.
5. **ApprovalTrip Controller** merespon dengan data JSON.
6. **Datatables** me-render baris data ke dalam tabel di sisi klien.

**B. Membuat/Menyimpan Pengajuan Trip (Entry)**
1. **User** menekan tombol `Tambah Trip` (via Modal/Halaman Tambah).
2. **User** mengisi Form: `Tanggal`, `Jumlah Trip`, `Mandor`.
3. Form di-submit via AJAX (POST) ke rute `/approval-trip/simpan`.
4. **Controller** memulai Database Transaction (`$this->db->transBegin()`).
5. Menyimpan Header ke `TrApprovalTripH`.
6. Mendapatkan Insert ID (FID), lalu looping sebanyak `Jumlah Trip` untuk menyimpan detail ke tabel `TrApprovalTripR`.
7. Jika sukses, `$this->db->transCommit()`, dan mengembalikan JSON Sukses.
8. Jika gagal, `$this->db->transRollback()`, dan mengembalikan JSON Gagal.

**C. Proses Approval (Approve/Reject)**
1. Pada tabel Datatables `Approval Trip`, **User** (dengan hak akses) menekan tombol **Approve**.
2. Sistem menembak rute AJAX POST `/approval-trip/approved` dengan membawa Array ID dari baris yang dicentang/dipilih.
3. **Controller** menerima ID.
4. Melakukan *Update* ke tabel `TrApprovalTripH` dengan mengisi `FTglApp`, `FUserApp`, dan status `FIsApp`.
5. Mengembalikan response sukses ke frontend (diikuti reload Datatables & Toast Notifikasi).
