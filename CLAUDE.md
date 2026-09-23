# CLAUDE.md

Gunakan `AGENTS.md` sebagai instruksi utama repository.

## Context wajib

Sebelum coding, baca:

1. `README.md`
2. `docs/PRD.md`
3. `docs/Architecture.md`
4. `docs/Rules.md`
5. `docs/Schema.md` jika menyentuh database
6. `docs/Design.md` jika menyentuh UI

## Gaya coding

Ikuti pola Laravel yang sudah digunakan project.

Default:

```text
Blade + Sneat + Bootstrap
        |
     jQuery/AJAX
        |
   routes/api.php
        |
   API Controller
        |
     Service       <- hanya jika perlu
        |
       Model
```

- CRUD sederhana boleh langsung Controller -> Model.
- Service untuk business logic/workflow non-trivial.
- Action dan Repository opsional.
- Jangan membuat abstraction hanya demi pattern.
- Jangan memindahkan logic ke layer lain tanpa alasan yang jelas.

## Perilaku

- Jangan mengarang business rule.
- Jangan memperluas scope.
- Jangan melakukan unrelated refactor.
- Pertahankan kontrak API yang sudah ada kecuali requirement mengubahnya.
- Gunakan migration untuk perubahan database.
- Tambahkan test untuk behavior penting.
- Perbarui dokumentasi yang terdampak.

## Keamanan

Jangan menjalankan operasi database, filesystem, deployment, atau production yang destruktif tanpa persetujuan manusia secara eksplisit.
