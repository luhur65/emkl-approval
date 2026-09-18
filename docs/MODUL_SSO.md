# Modul Single Sign-On (SSO)

Proyek: **EMKL Approval System (SBY)** — CodeIgniter 4.7 / PHP 8.2 / SQL Server
Acuan: `D:\php-project\sys-modern` (`dokumentasi_sso.md`, `LAPORAN_SSO.md`) — polanya
disalin dari sana dan disesuaikan ke tabel `FUserList` serta deployment per cabang.
Status: **terimplementasi & lulus uji otomatis (67 test); belum diuji ujung-ke-ujung
secara live** — lihat §8.

---

## 1. Ringkasan

Aplikasi ini bergabung ke SSO perusahaan sebagai **aplikasi anggota**, sederajat
dengan HR, CRM, SYS, dan trucking per cabang. Pengguna login sekali di dashboard
auth-sso, menekan kartu **EMKL APPROVAL → SURABAYA** (atau tombol "Masuk dengan SSO"
di halaman login sini), dan masuk tanpa mengetik kredensial lagi.

| Aspek | Hasil |
|---|---|
| Model SSO | Signed-ticket **RS256** (bukan OIDC/SAML) — sama dengan sys-modern |
| Verifikasi tiket | **Lokal** dengan public key — tanpa round-trip ke server SSO |
| Dependency baru | **Nol** — `ext-openssl` + `ext-curl` bawaan PHP |
| Perubahan skema DB | **Tidak ada** — memakai kolom `FUserList.FIDKaryawan` yang sudah ada |
| Kode aplikasi di SSO | **Per cabang**: `emkl-approval-surabaya` (pola `trucking-surabaya`) |
| Pencocokan identitas | `sub` tiket = `FUserList.FID`, dicocokkan ulang `FIDKaryawan` = klaim `karyawanId` |
| Single Logout | Aktif (polling introspeksi, cache 60 detik, fail-open) |
| Login lokal | Tetap hidup berdampingan; bisa dimatikan lewat `sso.passwordLoginEnabled` |
| Lock screen | **Dimatikan untuk sesi SSO** (user SSO tidak punya password lokal) |

Server SSO: `D:\project-next\sso` (dashboard, Next.js) dan `D:\project-next\ssoapi`
(API, NestJS). Lingkungan uji: `https://testsso.transporindo.com` /
`https://testssoapi.transporindo.com`.

---

## 2. Alur

```
1. User login di dashboard auth-sso (Google / Yahoo / password).
2. Menekan kartu EMKL APPROVAL > SURABAYA — atau, dari halaman login aplikasi ini,
   menekan "Masuk dengan SSO" -> sso/login -> <auth-sso>/launch/emkl-approval-surabaya
   (jalur SP-initiated: auth-sso meminta login SSO dulu bila perlu).
3. auth-sso -> POST auth-sso-api /auth/generate-ticket { appCode: "emkl-approval-surabaya" }
   auth-sso-api memeriksa: aplikasi terdaftar (TICKET_RELAY_APPS), access_menu user
   memuat "EMKL APPROVAL SURABAYA", sesi SSO hidup, DAN FUserList cabang ini punya
   baris dengan FIDKaryawan = users.karyawan_id akun SSO (APP_DIRECTORY_DB).
   FID baris itu ditandatangani sebagai klaim `sub`. Lebih dari satu baris ->
   dashboard meminta user memilih akun.
4. Browser diarahkan ke <base url>/auth/sso-callback?ticket=<JWT RS256, umur 60 dtk>
5. SsoAuth::callback memverifikasi tiket LOKAL (public key), membakar `jti`
   (anti-replay), menukar `sub` -> baris FUserList (FID) dan mencocokkan ulang
   FIDKaryawan-nya ke klaim `karyawanId`, lalu membuat sesi persis seperti login
   password (FUserID, FNamaUser, logged_emkl) + penanda `sso_login`, `sso_sid`.
6. Redirect ke /home.
```

### Bentuk tiket (auth-sso-api `generateTicket()`)

| Klaim | Isi | Diperiksa di sini |
|---|---|---|
| `sub` | **FID** baris FUserList yang dipilih auth-sso-api | wajib; jalur utama resolusi identitas |
| `aud` | `emkl-approval-surabaya` | **wajib = `sso.appCode`** — tiket cabang/aplikasi lain ditolak |
| `iss` | `auth-sso` | wajib |
| `jti` | nonce 128 bit | wajib, lalu **dibakar** sekali pakai |
| `exp` | 60 detik | wajib, belum lewat (toleransi `sso.leeway`) |
| `karyawanId` | id master karyawan HR | wajib > 0; dicocokkan ulang ke `FIDKaryawan` |
| `sid` | id sesi SSO | disimpan di sesi untuk Single Logout |
| `email`, `name` | tampilan/log | tidak dipakai (`FEmail` kosong di semua baris) |

Kalau `sub` **bukan** angka (tiket format lama), jalur cadangan mencari tepat satu
baris `FUserList` dengan `sso.matchColumn` = klaim `sso.matchClaim`.

---

## 3. Berkas

**Baru**

| Berkas | Peran |
|---|---|
| `app/Config/Sso.php` | Seluruh konfigurasi SSO (dibaca dari `.env` berawalan `sso.`) |
| `app/Libraries/SsoTicket.php` | Verifikator JWT RS256 (alg dipatok, iss/aud/exp/jti/sub) |
| `app/Libraries/SsoTicketException.php` | Penolakan tiket; pesannya untuk log, bukan layar |
| `app/Libraries/SsoNonceStore.php` | Sekali-pakai `jti` via `fopen(...,'xb')` atomik di `writable/sso_nonce` |
| `app/Libraries/SsoSlo.php` | Klien Single Logout (introspeksi + cache) |
| `app/Libraries/SsoExit.php` | Satu tempat memutuskan tujuan setelah sesi berakhir |
| `app/Controllers/SsoAuth.php` | `start()` -> SSO; `callback()` -> tukar tiket jadi sesi |
| `app/Commands/CheckSsoMatch.php` | `php spark sso:match [nilai]` |
| `app/Commands/SetSsoKaryawan.php` | `php spark sso:map <FUserID> <karyawan_id>` |
| `app/Commands/CheckSsoSlo.php` | `php spark sso:slo [sid]` |
| `tests/unit/SsoTicketTest.php`, `SsoNonceStoreTest.php`, `SsoWiringTest.php`, `SsoCallbackTest.php`, `SsoSessionEndTest.php` | 62 test |
| `tests/_support/sso/` | Pasangan kunci khusus test (lihat README di sana) |

**Diubah**

| Berkas | Perubahan |
|---|---|
| `app/Config/Routes.php` | `GET sso/login`, `GET auth/sso-callback` (wajib GET — cookie `SameSite=Lax`) |
| `app/Config/Filters.php` | Kedua route masuk `except` filter `auth` |
| `app/Filters/AuthFilter.php` | Cabang Single Logout; JSON 401 membawa `sessionExpired` + `redirect` (kunci `error`/`msg` lama tetap) |
| `app/Controllers/Login.php` | Pesan `?sso=<kode>`, saklar login lokal, logout sadar-SSO, `unlock` menolak di mode SSO-only |
| `app/Models/AuthModel.php` | `$primaryKey = 'FID'` (PK sebenarnya), `findByFid()`, `findByColumn()`, `hasColumn()` |
| `app/Views/auth/login.php` | Tombol "Masuk dengan SSO", form lokal disembunyikan di mode SSO-only |
| `app/Views/partials/footer.php` | Lock screen tidak dirender untuk sesi `sso_login` |
| `app/Views/partials/header.php` | Penangan `ajaxError` global untuk `sessionExpired` |
| `public/libraries/tas-lib/js/lockscreen.js` | Jawaban 403 `ssoOnly` diantar ke SSO, tidak dihitung percobaan gagal |
| `.env`, `env` | Blok `sso.*` |

Di luar proyek ini (belum di-commit, lihat §6):

| Berkas | Perubahan |
|---|---|
| `ssoapi/src/common/db-emkl-approval-surabaya.ts` | Koneksi read-only ke `dbTAsSby` |
| `ssoapi/src/modules/auth/auth.service.ts` | `emkl-approval-surabaya` di `AppDirectory`, `TICKET_RELAY_APPS`, `APP_DIRECTORY_DB` (tabel `FUserList`, id `FID`, karyawanId `FIDKaryawan`), `PANEL_CASTING_DIRECTORIES`; jenis aturan status baru `kind: 'none'` |
| `ssoapi/.env.example`, `ssoapi/.env` | `EMKL_APPROVAL_SURABAYA_SSMS_*` |
| `sso/constants/apps.ts` | Kartu `emkl-approval-surabaya` dalam grup `emkl-approval` |
| `sso/.env` | `NEXT_PUBLIC_EMKL_APPROVAL_SURABAYA_URL` |

---

## 4. Konfigurasi `.env`

```
sso.enabled          = true
sso.appCode          = emkl-approval-surabaya   # per cabang; = key TICKET_RELAY_APPS
sso.issuer           = auth-sso
sso.ticketPublicKey  = MIIBIjANBgkq...          # PUBLIC key, TANPA tanda kutip
sso.leeway           = 60
sso.nonceTtl         = 300                      # > TICKET_TTL_SECONDS + leeway
sso.dashboardUrl     = https://testsso.transporindo.com/dashboard
sso.launchUrl        = https://testsso.transporindo.com/launch/emkl-approval-surabaya
sso.apiBaseUrl       = https://testssoapi.transporindo.com
sso.sloSecret        = <SLO_INTROSPECT_SECRET auth-sso-api>
sso.sloPollSeconds   = 60
sso.matchClaim       = karyawanId
sso.matchColumn      = FIDKaryawan
sso.passwordLoginEnabled = true
sso.logoutToSso      = false
```

Catatan:

- Public key ditulis **tanpa tanda kutip**; nilainya sama persis dengan
  `sso.ticketPublicKey` sys-modern (server SSO yang sama).
- `sso.sloSecret` wajib sama dengan `SLO_INTROSPECT_SECRET` auth-sso-api; kalau
  beda, introspeksi dijawab 401 dan SLO diam-diam tidak jalan (`php spark sso:slo`).
- **Cabang lain**: salin blok ini ke `.env` server cabang dengan `sso.appCode` /
  `sso.launchUrl` cabangnya (`emkl-approval-medan`, dst.) — dan daftarkan cabang
  itu di ssoapi/sso (§6). Tidak ada kode cabang yang ditulis di dalam source.
- Mode SSO-only (`sso.passwordLoginEnabled = false`): `/login` dialihkan ke SSO
  kecuali saat membawa pesan kegagalan; `POST /login` -> `?sso=onlysso`;
  `POST login/unlock` -> 403 `{ssoOnly:true}`. Set hanya setelah SSO terbukti jalan
  **dan semua approver sudah dipetakan**.

---

## 5. Keputusan rancangan

**Per cabang, bukan satu kode `emkl`.** Tiap cabang punya SQL Server dan `FUserList`
sendiri, jadi tiap cabang adalah aplikasi tersendiri di mata SSO — persis
`trucking-surabaya`/`trucking-medan`. `aud` tiket mengikat tiket ke satu cabang;
tiket Surabaya tidak bisa dipakai di Medan (`SsoTicketTest::testTiketUntukCabangLainDitolak`).

**Pencocokan lewat `karyawanId` <-> `FIDKaryawan`, bukan email.** `FEmail` kosong di
seluruh 207 baris (diverifikasi 2026-09-17), sedangkan `FIDKaryawan` terisi 64 baris
dan unik semua. Ini identitas yang sama dengan `karyawan_id` HR yang dipakai semua
aplikasi anggota.

**Jalur utama `sub` = FID, dengan pencocokan ulang.** auth-sso-api menandatangani
`sub` sebagai PK baris yang dipilih (dokumen desain
`sso/docs/plans/2026-09-12-app-specific-account-selection-design.md`), supaya satu
karyawan dengan beberapa akun bisa memilih akunnya. `FIDKaryawan` tetap dicocokkan
ulang ke klaim `karyawanId`: `sub` dari format tiket lama (id internal ssoapi) bisa
kebetulan sama dengan sebuah FID milik orang lain.

**Tanpa auto-provisioning; nonaktif/duplikat ditolak seragam.** Tiket membuktikan
"sudah diautentikasi SSO", bukan "berhak masuk". Baris `FUserList` beserta menu
approval-nya (`checkMenu()`) tetap yang menentukan.

**Kode pesan tertutup.** View login merender `$error` tanpa escaping, jadi yang lewat
URL hanya kode (`?sso=replay`), teksnya dari `Login::ssoMessage()`. Kode `noclaim`
(tiket tanpa `karyawanId` — masalah admin SSO) dibedakan dari `unknown`/`account`
(masalah admin aplikasi ini).

**Panel Casting tidak didukung.** Aplikasi ini tidak masuk `PANEL_CASTING_APP_CODES`;
tiket `impersonated` ditolak (`?sso=casting`). `PANEL_CASTING_DIRECTORIES` tetap
diisi karena `findActiveAppAccounts()` mewajibkan aturan status.

**`kind: 'none'` di ssoapi.** `FUserList` tidak punya kolom status sama sekali; daripada
memaksakan aturan `marker` dengan daftar kosong (SQL `NOT IN ()` tidak sah), jenis
aturan baru dibuat eksplisit: semua baris aktif.

**Lock screen mati untuk sesi SSO** (keputusan pemilik proyek). Konsekuensi: browser
user SSO yang ditinggal terbuka bertahan sampai `Config\Session::$expiration` (2 jam)
atau dicabut lewat Single Logout.

**Logout lokal tidak mencabut sesi SSO** — itu akan melogout HR/CRM sekaligus;
wewenang dashboard SSO. Arah sebaliknya bekerja lewat SLO (polling, fail-open).

---

## 6. Yang perlu ada di sisi SSO & HR

1. **Deploy ulang `ssoapi` dan `sso`** dengan perubahan di §3 — tanpa itu kartu tidak
   ada dan tiket tidak terbit ("Aplikasi tidak ditemukan atau nonaktif").
2. **`.env` ssoapi di server**: `EMKL_APPROVAL_SURABAYA_SSMS_{DB,SERVER,USER,PASSWORD,PORT}`
   menunjuk SQL Server cabang Surabaya (`dbTAsSby`). Server ssoapi harus bisa
   menjangkau IP SQL Server cabang itu.
3. **`.env` sso di server**: `NEXT_PUBLIC_EMKL_APPROVAL_SURABAYA_URL=<base url>/auth/sso-callback`.
4. **Master data HR**: baris `program_menu` bernama persis **`EMKL APPROVAL SURABAYA`**
   (status AKTIF), lalu di-grant ke jabatan/user yang berhak (`access_menu`). Belum
   ada — pekerjaan admin HR, bukan kode. Tanpa ini `generateTicket()` menolak
   "Anda tidak memiliki akses ke aplikasi ini".
5. **Pemetaan `FIDKaryawan`** untuk tiap approver (§7).

---

## 7. Kesiapan data & perintah diagnosa

Kondisi awal (2026-09-17): dari 15 user bermenu approval, **0** punya `FIDKaryawan`.
Sudah dipetakan sebagai uji coba: **`citra` (FID 32) -> karyawan_id 332**
(akun SSO `CITRA INDRAWATI`, AKTIF, cabang SURABAYA). **`ryan`** belum: tidak ada
akun SSO bernama RYAN di `hrsso.users` — perlu konfirmasi nama lengkap/akun SSO-nya.

```bash
php spark sso:match            # konfigurasi + database yang benar-benar dipakai + statistik
php spark sso:match citra      # pemetaan satu user (FID, FIDKaryawan, menu approval)
php spark sso:match 332        # apakah satu karyawan_id cocok ke tepat satu baris
php spark sso:map ryan 1234    # petakan (minta konfirmasi; --yes untuk non-interaktif)
php spark sso:map ryan --clear # kosongkan pemetaan
php spark sso:slo              # rantai Single Logout: konfigurasi, CA, introspeksi
```

Nilai `karyawan_id` diambil dari akun SSO orang itu (`hrsso.users.karyawan_id`), bukan
dari `MKaryawan` lokal.

---

## 8. Cara menguji

```bash
php vendor/bin/phpunit --no-coverage --filter Sso
```

| Test | Yang dijamin |
|---|---|
| `SsoTicketTest` (16) | Tiket sah diterima; tanda tangan diubah, kunci lain, `alg:none`, HS256-palsu, `aud` cabang lain, `iss` salah, kedaluwarsa, tanpa `exp`/`jti`/`sub` — ditolak; public key di `.env` terbaca OpenSSL ≥ 2048 bit |
| `SsoNonceStoreTest` (6) | Penukaran kedua ditolak; `jti` tidak jadi nama berkas mentah; gc tidak membuka replay |
| `SsoWiringTest` (11) | Route GET terdaftar & dikecualikan filter; `appCode` per cabang; `nonceTtl`; SLO tanpa konfigurasi fail-open; tujuan `SsoExit`; tombol SSO punya warna di kedua tema; lock screen dipagari `sso_login` |
| `SsoCallbackTest` (19) | Lewat HTTP sungguhan ke `auth/sso-callback` dengan `FUserList` tiruan di SQLite: sesi terbentuk + `regenerate`, replay, `sub` tidak cocok/tidak ada/belum dipetakan, `noclaim`, `casting`, jalur cadangan, halaman login & mode SSO-only |
| `SsoSessionEndTest` (10) | AuthFilter: sesi kosong (halaman & AJAX), sesi hidup diloloskan, Single Logout mengakhiri sesi dengan kode `expired`, fail-open saat SLO belum dikonfigurasi |

Uji manual ujung-ke-ujung (setelah §6 terpenuhi):

1. `php spark sso:match <karyawan_id>` harus menjawab "tepat 1 baris".
2. Buka `<base url>/login`, tekan **Masuk dengan SSO** (atau kartu di dashboard) ->
   mendarat di `/home` sudah login, tanpa lock screen.
3. Tekan back, muat ulang URL callback -> "Tiket SSO sudah pernah dipakai".
4. Logout di dashboard SSO, lalu muat halaman mana pun di sini -> dalam
   `sso.sloPollSeconds` detik sesi berakhir dengan pesan "Sesi SSO Anda telah berakhir".
   (Saat dokumen ini ditulis, `php spark sso:slo` ke testssoapi menjawab HTTP 502 —
   API-nya sedang tidak bisa dihubungi; SLO fail-open sampai pulih.)
5. Logout dari sidebar -> kembali ke dashboard SSO (sesi lokal ke `/login`).
