<?php
// Kode cabang diambil dari sesi user lebih dulu -- itu yang benar bila suatu
// saat satu instance melayani lebih dari satu cabang. Selama belum, nilainya
// kosong dan fullTitle() jatuh ke `site.branchCode` dari .env server tempat
// instance ini dipasang. Tidak ada lagi kode cabang yang ditulis di dalam file.
$judulAplikasi = config('Site')->fullTitle(session()->get(SESSION_NAME . 'cabangid'));

// Sesi Panel Casting (login-as) HARUS terlihat. Tanpa penanda di layar, admin
// yang sedang melihat sebagai orang lain gampang lupa -- dan di aplikasi
// approval itu berbahaya: satu klik approve tercatat atas nama user yang
// dilihat. Spanduknya juga menyebut siapa yang membuka sesi, supaya layar yang
// ditinggal terbuka tetap bisa dijelaskan.
$castingAktif   = (bool) session()->get('sso_impersonated');
$castingLihat   = $castingAktif && config(\Config\Sso::class)->castingReadOnly;
$castingAktor   = trim((string) session()->get('sso_actor'));
$castingSetuju  = trim((string) session()->get('sso_approver'));
?>

<?php if ($castingAktif): ?>
<div class="text-center py-1 px-2" style="background-color: #b45309; color: #fff; font-size: 0.8rem; font-weight: 600; letter-spacing: 0.02em;">
    <i class="fas fa-user-secret" aria-hidden="true"></i>
    PANEL CASTING — Anda melihat aplikasi sebagai
    <strong><?= esc(strtoupper((string) (session()->get('FNamaUser') ?: session()->get('FUserID')))) ?></strong>
    <?php if ($castingAktor !== ''): ?>
        &middot; dibuka oleh <strong><?= esc(strtoupper($castingAktor)) ?></strong>
    <?php endif; ?>
    <?php if ($castingSetuju !== ''): ?>
        &middot; disetujui <strong><?= esc(strtoupper($castingSetuju)) ?></strong>
    <?php endif; ?>
    <?= $castingLihat ? '&middot; mode lihat-saja' : '&middot; PERUBAHAN DATA TERCATAT ATAS NAMA USER INI' ?>
</div>
<?php endif; ?>
<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-white navbar-light nav-compact">
    <ul class="navbar-nav">
        <li class="nav-item">
            <a id="sidebarButton" class="nav-link sidebars" data-widget="pushmenu" data-auto-collapse-size="0" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
    </ul>

    <img src="<?= asset('libraries/tas-lib/img/taslogo.png') ?>" alt="Logo" class="brand-image" style="width: 25px; margin-right: 5px;">
    <strong><?= esc($judulAplikasi) ?></strong>

    <ul class="navbar-nav ml-auto">
        <li class="nav-item mr-3 d-none d-md-block">
            <div class="text-right">
                your ip <span class="d-none d-lg-inline"> address : </span> (<?= $_SERVER['REMOTE_ADDR'] ?>)
            </div>
        </li>
        <li class="nav-item">
            <div class="datetime-place text-right">
                <span class="date-place"></span>
                <span class="time-place ml-3"></span>
            </div>
        </li>
        <li class="nav-item ml-3">
            <button id="toggle-dark" class="btn btn-sm btn-outline-secondary" title="Toggle Dark / Light">
                <i class="fas fa-moon"></i>
            </button>
        </li>
    </ul>
</nav>
<!-- /.navbar -->
