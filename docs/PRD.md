# Product Requirement Document

> Project: `E-DPL Rebuild`  
> Status: `Planning / Database Design`  
> Owner: `TBD`  
> Updated: `2026-10-06`

## 1. Project overview

| Item | Value |
|---|---|
| Project | `E-DPL Rebuild` |
| Code name | `E-DPL` |
| Owner | `TBD` |
| Repository | `laravel-new-edpl` |
| Environment | Development / Staging / Production |
| Backend | Laravel 13 / PHP 8.3+ |
| Database target | PostgreSQL |
| UI | Blade + Sneat + Bootstrap + jQuery/AJAX |

## 2. Problem

E-DPL existing telah berkembang melalui banyak perubahan dan special case sehingga struktur aplikasi/database menjadi sulit dipelihara. Rebuild bertujuan mempertahankan business capability yang masih diperlukan sambil merapikan domain, workflow approval, schema data, history, dan integrasi.

### Proses saat ini

1. SPV/AM membuat pengajuan DPL untuk outlet dan periode program.
2. Pengajuan memiliki satu atau lebih item product/distributor dengan commercial terms.
3. Item melewati approval berjenjang RM -> NSM -> GM -> SMD -> DMD.
4. DPL menjadi active setelah memenuhi approval yang diperlukan.
5. DPL dapat direvisi, diamend (legacy Make a Copy), diperpanjang (legacy Duplicate), atau dibuat dalam mode consolidation/RS Group.
6. Sistem juga memiliki special flow seperti clearance/ED dekat, threshold calculator >35%, surat keluar, dan reporting.

### Pain point

- Struktur legacy mencampur proposal, lifecycle, revision, copy/duplicate, dan consolidation dalam satu tabel utama.
- `dpl_item` legacy memuat terlalu banyak concern commercial/calculation dalam satu struktur.
- Audit/history tersebar pada beberapa tabel khusus.
- Banyak relasi legacy tidak dijaga oleh foreign key database.
- Ada tabel/logic legacy yang tidak lagi digunakan tetapi masih tersisa, misalnya `dpl_logistic`.
- Beberapa special case diimplementasikan sebagai flag/kolom tambahan dan proses manual.
- Data reporting besar bercampur dalam lingkungan data operasional.

## 3. Goal

- Membangun ulang E-DPL pada Laravel modern dan PostgreSQL.
- Membuat domain DPL, approval, commercial, consolidation, document, dan reporting lebih jelas.
- Mempertahankan business rule penting dan historical traceability.
- Membuat approval workflow configurable dan tidak bergantung pada kolom approval per jabatan.
- Menjadikan PostgreSQL `users` sebagai canonical user master baru untuk arah migrasi CRM/EBU/E-DPL.
- Menyiapkan identity model yang compatible dengan future JWT/integration.
- Memisahkan core transactional workload dari reporting secara logical.
- Menyediakan migration path dari legacy MySQL/MariaDB tanpa copy 1:1.

### Non-goal fase awal

- Rebuild CRM dan EBU secara penuh.
- Migrasi/reporting warehouse final untuk `selling_in`, `selling_out`, dan `target` pada fase core migration.
- Implementasi NetSuite/API integration yang behavior-nya belum dikonfirmasi.
- Menghidupkan kembali `dpl_logistic` legacy.
- Memigrasikan `distributor_outlet` ke core E-DPL.

## 4. User dan role

Project tetap menggunakan `users`, `roles`, `user_has_roles`, `menus`, dan `role_menu_accesses` yang sudah tersedia.

| Role bisnis | Description | Main capability |
|---|---|---|
| Administrator/IT | Administrasi aplikasi dan konfigurasi sesuai permission | Master/config/support |
| KAE | User visibility terhadap pengajuan yang berada dalam scope-nya | Read/monitor sesuai scope |
| SPV | Pengaju DPL | Create/edit/submit DPL sesuai scope |
| AM | Pengaju DPL | Create/edit/submit DPL sesuai scope |
| RM | Approver berdasarkan regional | Review/approve/reject |
| NSM | Approver berdasarkan zona | Review/approve/reject/edit sesuai workflow rejection |
| GM | Global/fixed approver | Review/approve/reject |
| SMD | Global/fixed approver | Review/approve/reject |
| DMD | Global/fixed final approver | Review/approve/reject |

Special case legacy: apabila area tidak memiliki SPV/AM, RM dapat diberi hak membuat pengajuan dan tetap mengikuti approval yang relevan. Implementasi dilakukan melalui permission/configuration, bukan hardcode role name di controller.

### Permission principle

- Authorization ditentukan server-side melalui permission system project.
- Nama role tidak boleh menjadi satu-satunya authorization check jika permission sebenarnya dapat dikonfigurasi.
- Approval assignment dan UI permission adalah dua hal berbeda.
- Visibility KAE dan approver assignment adalah dua domain berbeda.

## 5. Scope

### MVP / Core rebuild

- User/role existing PostgreSQL tetap digunakan.
- Organization master: zone, region, area.
- Master outlet, product, distributor.
- Outlet classification dan group.
- DPL proposal dan DPL item.
- Commercial terms/calculation storage model.
- Approval workflow per item.
- Approver resolution RM/NSM/global.
- DPL lifecycle/status history.
- Revision.
- Amendment / legacy Make a Copy.
- Renewal / legacy Duplicate.
- Consolidation / RS Group.
- Override request untuk calculator threshold.
- Audit menggunakan activity log + business history.

### Phase berikutnya

- Surat keluar lengkap termasuk template/signatory/file workflow.
- Legacy data migration execution dan reconciliation.
- Reporting schema/load untuk selling in/out/target.
- External integration setelah contract dikonfirmasi.
- Future JWT/API identity adoption.

### Out of scope core

- `dpl_logistic` legacy.
- `distributor_outlet` legacy.
- Direct dependency DPL transaction ke selling in/out/target.

## 6. Module

| Module | Purpose | Main actor |
|---|---|---|
| Identity & Access | User, role, permission, menu | Admin/User |
| Organization | Zone, region, area, user organization mapping | Admin/IT |
| Master Outlet | Outlet, category, ownership, hospital group | Admin/IT |
| Master Product | Product dan volume | Admin/IT |
| Master Distributor | Distributor/cabang | Admin/IT |
| Commercial | Price, bonus, margin, threshold parameter | Admin/authorized user |
| DPL Proposal | Header/lifecycle pengajuan | SPV/AM/RM special case |
| DPL Item | Product-distributor-program terms | SPV/AM |
| Approval | Approval per item | RM/NSM/GM/SMD/DMD |
| Revision | History perubahan proposal yang sama | Authorized user |
| Amendment | Tambah/ubah program aktif dengan periode sama | Original authorized proposer |
| Renewal | Buat periode baru dari DPL existing | Original authorized proposer |
| Consolidation | Pengajuan multi-outlet/RS Group | Authorized proposer |
| Override | Request open lock/exception calculator | Authorized user/approver |
| Document | Surat keluar dan attachment | Authorized PIC |
| Reporting | Recap dan analytical view | Authorized user |

## 7. Functional requirements

### FR-001 - Authentication and user identity

Sistem harus:

- menggunakan PostgreSQL `users` sebagai canonical user master E-DPL;
- menggunakan NIK sebagai unique employee/business identifier dan login identity;
- tidak menggunakan NIK sebagai primary key relational;
- menyediakan stable opaque identifier (`uuid`) untuk future API/JWT integration;
- menggunakan `users.id` sebagai FK internal;
- mempertahankan role/menu permission framework existing.

### FR-002 - Organization

Sistem harus:

- memiliki hierarchy `zone -> region -> area`;
- dapat mengaitkan user dengan organization scope;
- dapat menentukan region dan zone yang relevan untuk sebuah DPL berdasarkan outlet/area;
- menyimpan perubahan assignment secara traceable bila validity period digunakan.

### FR-003 - KAE visibility

Sistem harus:

- memisahkan visibility dari approval;
- memungkinkan KAE melihat pengajuan berdasarkan area/SPV/AM yang terkait;
- mendukung transformasi relasi legacy `users_level_order` menjadi visibility mapping;
- tidak menggunakan `users_level_order` untuk memilih approver.

### FR-004 - DPL proposal

Sistem harus:

- membuat DPL number unik;
- memilih outlet;
- menyimpan date offer, date start, date end, contract, team dan snapshot informasi yang perlu dipertahankan secara historis;
- mendukung proposal type minimal `NORMAL`, `CLEARANCE`, `CONSOLIDATION`;
- menyimpan proposal status dan program status sebagai konsep berbeda;
- menyimpan creator dengan FK `users.id`.

### FR-005 - DPL items

Sistem harus:

- mendukung banyak item dalam satu proposal;
- setiap item mereferensikan product dan distributor;
- menyimpan minimum order, bonus dan flags bisnis yang relevan;
- menyimpan commercial terms secara terstruktur;
- mendukung kombinasi product/distributor/discount yang berbeda sebagai item terpisah.

### FR-006 - Commercial calculation

Sistem harus:

- menggunakan NUMERIC/DECIMAL untuk financial/commercial value;
- dapat menyimpan principle discount on/off;
- dapat menyimpan distributor discount on/off;
- dapat menyimpan negotiated price, margin, logistic support dan effective/calculator result yang dibutuhkan;
- menggunakan configurable parameter untuk threshold bisnis;
- tidak bergantung pada `dpl_logistic` legacy.

### FR-007 - Approval workflow

Sistem harus:

- mendukung urutan standard RM -> NSM -> GM -> SMD -> DMD;
- resolve RM dari region;
- resolve NSM dari zone;
- resolve GM/SMD/DMD dari global assignment;
- snapshot assigned approver pada workflow instance;
- mendukung approval per DPL item;
- mendukung partial approval;
- menyimpan approval action sebagai history append-only secara application behavior;
- tidak menggunakan kolom `rm_approved`, `nsm_approved`, dst. pada DPL.

### FR-008 - Reject/edit/restart

Sistem harus mendukung behavior rejection/revision yang setara dengan business flow existing:

- DPL dapat diedit oleh authorized proposer selama belum final sesuai rule;
- perubahan yang memerlukan restart approval harus membuat revision/history terlebih dahulu;
- rejection dari level tertentu dapat dikembalikan ke NSM untuk revisi sesuai workflow existing;
- bila proposer mengedit setelah rejection, approval dapat restart dari RM sesuai rule;
- historical approval/action tidak boleh dihapus.

Detail exact transition matrix akan diimplementasikan/test berdasarkan workflow rule yang dikonfirmasi sebelum coding service.

### FR-009 - DPL active/inactive

Sistem harus:

- membedakan approval status dari program activation;
- DPL yang belum memenuhi approval tidak dianggap active;
- mendukung maksimal satu DPL ACTIVE per kombinasi outlet + team;
- mengizinkan Tim A dan Tim B masing-masing memiliki satu DPL active pada outlet yang sama;
- menyimpan status transition history.

### FR-010 - Amendment / Make a Copy

Sistem harus:

- hanya mengizinkan amendment dari DPL active;
- mempertahankan periode sumber;
- memungkinkan penambahan item/distributor atau perubahan item yang diizinkan;
- membuat hubungan source-target bertipe `AMENDMENT`;
- hanya memproses approval untuk perubahan yang memang perlu direview sesuai business implementation final.

### FR-011 - Renewal / Duplicate

Sistem harus:

- dapat membuat proposal baru berdasarkan DPL existing;
- mengizinkan periode baru;
- membuat hubungan source-target bertipe `RENEWAL`;
- memulai approval baru dari awal;
- dapat berasal dari DPL active maupun inactive sesuai rule legacy.

### FR-012 - Consolidation / RS Group

Sistem harus:

- mendukung satu consolidation dengan banyak outlet;
- mengaitkan outlet group bila relevan;
- menghasilkan/merelasikan DPL per outlet setelah proses yang dipersyaratkan;
- menyimpan reference consolidation ke generated DPL;
- tidak membuat parent consolidation menjadi duplicate active DPL untuk outlet.

### FR-013 - Clearance / ED dekat

Sistem harus:

- merepresentasikan clearance sebagai proposal type/business flow, bukan bergantung pada nama outlet yang mengandung string tertentu;
- mendukung informasi yang diperlukan untuk program stok ED dekat melalui item/remark/attachment sesuai desain implementasi;
- menjaga business calculation exception yang memang dikonfirmasi untuk clearance.

### FR-014 - Calculator threshold / override

Sistem harus:

- menggunakan configurable threshold; legacy threshold adalah 35%;
- dapat menandai/reject/lock pengajuan yang melewati threshold sesuai rule;
- mendukung request override/open lock dengan reason dan reviewer;
- menyimpan history override;
- tidak hardcode 35% pada database structure.

### FR-015 - Revision and audit

Sistem harus:

- menyimpan revision snapshot sebelum perubahan signifikan;
- menyimpan status history;
- menyimpan approval action history;
- menggunakan `activity_log` untuk audit teknis/data;
- tidak membuat ulang banyak tabel log legacy yang overlap.

### FR-016 - Surat keluar

Sistem harus:

- menggunakan master Kacab distributor;
- mendukung validity period Kacab;
- menyimpan snapshot nama/jabatan Kacab ke transaksi surat;
- mereferensikan DPL dan distributor;
- mendukung attachment;
- hanya memproses surat berdasarkan status bisnis DPL yang dipersyaratkan.

### FR-017 - Reporting

Sistem harus:

- menyediakan dashboard/filter status DPL dan approval;
- menyediakan monthly recap/export pada fase terkait;
- memisahkan legacy selling in/out/target dari dependency core transaction;
- tidak membuat FK dari core DPL ke reporting tables.

## 8. Business rules

| ID | Rule | Source/Owner | Status |
|---|---|---|---|
| BR-001 | Pengaju normal E-DPL adalah SPV atau AM; RM dapat diberi create access sebagai special case jika area tidak memiliki SPV/AM | Legacy E-DPL | Confirmed |
| BR-002 | Standard approval sequence adalah RM -> NSM -> GM -> SMD -> DMD | Legacy E-DPL | Confirmed |
| BR-003 | RM ditentukan berdasarkan regional | User confirmation | Confirmed |
| BR-004 | NSM ditentukan berdasarkan zona | User confirmation | Confirmed |
| BR-005 | GM/SMD/DMD menggunakan global/fixed assignment | User confirmation | Confirmed |
| BR-006 | Approval dapat berlangsung per item dan menghasilkan partial approved state | Legacy DB + manual | Confirmed |
| BR-007 | Date Start tidak boleh backdated | Legacy E-DPL | Confirmed |
| BR-008 | Maksimum masa program legacy adalah satu tahun dari Date Start | Legacy E-DPL | Confirmed |
| BR-009 | Minimal order produk per bulan 200 botol untuk memperoleh discount SAI; order di bawah itu tidak otomatis berarti data tidak boleh disimpan | Legacy E-DPL | Confirmed |
| BR-010 | Legacy calculator di atas 35% membutuhkan exception/override process | Legacy E-DPL | Confirmed |
| BR-011 | Threshold commercial harus configurable dan tidak di-hardcode di schema/controller | Rebuild decision | Confirmed |
| BR-012 | Maksimal satu DPL ACTIVE per outlet + team; Tim A dan Tim B dapat masing-masing active pada outlet yang sama | Legacy E-DPL + user confirmation | Confirmed |
| BR-013 | Make a Copy dipetakan sebagai AMENDMENT; hanya dari active DPL dan periode tidak berubah | Legacy E-DPL | Confirmed |
| BR-014 | Duplicate dipetakan sebagai RENEWAL; periode dapat berubah dan approval restart | Legacy E-DPL | Confirmed |
| BR-015 | Product grouping 500/250/100/25 ml ditentukan berdasarkan `products.volume` | User confirmation | Confirmed |
| BR-016 | Outlet GRUP1 adalah category (INSTANSI/RUMAH SAKIT) | User confirmation | Confirmed |
| BR-017 | Outlet GRUP2 adalah ownership (PEMERINTAH/BUKAN) | User confirmation | Confirmed |
| BR-018 | Outlet GRUP3 adalah hospital/outlet group yang dapat berisi beberapa outlet | User confirmation | Confirmed |
| BR-019 | `users_level_order` legacy digunakan untuk visibility KAE, bukan approval resolution | User confirmation | Confirmed |
| BR-020 | `dpl_logistic` tidak dimigrasikan karena hasil baca tidak digunakan | User confirmation | Confirmed |
| BR-021 | `distributor_outlet` tidak digunakan oleh E-DPL core | User confirmation | Confirmed |
| BR-022 | selling_in, selling_out, target adalah reporting concern | User confirmation | Confirmed |
| BR-023 | `dpl_exit_letter_master` legacy merupakan master Kacab untuk surat keluar | User confirmation | Confirmed |
| BR-024 | NIK adalah unique business identifier user dan mapping key legacy; internal FK menggunakan `users.id` | Rebuild decision | Confirmed |
| BR-025 | Future JWT menggunakan stable opaque subject (`users.uuid`); NIK boleh menjadi additional claim | Rebuild decision | Planned |
| BR-026 | Satu role + scope approval boleh memiliki lebih dari satu approver aktif (mis. 2 RM dalam satu region); cukup salah satu yang approve/reject | User confirmation | Confirmed |
| BR-027 | Nomor DPL `{YYMM}-SAI-{kode zona}-{urut 4 digit}` (mis. `2610-SAI-E-0039`); urutan per zona, mulai lagi dari 0001 setiap bulan; nomor dibuat saat draft dibuat (draft yang dihapus meninggalkan lubang); zona dari outlet -> area -> region -> zona | User confirmation | Confirmed |
| BR-029 | Tanggal penawaran otomatis hari pembuatan dan tidak bisa diubah; semua tanggal DPL tidak boleh backdated | User confirmation | Confirmed |
| BR-030 | Satu product hanya boleh satu item per distributor dalam satu DPL | User confirmation | Confirmed |
| BR-031 | HNA = harga product yang berlaku pada Date Start sesuai jenis harga item: `PRICE_BARU_2026` = `price` (default, semua product) atau `PRICE_LAMA_2025` = `regular_price` (hanya product 500 ml); HNA - Disc On = HNA × (1 − (Principle ON + Distributor ON)%); HNA+PPN - Disc On = HNA × (1 + PPN%) × (1 − (Principle ON + Distributor ON)%), keduanya dibulatkan ke rupiah (desimal >= 0,5 ke atas, < 0,5 ke bawah); PPN dari parameter `PPN_PERCENT` (11) | User confirmation | Confirmed |
| BR-035 | Minimum order wajib diisi untuk setiap item; harga nego dan konversi bonus tidak diinput | User confirmation | Confirmed |
| BR-036 | Product item DPL dipilih per group (1L, 500ml, 250ml, 100ml, 25ml dari volume; IV CAT WING, IV CAT PEN dari master Product); memilih group otomatis memilih semua product di group (tombol Pilih semua/Kosongkan), pilihan "Tampilkan semua product" menampilkan semua product tanpa terpilih untuk dicari & dipilih sendiri, satu product = satu item dengan syarat yang sama; product tanpa group tidak bisa diajukan; HNA hanya tampil di tabel item | User confirmation | Confirmed |
| BR-032 | Discount Share Principle OFF diisi -> buyer reward otomatis dicentang | User confirmation | Confirmed |
| BR-033 | Item Direct wajib logistic fee (boleh 0); Ambil Dari Pabrik -> logistic fee 0; Direct dan Ambil Dari Pabrik tidak boleh bersamaan (boleh keduanya tidak) | User confirmation | Confirmed |
| BR-034 | Margin item otomatis dari distributor, dengan margin khusus per outlet (legacy `dpl_distributor_spesial_margin_outlet`), per product (legacy `dpl_distributor_margin`), dan product alkes (IV CAT PEN, WING, Infusion) = 10 bila diajukan Tim B | User confirmation | Pending struktur legacy |
| BR-028 | Team DPL diambil dari team pada penugasan Wilayah User pembuat; bila user punya lebih dari satu team, dipilih saat membuat draft | User confirmation | Confirmed |

## 9. Workflow

### Standard DPL

```text
Draft
  -> Submit
  -> RM Review
  -> NSM Review
  -> GM Review
  -> SMD Review
  -> DMD Review
  -> Approved
  -> Active
```

Karena approval per item, satu proposal dapat berada pada kondisi partial approved selama item belum semuanya selesai.

### Amendment

```text
Active DPL
  -> Create Amendment
  -> Add/Edit allowed items
  -> Approval of affected changes
  -> Apply/activate result according to lifecycle rule
```

### Renewal

```text
Existing DPL
  -> Create Renewal
  -> Set new period
  -> Review copied items
  -> Submit
  -> Full approval from beginning
```

### Consolidation

```text
Create Consolidation
  -> Add multiple outlets
  -> Add program/items
  -> Approval
  -> Generate/link per-outlet DPL
```

## 10. Acceptance criteria

### AC-001 - Unique active DPL per outlet/team

**Given** outlet A memiliki DPL active untuk Team A  
**When** sistem mencoba mengaktifkan DPL lain untuk outlet A dan Team A  
**Then** operasi ditolak oleh business rule dan database invariant.

### AC-002 - Different team

**Given** outlet A memiliki DPL active untuk Team A  
**When** DPL Team B yang valid diaktifkan  
**Then** kedua DPL dapat active bersamaan karena team berbeda.

### AC-003 - Approver resolution

**Given** outlet berada pada Area X -> Region Y -> Zone Z  
**When** DPL disubmit  
**Then** RM di-resolve dari Region Y, NSM dari Zone Z, dan GM/SMD/DMD dari global assignments.

### AC-004 - Approver snapshot

**Given** approval instance sudah dibuat dan RM A telah ter-assign  
**When** master RM region berubah menjadi RM B  
**Then** approval instance existing tetap menyimpan RM A, sedangkan workflow baru menggunakan assignment terbaru.

### AC-008 - Multiple approver

**Given** Region Y memiliki dua RM aktif (RM A dan RM B)  
**When** DPL pada Region Y disubmit  
**Then** RM A dan RM B sama-sama menjadi kandidat step RM; aksi salah satu dari mereka menyelesaikan item/step tersebut dan pelakunya tercatat di history approval.

### AC-005 - Partial approval

**Given** DPL memiliki beberapa item  
**When** hanya sebagian item pada step tertentu approved  
**Then** sistem dapat menampilkan partial state tanpa menandai seluruh DPL selesai.

### AC-006 - Revision history

**Given** DPL yang boleh diedit sedang memiliki data sebelumnya  
**When** perubahan signifikan disimpan  
**Then** snapshot/revision dan audit yang diperlukan tersedia tanpa menghapus history approval sebelumnya.

### AC-007 - Validation

**Given** input tidak valid  
**When** request diproses  
**Then** request ditolak dan data existing tidak berubah.

## 11. Data

- Source of truth user: PostgreSQL `users`.
- Login identity: NIK.
- Internal relation key: `users.id`.
- Future integration/JWT identifier: `users.uuid`.
- Master DPL target: PostgreSQL.
- Historical legacy migration: Yes, melalui ETL/mapping terpisah.
- User mapping legacy: utamakan NIK, bukan legacy numeric ID.
- Core audit: `activity_log` + business history tables.
- Reporting source: legacy/ETL untuk selling in/out/target, dipisahkan dari core.

Detail schema ada di `Schema.md`.

## 12. Integration

| System | Purpose | Direction | Auth | Critical |
|---|---|---|---|---:|
| CRM | Future consumer/shared identity migration context | TBD | Future JWT/SSO TBD | Yes |
| EBU | Future consumer/shared identity migration context | TBD | Future JWT/SSO TBD | Yes |
| NetSuite | External IDs/integration exists in legacy context; exact E-DPL contract TBD | TBD | TBD | TBD |
| Reporting data source | selling in/out/target load | In | Internal/ETL | No for core transaction |

Jangan implementasikan integration behavior yang masih `TBD` tanpa konfirmasi.

## 13. Non-functional requirement

### Performance

- List besar menggunakan server-side pagination/Yajra DataTables.
- Hindari N+1 query.
- Index dibuat berdasarkan query dashboard, approval queue, relation, dan reporting yang nyata.
- Core DPL tidak boleh membutuhkan scan tabel reporting besar untuk submit/approval.

### Security

- Authentication wajib untuk protected resource.
- Authorization server-side.
- Validation server-side.
- Password/token/secret tidak boleh di-commit/log.
- JWT future menggunakan opaque stable subject; jangan gunakan NIK sebagai database PK.
- Audit tidak boleh menyimpan password/token.

### Reliability

- Workflow multi-table menggunakan transaction.
- Approval history tidak dihapus/ditulis ulang untuk sekadar memperbaiki current state.
- Historical document snapshot tidak berubah karena master data berubah.

## 14. Open questions

- [ ] Exact transition matrix untuk rejection dari RM/NSM/GM/SMD/DMD dan siapa yang dapat edit pada tiap state harus difinalkan sebelum approval service dibuat.
- [ ] Field clearance ED dekat yang harus menjadi structured columns vs cukup remark/attachment.
- [x] Apakah satu outlet boleh memiliki lebih dari satu active outlet group membership pada waktu yang sama. **Tidak: satu group aktif per outlet, histori tetap disimpan.**
- [ ] Exact distributor margin special-case yang masih digunakan di production.
- [ ] Exact NetSuite integration scope pada E-DPL baru.
- [ ] Password migration strategy dari legacy user bila hash legacy tidak compatible dengan Laravel hash current.
- [ ] Data cleanup hasil audit legacy untuk NIK duplicate/null sebelum cutover.

AI tidak boleh menebak jawaban open question yang mengubah business behavior.

## 15. Release

| Phase | Scope | Entry | Exit |
|---|---|---|---|
| Phase 1 - Schema Core | Organization, master, DPL, approval, identity adjustment | Docs approved | Migration review + migrate/test success |
| Phase 2 - Core Workflow | CRUD DPL, calculation, approval, revision, amendment, renewal | Schema stable | Feature tests + UAT core |
| Phase 3 - Consolidation & Documents | Consolidation, surat keluar, attachment | Core workflow stable | UAT module |
| Phase 4 - Legacy Migration | ETL user/master/DPL/history yang disepakati | Mapping/data cleanup ready | Reconciliation passed |
| Phase 5 - Reporting & Integration | Reporting data + external integration | Core production ready | Performance/integration acceptance |
