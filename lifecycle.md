# Tax Package — Lifecycle Analysis & Refactoring Blueprint

> **Date:** 2026-06-10
> **Package:** `aiarmada/tax`
> **Target PHP:** 8.4+
> **Target DB:** PostgreSQL (timestampTz)
> **Backward compat:** None — breaking allowed.

---

## 1. Executive Summary

The tax package has 4 entities (`TaxZone`, `TaxClass`, `TaxRate`, `TaxExemption`) across 4 tables. `TaxZone`, `TaxClass`, and `TaxRate` are **configuration entities** — their `is_active` / `is_default` booleans are admin toggles and do not require lifecycle state machines. `TaxExemption` is a **lifecycle entity** (pending→approved/rejected/revoked/expired) with a proper `ExemptionStatus` backed enum and lifecycle timestamps — it needs a spatie/model-states state machine and a missing `revoked_at` timestamp.

No backward compatibility is preserved. This is a greenfield rewrite of the persistence layer for `TaxExemption` only.

---

## 2. Full Inventory by Table

### 2.1 `tax_zones`

| Column | Type | Current Role | Notes |
|--------|------|-------------|-------|
| `id` | `uuid` | PK | OK |
| `owner_type` | `string` | Morph | OK |
| `owner_id` | `uuid` | Morph | OK |
| `name` | `string` | Display name | OK |
| `code` | `string` | Unique identifier | OK |
| `description` | `text` | Optional description | OK |
| `type` | `string` | Zone type (country/state/postcode) | Should be backed enum `ZoneType` |
| `countries` | JSON | Country codes | OK |
| `states` | JSON | State codes | OK |
| `postcodes` | JSON | Postcode patterns | OK |
| `priority` | `integer` | Matching priority | OK |
| `is_default` | `boolean` | Default zone marker | Designation — kept as-is |
| `is_active` | `boolean` | Active/inactive toggle | Admin config toggle — kept as-is |
| `created_at` | `timestampTz` | Created | OK |
| `updated_at` | `timestampTz` | Updated | OK |

**Indexes:** `code`, `[is_active, priority]`, `is_default`

### 2.2 `tax_classes`

| Column | Type | Current Role | Notes |
|--------|------|-------------|-------|
| `id` | `uuid` | PK | OK |
| `owner_type` | `string` | Morph | OK |
| `owner_id` | `uuid` | Morph | OK |
| `name` | `string` | Display name | OK |
| `slug` | `string` | URL-safe identifier | OK |
| `description` | `text` | Optional description | OK |
| `is_default` | `boolean` | Default class marker | Designation — kept as-is |
| `is_active` | `boolean` | Active/inactive toggle | Admin config toggle — kept as-is |
| `position` | `integer` | Sort order | OK |
| `created_at` | `timestampTz` | Created | OK |
| `updated_at` | `timestampTz` | Updated | OK |

**Indexes:** `slug`, `[is_active, position]`

### 2.3 `tax_rates`

| Column | Type | Current Role | Notes |
|--------|------|-------------|-------|
| `id` | `uuid` | PK | OK |
| `owner_type` | `string` | Morph | OK |
| `owner_id` | `uuid` | Morph | OK |
| `zone_id` | `foreignUuid` | FK to tax_zones | OK (keep as foreignUuid, no constraint) |
| `tax_class` | `string` | Class slug reference | OK |
| `name` | `string` | Display name | OK |
| `description` | `text` | Optional description | OK |
| `rate` | `unsignedInteger` | Basis points (600 = 6.00%) | OK |
| `is_compound` | `boolean` | Compound after other taxes | Rate config — kept as-is |
| `is_shipping` | `boolean` | Applies to shipping | Rate config — kept as-is |
| `priority` | `integer` | Compound ordering | OK |
| `is_active` | `boolean` | Active/inactive toggle | Admin config toggle — kept as-is |
| `created_at` | `timestampTz` | Created | OK |
| `updated_at` | `timestampTz` | Updated | OK |

**Indexes:** `[zone_id, tax_class, is_active]`, `[is_active, priority]`

### 2.4 `tax_exemptions`

| Column | Type | Current Role | Problem |
|--------|------|-------------|---------|
| `id` | `uuid` | PK | OK |
| `owner_type` | `string` | Morph | OK |
| `owner_id` | `uuid` | Morph | OK |
| `exemptable_type` | `string` | Morph target type | OK |
| `exemptable_id` | `uuid` | Morph target ID | OK |
| `tax_zone_id` | `foreignUuid` | Optional zone scope | OK (keep as foreignUuid, no constraint) |
| `reason` | `string` | Reason for exemption | OK |
| `certificate_number` | `string` | Certificate reference | OK |
| `document_path` | `string` | Supporting document | OK |
| `status` | `string` | Status (pending/approved/rejected) | Backed enum exists; needs state machine + extended enum values |
| `rejection_reason` | `text` | Reason if rejected | OK |
| `verified_at` | `timestampTz` | When verified | OK |
| `verified_by` | `uuid` | Who verified | OK |
| `starts_at` | `timestampTz` | Validity start | OK |
| `expires_at` | `timestampTz` | Validity end | OK |
| | | | **MISSING** → `revoked_at` (manual early termination) |
| `created_at` | `timestampTz` | Created | OK |
| `updated_at` | `timestampTz` | Updated | OK |

**Indexes:** `[exemptable_type, exemptable_id, status]`, `[status, expires_at]`, `[tax_zone_id]`

---

## 3. Problems Summary

### P1 — `status` stored as raw `string` on `tax_exemptions` with no state machine

The migration column is `$table->string('status')`. The model casts it to `ExemptionStatus::class`, but the DB layer has no type safety and there are no spatie/model-states transitions — status is set directly via `ApproveExemptionAction`/`RejectExemptionAction`. The `src/States/` directory exists but has zero files.

**Fix:** Implement spatie/model-states for `TaxExemption` (6 states with guarded transitions). Wire up `StateConfig` with allowed transitions. Replace direct status setting with state machine transitions.

### P2 — `ExemptionStatus` enum is incomplete

Current values: `Pending`, `Approved`, `Rejected`. Missing:
- `UnderReview` — document verification in progress
- `Expired` — past `expires_at` (currently inferred by `isExpired()` helper)
- `Revoked` — manually terminated before expiry

**Fix:** Extend enum with `UnderReview`, `Expired`, `Revoked` cases.

### P3 — Missing `revoked_at` timestamp on `tax_exemptions`

`TaxExemption` has `verified_at`, `starts_at`, `expires_at` but no `revoked_at` for manual early termination.

**Fix:** Add `revoked_at timestampTz` nullable.

### P4 — No `ZoneType` enum

The `type` column on `tax_zones` defaults to `'country'` as a raw string. No backed enum exists in `src/Enums/`.

**Fix:** Add `ZoneType` enum (Country, State, Postcode).

---

## 4. Recommended Structure

### 4.1 Configuration entities (no lifecycle changes)

`TaxZone`, `TaxClass`, and `TaxRate` keep their `is_active` booleans as admin config toggles. `is_default`, `is_compound`, `is_shipping` remain as designations/config flags. These entities do not need `status` columns, state machines, or lifecycle timestamps.

### 4.2 TaxExemption — State Machine + Timestamps

**New enum values** (extend `ExemptionStatus`):
```
Pending → UnderReview → Approved/Rejected
                        ↓
                    Expired (past expires_at)
                    Revoked (manual termination)
```

**State classes** in `src/States/TaxExemptionState/`:

```
src/States/TaxExemptionState/
├── TaxExemptionState.php      (abstract base)
├── PendingState.php
├── UnderReviewState.php
├── ApprovedState.php
├── RejectedState.php
├── ExpiredState.php
└── RevokedState.php
```

Transitions:
```
Pending      → UnderReview, Rejected, Revoked
UnderReview  → Approved, Rejected, Revoked
Approved     → Expired, Revoked
Rejected     → [terminal]
Expired      → [terminal]
Revoked      → [terminal]
```

### 4.3 New Migration Shape — `tax_exemptions` only

```php
// ADD:
$table->timestampTz('revoked_at')->nullable();

// `status` remains but enum values expand (see §4.2)
// `verified_at`, `starts_at`, `expires_at` kept as-is
```

### 4.4 New Enums

```php
// src/Enums/ZoneType.php
enum ZoneType: string
{
    case Country = 'country';
    case State = 'state';
    case Postcode = 'postcode';
}
```

`ExemptionStatus` (existing, extended):
```php
enum ExemptionStatus: string
{
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
```

### 4.5 TaxExemption Model Changes

- Implements `HasStates` from spatie/laravel-model-states
- Adds `$status` cast to `ExemptionStatus`
- Adds `revoked_at` cast as `immutable_datetime`
- Replaces direct status setting with state machine transitions
- Adds `revoke()`, `markUnderReview()`, `expire()` transition methods via state machine
- Status observer auto-sets `verified_at`, `revoked_at` on transition

---

## 5. Refactoring Plan — Parallel-Agent Checklist

Each item is independently grabbable. Agents should claim an item and update status.

Status legend: `[pending]` `[in-progress]` `[done]` `[blocked]`

### Phase 1 — Enums & Foundation (parallel)

- [x] **1.1** Add `src/Enums/ZoneType.php` (Country, State, Postcode)
- [x] **1.2** Extend `src/Enums/ExemptionStatus.php` — add UnderReview, Expired, Revoked

### Phase 2 — State Machine (single agent)

- [x] **2.1** Implement `src/States/TaxExemptionState/` — abstract base + PendingState, UnderReviewState, ApprovedState, RejectedState, ExpiredState, RevokedState

### Phase 3 — Migrations

- [x] **3.1** New migration `2001_03_01_000005_add_revoked_at_to_tax_exemptions.php`

### Phase 4 — Models

- [x] **4.1** Update `TaxExemption` — HasStates, extended status, add revoke/expire/markUnderReview, status observer for auto-timestamps
- [x] **4.2** Update `TaxZone` — add `ZoneType` enum cast for `type` column

### Phase 5 — Actions & Downstream Sync

- [x] **5.1** Update `ApproveExemptionAction` / `RejectExemptionAction` — wire state machine transitions instead of direct status setting
- [x] **5.2** Update `TaxCalculator` — ensure `scopeActive()` references unchanged `is_active` booleans on zones/classes/rates
- [x] **5.3** Update Filament resources in `packages/filament-tax` (if applicable)

### Phase 6 — Factories & Tests (parallel)

- [x] **6.1** Update `TaxExemptionFactory` — extended status values
- [x] **6.2** Write state machine transition tests for TaxExemption
- [x] **6.3** Write cross-tenant regression tests

### Phase 7 — Cleanup & Verification (sequential)

- [x] **7.1** Run Pint on changed files
- [x] **7.2** Run PHPStan on `packages/tax/src --level=6`
- [x] **7.3** Run Pest with `--parallel` on `tests/src/Tax/`
- [x] **7.4** Verify DB schema: `php artisan schema:dump` or equivalent

---

## 6. Migration Strategy

### 6.1 Tax exemptions — add `revoked_at`

```sql
ALTER TABLE tax_exemptions ADD COLUMN revoked_at TIMESTAMPTZ;
```

Existing rows keep their current status values (pending/approved/rejected). New status values (under_review, expired, revoked) are only for new/explicit transitions.

### 6.2 No changes to zones/classes/rates

`is_active` booleans, `is_default`, and indexes remain unchanged. Configuration entities do not need lifecycle migrations.

### 6.3 No rollback

Per project guidelines (`down()` not required), migrations are append-only. Create new migrations; do not edit existing ones.

---

## 7. Verification Commands

```bash
# 1. Format (Pint) — only changed package
./vendor/bin/pint packages/tax/src packages/tax/database packages/tax/config

# 2. Static analysis — package scope, level 6
./vendor/bin/phpstan analyse packages/tax/src --level=6

# 3. Run tax tests with parallelism
./vendor/bin/pest --parallel tests/src/Tax/

# 4. Cross-tenant regression
./vendor/bin/pest --parallel tests/src/Tax/ --filter=tenant

# 5. Verify migration runs cleanly
php artisan migrate:fresh --path=packages/tax/database/migrations

# 6. Audit — state classes exist for TaxExemption
ls packages/tax/src/States/TaxExemptionState/  | wc -l
# Expected: 7 state class files (6 concrete + 1 abstract base)

# 7. Audit — ExemptionStatus has 6 cases
rg -n "case " packages/tax/src/Enums/ExemptionStatus.php | wc -l
# Expected: 6
```
