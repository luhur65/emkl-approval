    </div>
    <!-- ./wrapper -->

    <?php if (session()->has(SESSION_NAME . 'logged_in')): ?>
    <!-- Lockscreen Overlay -->
    <div id="lockscreen-overlay" style="display:none; position:fixed; inset:0; z-index:10050; background:rgba(0,0,0,0.7); backdrop-filter:blur(5px); align-items:center; justify-content:center;">
        <div class="card shadow-lg" style="width: 95%; max-width: 400px;">
            <div class="card-header bg-primary">
                <h3 class="card-title"><i class="fas fa-lock"></i> SESI TERKUNCI</h3>
            </div>
            <div class="card-body">
                <p class="text-sm">Sesi Anda terkunci karena tidak ada aktivitas selama 15 menit. Masukkan password untuk melanjutkan.</p>
                <form id="lockscreen-form">
                    <div class="form-group">
                        <label>Username</label>
                        <div class="input-group">
                            <input type="text" class="form-control" value="<?= session()->get(SESSION_NAME . 'userid') ?>" readonly>
                            <div class="input-group-append">
                                <div class="input-group-text"><span class="fas fa-user"></span></div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-group">
                            <input type="password" id="lockscreen-password" class="form-control" autocomplete="current-password" required>
                            <div class="input-group-append" style="cursor: pointer;" onclick="toggleLockscreenPassword()">
                                <div class="input-group-text"><span id="lockscreen-eye" class="fas fa-eye"></span></div>
                            </div>
                        </div>
                        <p id="lockscreen-error" class="text-danger text-sm font-weight-bold mt-2" style="display:none;"></p>
                    </div>
                    <button type="submit" id="lockscreen-btn" class="btn btn-primary btn-block mt-2">Buka Kunci</button>
                    <button type="button" id="lockscreen-biometric-btn" class="btn btn-outline-dark btn-block mt-2" style="display:none;" onclick="triggerLockscreenBiometric()">
                        <i class="fas fa-fingerprint"></i> Quick Login
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bootstrap 4 -->
    <script src="<?= asset('libraries/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>

    <!-- daterangepicker -->
    <script src="<?= asset('libraries/adminlte/plugins/moment/moment.min.js') ?>"></script>
    <script src="<?= asset('libraries/adminlte/plugins/daterangepicker/daterangepicker.js') ?>"></script>

    <!-- overlayScrollbars -->
    <script src="<?= asset('libraries/adminlte/plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js') ?>"></script>

    <!-- AdminLTE App (Using adminlte.js from Trucking) -->
    <script src="<?= asset('libraries/adminlte/dist/js/adminlte.js') ?>"></script>

    <!-- Select2 -->
    <script src="<?= asset('libraries/adminlte/plugins/select2/js/select2.full.min.js') ?>"></script>

    <!-- AutoNumeric -->
    <script src="<?= asset('libraries/autonumeric/4.5.4/autonumeric.min.js') ?>"></script>

    <!-- Inputmask -->
    <script src="<?= asset('libraries/inputmask/5.0.6/jquery.inputmask.min.js') ?>"></script>

    <!-- Nestable2 -->
    <script src="<?= asset('libraries/nestable2/1.6.0/js/jquery.nestable.min.js') ?>"></script>

    <!-- Highlight -->
    <script src="<?= asset('libraries/highlight/highlight.js') ?>"></script>

    <!-- JQGrid 570 (From Trucking) -->
    <script src="<?= asset('libraries/jqgrid/570/js/i18n/grid.locale-en.js') ?>" type="text/javascript"></script>
    <script src="<?= asset('libraries/jqgrid/570/js/jquery.jqGrid.min.js') ?>" type="text/javascript"></script>

    <!-- TAS Libraries -->
    <script src="<?= asset('libraries/tas-lib/js/mains.js') ?>"></script>
    <script src="<?= asset('libraries/tas-lib/js/lazyLoadingGridMonolith.js') ?>"></script>
    <script src="<?= asset('libraries/tas-lib/js/lazyLoadingGridHelper.js') ?>"></script>
    <!-- <script src="<?= asset('libraries/tas-lib/js/lookup-columns.js') ?>"></script> -->
    <script src="<?= asset('libraries/tas-lib/js/pager.js') ?>"></script>
    <script src="<?= asset('libraries/tas-lib/js/MonthPicker.min.js') ?>"></script>
    <script src="<?= asset('libraries/tas-lib/js/YearPicker.js') ?>"></script>
    <script src="<?= asset('libraries/tas-lib/js/GridPreferenceManager.js') ?>"></script>
    <script src="<?= asset('libraries/tas-lib/js/ColumnSettingsManager.js') ?>"></script>
    <script src="<?= asset('libraries/tas-lib/js/GridAutoInjector.js') ?>"></script>
    
    <?php if (session()->has(SESSION_NAME . 'logged_in')): ?>
    <script>
        // Simpan userid secara lokal untuk keperluan auto-relogin lockscreen jika sesi server expire
        localStorage.setItem('lockscreen_userid', '<?= session()->get(SESSION_NAME . 'userid') ?>');
    </script>
    <script src="<?= asset('libraries/tas-lib/js/webauthn.js') ?>"></script>
    <script src="<?= asset('libraries/tas-lib/js/lockscreen.js') ?>"></script>
    <?php endif; ?>

    <script>
        $(document).ready(function() {
            // Sembunyikan loader utama saat dokumen siap
            $('#loader').addClass('d-none');
            $('.loader').addClass('d-none');

            // --- Sidebar events from Trucking ---
            $(document).on('collapsed.lte.pushmenu', () => {
                $('body').removeClass('sidebar-open')
            })

            $(document).on('shown.lte.pushmenu', () => {
                $('body').addClass('sidebar-open')
            })

            // --- Sidebar auto-close on click outside ---
            $(document).on('click', function(e) {
                const $body = $('body');
                // Jika sidebar sedang TERBUKA (class sidebar-collapse TIDAK ada)
                if (!$body.hasClass('sidebar-collapse')) {
                    // Dan yang diklik bukan bagian dari sidebar atau tombol toggle
                    if (!$(e.target).closest('.main-sidebar').length && !$(e.target).closest('#sidebarButton').length) {
                        $('[data-widget="pushmenu"]').PushMenu('collapse');
                    }
                }
            });

            // --- Theme Toggle Logic ---
            const $body = $('body');
            const $btn = $('#toggle-dark');
            const $theme = $('#jquery-theme');
            const $nav = $('nav.main-header');

            function applyDarkMode() {
                $theme.attr('href', '<?= asset('libraries/jquery-ui/darkhive/jquery-ui.min.css') ?>');
                localStorage.setItem('theme', 'dark');
                $('html').addClass('dark-mode');
                $body.addClass('dark-mode');
                $nav.addClass('navbar-dark').removeClass('navbar-white navbar-light');
                $btn.find('i').removeClass('fa-moon').addClass('fa-sun');
            }

            function applyLightMode() {
                $theme.attr('href', '<?= asset('libraries/jquery-ui/cupertino/jquery-ui.min.css') ?>');
                localStorage.setItem('theme', 'light');
                $('html').removeClass('dark-mode');
                $body.removeClass('dark-mode');
                $nav.removeClass('navbar-dark').addClass('navbar-white navbar-light');
                $btn.find('i').removeClass('fa-sun').addClass('fa-moon');
            }

            // Initial Theme Load
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                applyDarkMode();
            } else {
                applyLightMode();
            }

            $btn.on('click', function() {
                if ($body.hasClass('dark-mode')) {
                    applyLightMode();
                } else {
                    applyDarkMode();
                }
            });
        });
    </script>
    </body>

    </html>