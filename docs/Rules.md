# Coding Rules

## 1. General

1. Ikuti Laravel convention terlebih dahulu.
2. Ikuti style module yang sudah ada.
3. Pilih solusi paling sederhana yang benar.
4. Jangan over-engineer.
5. Jangan melakukan unrelated refactor.
6. Jangan menambahkan dependency tanpa alasan.

## 2. Controller

Controller harus tipis.

### Boleh

```php
public function store(StoreProductRequest $request)
{
    $product = $this->productService->create($request->validated());

    return response()->json([
        'success' => true,
        'message' => 'Product berhasil dibuat',
        'data' => $product,
    ]);
}
```

### Hindari

```php
public function store(Request $request)
{
    // 100+ lines validation, calculation,
    // multiple query, approval, logging, etc.
}
```

## 3. CRUD sederhana

Untuk CRUD sederhana, tidak wajib menggunakan Service.

```text
API Controller -> Eloquent Model
```

Service digunakan jika ada business rule/workflow yang memang membutuhkan pemisahan.

## 4. Service

Gunakan Service untuk business logic seperti:

- calculation;
- approval workflow;
- multi-table transaction;
- integration orchestration;
- state transition;
- proses yang digunakan beberapa controller.

Service harus memiliki tanggung jawab yang jelas.

Hindari:

```text
CommonService
HelperService
DataService
```

yang berisi banyak logic tidak berhubungan.

## 5. Action

Action opsional.

Cocok untuk:

```text
SubmitDpl
ApproveDpl
RejectDpl
GenerateBrClaim
SyncNetSuiteItem
```

Tidak perlu:

```text
CreateUserAction
UpdateUserAction
DeleteUserAction
```

jika operasi tersebut hanya CRUD biasa.

## 6. Repository

Repository opsional.

Gunakan hanya jika:

- query sangat kompleks;
- query dipakai ulang;
- persistence abstraction memang dibutuhkan.

Jangan membuat repository sebagai wrapper Eloquent sederhana.

## 7. Model

Gunakan model untuk:

- relationships;
- casts;
- scopes;
- accessor/mutator sederhana;
- query yang memang dekat dengan entity.

Jangan menaruh workflow lintas entity di model.

## 8. Form Request

Gunakan Form Request untuk request yang membutuhkan validation berarti.

```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:150'],
        'status' => ['required', 'in:active,inactive'],
    ];
}
```

Jangan bergantung pada validation JavaScript saja.

## 9. API route

Gunakan resource route jika sesuai.

```php
Route::apiResource('products', ProductController::class);
```

Gunakan endpoint action untuk domain action yang nyata:

```text
POST /api/dpl/{dpl}/submit
POST /api/dpl/{dpl}/approve
POST /api/dpl/{dpl}/reject
```

Jangan membuat endpoint action hanya untuk menghindari HTTP method/resource design yang benar.

## 10. Response API

Default:

```json
{
  "success": true,
  "message": "Data berhasil diproses",
  "data": {}
}
```

Error:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {}
}
```

Ikuti contract existing jika project sudah memiliki format lain.

## 11. Database

- Semua perubahan schema menggunakan migration.
- Gunakan PostgreSQL type yang sesuai.
- Gunakan foreign key bila relasi memang wajib.
- Gunakan index berdasarkan query nyata.
- Hindari duplicate source of truth.
- Jangan menyimpan calculated value tanpa alasan dan strategi konsistensi.

## 12. Naming

| Object | Convention | Example |
|---|---|---|
| Table | plural snake_case | `dpl_items` |
| Column | snake_case | `approval_status` |
| Model | singular PascalCase | `DplItem` |
| Controller | PascalCase + Controller | `DplController` |
| Service | PascalCase + Service | `DplService` |
| Request | Verb/Context + Request | `StoreDplRequest` |
| JS page | module name | `dpl.js` |
| View | lowercase/module style | `dpl/index.blade.php` |

## 13. Query

- Hindari N+1.
- Gunakan eager loading jika relationship diperlukan.
- Gunakan pagination untuk list besar.
- Jangan mengambil semua kolom jika hanya sebagian dibutuhkan untuk query berat.
- Query kompleks boleh dipindahkan ke Repository/query object bila benar-benar meningkatkan kejelasan.

## 14. Transaction

Gunakan transaction untuk perubahan multi-table yang harus atomic.

```php
DB::transaction(function () {
    // related writes
});
```

Jangan memasukkan external HTTP call panjang ke dalam transaction bila bisa dihindari.

## 15. Authorization

Authorization backend wajib.

Gunakan Policy/Gate/permission system sesuai project.

Jangan hanya mengandalkan hidden button di UI.

## 16. Logging / audit

Untuk perubahan bisnis penting, gunakan audit/activity log jika requirement membutuhkannya.

Log minimal:

- actor/user;
- action;
- record ID;
- timestamp;
- perubahan penting jika diperlukan.

Jangan log password/token/secret.

## 17. JS/jQuery

- Satu module JS menangani behavior halaman terkait.
- Jangan menaruh business rule penting di JS.
- AJAX error harus ditangani.
- Loading state harus dipertimbangkan untuk request asynchronous.
- Jangan membuat duplicate AJAX helper.
- Gunakan helper/project convention yang sudah ada.

## 18. Blade/Sneat

- Gunakan layout/component yang sudah tersedia.
- Hindari inline CSS/JS berulang.
- Gunakan Bootstrap utility terlebih dahulu.
- Blade fokus pada presentation.

## 19. Testing

Prioritas:

### Feature test

Untuk:

- endpoint;
- authorization;
- validation;
- workflow;
- database behavior.

### Unit test

Untuk business logic yang berdiri sendiri dan cukup kompleks untuk diuji terpisah.

Tidak perlu membuat unit test untuk setiap getter/setter sederhana.

## 20. Migration safety

Untuk database existing:

```text
add nullable/safe field
 -> deploy compatible code
 -> backfill
 -> validate
 -> enforce NOT NULL/constraint
```

Hindari drop/rename langsung pada production tanpa compatibility plan.

## 21. Git

Commit sebaiknya fokus pada satu tujuan.

Contoh:

```text
feat: add DPL approval workflow
fix: prevent duplicate BR claim
refactor: simplify product query
chore: update Laravel template rules
```

## 22. AI coding rule

AI wajib:

- membaca context sebelum coding;
- mengikuti existing pattern;
- menjelaskan asumsi jika diperlukan;
- tidak membuat business rule sendiri;
- tidak menambahkan architecture tanpa kebutuhan;
- tidak melakukan perubahan unrelated.
