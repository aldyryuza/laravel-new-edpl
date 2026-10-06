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
- Tabel master akses: `menus`, `roles`, `user_has_roles`, `role_menu_accesses` (flag create/read/update/delete/print/approve per role per menu), dan kolom `users.nik` (unique).
- Login session dengan NIK + password (`/login`, `/logout`, throttle 5x/menit).
- Permission `{kode_menu}.{action}` lewat `Gate::before`, dihitung dari role aktif user.
- Pilih role setelah login untuk user dengan lebih dari 1 role (`/active-role`), dan ganti role dari dropdown navbar tanpa login ulang. Middleware `ResolveActiveRole` (grup `web`) menentukan role aktif dari session; role tunggal dipakai otomatis, role yang dicabut admin otomatis dilepas.
- Halaman Profil Saya (`/profile`): ubah nama/email (`PUT api/profile`) dan ganti password dengan verifikasi password lama (`PUT api/profile/password`). NIK dan role hanya bisa diubah admin.
- Management User, Role (termasuk halaman pengaturan hak akses per menu), dan Menu: halaman Blade + API `api/users`, `api/roles`, `api/roles/{role}/access`, `api/menus`.
- `routes/api.php` (prefix `/api`, middleware `web` + `auth`, berbasis session untuk AJAX).
- Seeder `AccessControlSeeder`: menu bawaan, role Administrator dengan akses penuh, user `admin` (password awal `password`, wajib diganti).
- Helper JS `App.escape()` dan `App.confirm()`.
- Pencarian menu di sidebar (filter di browser, shortcut `/`, `Enter` membuka hasil pertama).
- Real-time dengan Laravel Reverb (`laravel/reverb`) + Echo statis: presence channel `online`, halaman **User Online** (role aktif + halaman yang dibuka) dan jumlah online di navbar (`online-users.read`). `composer dev` ikut menjalankan `reverb:start`.
- Audit log dengan `spatie/laravel-activitylog` v5: login/login gagal/logout, ganti role, ganti/reset password, perubahan User/Role/Menu/hak akses/role user, dan pelanggaran rate limit. Halaman **Audit Log** dengan filter kategori, pencarian, dan rentang tanggal (`audit-log.read`, `GET api/activity-logs`).
- Menu seeder `online-users` dan `audit-log` di grup Pengaturan.

- Laravel Pulse: dashboard `/pulse` (menu **Monitoring Server**, hak akses `pulse.read`); `composer dev` ikut menjalankan `pulse:check`.
- Laravel Excel (`maatwebsite/excel` v4): ekspor Daftar User (`GET /users/export`, hak akses `users.print`, rate limit `export` 10/menit per user), mengikuti pencarian tabel, dicatat ke audit log. Semua sel disimpan sebagai teks untuk mencegah formula injection.
- Yajra DataTables sebagai standar data tabel: endpoint `GET api/users|roles|menus|activity-logs/datatable`, helper JS `App.dataTableSource()`, sorting per kolom (whitelist).

- Migration schema E-DPL PostgreSQL (`2026_10_06_000001`-`000039`): kolom `users.uuid` (UUID v4, unique, backfill user existing), `users.is_active`, `users.last_login_at`; tabel organisasi, master outlet/product/distributor, commercial, DPL core, approval per item, dan dokumen sesuai `docs/Schema.md`. Partial unique index satu DPL ACTIVE per outlet + team. `distributor_margin_rules`/`distributor_margin_overrides` belum dibuat (desain pending).
- Model `User` mengisi `uuid` otomatis saat create (PK tetap `id`).
- Migration E-DPL dikelompokkan per domain di `database/migrations/{identity,organization,master,commercial,dpl,approval,documents}`; subfolder didaftarkan di `AppServiceProvider` dan urutan eksekusi tetap mengikuti timestamp nama file.
- PostgreSQL schema per sistem: tabel commercial, DPL, approval, dan dokumen E-DPL berada di schema `dpl` (`2026_10_06_000000_create_dpl_schema`); master bersama tetap di `public`. `search_path` menjadi `public,dpl`.
- Model Eloquent + factory untuk 37 tabel E-DPL (model schema `dpl` memakai `#[Table('dpl.…')]` dan berada di `App\Models\Dpl` / `Database\Factories\Dpl`; pivot `distributor_regions` lewat `belongsToMany`). Konstanta status/tipe DPL, relasi DPL, dan aksi approval mengikuti `docs/Architecture.md`/`docs/Schema.md`.
- Seeder `ApprovalReferenceSeeder` (role RM/NSM/GM/SMD/DMD + workflow `DPL_STANDARD` RM -> NSM -> GM -> SMD -> DMD) dan `OutletClassificationSeeder` (kategori RUMAH_SAKIT/INSTANSI, kepemilikan PEMERINTAH/NON_PEMERINTAH); idempotent, terdaftar di `DatabaseSeeder`.
- Status akun user: login user nonaktif ditolak ("Akun Anda nonaktif", hanya bila password benar), sesi user yang dinonaktifkan langsung diputus oleh middleware `EnsureUserIsActive` (web: redirect login, API: 401). `last_login_at` diisi saat login berhasil.
- Management User: switch **Aktif** di form, kolom Status & Login Terakhir di tabel dan ekspor Excel; perubahan status tercatat di audit log. Akun yang sedang login tidak bisa menonaktifkan dirinya sendiri.

### Changed (user)

- `DELETE api/users/{id}` mengembalikan 422 bila user sudah dipakai data bisnis (FK RESTRICT); user tersebut dinonaktifkan, bukan dihapus. Kontrak endpoint lain tidak berubah; `is_active` opsional di `POST`/`PUT api/users`.

### Changed (testing)

- Test suite memakai PostgreSQL lokal `edpl_testing` (sebelumnya SQLite in-memory), karena SQLite tidak mendukung schema. Buat database-nya sekali: `createdb -h 127.0.0.1 -U postgres edpl_testing`.

### Changed

- **Breaking (API):** `GET api/users`, `GET api/roles`, dan `GET api/activity-logs` dihapus, diganti endpoint `.../datatable` (protokol DataTables). `GET api/menus` tetap untuk pilihan parent. Filter kata kunci Audit Log kini bernama `keyword`.
- Tabel Menu memakai DataTables (sebelumnya render manual).
- Rate limit bernama: `login` (5/menit per NIK+IP, 20/menit per IP), `api` (60/menit per user), `password` (5/menit per user). Request yang kena limit dicatat sekali per window ke audit log kategori `security`.

- Route `/` diarahkan ke `/dashboard`.
- Sidebar hanya menampilkan logo (tanpa teks brand), dengan ukuran berbeda saat sidebar terbuka dan menciut. `THEME_BRAND` kini hanya dipakai sebagai `alt`/`title`.
- Template customizer dan navbar search Sneat dimatikan secara default (`THEME_CUSTOMIZER`, `THEME_SEARCH`) karena tidak dibutuhkan di aplikasi dan menambah beban load.
- Sidebar dibaca dari tabel `menus` dan disaring sesuai akses `read` user; `config/menu.php` dihapus.
- Semua halaman (kecuali login) wajib login; halaman contoh butuh akses `example.read`.
- `docs/Design.md`: struktur view disesuaikan dengan layout yang dibuat; JS halaman ditempatkan di `public/js/pages/` karena asset Sneat di-serve statis tanpa bundler.

### Fixed

- Dashboard Pulse error "incomplete object Illuminate\Support\Collection": `cache.serializable_classes` kini meng-allowlist class yang dibutuhkan Pulse saja.
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
