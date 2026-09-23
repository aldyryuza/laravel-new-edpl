# Laravel Coding Template v2

Template Laravel pribadi yang dibuat untuk pola kerja **sederhana, modular, dan mudah dipahami**, dengan fokus pada gaya development:

- Laravel 13.x
- PHP 8.3+
- PostgreSQL sebagai default
- Blade
- Bootstrap 5
- Sneat Bootstrap sebagai admin template
- jQuery + AJAX untuk interaksi data
- `routes/web.php` + Web Controller untuk halaman
- `routes/api.php` + API Controller untuk CRUD/API
- Service untuk business logic yang benar-benar membutuhkan layer tambahan

Template ini bukan implementasi "clean architecture" yang memaksa banyak layer. Prinsip utamanya adalah **gunakan abstraction hanya ketika memang memberi manfaat**.

## 1. Prinsip utama

> **Simple first. Add abstraction when complexity proves it is needed.**

Urutan default:

```text
Simple CRUD
Controller -> Model

CRUD dengan business rule
Controller -> Service -> Model

Workflow kompleks
Controller -> Service / Action -> Model / Query / Integration

Query persistence kompleks/reusable
Service -> Repository (opsional)
```

Jangan membuat:

```text
Controller -> Service -> Repository -> Model
```

untuk setiap CRUD hanya karena pola tersebut terlihat rapi.

## 2. UI dan request flow

### Halaman web

```text
Browser
  -> routes/web.php
  -> Web/Page Controller
  -> Blade + Sneat
  -> jQuery/AJAX
  -> routes/api.php
  -> API Controller
  -> Form Request
  -> Service (jika diperlukan)
  -> Model/Query
  -> PostgreSQL
```

### Web Controller

Digunakan untuk:

- menampilkan halaman;
- menentukan view;
- redirect;
- page-level data sederhana jika sesuai.

Web Controller tidak digunakan sebagai tempat business logic panjang.

### API Controller

Digunakan untuk:

- menerima HTTP request;
- memanggil Form Request;
- memanggil Service jika business logic non-trivial;
- mengembalikan JSON response.

## 3. Struktur proyek

```text
app/
├── Actions/                  # opsional
├── Exceptions/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   └── Web/
│   ├── Middleware/
│   └── Requests/
├── Jobs/
├── Models/
├── Policies/
├── Providers/
├── Repositories/             # opsional
├── Rules/
└── Services/

database/
├── factories/
├── migrations/
└── seeders/

resources/
├── css/
├── js/
│   ├── pages/
│   └── components/
└── views/
    ├── layouts/
    ├── components/
    └── pages/

routes/
├── web.php
├── api.php
└── console.php

tests/
├── Feature/
└── Unit/

docs/
├── PRD.md
├── Architecture.md
├── Design.md
├── Schema.md
├── Rules.md
└── Changelog.md
```

Folder `Actions`, `Repositories`, atau `Services` boleh tidak dipakai jika tidak dibutuhkan.

## 4. Dokumentasi workflow

| File              | Fungsi                                                       |
| ----------------- | ------------------------------------------------------------ |
| `PRD.md`          | Scope, user, requirement, business rule, acceptance criteria |
| `Architecture.md` | Alur aplikasi dan batas tanggung jawab layer                 |
| `Design.md`       | Blade/Sneat/Bootstrap/jQuery dan aturan UI                   |
| `Schema.md`       | Database, relasi, index, migration, legacy mapping           |
| `Rules.md`        | Coding convention dan aturan implementasi                    |
| `Changelog.md`    | Perubahan penting                                            |

## 5. Cara memulai project

1. Buat project Laravel sesuai versi yang dipakai.
2. Salin template ini ke root project.
3. Isi `docs/PRD.md`.
4. Tentukan schema awal di `docs/Schema.md`.
5. Tentukan UI di `docs/Design.md`.
6. Pastikan `docs/Architecture.md` sesuai kebutuhan project.
7. Baru mulai membuat migration, model, controller, service, view, dan JS.

Script instalasi tersedia di:

```text
scripts/install-template.sh
scripts/install-template.ps1
```

Script hanya menyalin dokumentasi/instruction template.

## 6. Definition of Ready

Fitur siap dikerjakan jika:

- masalah dan hasil yang diharapkan jelas;
- scope jelas;
- role/permission diketahui;
- business rule diketahui atau ditandai TBD;
- dampak database/API/UI diketahui;
- acceptance criteria dapat diuji.

## 7. Definition of Done

- acceptance criteria terpenuhi;
- validation dan authorization diterapkan;
- test relevan lulus;
- migration aman;
- tidak ada unrelated refactor;
- dokumentasi diperbarui jika diperlukan;
- changelog diperbarui untuk perubahan penting.
