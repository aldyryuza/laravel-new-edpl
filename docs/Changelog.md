# Changelog

Catat perubahan penting yang memengaruhi behavior, schema, API, security, UI, atau architecture.

## [Unreleased]

### Added

- Layout utama Blade + Sneat (`layouts/app.blade.php`, `layouts/blank.blade.php`) beserta partial head/navbar/sidebar/footer/scripts.
- Registry asset per halaman di `config/sneat.php`; halaman mendeklarasikan `['vendors' => [...]]` sehingga hanya memuat CSS/JS yang dipakai.
- Menu sidebar dinamis dari `config/menu.php` dengan deteksi state aktif dan filter permission (UX).
- Helper `theme_asset()` / `asset_v()` dengan cache busting berbasis `filemtime`.
- Komponen Blade `<x-page-header>` dan `<x-alert>`.
- `public/js/app.js`: setup CSRF, `App.url()`, notifikasi, dan penanganan error AJAX sesuai envelope API project.
- Halaman contoh `dashboard` dan `example` beserta Web Controller-nya.

### Changed

- Route `/` diarahkan ke `/dashboard`.
- Sidebar hanya menampilkan logo (tanpa teks brand), dengan ukuran berbeda saat sidebar terbuka dan menciut. `THEME_BRAND` kini hanya dipakai sebagai `alt`/`title`.
- Template customizer dan navbar search Sneat dimatikan secara default (`THEME_CUSTOMIZER`, `THEME_SEARCH`) karena tidak dibutuhkan di aplikasi dan menambah beban load.
- `docs/Design.md`: struktur view disesuaikan dengan layout yang dibuat; JS halaman ditempatkan di `public/js/pages/` karena asset Sneat di-serve statis tanpa bundler.

### Fixed

- Theme switcher light/dark/system di navbar: klik-nya diikat sendiri di `public/js/app.js` memakai API `window.Helpers`, karena binding bawaan Sneat berada di `template-customizer.js` yang dimatikan. Pilihan disimpan di localStorage dengan key yang sama seperti Sneat dan diterapkan sebelum render untuk mencegah kedip.
- `template-customizer.js` kini ikut memuat Pickr saat `THEME_CUSTOMIZER=true`, supaya customizer tidak error ketika dinyalakan. Saat customizer aktif, tombol theme di navbar disembunyikan karena panel customizer sudah menyediakannya.

### Removed

- 

### Security

- 

## [0.1.0] - `[YYYY-MM-DD]`

### Added

- Initial Laravel Coding Template v2.
- Laravel + PostgreSQL baseline.
- Blade + Bootstrap + Sneat + jQuery/AJAX convention.
- Pragmatic Controller/Service/Model architecture.
