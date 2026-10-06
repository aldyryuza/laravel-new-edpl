# Design & UI Convention

> Project: `[PROJECT_NAME]`  
> UI stack: `Blade + Bootstrap 5 + Sneat`  
> JavaScript: `jQuery + AJAX`  
> Updated: `[YYYY-MM-DD]`

## 1. Prinsip UI

- Gunakan komponen Sneat yang sudah tersedia sebelum membuat komponen baru.
- Gunakan Bootstrap utility/class sebelum membuat CSS khusus.
- UI harus konsisten antar module.
- Jangan menaruh business logic di Blade.
- Jangan menaruh AJAX besar langsung di Blade.
- JavaScript halaman berada di file JS terkait.

## 2. Struktur view

```text
resources/views/
├── layouts/
│   ├── app.blade.php            # layout utama (sidebar + navbar)
│   ├── blank.blade.php          # layout tanpa menu (login, error, print)
│   └── partials/
│       ├── head.blade.php
│       ├── scripts.blade.php
│       ├── sidebar.blade.php
│       ├── menu-items.blade.php # render menu rekursif
│       ├── navbar.blade.php
│       └── footer.blade.php
├── components/
│   ├── page-header.blade.php    # <x-page-header>
│   ├── alert.blade.php          # <x-alert> (flash message session)
│   └── excel-import-modal.blade.php # <x-excel-import-modal> + public/js/excel-import.js
└── pages/
    ├── dashboard/
    └── [module]/
```

Gunakan struktur yang sesuai project; jangan memecah file Blade terlalu kecil tanpa manfaat.

## 3. Struktur JavaScript

Default:

```text
public/js/
├── app.js                   # bootstrap: CSRF, App.url(), notifikasi, handle error AJAX
├── excel-import.js          # perilaku <x-excel-import-modal>; event `excel-import:done` setelah sukses
└── pages/
    ├── users.js
    ├── products.js
    └── [module].js
```

JS halaman disimpan di `public/js/` (bukan `resources/js/`) karena template Sneat
di-serve langsung dari `public/assets/` tanpa bundler. Tidak ada build step untuk
UI admin, sehingga file JS halaman diperlakukan sama seperti asset Sneat.

Untuk module kecil, satu file page JS sudah cukup.

Muat dengan cache busting:

```blade
@push('scripts')
    <script src="{{ asset_v('js/pages/users.js') }}"></script>
@endpush
```

## 4. Pola CRUD page

```text
Blade
 ├── table
 ├── filter/search
 ├── create modal/form
 └── edit modal/form
        |
        v
resources/js/pages/module.js
        |
        v
jQuery AJAX
        |
        v
routes/api.php
        |
        v
API Controller
```

JS bertanggung jawab atas:

- event handler;
- AJAX request;
- render/update UI;
- loading state;
- menampilkan validation/error message.

Business rule tetap di backend.

## 5. AJAX convention

Gunakan pola yang konsisten:

```javascript
$.ajax({
    url: '/api/products',
    type: 'POST',
    data: payload,
    success: function (response) {
        // update UI
    },
    error: function (xhr) {
        // tampilkan validation/application error
    }
});
```

Sesuaikan dengan helper AJAX yang sudah tersedia di project. Jangan membuat helper kedua jika project sudah memiliki helper.

## 6. CRUD UI

Default page:

```text
Page title
  |
  +-- Action button: Add/Create
  |
  +-- Filter/Search
  |
  +-- Data table
  |      +-- View
  |      +-- Edit
  |      +-- Delete/Action sesuai permission
  |
  +-- Pagination
```

## 7. Form

- Gunakan label yang jelas.
- Tandai required field.
- Validation server adalah sumber kebenaran.
- Client-side validation hanya sebagai UX tambahan.
- Tampilkan validation error dekat field bila memungkinkan.
- Jangan menghapus input user ketika request gagal.

## 8. Modal

Gunakan modal untuk form kecil/CRUD cepat jika sesuai.

Untuk form kompleks atau workflow panjang, gunakan halaman tersendiri agar lebih mudah dipahami.

## 9. Table

- Gunakan pagination untuk dataset besar.
- Jangan render ribuan row sekaligus.
- Action column mengikuti permission.
- Format tanggal, angka, dan status harus konsisten.
- Gunakan empty state yang jelas.

## 10. Permission-aware UI

UI boleh menyembunyikan tombol yang tidak memiliki permission, tetapi **authorization tetap wajib dilakukan backend**.

```text
UI permission check = UX
Backend authorization = Security
```

## 11. Status dan feedback

Gunakan pola konsisten:

- success -> notification/toast;
- validation -> field error;
- business rule -> alert/modal yang menjelaskan alasan;
- unexpected error -> pesan umum yang aman.

## 12. Responsive

Target device:

- Desktop: `[YES/NO]`
- Tablet: `[YES/NO]`
- Mobile: `[YES/NO]`

Gunakan responsive utility Bootstrap/Sneat sebelum CSS custom.

## 13. Custom CSS

Buat custom CSS hanya jika Bootstrap/Sneat tidak cukup.

Hindari inline style yang berulang.

## 14. Accessibility minimum

- label form jelas;
- tombol memiliki nama yang bermakna;
- modal dapat ditutup dengan cara standar;
- warna bukan satu-satunya indikator status;
- keyboard navigation tidak rusak oleh custom JS.

## 15. Layout dan pemuatan asset

Template Sneat berisi ratusan file vendor. Supaya page load tetap ringan,
**setiap halaman hanya memuat asset yang dideklarasikannya**.

### Cara pakai layout

```blade
@extends('layouts.app', ['vendors' => ['datatables', 'select2']])

@section('title', 'Daftar User')

@section('content')
    <x-page-header title="Daftar User" :breadcrumbs="[['label' => 'Master']]">
        <x-slot:actions>
            <button class="btn btn-primary">Tambah</button>
        </x-slot:actions>
    </x-page-header>

    <x-alert />
@endsection

@push('scripts')
    <script src="{{ asset_v('js/pages/users.js') }}"></script>
@endpush
```

Yang tersedia di layout:

| Hook | Fungsi |
|---|---|
| `['vendors' => [...]]` | daftar vendor dari `config/sneat.php` |
| `['container' => '...']` | override container content |
| `@section('title')` | judul halaman |
| `@section('content')` | isi halaman |
| `@push('styles')` | CSS khusus halaman |
| `@push('scripts')` | JS khusus halaman |
| `@push('head')` | meta/tag tambahan di `<head>` |
| `@push('modals')` | modal, dirender sebelum script |

### Registry vendor

Nama vendor didefinisikan di `config/sneat.php`:

```php
'select2' => [
    'css' => ['vendor/libs/select2/select2.css'],
    'js'  => ['vendor/libs/select2/select2.js'],
],

'daterangepicker' => [
    'requires' => ['moment'],
    'css' => ['vendor/libs/bootstrap-daterangepicker/bootstrap-daterangepicker.css'],
    'js'  => ['vendor/libs/bootstrap-daterangepicker/bootstrap-daterangepicker.js'],
],
```

Menambah library baru = menambah satu entry di config, bukan mengedit layout.
Dependency antar vendor diselesaikan lewat `requires`, duplikat otomatis dibuang.

### Hemat resource

- Halaman tanpa `vendors` hanya memuat core CSS/JS (Bootstrap, menu, perfect-scrollbar).
- `template-customizer.js` mati secara default (`THEME_CUSTOMIZER=false`); tombol
  light/dark/system di navbar yang menggantikannya. Jika customizer dinyalakan,
  tombol navbar otomatis disembunyikan agar tidak ada dua pengatur theme.
- Navbar search (Algolia) mati secara default (`THEME_SEARCH=false`).
- Semua URL asset diberi `?v=<filemtime>` supaya browser bisa cache agresif
  dan tetap dapat file baru setelah deploy (`THEME_CACHE_BUST=false` untuk mematikan).
- Menu, layout, dan registry vendor berasal dari config, sehingga ikut ter-cache
  oleh `php artisan config:cache`.

### Logo sidebar

Logo diambil dari `config/sneat.php`:

```php
'brand' => env('THEME_BRAND', 'Satoria'),   // hanya alt/title, tidak tampil sebagai teks
'logo' => 'img/satoria/satoriapharma_logo.png',
'logo_collapsed' => null,                   // null = pakai logo yang sama
```

Sidebar merender dua `<img>`: `.app-brand-img` (sidebar terbuka) dan
`.app-brand-img-collapsed` (sidebar menciut). Pertukarannya ditangani CSS bawaan
Sneat, ukurannya diatur di `public/css/app.css`. Tidak ada JavaScript yang terlibat.

Saat logo perlu mengikuti company milik user, cukup isi nilai config tersebut
dari view composer; Blade tidak perlu diubah.

## 16. Menu sidebar

Menu disimpan di tabel `menus` dan dikelola lewat halaman **Pengaturan > Menu**.
`App\Services\MenuService::sidebarFor($user)` menyusunnya menjadi item untuk
`layouts.partials.menu-items`.

- Menu dengan route tampil jika user punya akses `read` ke kode menu tersebut.
- Menu grup (route kosong) tampil jika minimal satu sub menunya tampil.
- Menu `is_active = false` tidak tampil.
- State aktif dihitung dari prefix route: `users.index` -> `users.*`.
- Route yang belum terdaftar otomatis jatuh ke `javascript:void(0);`, tidak error.
- Kolom **Cari menu** di atas sidebar memfilter item di browser (`initSidebarSearch` di
  `public/js/app.js`). Parent dari hasil yang cocok ikut dibuka; `Enter` membuka hasil
  pertama, `Esc` mengosongkan, `/` memfokuskan kolom. Disembunyikan saat sidebar menciut.

## 17. Hak akses di UI

Ability berformat `{kode_menu}.{action}`, action: `create`, `read`, `update`,
`delete`, `print`, `approve`. Dicek via `Gate::before` ke tabel `role_menu_accesses`
untuk **role aktif** user (`auth()->user()->activeRole()`). Role aktif tampil sebagai
badge di dropdown user navbar; user dengan lebih dari 1 role bisa menggantinya dari sana.

```blade
@can('users.create')
    <button class="btn btn-primary">Tambah</button>
@endcan
```

Untuk tombol yang dirender JS, kirim flag lewat data attribute:

```blade
<div id="users-page" data-can-update="{{ (int) auth()->user()->can('users.update') }}">
```

Route web/API wajib tetap memakai middleware `can:{kode_menu}.{action}`.

## 18. Pola management CRUD

Contoh lengkap: `pages/users`, `pages/roles`, `pages/menus` + `public/js/pages/*.js`.

- **Semua tabel data memakai Yajra DataTables** (server-side) lewat endpoint `GET api/{resource}/datatable`:

  ```javascript
  $('#users-table').DataTable({
      serverSide: true,
      processing: true,
      ajax: App.dataTableSource('api/users/datatable'),          // + fungsi filter tambahan bila perlu
      columns: [{ data: 'name', name: 'name', render: App.escape }, ...]
  });
  ```

  - `name` kolom harus sama dengan kolom yang di-whitelist di controller (sort/search).
  - Kolom hasil hitung/relasi: `orderable: false, searchable: false`.
  - Filter custom (mis. form Audit Log) dikirim lewat argumen kedua `App.dataTableSource(path, () => ({...}))`.
  - Data dari Yajra **tidak di-escape di server**; selalu render dengan `App.escape()`.
- Form tambah/edit di modal, submit AJAX, error 422 ditampilkan per field via `App.handleError(xhr, $form)`.
- Konfirmasi hapus: `App.confirm(message)`; teks dari data: `App.escape(value)`.

## 19. Real-time di halaman

`public/js/realtime.js` dimuat otomatis untuk user login (saat `BROADCAST_CONNECTION=reverb`)
dan menyediakan `window.Realtime` (atau `null` jika tidak aktif):

```javascript
if (window.Realtime) {
    Realtime.onMembers(list => { /* [{ id, nik, name, role, page, title }] */ });
    Realtime.onStatus(state => { /* connecting | connected | unavailable | ... */ });
}
```

Elemen dengan atribut `data-online-count` otomatis diisi jumlah user online.
Halaman wajib tetap berfungsi (dengan pesan yang jelas) saat Reverb tidak berjalan.
