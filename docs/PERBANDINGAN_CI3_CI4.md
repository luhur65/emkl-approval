# Audit Migrasi: EMKL Approval SBY dari CodeIgniter 3 ke CodeIgniter 4

Perbandingan menyeluruh antara `emkl-approval-sby` (CI 3, 2018–2024) dan `emkl-approval-sby-ci4`: skema arsitektur, alur request, apa yang dirombak beserta alasannya, perubahan antarmuka, dan status pengujian yang sebenarnya ada di repositori.

| | Lama | Baru |
| --- | --- | --- |
| Framework | CodeIgniter 3.1 | CodeIgniter 4.7 |
| PHP | 5/7 | 8.2 |
| Grid | DataTables | jqGrid 5.7.0 |
| Tema | AdminLTE 2 / Bootstrap 3 | AdminLTE 3 / Bootstrap 4 |
| Database | SQL Server (2 grup koneksi: `default`, `dbtruck2`) | sama, tidak berubah |

Disusun 14 Agustus 2026 dari pembacaan langsung kedua basis kode.

---

## Daftar Isi

1. [Ringkasan](#1-ringkasan)
2. [Peta modul: apa yang pindah, apa yang tidak](#2-peta-modul-apa-yang-pindah-apa-yang-tidak)
3. [Skema arsitektur](#3-skema-arsitektur)
4. [Alur aplikasi](#4-alur-aplikasi)
5. [Apa saja yang dirombak](#5-apa-saja-yang-dirombak)
6. [Sisi antarmuka](#6-sisi-antarmuka)
7. [Pengujian: apa yang benar-benar ada](#7-pengujian-apa-yang-benar-benar-ada)
8. [Sisa pekerjaan yang terlihat dari repositori](#8-sisa-pekerjaan-yang-terlihat-dari-repositori)

---

## 1. Ringkasan

Aplikasi lama adalah CI 3 gaya 2018: satu file controller berisi segalanya, SQL dirangkai dari string, sesi memakai `session_start()` mentah, dan setiap query menembak SQL Server lewat koneksi `sqlsrv_connect()` sendiri di luar database layer framework.

Versi CI 4 memakai ulang **database, stored procedure, dan aturan bisnis yang sama persis** — yang diganti adalah seluruh lapisan aplikasi di atasnya: routing, otorisasi, akses data, pipeline grid, dan antarmuka.

| Angka | Nilai |
| --- | ---: |
| Modul approval dipindah | 8 |
| Modul entri (mandor) | 2 |
| Modul CI3 tidak dibawa | 6 |
| Service layer baru | 10 |
| Baris PHP aplikasi (`app/`, non-view) | ~6.200 |
| Baris view | ~8.000 |

**Kesimpulan singkat:** migrasi fungsional sudah lengkap untuk lingkup approval (8 modul + login + dashboard + 2 modul entri), dengan perbaikan keamanan dan kebenaran data yang substansial. Yang belum ada adalah **bukti uji otomatis** — verifikasi dilakukan manual, dan direktori `build/` menunjukkan PHPUnit belum pernah dijalankan pada checkout ini. Rinciannya di [Bagian 7](#7-pengujian-apa-yang-benar-benar-ada).

---

## 2. Peta modul: apa yang pindah, apa yang tidak

| Modul | CI 3 | CI 4 | Status | Catatan pemindahan |
| --- | --- | --- | --- | --- |
| **Login / Logout** | `Login.php` + `Mlogin` | `Login.php` + `AuthModel` | Pindah | SQL injection pada form login dihilangkan; ditambah endpoint `login/unlock` untuk lockscreen. |
| **Dashboard** | `Home.php` | `Home.php` + `HomeModel` | Pindah | Hitungan TOP belum di-approve; query pindah ke model. |
| **Approval TOP Pre Orderan** | `ApprovalTOP.php` | Controller + Service + Model | Pindah | SP `usp_GetListAppPreJob`, `usp_AppPreJob`, `usp_UnAppPreJob`. Ditambah subgrid detail invoice. |
| **Approval PO** | `ApprovalPO.php` | Controller + Service + Model | Pindah | Grid menampilkan kedua status sekaligus; baris yang tak cocok aksi ditolak server. |
| **Approval Penawaran Harga** | `ApprovalPHarga.php` | Controller + Service + Model | Pindah | Dua halaman: approval & cetak ulang PH (`approvalpharga/cetak`). |
| **Approval Trip** | `ApprovalTrip.php` | Controller + 2 Service + 2 Model | Pindah | Sisi approval dan sisi entri (mandor) dipisah jadi route & hak akses berbeda. |
| **Approval Pengajuan Supir Serap** | `ApprovalPengajuan.php` | Controller + Service + Model | Pindah | Tabel `TrApprovalAbsensi`; `unapproved` hanya mengembalikan `FIsApp` ke 0. |
| **Approval Absensi Jam 9** | `ApprovalAbsensi.php` | Controller + Service + Model | Pindah | Kunci gabungan (`FKGdg`, `FKSupir`, `FTgl`); `unapproved` menghapus baris penanda. |
| **Approval Extra Supir** | `ApprovalExtraSupir.php` | Controller + Service + Model | Pindah | Filter rentang tanggal, bukan satu hari. Seluruh perubahan lewat SP `Net_usp_appExtraSupir`. |
| **Pengajuan Supir Serap (entri)** | `Pengajuan.php` | `Pengajuan.php` + Service + Model | Pindah | Method CI3 bernama `approved()` padahal isinya INSERT — di CI4 dinamai `simpan()`. |
| **Kalender hari libur** | — | `Harilibur.php` | Baru | Proxy ke API hari libur nasional untuk pewarnaan datepicker. |
| **Trucking** | `Trucking.php` (5,7 KB) | — | Tidak dibawa | Di luar lingkup approval; masih hidup di aplikasi Trucking terpisah. |
| **Trip (operasional)** | `Trip.php` (6,2 KB) | — | Tidak dibawa | — |
| **Stock, Profile, Welcome, Approval** | 4 controller | — | Tidak dibawa | Sebagian besar sudah tidak dipakai / view-nya kosong di CI3. |

> **Perlu diputuskan.** Enam controller CI 3 tidak ikut dipindah. Kalau itu memang keputusan lingkup, sebaiknya ditulis eksplisit di `docs/PRD.md` — PRD saat ini masih menyebut "Modul Operasional: Trucking, Trip, dan Pengelolaan Stock" sebagai bagian dari lingkup.

---

## 3. Skema arsitektur

Perubahan paling mendasar bukan pada versi framework, melainkan pada **jumlah lapisan** dan pada **siapa yang memegang koneksi database**.

### CI 3 — dua lapis

```
Controller  →  Generate_Procedure()  →  sqlsrv_connect()
```

- Controller memegang semuanya: baca `$_POST`, rangkai SQL, format tanggal, susun JSON, `echo`.
- Model hanya pembungkus tipis — beberapa berisi 1–2 baris, bahkan ada yang kosong (`Mhome.php`: 47 byte).
- `MY_Helper.php` punya tiga varian `Generate_Procedure` yang membuka koneksi `sqlsrv` **baru setiap pemanggilan**, di luar database layer CI.
- Kredensial `sa` per cabang ditulis langsung di dalam helper.
- Otorisasi menu ada di `__construct()` — dan hanya di 2 dari 8 modul.

### CI 4 — empat lapis

```
Filter  →  Controller  →  Service  →  Model
```

- **Filter** — `AuthFilter` (sesi) dan `csrf` berlaku global sebelum controller mana pun jalan.
- **Controller** — hanya routing, hak akses per-endpoint, dan bentuk respons. 18–260 baris.
- **Service** — logika grid, format tampilan, aturan kelayakan approve, ringkasan hasil. 212–304 baris.
- **Model** — hanya SQL / pemanggilan SP dengan parameter terikat.
- `GridPipeline` (trait) — pencarian, sorting, paging, "pilih semua" dipakai bersama lintas modul.
- Koneksi lewat grup `default` & `dbtruck2` di `.env`; pooling dikelola framework.

### Pemetaan komponen satu per satu

| Komponen CI 3 | Menjadi | Perubahan inti |
| --- | --- | --- |
| `core/MY_Controller.php` | `Controllers/BaseController.php` | `render()` mengembalikan string view (tidak lagi `echo` berantai), plus helper JSON error database. |
| `helpers/MY_Helper.php` | **Dihapus** | Tiga `Generate_Procedure*` dibuang; peta host + password `sa` per cabang ikut hilang dari kode. |
| `checkMenu()`, `checkMenuMandor()` | `Helpers/my_helper.php` | Dipertahankan, tapi kini lewat Query Builder dengan parameter terikat. |
| `$this->load->database('dbtruck2')` | Grup `dbtruck2` di `Config/Database.php` + `.env` | Nama database per cabang tidak lagi hard-coded. |
| `hooks/maintenance_hook.php` | Belum dipakai | Belum ada padanan Events/Filter di CI4. |
| Cek sesi di tiap `__construct()` | `Filters/AuthFilter.php` (global) | Satu tempat, plus balasan 401 JSON khusus untuk AJAX. |
| — | `Filters/SecurityHeaders.php` | HSTS, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy pada setiap respons. |

---

## 4. Alur aplikasi

### 4.1 Login

**CI 3**

1. POST `pUser` + `pPassword` tanpa token CSRF.
2. Password di-MD5 lalu **dirangkai langsung ke string SQL**.
3. `Generate_Procedure()` membuka koneksi `sqlsrv` baru dan menjalankannya.
4. Sukses → `$_SESSION` diisi lewat `session_start()` native.
5. Redirect ke `home`.

**CI 4**

1. POST dilindungi **token CSRF** (`csrf_field()`).
2. `$this->validate()` memastikan kedua field terisi.
3. `AuthModel::validateUser()` — Query Builder, **parameter terikat**.
4. Sukses → `session()->set()` (Session service CI4).
5. Redirect ke `home`. Sesi aktif → `/login` otomatis dipantulkan balik.

MD5 dipertahankan karena kolom `FUserList.FKode` di database produksi memang menyimpan MD5 — bukan pilihan desain baru.

### 4.2 Setiap request

**CI 3**

1. Auto-routing: URL apa pun dipetakan ke method publik controller.
2. Tiap controller memanggil `session_start()` sendiri di constructor.
3. Cek `@$_SESSION['logged_emkl']` — **disalin-tempel di 9 controller**.
4. Gagal → `redirect("")`, termasuk untuk request AJAX.

**CI 4**

1. Route eksplisit per URL + per HTTP verb di `Config/Routes.php`.
2. `csrf` filter memeriksa token pada seluruh request pengubah data.
3. `AuthFilter` memeriksa sesi — **satu tempat untuk semua rute**.
4. Gagal + AJAX → **HTTP 401 + JSON**; gagal + halaman → redirect ke `/login`.
5. Controller memanggil `denyIfNoAccess()` untuk hak akses menu — di **setiap** endpoint, bukan hanya halamannya.

> **Kenapa 401, bukan redirect.** jQuery mengikuti redirect diam-diam lalu menyerahkan HTML halaman login ke handler `success`. Pada endpoint approve, sesi yang habis akan terlihat persis seperti proses yang berhasil — user diberi tahu "berhasil" padahal tidak satu baris pun diproses.

### 4.3 Memuat data grid

**CI 3 — DataTables**

1. Halaman render, JS memanggil `ajax_list?tgl=…&bit=…`.
2. Controller merangkai SQL berisi **`$_GET` mentah** atau memanggil SP.
3. Seluruh baris dikirim sekaligus sebagai array posisional `{"data": [[...]]}`.
4. DataTables melakukan paging, sorting, dan pencarian **di browser**.

**CI 4 — jqGrid + lazy loading**

1. Grid meminta halaman pertama ke `ajax_list` (`page`, `rows`, `sidx`, `sord`, `filters`).
2. Service mengambil data lewat SP, memetakannya ke **array asosiatif bernama kunci**.
3. `GridPipeline` menyaring → mengurutkan → memotong satu halaman.
4. Respons standar jqGrid: `page`, `total`, `records`, `rows`.
5. Scroll memuat halaman berikutnya dan menyimpannya di cache klien.
6. "Pilih semua" memanggil `select_all_ids` — **seluruh kunci hasil filter**, bukan hanya baris yang tampil.

### 4.4 Proses approve

**CI 3**

1. Semua id terpilih disuntikkan sebagai `<input name="id[]">` lalu form di-`serialize()`.
2. Jenis proses dikirim sebagai field POST `prosesdata` (0 = approve, 1 = un-approve).
3. Server **looping tanpa validasi apa pun** dan menjalankan SP per id.
4. Selesai → `alert('Proses berhasil')`, tanpa tahu berapa baris benar-benar terproses.

**CI 4**

1. Id dikirim sebagai **satu** field `ids` berisi JSON — menghindari batas `max_input_vars`.
2. Jenis proses ditentukan **dari route yang dipanggil** (`/approved` vs `/unapproved`).
3. Hak akses diperiksa ulang di endpoint.
4. Duplikat dibuang; nilai non-skalar disaring.
5. Service menyusun **daftar baris yang benar-benar layak** lewat SP yang sama dengan pengisi grid.
6. Baris tak layak dilewati, bukan dieksekusi diam-diam.
7. Balasan berisi rincian: `total`, `berhasil`, `gagal[]`, `dilewati[]`.

> **Kenapa penyaringan kelayakan itu penting.** `usp_UnAppPreJob` memanggil `usp_GetReminderAppPreJob`, yang merender PDF lewat `xp_cmdshell` lalu **mengirim pesan WhatsApp ke manajemen**. Di CI 3, id duplikat atau sisa seleksi dari filter sebelumnya tetap dieksekusi — artinya notifikasi ganda ke manajemen, dan tetap dilaporkan "Proses berhasil".

---

## 5. Apa saja yang dirombak

### A. Keamanan

| Isu di CI 3 | Dampak | Penanganan di CI 4 |
| --- | --- | --- |
| SQL injection pada form login dan pada `ajax_list` (`$_GET['tgl']` masuk mentah ke string SQL) | Kritis | Query Builder + parameter terikat (`EXEC sp ?, ?`) di seluruh model. |
| Tidak ada proteksi CSRF sama sekali | Kritis | Filter `csrf` global; token disuntikkan ke **semua** AJAX pengubah data lewat satu `$.ajaxPrefilter` di header. |
| Hapus data lewat GET: `approvaltrip/delete/$id` | Kritis | Route POST-only. Sebuah `<img src>` di halaman mana pun tidak lagi bisa menghapus data atas nama user. |
| Approve & un-approve berbagi satu endpoint, dibedakan field POST | Tinggi | Dua route terpisah; jenis proses tidak lagi bisa ditukar dari sisi klien. |
| Hak akses menu hanya diperiksa di `__construct()`, dan hanya pada 2 dari 8 modul | Tinggi | `denyIfNoAccess()` di setiap endpoint; modul entri mandor pakai `checkMenuMandor()`. |
| Password `sa` semua cabang tertulis di `MY_Helper.php` dan ikut masuk repositori | Kritis | Helper dihapus; kredensial hanya di `.env` yang tidak di-commit. |
| Sesi habis dibalas redirect pada request AJAX | Tinggi | 401 + JSON, sehingga klien bisa membedakan gagal dari berhasil. |
| Tidak ada security header | Sedang | HSTS, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy. |

### B. Kebenaran data

- **Approve buta → approve tersaring.** CI 3 menjalankan SP untuk setiap id yang dikirim klien. CI 4 lebih dulu menyusun himpunan baris yang benar-benar berstatus sesuai, memakai SP yang sama dengan pengisi grid — jadi definisi "layak" persis sama dengan yang dilihat user.
- **Laporan hasil per baris.** SP approval tidak melaporkan `@@ROWCOUNT` maupun `RAISERROR`, sehingga 0 baris terupdate tetap terbaca sukses. Sekarang jumlah berhasil / gagal / dilewati dihitung di aplikasi.
- **Duplikat baris di Approval Absensi.** CI 3 memakai `UNION ALL`, sehingga kendaraan yang belum tercatat lewat dua jalur muncul dua kali. Di DataTables itu sekadar baris kembar; di jqGrid kedua baris berbagi identitas DOM yang sama dan merusak seleksi. Diganti `UNION`.
- **Batas `max_input_vars`.** Kiriman `id[]` lebih dari 1000 elemen dipotong PHP tanpa error — "pilih semua" pada data besar hanya terproses sebagian. Sekarang id dikirim sebagai satu string JSON.
- **Kegagalan koneksi database.** Modul yang datanya di `dbtruck2` membalas **503 + JSON** yang bisa dibaca user, bukan halaman error 500 yang tenggelam di dalam respons AJAX (dan membocorkan host serta SQLSTATE).
- **Penjaga saat `DBDebug` dimatikan.** `query()` hanya melempar exception selama DBDebug menyala; hasil `false` ikut diperiksa agar kegagalan tidak dilaporkan sebagai sukses di produksi.

### C. Struktur dan konvensi

- Namespace PSR-4, `BaseController` abstrak, penamaan `PascalCase` + akhiran `Model` / `Service`.
- Logika grid yang tadinya disalin di setiap controller kini satu trait `GridPipeline` (304 baris) yang dipakai bersama.
- Transaksi database memakai API CI4 (`transBegin` / `transCommit` / `transRollback`); label `goto selesai` gaya CI3 dihilangkan.
- Nama method diluruskan mengikuti isinya — `Pengajuan::approved()` (yang isinya INSERT) menjadi `simpan()`.
- Route ditulis eksplisit per verb. `setAutoRoute(true)` masih menyala tapi efektif tidak menjangkau apa pun karena `AutoRouterImproved` menuntut nama method `getAjax_list()` — hal ini didokumentasikan di berkas route.

---

## 6. Sisi antarmuka

Tumpukan UI diganti utuh dan diselaraskan dengan aplikasi Trucking (`tas-lib`), sehingga operator yang sudah memakai Trucking menemukan pola yang sama.

| Aspek | CI 3 | CI 4 |
| --- | --- | --- |
| Kerangka tema | AdminLTE 2 / Bootstrap 3, `box` | AdminLTE 3 / Bootstrap 4, `card`, sidebar treeview |
| Komponen tabel | DataTables (paging & sort di browser) | jqGrid 5.7.0, server-side + lazy loading berbasis scroll |
| Pencarian | Kotak pencarian global bawaan DataTables | Toolbar filter per kolom (debounce 500 ms) + pencarian global; keduanya diproses server |
| Detail baris | Baris anak dirakit sebagai string HTML di JS | Subgrid jqGrid; pembatalan request per baris agar balasan lambat tidak menumpuk |
| Seleksi baris | Checkbox plugin DataTables | Kolom gabungan checkbox + nomor baris, highlight baris, "pilih semua" lintas halaman |
| Navigasi keyboard | Tidak ada | Panah / PageUp / PageDown / Home / End / Space / Enter, plus panah kiri-kanan untuk buka-tutup subgrid |
| Tombol aksi | Tombol submit di atas tabel | `customPager` dengan menu aksi; tombol dinonaktifkan bila tidak cocok dengan filter status |
| Notifikasi | `alert()` dan `confirm()` bawaan browser | Dialog jQuery UI (`showDialog` / `showConfirm`) dengan varian sukses, peringatan, error |
| Datepicker | bootstrap-datepicker | jQuery UI datepicker; hari libur nasional dari API ditandai merah, Sabtu hijau |
| Tema gelap | Tidak ada | Toggle terang/gelap, tersimpan di `localStorage`, ikut mengganti tema jQuery UI |
| Keamanan layar | Tidak ada | Lockscreen otomatis setelah 15 menit idle, dengan auto-relogin bila sesi server sudah hilang |
| Umpan balik proses | Tidak ada indikator | Overlay loader terpisah untuk halaman, grid, lookup, dan proses |
| Preferensi kolom | Tidak ada | `GridPreferenceManager` + `ColumnSettingsManager` (lebar & susunan kolom) |

> **Catatan dokumentasi.** `docs/PRD.md`, `docs/FLOW_APLIKASI.md`, dan `docs/RULES.md` masih menyebut **DataTables** dan **SweetAlert2** sebagai target. Implementasinya memakai **jqGrid + dialog jQuery UI** agar seragam dengan Trucking. Dokumen tersebut perlu disesuaikan supaya tidak menyesatkan pengembang berikutnya.

---

## 7. Pengujian: apa yang benar-benar ada

Ini bagian yang perlu dibaca apa adanya. Di dalam repositori, **tidak ada satu pun test otomatis yang ditulis untuk modul aplikasi ini**.

| Berkas di `tests/` | Isi | Asal |
| --- | --- | --- |
| `unit/HealthTest.php` | 2 assertion: `APPPATH` terdefinisi, `baseURL` valid | Bawaan CI4 |
| `database/ExampleDatabaseTest.php` | Contoh seeder | Bawaan CI4 |
| `session/ExampleSessionTest.php` | Contoh sesi | Bawaan CI4 |

Direktori `build/` (target laporan coverage di `phpunit.dist.xml`) tidak ada, yang berarti **PHPUnit belum pernah dijalankan** pada checkout ini.

### Yang benar-benar dilakukan: verifikasi manual bertingkat

Prosedur uji yang dipakai selama pengembangan terdokumentasi dan konsisten, terdiri dari tiga tingkat:

1. **Layer Service/Model dari CLI.** Bootstrap `system/Test/bootstrap.php` dengan `ENVIRONMENT` ditetapkan `development` lebih dulu — tanpa itu `Model::__construct()` menyambung ke grup `tests` (SQLite in-memory) yang ekstensinya tidak terpasang.
2. **Render controller dari CLI.** Berguna hanya untuk memastikan view ter-render tanpa error PHP. **Tidak bisa** dipakai menguji parsing request: di SAPI CLI, `getGet()` / `getPost()` selalu kosong dan `isAJAX()` selalu `false`.
3. **HTTP sungguhan.** Satu-satunya cara menguji route + parsing request. Login dilewati dengan menanam berkas sesi di `writable/session/` lalu `curl -b`, memakai user yang lolos syarat `checkMenu()`.

> **Batasan penting.** Database yang dipakai menguji adalah **data produksi** (`dbtruck2` → server SQL Server cabang Surabaya). Uji tulis karena itu dijalankan di dalam transaksi yang selalu di-`ROLLBACK`. Ini berjalan, tapi artinya tidak ada lingkungan uji yang bisa dipakai untuk regression suite otomatis — dan itu penghalang utama untuk menambahkan test.

### Jarak menuju "dinyatakan selesai"

Dengan kondisi di atas, klaim "selesai" saat ini bersandar pada verifikasi manual per modul, bukan pada suite yang bisa diulang. Kriteria selesai yang wajar untuk sistem yang menyentuh approval keuangan dan mengirim notifikasi ke manajemen:

- [ ] **Unit test `GridPipeline`** — filter, sort, paging, `keysOf()`. Murni PHP, tanpa database: paling murah dan paling sering rusak diam-diam.
- [ ] **Unit test kelayakan approve** — `processApproval()` dengan model di-*mock*: id duplikat, id tak layak, id non-skalar, sesi kosong, `prosesdata` tak dikenal.
- [ ] **Feature test otorisasi** — setiap endpoint `ajax_list` / `approved` / `unapproved` dipanggil tanpa sesi (harus 401 JSON untuk AJAX) dan dengan user tanpa hak menu (harus 403 JSON).
- [ ] **Feature test CSRF** — POST tanpa token harus ditolak.
- [ ] **Feature test route** — `hapus` lewat GET harus 404 / method not allowed.
- [ ] **Database uji terpisah** — restore salinan `dbtruck2` ke instance non-produksi supaya test tulis bisa jalan tanpa rollback manual.
- [ ] **Uji penerimaan per modul** bersama user (mandor & kantor), ditandatangani per modul.
- [ ] **Uji beban ringan pada "pilih semua"** — konfirmasi tidak ada pemotongan di atas 1000 id.

---

## 8. Sisa pekerjaan yang terlihat dari repositori

- **Menu masih dibatasi.** Di `Views/partials/sidebar.php`, variabel `$menuAktif` hanya memuat `approvaltop` dan `approvalpo`. Enam modul approval lain sudah jadi dan route-nya hidup, tapi belum muncul di sidebar — kemungkinan besar disengaja untuk rilis bertahap, tapi perlu dipastikan.
- **Enam berkas belum di-commit** di working tree (view Approval TOP, footer, sidebar, dan tiga berkas `tas-lib`).
- **Dokumen belum sinkron** dengan implementasi — lihat catatan di Bagian 6, ditambah `docs/PRD.md` yang masih memuat modul yang tidak dibawa.
- **CSP dinonaktifkan** untuk menampung inline style/script warisan; layak dijadwalkan setelah view stabil.
- **Akun `sa` masih dipakai** di `.env` untuk kedua grup koneksi. Kredensial sudah keluar dari kode, tapi tingkat privilegenya belum diturunkan.
- **MD5 untuk password** dipertahankan karena terikat kolom `FUserList.FKode` di database bersama; penggantiannya adalah keputusan lintas aplikasi, bukan keputusan proyek ini.

---

*Disusun dari pembacaan langsung kedua basis kode pada 14 Agustus 2026: `D:\php-project\emkl-approval-sby` (CI 3, commit terakhir menyentuh `ApprovalTrip.php` Maret 2026) dan `D:\php-project\emkl-approval-sby-ci4` (CI 4, 39 commit, branch `main`). Angka baris kode dihitung dari `wc -l` pada `app/`.*
