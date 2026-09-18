# EMKL Approval System (CodeIgniter 4 Refactor)

Sistem Informasi Persetujuan dan Operasional Ekspedisi Muatan Kapal Laut (EMKL) yang di-refactor menggunakan framework modern **CodeIgniter 4**. Aplikasi ini bertujuan untuk memfasilitasi proses approval internal perusahaan seperti *Approval Trip*, *Approval PO*, *Approval Pengajuan*, *Approval Absensi*, dan *Trucking* dengan database Microsoft SQL Server.

## Fitur Utama
1. **Sistem Login Tersentralisasi**: Menggunakan intersep filter autentikasi untuk keamanan maksimum.
2. **Datatables Server-Side AJAX**: Memproses data secara efisien tanpa *page reload*.
3. **Approval Interaktif**: Kemampuan approve/reject secara langsung dengan konfirmasi visual `sys-modern`.
4. **Transaksi Terkelola**: Database Transactions (Commit/Rollback) pada penyimpanan Data Master & Detail (misal: Header & Detail Trip).

## Struktur Repositori & Dokumentasi
Dokumen penting lainnya telah dilampirkan dalam folder `docs/`:
- [`PRD.md`](docs/PRD.md) - *Product Requirements Document*, mencakup detail fitur dan arsitektur refactoring.
- [`FLOW_APLIKASI.md`](docs/FLOW_APLIKASI.md) - Skema alur aplikasi dari login hingga proses simpan database.
- [`RULES.md`](docs/RULES.md) - Standar penulisan kode dan aturan pengembangan dalam tim (`sys-modern` strict).
- [`MODUL_SSO.md`](docs/MODUL_SSO.md) - Single Sign-On (auth-sso / auth-sso-api): alur, konfigurasi `sso.*`, pendaftaran cabang di sisi SSO, pemetaan `FIDKaryawan`, dan perintah diagnosa `spark sso:*`.

## Panduan Instalasi (Development)

### 1. Persyaratan Sistem
- PHP >= 8.1
- Ekstensi PHP yang diaktifkan: `intl`, `mbstring`, `sqlsrv`, `pdo_sqlsrv`, `json`, `curl`.
- Web Server lokal: XAMPP / Laragon / IIS.
- Microsoft SQL Server.

### 2. Konfigurasi
1. Gandakan file `env` menjadi `.env`.
2. Buka `.env` dan atur Environment menjadi `development`:
   ```env
   CI_ENVIRONMENT = development
   ```
3. Atur koneksi Database ke SQL Server:
   ```env
   database.default.hostname = 'YOUR_SQL_SERVER_HOST'
   database.default.database = 'YOUR_DB_NAME'
   database.default.username = 'sa'
   database.default.password = 'YOUR_PASSWORD'
   database.default.DBDriver = 'SQLSRV'
   database.default.port     = 1433
   ```
4. Instal dependensi (apabila menggunakan Composer):
   ```bash
   composer install
   ```

### 3. Menjalankan Server Lokal
Jalankan perintah berikut di dalam direktori project:
```bash
php spark serve
```
Aplikasi dapat diakses melalui `http://localhost:8080`.

## Desain Antarmuka (`sys-modern`)
Aplikasi ini diinstruksikan untuk menggunakan pendekatan UI/UX bergaya **`sys-modern`**. Artinya:
- **Clean & Sleek**: Antarmuka bersih dari keramaian visual yang tidak perlu.
- **Card-based & Hover Effects**: Menampilkan data menggunakan gaya "Card" dengan bayangan halus.
- **Warna Monokrom dengan Aksen Primer**: Latar belakang putih/abu-abu halus dipadu aksen biru solid/teal untuk tombol dan notifikasi aktif.
- **SweetAlert2 & Toast**: Menggunakan notifikasi modern menggantikan `alert()` bawaan browser.

---
Dikembangkan oleh AI Assistant - Proyek Refactoring 2026.
