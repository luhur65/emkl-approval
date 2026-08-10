<?php
// Kode cabang ikut ditampilkan di halaman login supaya operator tahu server
// cabang mana yang sedang dibuka -- tiap cabang memasang aplikasi yang sama
// dengan tampilan identik, jadi tanpa label ini keduanya tidak bisa dibedakan.
$judulAplikasi = config('Site')->fullTitle();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc($judulAplikasi) ?> | Log in</title>

  <!-- Meta Tags -->
  <meta name="description" content="<?= esc($judulAplikasi) ?>">
  
  <link rel="icon" href="<?= base_url('image/IcTas-Small.png') ?>" type="image/x-icon">

  <!-- Aesthetic Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Manrope:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="<?= base_url('libraries/adminlte/plugins/fontawesome-free/css/all.min.css') ?>">
  <!-- Theme style -->
  <link rel="stylesheet" href="<?= base_url('libraries/adminlte/dist/css/adminlte.min.css') ?>">
  <!-- Jquery UI -->
  <link rel="stylesheet" href="<?= base_url('libraries/jquery-ui/cupertino/jquery-ui.min.css') ?>">
  <!-- Custom Style -->
  <link rel="stylesheet" href="<?= base_url('libraries/tas-lib/css/styles.css') ?>">

  <style>
    /* VERDANT CUSTOM STYLES */
    :root {
      --bg-light: #f5f0e6;
      --bg-dark: #151813;
      --paper-light: #fbf7ed;
      --paper-dark: #1c201a;
      --card-light: #ffffff;
      --card-dark: #1f231d;
      --ink-light: #1f241c;
      --ink-dark: #ede8db;
      --muted-light: #6e7567;
      --muted-dark: #8a8d80;
      --line-light: #e0d8c6;
      --line-dark: #2c302a;
      --sage: #a8b598;
      --sage-deep-light: #6d7d5e;
      --sage-deep-dark: #b8c4a6;
      --terracotta-light: #c98364;
      --terracotta-dark: #e09c80;
      --leaf-soft-light: #ecf0e0;
      --leaf-soft-dark: #252a22;
    }

    @keyframes verdant-reveal {
      from { opacity: 0; transform: translateY(10px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes verdant-sway {
      0%, 100% { transform: rotate(-3deg) translateY(0); }
      50%       { transform: rotate(3deg) translateY(-8px); }
    }

    .verdant-outer-shell {
      position: relative;
      width: 100%;
      overflow: hidden;
      min-height: 100vh;
    }
    
    .verdant-outer-shell *,
    .verdant-outer-shell *::before,
    .verdant-outer-shell *::after {
      text-transform: none !important;
      box-sizing: border-box !important;
    }

    body {
      background-color: var(--bg-light);
      font-family: 'Manrope', -apple-system, BlinkMacSystemFont, sans-serif !important;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      margin: 0;
      min-height: 100vh;
      overflow-x: hidden;
      display: block !important; /* override AdminLTE default flex */
    }
    body.dark-mode {
      background-color: var(--bg-dark) !important;
      color: var(--ink-dark) !important;
    }

    .verdant-blob-1 {
      position: absolute;
      left: -5rem; top: -5rem;
      z-index: 0;
      width: 480px; height: 480px;
      pointer-events: none;
      background: radial-gradient(circle at 30% 30%, rgba(168,181,152,0.35), transparent 60%);
    }
    .verdant-blob-2 {
      position: absolute;
      right: -9rem; bottom: -9rem;
      z-index: 0;
      width: 520px; height: 520px;
      pointer-events: none;
      background: radial-gradient(circle at 70% 70%, rgba(201,131,100,0.18), transparent 65%);
    }

    .verdant-svg-1 { position: absolute; right: 6%; top: 8%; z-index: 0; color: var(--sage); opacity: 0.6; animation: verdant-sway 8s ease-in-out infinite; pointer-events: none; }
    body.dark-mode .verdant-svg-1 { opacity: 0.35; }
    
    .verdant-svg-2 { position: absolute; left: 5%; bottom: 10%; z-index: 0; color: var(--sage); opacity: 0.5; animation: verdant-sway 10s ease-in-out infinite reverse; pointer-events: none; }
    body.dark-mode .verdant-svg-2 { opacity: 0.35; }

    .verdant-nav {
      position: fixed; top: 0; left: 0; right: 0; z-index: 10;
      display: flex; align-items: center; justify-content: space-between;
      padding: max(2rem, env(safe-area-inset-top)) 2rem 1rem 2rem;
    }
    .verdant-brand {
      display: flex; align-items: center; gap: 0.625rem;
      font-size: 1.25rem;
      font-weight: 600; letter-spacing: -0.005em; color: var(--ink-light);
    }
    body.dark-mode .verdant-brand { color: var(--ink-dark); }
    .verdant-logo-circle {
      display: flex; align-items: center; justify-content: center;
      width: 30px; height: 30px; border-radius: 50%;
      background-color: var(--sage-deep-light);
    }
    body.dark-mode .verdant-logo-circle { background-color: var(--sage-deep-dark); }
    .verdant-brand-italic {
      font-family: 'Instrument Serif', Georgia, serif;
      font-style: italic; font-weight: 400;
      color: var(--terracotta-light);
    }
    body.dark-mode .verdant-brand-italic { color: var(--terracotta-dark); }

    .verdant-nav-right {
      display: flex; align-items: center; gap: 1rem;
      color: var(--muted-light); font-size: 1.25rem;
    }
    body.dark-mode .verdant-nav-right { color: var(--muted-dark); }
    .verdant-theme-toggle {
      display: flex; align-items: center; justify-content: center;
      width: 34px; height: 34px; border-radius: 50%;
      border: 1px solid var(--line-light); background-color: var(--paper-light);
      color: var(--ink-light); cursor: pointer;
      transition: all 0.2s;
    }
    .verdant-theme-toggle:hover { background-color: var(--leaf-soft-light); }
    body.dark-mode .verdant-theme-toggle {
      border-color: var(--line-dark); background-color: var(--paper-dark);
      color: var(--ink-dark);
    }
    body.dark-mode .verdant-theme-toggle:hover { background-color: var(--leaf-soft-dark); }

    .verdant-main-wrapper {
      display: flex; align-items: center; justify-content: center;
      min-height: 100vh; padding: 7rem 1.5rem 5rem 1.5rem;
      position: relative;
    }
    .verdant-login-card {
      position: relative; width: 100%; max-width: 480px; z-index: 10;
      border-radius: 28px; border: 1px solid var(--line-light);
      background-color: var(--card-light);
      padding: 3.5rem 3.5rem 3rem 3.5rem;
      box-shadow: 0 1px 1px rgba(31,36,28,0.04), 0 30px 60px -25px rgba(31,36,28,0.15);
    }
    body.dark-mode .verdant-login-card {
      border-color: var(--line-dark); background-color: var(--card-dark);
      box-shadow: 0 1px 1px rgba(0,0,0,0.3), 0 30px 60px -25px rgba(0,0,0,0.5);
    }

    .verdant-card-mark {
      position: absolute; top: -1.75rem; left: 50%; transform: translateX(-50%);
      width: 56px; height: 56px; border-radius: 50%;
      background-color: var(--terracotta-light);
      display: flex; align-items: center; justify-content: center; z-index: 10;
    }
    body.dark-mode .verdant-card-mark { background-color: var(--terracotta-dark); }

    .verdant-seal {
      color: var(--sage-deep-light);
      font-size: 0.8rem; font-weight: 600; letter-spacing: 0.32em; text-align: center;
      margin-top: 1rem; margin-bottom: 1.5rem;
      text-transform: uppercase !important;
      animation: verdant-reveal 0.8s cubic-bezier(0.16,1,0.3,1) 0.08s both;
    }
    body.dark-mode .verdant-seal { color: var(--sage-deep-dark); }

    .verdant-heading {
      font-family: 'Instrument Serif', Georgia, serif;
      font-size: 2.75rem; letter-spacing: -0.015em; font-weight: 400; line-height: 1.1;
      text-align: center; margin-bottom: 0.75rem; color: var(--ink-light);
      animation: verdant-reveal 0.8s cubic-bezier(0.16,1,0.3,1) 0.16s both;
    }
    body.dark-mode .verdant-heading { color: var(--ink-dark); }

    .verdant-subheading {
      color: var(--muted-light); text-align: center; line-height: 1.55;
      font-size: 1.0625rem; max-width: 340px; margin: 0 auto 1.5rem auto;
      animation: verdant-reveal 0.8s cubic-bezier(0.16,1,0.3,1) 0.24s both;
    }
    body.dark-mode .verdant-subheading { color: var(--muted-dark); }

    .verdant-form-group {
      margin-bottom: 1.75rem;
      animation: verdant-reveal 0.8s cubic-bezier(0.16,1,0.3,1) 0.32s both;
    }
    .verdant-form-group:nth-child(2) { animation-delay: 0.4s; }

    .verdant-label-row {
      display: flex; justify-content: space-between; align-items: center;
      margin-bottom: 0.625rem;
    }
    .verdant-label {
      font-size: 0.9375rem; font-weight: 600; letter-spacing: 0.005em;
      color: var(--ink-light); margin: 0;
    }
    body.dark-mode .verdant-label { color: var(--ink-dark); }
    
    .verdant-input-wrapper { position: relative; }
    .verdant-input {
      width: 100%; border-radius: 14px; padding: 1.15rem 1.125rem;
      font-size: 1.0625rem;
      border: 1px solid var(--line-light); background-color: var(--paper-light);
      color: var(--ink-light); outline: none; transition: all 0.2s;
      text-transform: uppercase !important;
    }
    .verdant-input::placeholder { color: #a8a294; text-transform: uppercase !important; }
    body.dark-mode .verdant-input {
      border-color: var(--line-dark); background-color: var(--paper-dark);
      color: var(--ink-dark);
    }
    body.dark-mode .verdant-input::placeholder { color: #5d6055; }
    
    .verdant-input:focus {
      border-color: var(--sage-deep-light); background-color: #fff;
      box-shadow: 0 0 0 4px var(--leaf-soft-light);
    }
    body.dark-mode .verdant-input:focus {
      border-color: var(--sage-deep-dark); background-color: #161914;
      box-shadow: 0 0 0 4px var(--leaf-soft-dark);
    }
    
    .verdant-input-password { text-transform: none !important; padding-right: 3rem; }

    .verdant-toggle-pass {
      position: absolute; right: 1rem; top: 50%; transform: translateY(-50%);
      color: var(--muted-light); cursor: pointer; border: none; background: transparent; padding: 0.25rem;
    }
    body.dark-mode .verdant-toggle-pass { color: var(--muted-dark); }

    .verdant-btn {
      width: 100%; border-radius: 50px; background-color: var(--sage-deep-light);
      color: var(--paper-light); font-size: 1.0625rem; font-weight: 600; letter-spacing: 0.01em;
      padding: 1.15rem 2rem; border: none; cursor: pointer;
      display: flex; align-items: center; justify-content: center; gap: 0.5rem;
      transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
      margin-top: 1rem; margin-bottom: 0.5rem;
      animation: verdant-reveal 0.8s cubic-bezier(0.16,1,0.3,1) 0.48s both;
    }
    .verdant-btn:hover {
      transform: translateY(-2px); background-color: var(--ink-light);
      box-shadow: 0 14px 28px -10px rgba(31,36,28,0.35); color: #fff;
    }
    body.dark-mode .verdant-btn { background-color: var(--sage-deep-dark); color: var(--bg-dark); }
    body.dark-mode .verdant-btn:hover { background-color: var(--terracotta-dark); box-shadow: 0 14px 28px -10px rgba(224,156,128,0.4); }

    .verdant-footer {
      text-align: center; margin-top: 2rem; color: var(--muted-light);
      font-size: 0.875rem; letter-spacing: 0.03em;
      animation: verdant-reveal 0.8s cubic-bezier(0.16,1,0.3,1) 0.64s both;
    }
    body.dark-mode .verdant-footer { color: var(--muted-dark); }
    .verdant-footer-bold { font-weight: 600; color: var(--ink-light); }
    body.dark-mode .verdant-footer-bold { color: var(--ink-dark); }

    .verdant-footnote {
      position: absolute; bottom: 1.5rem; left: 0; right: 0; z-index: 10;
      text-align: center; color: var(--muted-light); font-size: 0.875rem; letter-spacing: 0.03em;
    }
    body.dark-mode .verdant-footnote { color: var(--muted-dark); }
    
    @media (max-width: 576px) {
      .verdant-nav { padding: max(1rem, env(safe-area-inset-top)) 1rem 1rem 1rem; }
      .verdant-main-wrapper { padding: 6rem 1rem 4rem 1rem; }
      .verdant-login-card { padding: 3.5rem 1.25rem 2rem 1.25rem; }
      .verdant-input { padding: 1.6rem 1.25rem; }
      .verdant-nav-text { display: none; }
      .verdant-seal { letter-spacing: 0.1em; font-size: 0.7rem; }
      .verdant-heading { font-size: 2.25rem; }
    }
  </style>
</head>

<body>
  <div class="processing-loader d-none" id="processingLoader">
    <img src="<?= base_url('libraries/tas-lib/img/loading-color.gif') ?>" rel="preload">
    <span>Processing</span>
  </div>

  <div class="verdant-outer-shell">
    <div class="verdant-blob-1"></div>
    <div class="verdant-blob-2"></div>
    
    <svg class="verdant-svg-1" width="120" height="180" viewBox="0 0 120 180" fill="none">
        <path d="M60 10 C 80 50, 90 100, 60 170 C 30 100, 40 50, 60 10 Z" stroke="currentColor" stroke-width="1.5" fill="none" />
        <path d="M60 30 L 60 170" stroke="currentColor" stroke-width="1" opacity="0.5" />
        <path d="M60 60 L 78 75" stroke="currentColor" stroke-width="1" opacity="0.5" />
        <path d="M60 60 L 42 75" stroke="currentColor" stroke-width="1" opacity="0.5" />
        <path d="M60 90 L 82 105" stroke="currentColor" stroke-width="1" opacity="0.5" />
        <path d="M60 90 L 38 105" stroke="currentColor" stroke-width="1" opacity="0.5" />
    </svg>

    <svg class="verdant-svg-2" width="160" height="160" viewBox="0 0 160 160" fill="none">
        <circle cx="80" cy="80" r="22" stroke="currentColor" stroke-width="1.5" />
        <path d="M80 58 C 95 40, 110 35, 120 38" stroke="currentColor" stroke-width="1.5" />
        <path d="M80 58 C 65 40, 50 35, 40 38" stroke="currentColor" stroke-width="1.5" />
        <path d="M80 102 C 95 120, 110 125, 120 122" stroke="currentColor" stroke-width="1.5" />
        <path d="M80 102 C 65 120, 50 125, 40 122" stroke="currentColor" stroke-width="1.5" />
        <circle cx="120" cy="38" r="6" fill="currentColor" opacity="0.5" />
        <circle cx="40" cy="38" r="6" fill="currentColor" opacity="0.5" />
        <circle cx="120" cy="122" r="6" fill="currentColor" opacity="0.5" />
        <circle cx="40" cy="122" r="6" fill="currentColor" opacity="0.5" />
    </svg>

    <nav class="verdant-nav">
      <div class="verdant-brand">
        <div class="verdant-logo-circle">
          <img src="<?= base_url('image/IcTas-Small.png') ?>" alt="TAS" width="18" height="18" style="object-fit: contain;">
        </div>
        <span>EMKL <em class="verdant-brand-italic">Approval</em></span>
      </div>
      <div class="verdant-nav-right">
        <span class="verdant-nav-text d-none d-sm-inline"><?= esc($judulAplikasi) ?></span>
        <button type="button" class="verdant-theme-toggle" id="themeToggleBtn" aria-label="Toggle theme">
          <span id="themeIconContainer">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
            </svg>
          </span>
        </button>
      </div>
    </nav>

    <div class="verdant-main-wrapper">
      <main class="verdant-login-card">
        <div class="verdant-card-mark">
          <img src="<?= base_url('image/IcTas-Small.png') ?>" alt="TAS" width="36" height="36" style="object-fit: contain;">
        </div>

        <p class="verdant-seal">— PT. Transporindo Agung Sejahtera —</p>
        <h1 class="verdant-heading">Selamat datang,</h1>
        <p class="verdant-subheading">Sistem Approval EMKL.</p>

        <form action="<?= base_url('login') ?>" method="POST">
          <?= csrf_field() ?>
          
          <div class="verdant-form-group">
            <div class="verdant-label-row">
              <label class="verdant-label" for="pUser">Username</label>
            </div>
            <div class="verdant-input-wrapper">
              <input type="text" name="pUser" id="pUser" class="verdant-input" value="<?= old('pUser') ?>" placeholder="USERNAME ANDA" autofocus autocomplete="off">
            </div>
          </div>

          <div class="verdant-form-group">
            <div class="verdant-label-row mt-3">
              <label class="verdant-label" for="pPassword">Kata sandi</label>
            </div>
            <div class="verdant-input-wrapper">
              <input type="password" name="pPassword" id="pPassword" class="verdant-input verdant-input-password" placeholder="••••••••••" autocomplete="off">
              <button type="button" class="verdant-toggle-pass toggle-password" toggle="#pPassword">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
          
          <?php if (!empty($error)): ?>
            <div id="error" class="text-danger mt-2 text-center" style="font-size: 0.85rem; color: var(--terracotta-light) !important;">
              <?= $error ?>
            </div>
          <?php endif; ?>

          <button type="submit" class="verdant-btn">
            Masuk ke aplikasi
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M5 12h14M13 5l7 7-7 7" />
            </svg>
          </button>

          <div class="verdant-footer">
            <p style="margin-bottom: 0.25rem;">Halaman dimuat dalam <span class="verdant-footer-bold"><?= number_format(timer()->getElapsedTime('total_execution'), 2) ?></span> detik</p>
            <p>Copyright &copy; <?= date('Y') ?> PT. Transporindo Agung Sejahtera</p>
          </div>
        </form>
      </main>
    </div>

    <p class="verdant-footnote">
      Dirawat oleh tim <em style="font-family: 'Instrument Serif', serif; font-style: italic; color: var(--ink-light);" class="dark-italic">Transporindo</em> &middot; Sistem Internal &middot; <?= date('Y') ?>
    </p>
  </div>

  <!-- jQuery -->
  <script src="<?= base_url('libraries/adminlte/plugins/jquery/jquery.min.js') ?>"></script>
  <!-- jQuery UI -->
  <script src="<?= base_url('libraries/jquery-ui/1.13.1/jquery-ui.min.js') ?>"></script>
  <!-- Bootstrap 4 -->
  <script src="<?= base_url('libraries/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
  
  <script>
    $(document).ready(function() {
      $("input").attr("autocomplete", "off");

      $('form').on('submit', function() {
        $(this).find('button[type="submit"]').prop('disabled', true);
        $('#processingLoader').removeClass('d-none');
      });

      $(document).on('click', ".toggle-password", function(event) {
        $(this).find('i').toggleClass("fa-eye fa-eye-slash");
        var input = $($(this).attr("toggle"));
        if (input.attr("type") == "password") {
          input.attr("type", "text");
        } else {
          input.attr("type", "password");
        }
      });

      // Theme toggle functionality
      $('#themeToggleBtn').on('click', function() {
        if ($('body').hasClass('dark-mode')) {
          applyLightMode();
        } else {
          applyDarkMode();
        }
      });
    });

    const sunSvg = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" /></svg>`;
    const moonSvg = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" /></svg>`;

    function applyDarkMode() {
      const $body = $('body');
      localStorage.setItem('theme', 'dark');
      $body.addClass('dark-mode');
      $('#themeIconContainer').html(sunSvg);
      $('.dark-italic').css('color', 'var(--ink-dark)');
    }

    function applyLightMode() {
      const $body = $('body');
      localStorage.setItem('theme', 'light');
      $body.removeClass('dark-mode');
      $('#themeIconContainer').html(moonSvg);
      $('.dark-italic').css('color', 'var(--ink-light)');
    }

    $(function() {
      const savedTheme = localStorage.getItem('theme');
      if (savedTheme === 'dark') {
        applyDarkMode();
      } else if (savedTheme === 'light') {
        applyLightMode();
      } else {
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (systemPrefersDark) {
          applyDarkMode();
        } else {
          applyLightMode();
        }
      }
    });
  </script>
</body>
</html>
