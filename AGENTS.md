# AGENTS.md

Instruksi utama repository untuk AI coding agent dan contributor.

## 1. Konteks wajib

Sebelum mengubah kode, baca:

1. `README.md`
2. `docs/PRD.md`
3. `docs/Architecture.md`
4. `docs/Rules.md`
5. `docs/Schema.md` jika menyentuh database/data
6. `docs/Design.md` jika menyentuh UI/UX
7. Source code dan test yang relevan

Jika instruksi antar-dokumen bertentangan, utamakan requirement proyek yang sudah dikonfirmasi, lalu `AGENTS.md`, kemudian dokumentasi arsitektur/aturan.

## 2. Gaya implementasi utama

Template ini mengikuti gaya Laravel yang sederhana dan pragmatis:

- Blade + Bootstrap + Sneat untuk UI web secara default.
- jQuery/AJAX untuk interaksi data pada halaman yang menggunakan pola tersebut.
- `routes/web.php` untuk halaman/view dan `routes/api.php` untuk operasi data/API.
- Web Controller bertanggung jawab atas render view/redirect.
- API Controller bertanggung jawab atas request/response HTTP JSON.
- Service digunakan untuk business logic/workflow yang non-trivial.
- Eloquent Model digunakan langsung untuk CRUD dan query sederhana.
- Repository dan Action bersifat **opsional**, bukan kewajiban.
- Jangan membuat layer hanya demi mengikuti pola.

### Pilihan layer

```text
CRUD sederhana
    Controller -> Model

CRUD + business rule
    API Controller -> Service -> Model

Workflow kompleks
    API Controller -> Service / Action -> Model / Query / Integration

Query sangat kompleks/reusable
    Service -> Repository (opsional)
```

## 3. Perilaku wajib

- Jangan mengarang business rule.
- Jangan memperluas scope tanpa requirement.
- Ikuti pola kode yang sudah ada di module terkait sebelum memperkenalkan pola baru.
- Pilih implementasi paling sederhana yang tetap benar dan mudah dirawat.
- Controller harus tipis.
- Jangan membuat `Repository`, `Action`, atau `Service` untuk CRUD sederhana tanpa alasan.
- Gunakan Form Request untuk validation request yang memiliki aturan berarti.
- Authorization selalu dilakukan di server.
- Gunakan migration untuk perubahan schema.
- Pertimbangkan data production yang sudah ada sebelum mengubah schema.
- Jangan menambahkan dependency tanpa alasan yang jelas.
- Jangan melakukan refactor unrelated.
- Tambahkan/perbarui test untuk behavior penting.
- Jangan mengubah atau menghapus test hanya agar test lulus.

## 4. Pola web + AJAX

Untuk halaman Blade yang menggunakan AJAX:

```text
routes/web.php
    -> Web/Page Controller
    -> Blade/Sneat
    -> JS/jQuery
    -> routes/api.php
    -> API Controller
    -> Form Request
    -> Service (jika perlu)
    -> Model/Query
    -> PostgreSQL
```

- Jangan menaruh AJAX besar di Blade.
- Logic halaman berada di file JS/module terkait.
- Jangan membuat API endpoint baru jika endpoint yang ada sudah cukup.
- API response harus mengikuti format proyek.

## 5. Sebelum mengedit

Tentukan:

- requirement yang dikerjakan;
- file/module yang terdampak;
- business rule;
- dampak schema/API/UI;
- test yang membuktikan perubahan.

Jika requirement penting ambigu, berhenti pada titik yang ambigu dan minta keputusan; jangan menebak.

## 6. Database safety

Jangan menganggap tabel production kosong.

Untuk migration:

- cek data existing;
- pertimbangkan backward compatibility;
- gunakan backfill untuk field baru yang wajib;
- hindari migration destruktif tanpa rollout plan;
- dokumentasikan index, foreign key, constraint;
- jangan menjalankan command production yang destruktif tanpa persetujuan eksplisit manusia.

## 7. API safety

- Validasi semua input eksternal.
- Authorization di server.
- Jangan expose secret, token, SQL, stack trace, atau internal path.
- Gunakan HTTP status code yang konsisten.
- Pertimbangkan duplicate request/idempotency untuk operasi sensitif.

## 8. Definition of done

Sebelum pekerjaan dianggap selesai:

- test relevan dijalankan;
- tidak ada perubahan unrelated;
- dokumentasi yang terdampak diperbarui;
- migration aman terhadap data existing;
- perubahan behavior penting dicatat di changelog;
- asumsi dan risiko dijelaskan.
