# Architecture

> Project: `E-DPL Rebuild`  
> Laravel: `13.x`  
> PHP: `>= 8.3`  
> Database: `PostgreSQL`  
> Updated: `2026-10-06`

## 1. Prinsip

1. Ikuti konvensi Laravel selama sudah cukup.
2. Gunakan layer sesedikit mungkin.
3. Controller tipis.
4. Business logic berada di Service jika sudah non-trivial.
5. Eloquent digunakan langsung untuk CRUD/query sederhana.
6. Repository dan Action bersifat opsional.
7. Database constraint digunakan untuk invariant yang memang harus dijaga database.
8. Jangan membuat architecture baru tanpa masalah nyata yang ingin diselesaikan.
9. Sistem baru tidak menyalin struktur legacy MySQL/MariaDB 1:1; schema baru mengikuti domain E-DPL.
10. PostgreSQL `users` adalah canonical user master baru untuk arah migrasi CRM, EBU, dan E-DPL.
11. Business history seperti revision, status transition, dan approval action disimpan eksplisit; audit teknis/data tetap menggunakan `spatie/laravel-activitylog`.
12. Reporting tidak boleh menjadi dependency transaksi core E-DPL.

## 2. Target system context

```text
                         PostgreSQL
                             |
                     +-------+-------+
                     |     users     |
                     | canonical IAM |
                     +-------+-------+
                             |
            +----------------+----------------+
            |                |                |
           CRM              EBU             E-DPL
         future           future            current
        migration        migration          rebuild
```

Pada fase rebuild E-DPL ini, PostgreSQL menjadi source of truth untuk user E-DPL. Struktur `users`, `roles`, `user_has_roles`, `menus`, dan `role_menu_accesses` yang sudah ada tetap digunakan dan tidak dibuat ulang sebagai subsistem lain.

### Identity convention

- `users.id`: internal relational key dan FK aplikasi.
- `users.nik`: business/employee identifier, unique, dipakai untuk login dan mapping legacy.
- `users.uuid`: stable opaque identifier untuk kebutuhan integrasi/JWT di masa depan.
- Password tetap disimpan sebagai hash Laravel; password/token tidak boleh masuk audit log.

Future JWT convention:

```text
sub -> users.uuid
nik -> optional business claim
```

NIK tidak digunakan sebagai primary key database.

## 3. Decision tree layer

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

## 4. Struktur layer

### Route

- URI;
- HTTP method;
- route name;
- middleware;
- binding.

Tidak ada business logic.

### Web Controller

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

- calculation commercial;
- approval workflow;
- state transition DPL;
- amendment/renewal;
- consolidation;
- multi-table transaction;
- integration orchestration.

Contoh kandidat service:

```text
DplSubmissionService
DplApprovalService
DplCalculationService
DplAmendmentService
DplRenewalService
DplConsolidationService
ExitLetterService
```

Jangan membuat service pass-through yang hanya meneruskan `Model::create()`.

### Action

Opsional untuk use-case yang jelas, misalnya:

```text
SubmitDpl
ApproveDplItem
RejectDplItem
RequestDplOverride
ActivateDpl
GenerateExitLetter
```

### Model

Fokus pada:

- relationship;
- casts;
- scopes;
- attribute behavior kecil;
- query sederhana.

Workflow lintas entity tidak ditempatkan di Model.

## 5. Domain/module boundaries

```text
Identity & Access
├── users
├── roles
├── user_has_roles
├── menus
└── role_menu_accesses

Organization
├── sales_zones
├── sales_regions
├── sales_areas
└── user_org_assignments

Master Data
├── outlet_categories
├── ownership_types
├── outlet_groups
├── outlet_group_members
├── outlets
├── products
├── distributors
└── distributor_regions

Commercial
├── price_regions
├── product_prices
├── product_price_overrides
├── bonus_schemes
├── distributor_margin_rules
├── distributor_margin_overrides
└── commercial_parameters

DPL Core
├── dpl_proposals
├── dpl_items
├── dpl_item_terms
├── dpl_revisions
├── dpl_relations
├── dpl_status_histories
├── dpl_consolidations
└── dpl_consolidation_outlets

Approval
├── approval_workflows
├── approval_workflow_steps
├── approver_assignments
├── approval_instances
├── approval_step_instances
├── approval_item_states
├── approval_actions
└── approval_override_requests

Documents
├── distributor_branch_heads
├── exit_letters
└── attachments

Audit
└── activity_log (Spatie)

Reporting
├── selling_in / sales_in
├── selling_out / sales_out
└── target / sales_targets
```

Nama final tabel reporting boleh diputuskan pada fase migrasi reporting. Core DPL tidak memiliki FK ke reporting.

## 6. Organization and approval resolution

Hierarchy organisasi:

```text
ZONE
  └── REGION
       └── AREA
```

Penentuan approver:

```text
RM  -> berdasarkan region DPL/outlet
NSM -> berdasarkan zone dari region
GM  -> global/fixed assignment
SMD -> global/fixed assignment
DMD -> global/fixed assignment
```

Urutan standard approval:

```text
RM -> NSM -> GM -> SMD -> DMD
```

`users_level_order` legacy **bukan** sumber approval. Tabel tersebut hanya digunakan sebagai bagian dari visibility KAE terhadap pengajuan SPV/AM/atasannya.

Approver yang sudah di-resolve saat workflow dimulai harus di-snapshot ke `approval_step_instances.assigned_user_id`. Perubahan master approver setelah workflow berjalan tidak boleh mengubah historical assignment.

## 7. KAE visibility

Visibility dipisahkan dari approval.

KAE dapat melihat pengajuan berdasarkan:

- area yang menjadi scope-nya;
- SPV/AM yang terkait;
- relasi visibility yang berasal dari mapping legacy `users_level_order`.

Implementasi target menggunakan kombinasi:

```text
user_area_scopes
user_visibility_edges
```

Authorization backend tetap wajib; menyembunyikan tombol/menu hanya UX.

## 8. DPL lifecycle

Proposal status dan program status adalah konsep berbeda.

### Proposal status

```text
DRAFT
SUBMITTED
IN_APPROVAL
PARTIALLY_APPROVED
APPROVED
REJECTED
CANCELLED
```

### Program status

```text
INACTIVE
ACTIVE
EXPIRED
SUPERSEDED
```

Simplified flow:

```text
DRAFT
  -> SUBMITTED
  -> IN_APPROVAL
  -> PARTIALLY_APPROVED (jika hanya sebagian item selesai)
  -> APPROVED
  -> ACTIVE
  -> EXPIRED / SUPERSEDED

IN_APPROVAL
  -> REJECTED / RETURNED sesuai workflow
```

Approval bersifat per DPL item. `approval_actions` adalah immutable business event/history; current state berada pada `approval_item_states` dan step instance.

## 9. Revision, amendment, renewal

Tiga konsep harus dibedakan:

```text
REVISION
- proposal yang sama diedit
- historical snapshot disimpan
- dapat menyebabkan approval restart sesuai business rule

AMENDMENT
- legacy "Make a Copy"
- sumber harus DPL active
- periode program tetap
- digunakan untuk tambah/ubah item tertentu

RENEWAL
- legacy "Duplicate"
- membuat proposal baru untuk periode baru
- periode dapat berubah
- approval dimulai dari awal
```

Hubungan antar DPL disimpan pada `dpl_relations`, bukan melalui kombinasi flag/string seperti legacy `ref_id_dpl`, `from_dpl_action`, atau `tag_make_a_copy`.

## 10. Commercial calculation

Nilai commercial dipisahkan dari identitas dasar DPL item.

```text
DplItem
  -> DplItemTerms
       ├── principle discount on/off
       ├── distributor discount on/off
       ├── negotiated price
       ├── margin
       ├── logistic support
       ├── effective discount
       └── calculator percentage
```

Aturan:

- financial/commercial value menggunakan PostgreSQL `NUMERIC`, bukan float/double;
- threshold seperti 35% tidak di-hardcode pada schema/controller; gunakan `commercial_parameters`;
- calculated value hanya disimpan jika dibutuhkan untuk snapshot/history/performance dan strategi konsistensinya jelas;
- `dpl_logistic` legacy tidak dimigrasikan karena praktis tidak digunakan.

## 11. Product and outlet classification

Product grouping 500 ml / 250 ml / 100 ml / 25 ml ditentukan dari `products.volume`. Tidak dibuat tabel `product_groups` hanya untuk grouping volume.

Outlet legacy:

```text
GRUP1 -> outlet category (INSTANSI / RUMAH SAKIT)
GRUP2 -> ownership type (PEMERINTAH / BUKAN PEMERINTAH)
GRUP3 -> hospital/outlet group (HERMINA, SILOAM, dst.)
```

GRUP1, GRUP2, dan GRUP3 bukan hierarchy yang sama dan dinormalisasi menjadi master/relationship terpisah.

## 12. Consolidation

Consolidation/RS Group adalah parent process yang dapat memiliki banyak outlet.

```text
DPL Consolidation
  ├── Outlet A -> generated DPL A
  ├── Outlet B -> generated DPL B
  └── Outlet C -> generated DPL C
```

Setelah full approval, DPL per outlet menjadi transaksi operasional yang berlaku; parent consolidation tetap menjadi referensi history dan tidak menjadi DPL aktif per outlet.

## 13. Document / Surat Keluar

`dpl_exit_letter_master` legacy digunakan sebagai master nama Kacab. Desain baru menggunakan `distributor_branch_heads` dengan validity period.

Pada saat surat dibuat, nama/jabatan Kacab disimpan sebagai snapshot pada `exit_letters` agar histori dokumen tidak berubah ketika master Kacab berubah.

Surat keluar hanya dapat diproses dari DPL yang memenuhi status bisnis yang dipersyaratkan (legacy: DPL active).

## 14. Audit and business history

Gunakan dua mekanisme dengan tujuan berbeda:

### `activity_log`

Dipakai untuk audit teknis/data dengan `spatie/laravel-activitylog`:

- siapa;
- action;
- subject;
- perubahan attribute penting;
- timestamp;
- metadata aman seperti IP/user-agent bila perlu.

### Business history

Tetap disimpan pada tabel domain:

```text
dpl_revisions
dpl_status_histories
approval_actions
```

Jangan mengganti business history dengan activity log karena kebutuhan query dan semantics berbeda.

## 15. Reporting boundary

Legacy:

```text
selling_in
selling_out
target
```

hanya digunakan untuk reporting dan tidak masuk transaction dependency core E-DPL.

Volume data reporting yang besar harus diperlakukan sebagai workload terpisah secara logical. Fase awal boleh tetap satu PostgreSQL instance, tetapi query/reporting tidak boleh membuat submit/approval DPL bergantung pada tabel reporting.

`distributor_outlet` legacy tidak digunakan E-DPL dan tidak dimigrasikan ke core.

## 16. Web architecture

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
  -> Service (optional/required for workflow)
  -> Model / Query
  -> PostgreSQL
```

## 17. API architecture

```text
Client / jQuery AJAX
  -> routes/api.php
  -> Middleware/Auth
  -> Form Request
  -> API Controller
  -> Service
  -> Model / Query / Integration
  -> JSON Response
```

Endpoint domain action diperbolehkan untuk workflow nyata, misalnya:

```text
POST /api/dpl/{dpl}/submit
POST /api/dpl/{dpl}/approve
POST /api/dpl/{dpl}/reject
POST /api/dpl/{dpl}/request-override
POST /api/dpl/{dpl}/amend
POST /api/dpl/{dpl}/renew
```

## 18. Transaction boundary

Gunakan `DB::transaction()` untuk workflow multi-table.

Contoh submit:

```text
Submit DPL
 -> validate business rule
 -> freeze revision/snapshot bila perlu
 -> update proposal status
 -> create approval instance
 -> resolve approvers
 -> create step/item states
 -> create status history
 -> commit
```

Jangan menahan database transaction saat menunggu external HTTP request jika bisa dihindari.

## 19. Integration direction

Arah future:

```text
Service
  -> Integration Client
  -> External API
```

NetSuite/external integration tidak boleh di-hardcode ke model/controller. External IDs yang sudah menjadi bagian data legacy dapat dipertahankan, tetapi behavior integrasi harus diimplementasikan hanya setelah requirement integrasi dikonfirmasi.

## 20. Migration strategy

Rebuild dilakukan sebagai transformasi domain, bukan table copy 1:1.

```text
Legacy MySQL/MariaDB
        |
        v
Extract + validate
        |
        v
Transform / mapping
        |
        v
PostgreSQL target schema
        |
        v
Reconcile counts/business samples
        |
        v
Cutover
```

NIK menjadi identifier utama untuk mapping user legacy ke PostgreSQL user baru. Legacy numeric user ID tidak dianggap stable business identity.
