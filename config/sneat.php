<?php

/*
|--------------------------------------------------------------------------
| Sneat / Theme configuration
|--------------------------------------------------------------------------
|
| Satu tempat untuk mengatur layout dan asset template.
| Tujuan utama: halaman hanya memuat asset yang benar-benar dipakai,
| sehingga page load tetap ringan walaupun template Sneat besar.
|
| Semua path relatif terhadap "public/assets".
|
*/

return [

    // Folder asset Sneat di dalam public/.
    'assets_path' => 'assets',

    /*
    | Cache busting.
    | true  -> menambahkan ?v=<filemtime> (aman untuk deploy, stat file di-cache per request)
    | false -> URL polos (pakai ini jika sudah ada versioning di level CDN/webserver)
    */
    'cache_bust' => (bool) env('THEME_CACHE_BUST', true),

    /*
    | Template customizer Sneat (panel setting di kanan layar).
    | Berat dan tidak dibutuhkan di aplikasi production -> default mati.
    */
    'customizer' => (bool) env('THEME_CUSTOMIZER', false),

    // Navbar search (Algolia autocomplete). Nyalakan hanya jika dipakai.
    'search' => (bool) env('THEME_SEARCH', false),

    'layout' => [
        'html_class' => 'layout-navbar-fixed layout-menu-fixed layout-compact',
        'skin' => 'default',
        'theme' => env('THEME_MODE', 'light'), // light | dark | system
        'template' => 'vertical-menu-template',
        'container' => 'container-xxl',
        // Dipakai sebagai alt/title logo, tidak ditampilkan sebagai teks.
        'brand' => env('THEME_BRAND', 'Satoria'),

        /*
        | Logo sidebar. Sementara statis; nanti bisa diisi per company user
        | (mis. lewat view composer) tanpa mengubah Blade.
        |
        | logo           -> dipakai saat sidebar terbuka
        | logo_collapsed -> dipakai saat sidebar menciut (opsional, default sama)
        */
        'logo' => 'img/satoria/satoriapharma_logo.png',
        'logo_collapsed' => null,
    ],

    /*
    | Asset inti: dimuat di semua halaman.
    */
    'core' => [
        'css' => [
            'vendor/fonts/iconify-icons.css',
            'vendor/css/core.css',
            'css/demo.css',
            'vendor/libs/perfect-scrollbar/perfect-scrollbar.css',
        ],
        'js' => [
            'vendor/libs/jquery/jquery.js',
            'vendor/libs/popper/popper.js',
            'vendor/js/bootstrap.js',
            'vendor/libs/perfect-scrollbar/perfect-scrollbar.js',
            'vendor/libs/hammer/hammer.js',
            'vendor/js/menu.js',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Vendor registry
    |--------------------------------------------------------------------------
    |
    | Halaman mendeklarasikan vendor yang dibutuhkan:
    |
    |     @extends('layouts.app', ['vendors' => ['datatables', 'select2']])
    |
    | Layout yang menerjemahkannya menjadi tag <link>/<script>.
    | "requires" dipakai untuk dependency antar vendor.
    |
    */
    'vendors' => [

        'datatables' => [
            'css' => [
                'vendor/libs/datatables-bs5/datatables.bootstrap5.css',
                'vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css',
                'vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css',
            ],
            'js' => [
                'vendor/libs/datatables-bs5/datatables-bootstrap5.js',
            ],
        ],

        'select2' => [
            'css' => ['vendor/libs/select2/select2.css'],
            'js' => ['vendor/libs/select2/select2.js'],
        ],

        'bootstrap-select' => [
            'css' => ['vendor/libs/bootstrap-select/bootstrap-select.css'],
            'js' => ['vendor/libs/bootstrap-select/bootstrap-select.js'],
        ],

        'form-validation' => [
            'css' => ['vendor/libs/@form-validation/form-validation.css'],
            'js' => [
                'vendor/libs/@form-validation/popular.js',
                'vendor/libs/@form-validation/bootstrap5.js',
                'vendor/libs/@form-validation/auto-focus.js',
            ],
        ],

        'sweetalert2' => [
            'css' => ['vendor/libs/sweetalert2/sweetalert2.css'],
            'js' => ['vendor/libs/sweetalert2/sweetalert2.js'],
        ],

        'notyf' => [
            'css' => ['vendor/libs/notyf/notyf.css'],
            'js' => ['vendor/libs/notyf/notyf.js'],
        ],

        'moment' => [
            'js' => ['vendor/libs/moment/moment.js'],
        ],

        'flatpickr' => [
            'css' => ['vendor/libs/flatpickr/flatpickr.css'],
            'js' => ['vendor/libs/flatpickr/flatpickr.js'],
        ],

        'daterangepicker' => [
            'requires' => ['moment'],
            'css' => ['vendor/libs/bootstrap-daterangepicker/bootstrap-daterangepicker.css'],
            'js' => ['vendor/libs/bootstrap-daterangepicker/bootstrap-daterangepicker.js'],
        ],

        'apexcharts' => [
            'css' => ['vendor/libs/apex-charts/apex-charts.css'],
            'js' => ['vendor/libs/apex-charts/apexcharts.js'],
        ],

        'cleave' => [
            'js' => ['vendor/libs/cleave-zen/cleave-zen.js'],
        ],

        'dropzone' => [
            'css' => ['vendor/libs/dropzone/dropzone.css'],
            'js' => ['vendor/libs/dropzone/dropzone.js'],
        ],

        'quill' => [
            'css' => [
                'vendor/libs/quill/typography.css',
                'vendor/libs/quill/editor.css',
            ],
            'js' => ['vendor/libs/quill/quill.js'],
        ],

        'tagify' => [
            'css' => ['vendor/libs/tagify/tagify.css'],
            'js' => ['vendor/libs/tagify/tagify.js'],
        ],

        'nouislider' => [
            'css' => ['vendor/libs/nouislider/nouislider.css'],
            'js' => ['vendor/libs/nouislider/nouislider.js'],
        ],

        'spinkit' => [
            'css' => ['vendor/libs/spinkit/spinkit.css'],
        ],

        'animate' => [
            'css' => ['vendor/libs/animate-css/animate.css'],
        ],

        // Page CSS bawaan Sneat
        'page-auth' => [
            'css' => ['vendor/css/pages/page-auth.css'],
        ],

        'page-misc' => [
            'css' => ['vendor/css/pages/page-misc.css'],
        ],

        'page-profile' => [
            'css' => ['vendor/css/pages/page-profile.css'],
        ],

        'autocomplete' => [
            'js' => ['vendor/libs/@algolia/autocomplete-js.js'],
        ],
    ],
];
