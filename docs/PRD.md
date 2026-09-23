# Product Requirement Document

> Project: `[PROJECT_NAME]`  
> Status: `Planning`  
> Owner: `[OWNER]`  
> Updated: `[YYYY-MM-DD]`

## 1. Project overview

| Item | Value |
|---|---|
| Project | `[PROJECT_NAME]` |
| Code name | `[CODE_NAME]` |
| Owner | `[OWNER]` |
| Repository | `[REPOSITORY_URL]` |
| Environment | Development / Staging / Production |

## 2. Problem

### Masalah

`[Masalah konkret yang ingin diselesaikan.]`

### Proses saat ini

1. `[STEP_1]`
2. `[STEP_2]`
3. `[STEP_3]`

### Pain point

- `[PAIN_POINT]`
- `[PAIN_POINT]`

## 3. Goal

- `[GOAL_1]`
- `[GOAL_2]`
- `[GOAL_3]`

### Non-goal

- `[NON_GOAL_1]`
- `[NON_GOAL_2]`

## 4. User dan role

| Role | Description | Main capability |
|---|---|---|
| Admin | `[DESCRIPTION]` | `[CAPABILITY]` |
| User | `[DESCRIPTION]` | `[CAPABILITY]` |
| Approver | `[DESCRIPTION]` | `[CAPABILITY]` |

### Permission

| Capability | Admin | User | Approver |
|---|---:|---:|---:|
| View | Yes | Yes | Yes |
| Create | Yes | Yes | No |
| Update | Yes | Own/Rule | No |
| Delete | Yes | Rule | No |
| Approve | Yes | No | Yes |
| Export | Rule | Rule | Rule |

Jangan menentukan permission hanya berdasarkan nama role. Tulis aturan sebenarnya.

## 5. Scope

### MVP

- `[FEATURE_1]`
- `[FEATURE_2]`
- `[FEATURE_3]`

### Out of scope

- `[FEATURE]`
- `[FEATURE]`

### Future scope

- `[FEATURE]`

## 6. Module

| Module | Purpose | Main actor |
|---|---|---|
| `[MODULE]` | `[PURPOSE]` | `[ROLE]` |

## 7. Functional requirements

Gunakan ID stabil.

### FR-001 - `[MODULE/FUNCTION]`

Sistem harus:

- `[REQUIREMENT]`;
- `[REQUIREMENT]`;
- `[REQUIREMENT]`.

### FR-002 - `[WORKFLOW]`

Sistem harus:

- `[REQUIREMENT]`;
- `[REQUIREMENT]`.

## 8. Business rules

Business rule harus eksplisit. Jika belum diketahui, gunakan `TBD`.

| ID | Rule | Source/Owner | Status |
|---|---|---|---|
| BR-001 | `[RULE]` | `[OWNER]` | Confirmed/TBD |
| BR-002 | `[RULE]` | `[OWNER]` | Confirmed/TBD |

## 9. Workflow

```text
Draft
  -> Submit
  -> Review
  -> Approved / Rejected
  -> Completed
```

Ganti dengan workflow sebenarnya.

## 10. Acceptance criteria

### AC-001 - `[FEATURE]`

**Given** `[INITIAL_STATE]`  
**When** `[ACTION]`  
**Then** `[EXPECTED_RESULT]`

### AC-002 - Validation

**Given** input tidak valid  
**When** request diproses  
**Then** request ditolak dan data existing tidak berubah.

## 11. Data

- Source of truth: `[SYSTEM/TABLE]`
- Historical data: `[YES/NO + DETAIL]`
- Import/migration: `[YES/NO]`
- Sensitive data: `[DETAIL/NONE]`
- Retention: `[POLICY]`

Detail schema ada di `Schema.md`.

## 12. Integration

| System | Purpose | Direction | Auth | Critical |
|---|---|---|---|---:|
| `[SYSTEM]` | `[PURPOSE]` | In/Out | `[METHOD]` | Yes/No |

## 13. Non-functional requirement

### Performance

- API normal target: `[TARGET]`
- List besar menggunakan pagination.
- Hindari N+1 query.

### Security

- Authentication untuk resource protected.
- Authorization server-side.
- Validation server-side.
- Secret tidak di-commit/log.

## 14. Open questions

- [ ] `[QUESTION]`
- [ ] `[QUESTION]`

AI tidak boleh menebak jawaban yang memengaruhi business behavior.

## 15. Release

| Phase | Scope | Entry | Exit |
|---|---|---|---|
| MVP | `[SCOPE]` | `[ENTRY]` | `[EXIT]` |
| Phase 2 | `[SCOPE]` | `[ENTRY]` | `[EXIT]` |
