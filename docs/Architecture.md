# Architecture

> Project: `[PROJECT_NAME]`  
> Laravel: `13.x`  
> PHP: `>= 8.3`  
> Database: `PostgreSQL`  
> Updated: `[YYYY-MM-DD]`

## 1. Prinsip

1. Ikuti konvensi Laravel selama sudah cukup.
2. Gunakan layer sesedikit mungkin.
3. Controller tipis.
4. Business logic berada di Service jika sudah non-trivial.
5. Eloquent digunakan langsung untuk CRUD/query sederhana.
6. Repository dan Action bersifat opsional.
7. Database constraint digunakan untuk invariant yang memang harus dijaga database.
8. Jangan membuat architecture baru tanpa masalah nyata yang ingin diselesaikan.

## 2. Decision tree layer

```text
Apakah hanya CRUD/query sederhana?
        |
       YES ---> Controller -> Model
        |
       NO
        v
Apakah ada business rule/workflow?
        |
       YES ---> Controller -> Service -> Model
        |
       NO
        v
Apakah satu use-case kompleks dan berdiri sendiri?
        |
       YES ---> Controller -> Action/Service -> ...
        |
       NO
        v
Tetap gunakan struktur paling sederhana.
```

Repository hanya dipertimbangkan jika persistence/query sudah kompleks atau reusable lintas beberapa service.

## 3. Struktur layer

### Route

- URI;
- HTTP method;
- route name;
- middleware;
- binding.

Tidak ada business logic.

### Web Controller

```php
public function index()
{
    return view('products.index');
}
```

Fokus pada halaman, view, redirect, dan kebutuhan HTTP web.

### API Controller

Fokus pada:

- request;
- Form Request;
- authorization;
- pemanggilan Service bila diperlukan;
- JSON response.

### Form Request

Fokus pada validation dan request-level authorization/normalization.

### Service

Digunakan untuk:

- business rule;
- multi-step workflow;
- koordinasi beberapa model;
- transaction boundary;
- integration orchestration.

Jangan membuat service pass-through yang hanya meneruskan `Model::create()`.

### Action

Opsional untuk satu use-case yang jelas:

```text
SubmitDpl
ApproveDpl
RejectDpl
GenerateReport
```

Jangan membuat Action untuk semua CRUD.

### Repository

Opsional untuk query/persistence kompleks atau reusable.

Jangan membuat:

```php
public function find($id)
{
    return Model::find($id);
}
```

hanya untuk membungkus Eloquent.

### Model

Fokus pada:

- relationship;
- casts;
- scopes;
- attribute behavior kecil;
- query sederhana.

## 4. Web architecture

```text
Browser
  -> routes/web.php
  -> Web Controller
  -> Blade + Sneat
  -> resources/js/pages/*.js
  -> AJAX
  -> routes/api.php
  -> API Controller
  -> Form Request
  -> Service (optional)
  -> Model / Query
  -> PostgreSQL
```

Halaman tidak perlu memanggil database langsung dari JavaScript.

## 5. API architecture

```text
Client / jQuery AJAX
  -> routes/api.php
  -> Middleware/Auth
  -> Form Request
  -> API Controller
  -> Service (optional)
  -> Model / Query / Integration
  -> JSON Response
```

Gunakan API Resource jika response transformation cukup kompleks atau membutuhkan contract yang stabil.

## 6. Standard API response

Default project:

```json
{
  "success": true,
  "message": "Data berhasil diproses",
  "data": {}
}
```

List:

```json
{
  "success": true,
  "message": "Data berhasil diambil",
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 10,
    "total": 100
  }
}
```

Validation/error:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {}
}
```

Jika project sudah memiliki contract berbeda, ikuti contract existing dan dokumentasikan di sini.

## 7. Transaction

Gunakan `DB::transaction()` ketika beberapa perubahan database harus atomic.

Contoh:

```text
Submit
 -> validate business rule
 -> update header
 -> create detail/history
 -> create approval record
 -> create audit record
 -> commit
```

Jangan menahan database transaction saat menunggu external HTTP request jika bisa dihindari.

## 8. Integration

```text
Service
  -> Integration Client
  -> External API
```

Integration client menangani authentication, timeout, serialization, retry, dan mapping error.

Credential berasal dari `.env`/config, bukan hardcode.

## 9. Queue/job

Gunakan Job jika proses:

- lambat;
- dapat di-retry;
- tidak harus selesai sebelum response;
- cocok untuk email, report, file processing, atau integration sync.

Tentukan retry/backoff/timeout/idempotency untuk job penting.

## 10. Logging

Log context yang berguna:

- request/correlation ID;
- user ID jika sesuai;
- business record ID;
- operation;
- integration;
- retry attempt.

Jangan log password, token, secret, atau payload sensitif yang tidak diperlukan.

## 11. Security

- Authorization server-side.
- Validation server-side.
- `.env` tidak di-commit.
- Production `APP_DEBUG=false`.
- Public web root adalah `public/`.
- Mass assignment dikontrol.
- Sensitive attribute tidak diekspos melalui API.
