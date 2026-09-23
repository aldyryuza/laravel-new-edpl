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
│   ├── app.blade.php
│   └── auth.blade.php
├── components/
│   ├── alert.blade.php
│   ├── modal.blade.php
│   └── table.blade.php
└── pages/
    ├── dashboard/
    ├── users/
    └── [module]/
```

Gunakan struktur yang sesuai project; jangan memecah file Blade terlalu kecil tanpa manfaat.

## 3. Struktur JavaScript

Default:

```text
resources/js/
├── pages/
│   ├── users.js
│   ├── products.js
│   └── [module].js
└── components/
    ├── datatable.js
    ├── modal.js
    └── notification.js
```

Untuk module kecil, satu file page JS sudah cukup.

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
