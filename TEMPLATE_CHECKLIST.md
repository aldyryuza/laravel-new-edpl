# Laravel Coding Template v2 - Checklist

Gunakan saat memulai project baru.

## Project

- [ ] Isi `[PROJECT_NAME]`, owner, repository, dan tanggal.
- [ ] Konfirmasi Laravel/PHP/PostgreSQL version.
- [ ] Tentukan environment.

## Requirement

- [ ] Isi `docs/PRD.md`.
- [ ] Tentukan MVP dan out-of-scope.
- [ ] Tentukan role/permission.
- [ ] Tulis business rule yang confirmed.
- [ ] Tandai rule yang masih TBD.
- [ ] Tulis acceptance criteria.

## Architecture

- [ ] Pastikan web/API flow sesuai project.
- [ ] Tentukan apakah CRUD cukup Controller -> Model.
- [ ] Tambahkan Service hanya jika business logic membutuhkannya.
- [ ] Tambahkan Action hanya jika use-case jelas membutuhkan class terpisah.
- [ ] Tambahkan Repository hanya jika query/persistence kompleks/reusable.

## Database

- [ ] Isi ERD.
- [ ] Isi table catalog.
- [ ] Tentukan relationship.
- [ ] Tentukan index berdasarkan query nyata.
- [ ] Tentukan constraint.
- [ ] Jika rebuild/migrasi, isi legacy mapping.
- [ ] Pastikan migration aman terhadap data existing.

## UI

- [ ] Konfirmasi Blade + Bootstrap + Sneat.
- [ ] Tentukan layout dan component yang akan digunakan.
- [ ] Tentukan file JS per page/module.
- [ ] Tentukan pola AJAX.
- [ ] Tentukan responsive target.

## API

- [ ] Tentukan route/resource.
- [ ] Tentukan response envelope.
- [ ] Tentukan validation.
- [ ] Tentukan authorization.
- [ ] Tentukan error handling.

## AI instruction

- [ ] Review `AGENTS.md`.
- [ ] Review `CLAUDE.md`.
- [ ] Review `.github/copilot-instructions.md`.
- [ ] Pastikan tidak ada rule generik yang bertentangan dengan coding style project.

## Before first feature

- [ ] Documentation cukup jelas untuk mulai coding.
- [ ] Tidak ada business-critical ambiguity yang belum diputuskan.
- [ ] Commit template/documentation baseline.
