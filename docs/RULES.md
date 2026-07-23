# Aturan Pengembangan (RULES) untuk Refactoring ke CI4

Untuk menjaga konsistensi, keamanan, dan kebersihan kode, seluruh pengembang yang berpartisipasi dalam proyek `emkl-approval-sby-ci4` diwajibkan mengikuti aturan berikut:

## 1. Aturan Global (User-Global)
1. **Hanya lakukan perubahan yang secara eksplisit diminta oleh user/task.**
2. **Strict Minimal-Diff:** Jangan mengubah bagian kode lain yang tidak diminta.
3. **Format Kode:** Ikuti format kode, indentasi, style, dan struktur yang sudah ada di codebase CI4 (PSR-12).
4. **Style Konsisten:** Jangan mengubah spasi, tab, newline, urutan kode, atau style penulisan yang sudah standar.
5. **Penambahan Tanpa Merombak:** Tambahkan kode baru hanya pada bagian yang diperlukan tanpa merombak ulang seluruh struktur file.
6. **Ikuti Pola:** Ikuti pola arsitektur, naming convention, dan struktur modul CI4 yang ditetapkan.
7. **No Unwanted Refactoring:** Jangan melakukan refactor, rename variabel, optimasi, atau cleanup KECUALI diminta secara eksplisit.
8. **Minimal Edit:** Jangan menulis ulang satu file penuh dalam PR/Diskusi, tampilkan hanya patch/section yang berubah.
9. **Fokus pada Baris Diubah:** Jika ada perubahan pada file, dokumentasikan hanya baris yang ditambah/diubah, bukan seluruh file.
10. **No Assumptions:** Jangan membuat asumsi di luar permintaan (misal: secara otomatis membuat route baru, model, atau struktur direktori jika tidak diinstruksikan).
11. **Minta Klarifikasi:** Jika instruksi/requirement kurang jelas, selalu minta klarifikasi sebelum mengubah kode.

## 2. Naming Conventions (Aturan CI4)
- **Controllers:** Menggunakan `PascalCase` dengan akhiran jamak/sesuai fungsi (contoh: `ApprovalTrip.php`). Terletak di `app/Controllers/`. Class harus inherit dari `BaseController`.
- **Models:** Menggunakan `PascalCase` dengan akhiran `Model` (contoh: `ApprovalTripModel.php`). Terletak di `app/Models/`. Class harus inherit dari `CodeIgniter\Model`.
- **Views:** Menggunakan `kebab-case` atau `snake_case` dalam subfolder sesuai entitas (contoh: `app/Views/approval/trip/index.php`).
- **Methods:** Menggunakan `camelCase` (contoh: `getApprovalList()`, `approveTransaction()`).
- **Database Tables & Fields:** Mempertahankan camelCase/PascalCase dari database asalnya (`TrApprovalTripH`, `FUserID`) karena bersumber dari database eksisting SQL Server (legacy compatibility).

## 3. Database dan Koneksi (SQL Server)
- Menggunakan Query Builder dari CodeIgniter 4 (`$this->db->table(...)`) sebanyak mungkin untuk operasi CRUD.
- Fungsi legacy `Generate_Procedure` dipindahkan sebagai method helper di dalam struktur Library/BaseModel dan hanya digunakan khusus mengeksekusi Stored Procedures yang rumit dari database `sqlsrv`.
- Harus selalu menggunakan Database Transactions (`transBegin()`, `transCommit()`, `transRollback()`) untuk setiap aksi insert/update multi-tabel (contoh: `TrApprovalTripH` dan `TrApprovalTripR`).

## 4. Keamanan dan Validasi
- Selalu gunakan perlindungan CSRF yang disetel `true` pada config CI4.
- Gunakan fitur Validasi bawaan CI4 (`$this->validate()`) sebelum memproses request POST.
- Escape output pada view (`esc($variabel)`) untuk mencegah serangan XSS.

## 5. UI/UX (Desain `sys-modern`)
- Gunakan framework CSS (seperti Bootstrap 5 atau Tailwind CSS) yang mendukung kustomisasi tema ke arah `sys-modern`.
- Gunakan SweetAlert2 untuk modal dialog Konfirmasi (contoh: "Apakah Anda yakin ingin menyetujui dokumen ini?").
- Hindari reload halaman sepenuhnya untuk modul approval, gunakan **AJAX fetch/XHR** dan render Datatables secara *Server-Side*.
