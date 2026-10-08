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
- Approval multi-approver (BR-026): tabel `dpl.approval_step_assignees` menyimpan snapshot semua kandidat approver per step; kolom `approval_step_instances.assigned_user_id` dihapus (migration `2026_10_06_000040`). Model `ApprovalStepAssignee`, relasi `ApprovalStepInstance::assignees()/assigneeUsers()`.
- Menu **Master Organisasi**: halaman Zona (`/sales-zones`), Region (`/sales-regions`), Area (`/sales-areas`), dan Approver DPL (`/dpl/approver-assignments`) dengan API `api/sales-zones|sales-regions|sales-areas` dan `api/dpl/approver-assignments` (datatable + CRUD). Hak akses: `sales-zones.*`, `sales-regions.*`, `sales-areas.*`, `approver-assignments.*`. Perubahan tercatat di audit log.
- Approver DPL: scope dipilih eksplisit (Region/Zona/Global, tidak bergantung nama role), role dibatasi pada role workflow approval, user harus aktif; beberapa approver per role + scope diizinkan, tetapi user yang sama tidak boleh punya assignment aktif ganda dengan periode bertumpang tindih.
- Trait `DeletesRestrictedModels`: hapus data yang masih dipakai (FK RESTRICT) membalas 422 dengan saran menonaktifkan; dipakai juga oleh API User.
- Import Excel untuk Zona, Region, Area: template (`GET /sales-zones|sales-regions|sales-areas/import-template`, sheet Data + Petunjuk berisi kode referensi) dan upload (`POST api/{resource}/import`). Upsert berdasarkan kode, parent dirujuk lewat kode (`kode_zona`, `kode_region`), all-or-nothing dengan daftar error per baris, maks. 5.000 baris/5 MB, butuh hak `create` + `update`, tercatat di audit log (`imported`). Komponen bersama `<x-excel-import-modal>` + `public/js/excel-import.js`, service `OrganizationImportService`.
- Menu **Master Outlet**: Outlet (`/outlets`, filter region/kategori/kepemilikan/group/status, import Excel), Group RS (`/outlet-groups`), Klasifikasi Outlet (`/outlet-classifications`: kategori GRUP1 + kepemilikan GRUP2). API `api/outlets`, `api/outlet-groups`, `api/outlet-categories`, `api/ownership-types`. Hak akses `outlets.*`, `outlet-groups.*`, `outlet-classifications.*`.
- Satu group aktif per outlet: partial unique index `outlet_group_members (outlet_id) WHERE valid_until IS NULL` (migration `2026_10_06_000042`); pindah group menutup keanggotaan lama per hari ini (`OutletService`).
- Peta Leaflet di form Outlet: klik/geser marker mengisi latitude/longitude, input angka memindahkan marker, tombol "Lokasi saya" (geolocation browser, butuh HTTPS/localhost) dan "Hapus titik"; ikon pin di tabel untuk outlet berkoordinat. Vendor `leaflet` terdaftar di `config/sneat.php`; tile server via `config('services.map')` / env `MAP_TILE_URL` (default OpenStreetMap).
- Menu **Master Produk & Distributor**: Product (`/products`, volume dalam ml untuk grouping 500/250/100/25 ml, filter volume/branded/status, import Excel) dan Distributor (`/distributors`, induk -> cabang, multi region `distributor_regions`, filter region/jenis/status, import Excel). API `api/products`, `api/distributors`; hak akses `products.*`, `distributors.*`.
- Distributor: hierarki induk melingkar ditolak (form via `DistributorService::createsCycle`, import via cek in-memory); perubahan region tercatat di audit log (`regions_updated`). Import: `kode_induk` boleh merujuk baris di file yang sama, `kode_region` dipisah koma (kosong = region tidak diubah).
- Distributor: kontak (alamat, telepon, fax, email, NPWP) dan lokasi monthly meeting CRM (latitude, longitude, `meeting_radius_meters`) di form, tabel, dan import Excel (migration `2026_10_06_000043`). Mapping region harga distributor `dpl.distributor_price_regions` (`000044`) dan telepon Kacab `dpl.distributor_branch_heads.phone` (`000045`). Mapping kolom legacy `distributor` dicatat di `docs/Schema.md` §16.
- Master wilayah administratif: Provinsi (`/provinces`) dan Kota/Kabupaten (`/cities`) di menu Master Organisasi, kode = kode wilayah BPS tanpa titik (mis. 8271), CRUD + import Excel (`OrganizationImportService` tipe `province`/`city`). Hak akses `provinces.*`, `cities.*`. Migration `2026_10_06_000046`–`000047`.
- Outlet dan Distributor memakai master kota (`city_id`, pilihan select2; kolom import `kode_kota`). `outlets.city_id` ditambahkan (`000049`).
- Menu **Komersial DPL**: Region Harga (`/dpl/price-regions`, CRUD + import), Region Harga Outlet & Distributor (`/dpl/outlet-price-regions`, `/dpl/distributor-price-regions`: atur per baris atau import `kode_outlet|kode_distributor, kode_region_harga`; region kosong = mapping dihapus), Harga Product (`/dpl/product-prices`: harga & harga reguler NUMERIC(18,4), periode berlaku, filter "berlaku hari ini", import upsert per product + region + berlaku mulai sehingga histori harga tersimpan). Hak akses `price-regions.*`, `outlet-price-regions.*`, `distributor-price-regions.*`, `product-prices.*`. Overlap periode harga belum diblokir (menunggu aturan histori harga).
- Komersial DPL lanjutan: Harga Khusus (`/dpl/special-prices`: jenis harga mis. JKN/PADMA disimpan huruf besar, opsional per group RS, periode berlaku, filter + import upsert per jenis + group + product + berlaku mulai), Skema Bonus (`/dpl/bonus-schemes`: nilai manual, diskon efektif tidak dihitung otomatis, tidak bisa dihapus bila dipakai item DPL), Parameter Komersial (`/dpl/commercial-parameters`: kode KODE_HURUF_BESAR, nilai bertipe angka/teks/ya-tidak, tidak di-seed). Hak akses `special-prices.*`, `bonus-schemes.*`, `commercial-parameters.*`.
- Skema bonus disamakan dengan legacy `dpl_bonus`: kolom `bonus`, `discount_invoice`, `discount_bonus`, `discount_rounded`, `total_discount` (wajib, default 0); `buy_quantity`/`bonus_quantity` dihapus dan `effective_discount` diganti nama `total_discount` (migration `2026_10_06_000051`).
- Wilayah & team user: master **Team** (`/teams`, tabel `teams`, hak akses `teams.*`) dan halaman **Wilayah User** (`/user-assignments`, hak akses `user-assignments.*`) untuk menugaskan user ke Nasional/zona/region/area + team + periode berlaku (boleh lebih dari satu). `user_org_assignments` mendapat `zone_id`, `region_id`, `team_id` (FK RESTRICT, CHECK maksimal satu level wilayah) dan `team_code` dihapus (migration `2026_10_07_000052`–`000053`). `AccessControlSeeder` memberi user `admin` wilayah Nasional.
- Form item DPL: discount (Principle/Distributor ON/OFF) default 0.00, dikosongkan = 0, ditampilkan dan diinput 2 angka di belakang koma; hitung balik Principle ON dibulatkan 2 desimal.
- Form item DPL: HNA - Discount On bisa diketik (mode Satuan); Discount Share Principle ON otomatis dihitung balik.
- Form item DPL: radio Pilih Product Satuan (default, HNA langsung tampil di modal) / Group (semua product di group terpilih, tombol Pilih semua/Kosongkan, HNA tampil di tabel setelah disimpan).
- Group product: kolom `products.product_group` (migration `2026_10_07_000056`; form, tabel, dan import Product kolom `group_product`). Form item DPL memilih Group Product (1L/500ml/250ml/100ml/25ml/IV CAT WING/IV CAT PEN) yang otomatis memilih semua product di group; disimpan menjadi satu item per product. Product tanpa group tidak bisa diajukan. HNA tidak lagi tampil di modal item, hanya di tabel.
- Jenis harga `PRICE_LAMA_2025` hanya boleh untuk product 500 ml (config `dpl.price_type_volumes`; opsi dinonaktifkan di form dan divalidasi server).
- Form item DPL lanjutan: pilihan jenis harga per item `PRICE_BARU_2026` (price, default) / `PRICE_LAMA_2025` (regular_price) disimpan di `dpl_item_terms.price_type` dan menentukan snapshot `base_price`; HNA - Disc On dan HNA+PPN - Disc On dibulatkan ke rupiah (setengah ke atas); minimum order wajib; harga nego dan konversi bonus dihapus dari form/API (kolom lama dibiarkan). `GET api/dpl/proposals/pricing` kini mengembalikan `prices` per jenis harga.
- Form item DPL: tanggal penawaran otomatis hari ini (read-only), Date End tidak boleh backdated, satu product hanya sekali per distributor, tampilan HNA - Discount On dan HNA+PPN - Discount On saat product dipilih (HNA berlaku pada Date Start, PPN dari parameter `PPN_PERCENT` yang di-seed 11 oleh `CommercialParameterSeeder`; `GET api/dpl/proposals/pricing`), snapshot HNA ke `dpl_item_terms.base_price`, buyer reward otomatis bila Discount Share Principle OFF diisi, opsi Direct (logistic fee wajib) / Ambil Dari Pabrik (logistic fee 0) yang saling eksklusif (kolom `dpl_items.is_factory_pickup`, migration `2026_10_07_000055`). Margin otomatis belum (menunggu struktur margin legacy).
- Pengajuan DPL tahap draft: halaman **Pengajuan DPL** (`/dpl/proposals`, hak akses `dpl-proposals.*`, menu grup DPL) dan form draft (`/dpl/proposals/create`, `/dpl/proposals/{id}`) dengan item + commercial terms (diskon prinsipal/distributor on/off, harga nego, margin, logistic support, minimum order, skema bonus, opsi item). Nomor DPL `{YYMM}-SAI-{zona}-{urut}` per zona per bulan (tabel `dpl.dpl_number_sequences`, migration `2026_10_07_000054`, config `config/dpl.php`). Validasi: Date Start tidak backdated (BR-007), masa program maksimal 1 tahun (BR-008), outlet/distributor di wilayah user, team dari Wilayah User. Draft hanya bisa diubah/dihapus pembuatnya; outlet dan team tidak bisa diganti setelah draft dibuat. Kalkulator, submit, dan approval belum.
- Pembatasan data per wilayah user: tabel, detail, ubah, dan hapus Outlet & Distributor hanya untuk data di wilayah user (di luar wilayah = 404); user tanpa wilayah aktif tidak melihat data; pilihan area/region di form dibatasi wilayah; import Excel outlet/distributor hanya untuk user Nasional (403). Pilihan induk distributor tidak dibatasi wilayah.
- Helper parsing nilai uang dan tanggal import dipindah ke `ExcelImporter` (dipakai import harga product dan harga khusus).
- `OrganizationImportService` diganti nama menjadi `CodeMasterImportService` (dipakai organisasi, wilayah, dan region harga).
- Komponen peta bersama `<x-location-picker>` + `public/js/location-picker.js` (marker, lokasi saya, hapus titik, lingkaran radius); dipakai halaman Outlet dan Distributor.
- Base class `ExcelImporter` untuk import Excel master (dipakai import organisasi dan outlet).

### Changed (schema)

- `outlets.price_region_id` dihapus; region harga outlet pindah ke mapping `dpl.outlet_price_regions` (migration `2026_10_06_000041`, data lama ikut dipindah). Model `Dpl\OutletPriceRegion`; relasi `Outlet::priceRegion()` dihapus.
- **Breaking (schema/API/import):** `distributors.city_name` (teks) diganti `distributors.city_id` (FK master kota), migration `2026_10_06_000048`. Teks lama dicocokkan ke `cities.name`; bila ada yang tidak cocok migration berhenti (tidak ada data hilang diam-diam). Field API `city_name` -> `city_id`; kolom import distributor `kota` -> `kode_kota`.
- **Dihapus (keputusan user): region harga.** Harga DPL tidak dibedakan per region, sehingga `dpl.price_regions`, `dpl.outlet_price_regions`, `dpl.distributor_price_regions`, dan kolom `price_region_id` di `dpl.product_prices`/`dpl.product_price_overrides` dihapus (migration `2026_10_06_000050`, termasuk menu Region Harga/Region Harga Outlet/Region Harga Distributor). Harga Product menjadi satu harga per product + periode berlaku; import tanpa kolom `kode_region_harga`, upsert per product + berlaku mulai.

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
