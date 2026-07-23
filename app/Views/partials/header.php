<?php $siteConfig = config('Site'); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= (isset($title) ? ucwords(strtolower($title)) . ' | ' : '') . $siteConfig->siteTitle; ?></title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?= asset('libraries/adminlte/plugins/fontawesome-free/css/all.min.css') ?>">
    <!-- Theme style -->
    <link rel="stylesheet" href="<?= asset('libraries/adminlte/dist/css/adminlte-customized.min.css') ?>">
    <!-- overlayScrollbars -->
    <link rel="stylesheet" href="<?= asset('libraries/adminlte/plugins/overlayScrollbars/css/OverlayScrollbars.min.css') ?>">
    
    <!-- JQGrid 570 Bootstrap 4 (From Trucking) -->
    <link rel="stylesheet" href="<?= asset('libraries/jqgrid/570/css/ui.jqgrid-bootstrap4.css') ?>" />

    <!-- Select2 -->
    <link rel="stylesheet" href="<?= asset('libraries/adminlte/plugins/select2/css/select2.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('libraries/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') ?>">

    <!-- Nestable2 -->
    <link rel="stylesheet" href="<?= asset('libraries/nestable2/1.6.0/css/jquery.nestable.min.css') ?>" />

    <!-- Jquery UI -->
    <link id="jquery-theme" rel="stylesheet" href="<?= asset('libraries/jquery-ui/cupertino/jquery-ui.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('libraries/jquery-ui/1.13.1/jquery-ui.min.css') ?>">

    <!-- Custom Style (From Trucking) -->
    <link rel="stylesheet" href="<?= asset('libraries/tas-lib/css/pager.css') ?>">
    <link rel="stylesheet" href="<?= asset('libraries/tas-lib/css/MonthPicker.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('libraries/tas-lib/css/YearPicker.css') ?>">
    <link rel="stylesheet" href="<?= asset('libraries/tas-lib/css/styles.css') ?>?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= asset('libraries/tas-lib/css/button-styles.css') ?>">
    
    <!-- Legacy Icons -->
    <link rel="stylesheet" href="<?= asset('libraries/menu/menustyle.css') ?>">
    <style>
        /* Patch for legacy background-image icons inside AdminLTE nav-link */
        .nav-icon[class*="icon-"] {
            display: inline-block;
            height: 16px !important;
            width: 16px !important;
            background-size: contain !important;
            vertical-align: sub;
        }
    </style>

    <!-- Scripts - Moved to header to support legacy inline scripts in views (Matching Trucking placement) -->
    <script src="<?= asset('libraries/adminlte/plugins/jquery/jquery.min.js') ?>"></script>
    <script src="<?= asset('libraries/jquery-ui/1.13.1/jquery-ui.min.js') ?>"></script>

    <script>
        const appUrl = '<?= base_url() ?>';
        const baseUrl = '<?= base_url() ?>';
        window.apiUrl = appUrl;

        (function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark-mode');
            }
        })();
    </script>
</head>

<body class="hold-transition sidebar-collapse layout-fixed">
    <div class="modal-loader d-none">
        <div class="modal-loader-content d-flex align-items-center justify-content-center">
            <img src="<?= asset('libraries/tas-lib/img/loading-blue.gif') ?>" rel="preload">
            Loading...
        </div>
    </div>

    <div class="loader" id="loader">
        <img src="<?= asset('libraries/tas-lib/img/hour-glass.gif') ?>" rel="preload">
        <span>Loading</span>
    </div>

    <div class="loaderGrid d-none" id="loaderGrid">
        <span><img src="<?= asset('libraries/tas-lib/img/loading-red.gif') ?>" rel="preload">Loading ...</span>
    </div>

    <div class="lookup-loader d-none">
        <div class="lookup-loader-content d-flex align-items-center justify-content-center">
            <img src="<?= asset('libraries/tas-lib/img/loading-blue.gif') ?>" rel="preload">
            Loading...
        </div>
    </div>

    <div class="processing-loader d-none" id="processingLoader">
        <img src="<?= asset('libraries/tas-lib/img/loading-color.gif') ?>" rel="preload">
        <span>Processing</span>
    </div>

    <div class="wrapper">
