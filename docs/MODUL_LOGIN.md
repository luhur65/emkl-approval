# Dokumentasi Migrasi Modul Login (CI3 ke CI4)

Proyek: **EMKL Approval System (SBY)**  
Status: **Selesai Di-migrasi**  

Modul ini menangani autentikasi pengguna ke dalam sistem, beralih dari struktur CodeIgniter 3 yang tradisional menuju arsitektur CodeIgniter 4 yang lebih kokoh dan aman, sembari mengadopsi tampilan antar muka "Verdant Theme" dari sistem `sys-modern`.

---

## 1. Perbandingan Arsitektur

### CI3 (Sistem Lama)
- **Controller**: `application/controllers/Login.php`
- **Sesi**: Menggunakan native PHP `session_start()` dan `$_SESSION`.
- **Database**: Membaca basis data menggunakan helper buatan sendiri (`Generate_Procedure($sql)`).
- **Keamanan**: Sangat rentan terhadap serangan **SQL Injection** karena input pengguna digabungkan langsung secara mentah ke dalam kueri string (contoh: `where FUserID='".$pUser."'`).
- **Antarmuka**: Standar.

### CI4 (Sistem Baru)
- **Controller**: `app/Controllers/Login.php`
- **Model**: `app/Models/AuthModel.php`
- **Sesi**: Menggunakan _Service_ Session bawaan CI4 (`session()`) yang lebih terstruktur dan terlindungi (mendukung enkripsi cookie).
- **Database**: Menggunakan *Query Builder* (Active Record) CI4 yang secara default menggunakan metode _parameter binding_ dan _prepared statements_.
- **Keamanan**: Terlindungi penuh dari SQL Injection dan CSRF (melalui `csrf_field()` pada form login HTML).
- **Antarmuka**: Mengadopsi _Verdant Theme_ milik `sys-modern` (elegan, mendukung *dark-mode*, dan dilengkapi *security headers* tingkat lanjut yang memproteksi web dari *clickjacking* & *sniffing*).

---

## 2. Alur Kerja (Flow) Aplikasi

1. **Akses Halaman**: Pengguna mengakses `/login` atau root aplikasi (yang di-*routing* secara *default* ke `Login::index`).
2. **Cek Sesi Berjalan**: Controller akan mendeteksi apakah key `logged_emkl` sudah ada di dalam sesi. Jika YA, aplikasi langsung mem-bypass form dan mengarahkan ke halaman `/dashboard`.
3. **Pengisian Form**: Pengguna memasukkan `pUser` dan `pPassword`. Form ini dilindungi oleh token CSRF.
4. **Validasi (Backend)**: Saat tombol ditekan (metode POST), fungsi `validate()` dari CI4 memastikan bahwa username maupun kata sandi tidak boleh kosong (akan memberikan pesan peringatan ke halaman UI jika kosong).
5. **Eksekusi Autentikasi**:
   - `pPassword` akan dienkripsi dengan metode MD5 untuk menyesuaikan _legacy encoding_ di database SQL Server (*Tabel: `FUserList`*).
   - Data dilempar ke `AuthModel::validateUser($userId, $password)`.
   - Method `where()` CI4 menyeleksi *record* pengguna secara aman (tanpa *SQL string concatenation*).
6. **Inisialisasi Sesi**:
   - Jika pengguna terdaftar, 3 variabel utama disimpan ke sesi aktif: `FUserID`, `FNamaUser`, dan `logged_emkl` (sebagai penanda otorisasi utama).
   - Terjadi _redirect_ otomatis menuju laman internal sistem (Dashboard).

---

## 3. Komponen Tabel Database (SQL Server)

Modul ini berinteraksi dengan satu tabel yang dipetakan oleh model.
- **Tabel**: `FUserList`
- **Primary Key**: `FID` (int) — `FUserID` hanya identitas login (varchar, tanpa unique index). Diverifikasi lewat `INFORMATION_SCHEMA` saat integrasi SSO; `AuthModel::$primaryKey` mengikuti ini.
- **Kolom Target**:
  - `FUserID` (Username & Identifier)
  - `FNamaUser` (Nama Panjang Pegawai/Pengguna)
  - `FKode` (Password String yang disamakan dengan input MD5 pengguna)
  - `FIDKaryawan` (id master karyawan HR — dipakai login SSO, lihat [`MODUL_SSO.md`](MODUL_SSO.md))

---

## 4. Keamanan Lanjutan (Security Headers)

Khusus di CI4 ini, file `app/Filters/SecurityHeaders.php` didaftarkan secara _global_ untuk merender HTTP *response headers* di setiap halaman, termasuk Login, dengan konfigurasi sebagai berikut:
*   `X-Frame-Options: SAMEORIGIN`
*   `X-Content-Type-Options: nosniff`
*   `Referrer-Policy: strict-origin-when-cross-origin`
*   `Content-Security-Policy`: Dinonaktifkan sementara untuk menampung *legacy inline-style* Verdant dari `sys-modern`. 

## 5. File yang Dimodifikasi/Dibuat

| Direktori                                | Fungsi                                                                |
| ---------------------------------------- | --------------------------------------------------------------------- |
| `app/Controllers/Login.php`              | Inti _controller_ yang menangani otorisasi masuk dan keluar.          |
| `app/Models/AuthModel.php`               | Kelas _model_ untuk memetakan tabel `FUserList`.                      |
| `app/Views/auth/login.php`               | Tampilan UI Form (_Verdant Theme_, HTML/CSS/JS).                      |
| `public/`                                | Semua aset statis _sys-modern_ tersalin utuh (_css_, _js_, _fonts_).  |
| `app/Config/Filters.php`                 | Penyetelan filter keamanan.                                           |
| `app/Filters/SecurityHeaders.php`        | Eksekusi penyetelan _Header_ anti-serangan modern.                    |
| `app/Config/App.php`                     | Logika dinamis untuk penyesuaian _BaseURL_ secara otomatis via server.|
