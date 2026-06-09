## Second pass — 2026-06-09

### Confirmed [done]

| Item | Status | Evidence |
|------|--------|----------|
| Phase 1 — RequestTaxExemption | ✅ Done | `Actions/Exemption/RequestTaxExemption` exists, grouped with `ApproveExemptionAction`, `RejectExemptionAction` |
| Phase 2 — TaxZoneResolverInterface | ✅ Done | `Contracts/TaxZoneResolverInterface` exists with 4 implementations: `DefaultZoneResolver`, `AddressZoneResolver`, `ZoneIdResolver`, `CompositeZoneResolver` under `Services/ZoneResolver/` |
| Phase 2 — TaxRateApplierInterface | ✅ Done | `Contracts/TaxRateApplierInterface` exists with `StandardRateApplier` implementation under `Services/RateApplier/` |
| Phase 3 — MoneyNormalizer | ✅ Done | `TaxCalculator::calculateTax()` calls `MoneyNormalizer::toCents()` |

### Still open / issues

| Item | Status | Detail |
|------|--------|----------|
| TaxCalculator not using resolver interface | ⚠️ **Major gap** | `TaxCalculator::resolveZone()` (lines 90–141) has fully inline zone resolution logic — it does NOT inject or use `TaxZoneResolverInterface`. The interface and implementations exist but the calculator's `calculateTax()` calls `$this->resolveZone()` not a resolver. The contract is dead code from the calculator's perspective. |
| TaxCalculator not using rate applier interface | ⚠️ **Major gap** | `TaxCalculator::calculateWithRates()` (lines 173–226) has inline rate application, compound/non-compound logic, and rounding — it does NOT inject or use `TaxRateApplierInterface`. Same dead-code problem. |
| Finding #4 — No Console/Commands | 🔴 Still open | No `src/Console/Commands` directory. Rate updates, zone migrations, exemption expirations have no home. |
| Finding #6 — Contracts for zone/rate primitives | ✅ Resolved | Now 3 contracts exist (Calculator, ZoneResolver, RateApplier). |
| Exemption workflow missing state machine | 🔴 Still open | Only Request/Approve/Reject exist. No auto-approval, time-limited, or document-required exemption flows. |

### New findings

| Finding | Detail |
|---------|--------|
| Phase 3 step "TaxResultData carries Money object" not verified | Quick read of `TaxResultData` constructor shows `int $taxAmount` + `string $currency` — likely NOT a `Money` DTO but separate fields. Verify. |
| Two copies of exemption Actions | `Actions/ApproveExemptionAction.php` + `Actions/RejectExemptionAction.php` exist at root AND in `Actions/Exemption/`. These are duplicate/wrapper files — the migration left the old files in place. |
| `ResolveBasePrice` in pricing has a similar concern | Cross-package note: both pricing and tax have "resolver interfaces exist but calculator still has inline logic" — a systemic pattern. |

### Updated recommendation

1. **Wire resolvers into TaxCalculator** — inject `TaxZoneResolverInterface` and `TaxRateApplierInterface` and delegate. This is the highest-priority gap.
2. **Remove stale exemption Action files** at root level (`Actions/ApproveExemptionAction.php`, `Actions/RejectExemptionAction.php`) — keep only the `Actions/Exemption/` versions.
3. **Verify TaxResultData** carries a proper Money object (Phase 3 step 2).
4. **Add Console/Commands** for batch rate/zone operations.

---

# Tax friendliness review

This note reviews `packages/tax` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Services`
- `src/Actions`
- `src/Contracts`
- `src/Models`
- `src/Events`
- `src/Enums`
- `src/Settings`
- `src/Facades`
- downstream consumers in `cart`, `checkout`, `pricing`

## What is already friendly

### Real tax calculator contract

- `Services/TaxCalculator.php` (impl `Contracts/TaxCalculatorInterface.php`)
- `Facades/Tax.php`

The contract + facade shape is the right boundary. Callers depend on the contract, not the concrete service.

### Exemption workflow is in Actions

- `Actions/ApproveExemptionAction.php`
- `Actions/RejectExemptionAction.php`

This is the right pattern. Approval and rejection are explicit Actions, not methods on the calculator.

### Money result is a DTO

- `Data/TaxResultData.php`

Tax calculation result is typed. Callers can rely on its shape.

### Domain events are explicit

- `Events/TaxCalculated.php`
- `Events/TaxZoneResolved.php`
- `Events/TaxExemptionApplied.php`

These give cart, checkout, and analytics a stable event surface.

## Findings

### 1. Only two Actions, but exemption is a multi-step workflow

**Files**

- `Actions/ApproveExemptionAction.php`
- `Actions/RejectExemptionAction.php`
- `Events/TaxExemptionApplied.php`
- `Enums/ExemptionStatus.php`

**Why this hurts friendliness**

Approve and reject exist, but the full exemption workflow (request, validate, approve, reject, expire) is split across Actions, events, and the calculator. New exemption flows (auto-approval, time-limited exemptions, document-required exemptions) will need new Actions, but the pattern for "where does a new step live" is not clear.

**Recommendation**

Group exemption-related Actions in a subfolder and add a thin `Actions/RequestTaxExemption` for the user-facing path. The `ExemptionStatus` enum + a small state machine would make the workflow explicit.

### 2. Tax zone resolution is an event but not a clear strategy

**Files**

- `Events/TaxZoneResolved.php`
- `Models/TaxZone.php`
- `Models/TaxClass.php`
- `Models/TaxRate.php`

**Why this hurts friendliness**

Zone resolution (which zone applies for a given address, customer, or product) is a variant-heavy area. Different jurisdictions, B2B vs B2C, digital vs physical goods will all need different resolution rules. The `TaxZoneResolved` event exists, but the resolver is unclear.

**Recommendation**

Add a `TaxZoneResolverInterface` and one strategy per resolution rule. The calculator asks the resolver for the applicable zone(s), then applies rates. Built-in strategies: address-based, customer-based, product-class-based.

### 3. Tax rate application is likely embedded in the calculator

**Files**

- `src/Services/TaxCalculator.php`
- `src/Models/TaxRate.php`

**Why this hurts friendliness**

Rate application (rate × base × adjustments, then rounding, then per-line vs per-invoice mode) is a variant-heavy area. Different rounding rules, compound taxes, and inclusive vs exclusive tax will all want their own implementation.

**Recommendation**

Extract a `TaxRateApplierInterface` and one applier per rate type. The calculator coordinates resolvers and appliers.

### 4. No `Console/Commands` despite bulk operations being likely

**Files**

- (none)

**Why this hurts friendliness**

Bulk operations (rate updates, zone migrations, exemption expirations) currently have no clear owner. As the package grows, the need for these operations will too.

**Recommendation**

Add a `src/Console/Commands` directory when the first batch operation is needed. Wire it through a `Console` registrar.

### 5. Settings are split but the calculator owns the join

**Files**

- `Settings/TaxSettings.php`
- `Settings/TaxZoneSettings.php`

**Why this hurts friendliness**

Like pricing, settings are split per concern, but the calculator still owns the join. Changing zone behavior requires editing the calculator even though zone config is in its own file.

**Recommendation**

Use the same shape as pricing: move zone resolution out of the calculator into a `Support/ZoneResolverRegistrar` or similar.

### 6. No `Contracts/` for zone or rate primitives

**Files**

- `Contracts/TaxCalculatorInterface.php` (only)

**Why this hurts friendliness**

Only the calculator is contracted. Zones, rates, and exemptions are not. External packages that want to contribute new zone rules or new rate types have no entry point.

**Recommendation**

Add contracts for the variant-heavy primitives:

- `Contracts/TaxZoneResolverInterface`
- `Contracts/TaxRateApplierInterface`
- `Contracts/TaxExemptionPolicyInterface`

## Concrete refactor plan

### Phase 1 — group exemption Actions and add the missing entry point

**Steps**

1. Add `Actions/RequestTaxExemption`.
2. Group exemption Actions in `Actions/Exemption/` for clarity.
3. Update callers.

### Phase 2 — extract zone resolution and rate application strategies

**Steps**

1. Add `Contracts/TaxZoneResolverInterface` and built-in strategies.
2. Add `Contracts/TaxRateApplierInterface` and built-in appliers.
3. Make the calculator delegate to resolvers and appliers.

### Phase 3 — adopt `commerce-support` money primitives

**Steps**

1. Use `MoneyNormalizer` and `MoneyFormatter` from foundation.
2. Make `TaxResultData` carry a `Money` object.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — group exemption Actions and add the missing entry point

- [done] Add `Actions/RequestTaxExemption`.
- [done] Group exemption Actions in `Actions/Exemption/` for clarity.
- [done] Update callers.

### Phase 2 — extract zone resolution and rate application strategies

- [done] Add `Contracts/TaxZoneResolverInterface` and built-in strategies.
- [done] Add `Contracts/TaxRateApplierInterface` and built-in appliers.
- [done] Make the calculator delegate to resolvers and appliers. — `TaxCalculator` now injects `TaxZoneResolverInterface` (via `CompositeZoneResolver`) and `TaxRateApplierInterface` (via `StandardRateApplier`).

### Phase 3 — adopt `commerce-support` money primitives

- [done] Use `MoneyNormalizer` and `MoneyFormatter` from foundation.
- [done] Make `TaxResultData` carry a `Money` object. — Verified: `TaxResultData::getMoney()` returns `Money::{$currency}($this->taxAmount)`. The DTO carries `int $taxAmount` + `string $currency` as separate fields (standard minor-units pattern), with a `getMoney()` helper to construct the Money object.

### Phase 4 — wire resolvers and appliers into TaxCalculator

- [done] Inject `TaxZoneResolverInterface` into `TaxCalculator` and delegate `resolveZone()` to it.
- [done] Inject `TaxRateApplierInterface` into `TaxCalculator` and delegate `calculateWithRates()` to it.
- [done] Verify `TaxResultData` carries a proper `Money` object — confirmed via `getMoney()` method.

### Phase 5 — clean stale files and add console commands

- [done] Remove stale exemption Action files at root level (`Actions/ApproveExemptionAction.php`, `Actions/RejectExemptionAction.php`) — removed; `Actions/Exemption/` versions remain.
- [done] Add `src/Console/Commands` directory for batch operations (rate updates, zone migrations, exemption expirations).
- [deferred] Add exemption state machine (auto-approval, time-limited, document-required flows).
    **Reason:** No state machine infrastructure exists in `packages/tax`. Adding this requires designing and implementing a new state machine with spatie/laravel-model-states, adding new statuses to `ExemptionStatus` enum, and wiring auto-approval/time-limited/document-required flows. Significant feature work beyond remaining cleanup scope. — Deferred: needs spatie/laravel-model-states setup and new state classes



## Suggested verification scope

- `tests/src/Tax/Unit/TaxCalculatorTest.php`
- `tests/src/Tax/Unit/ApproveExemptionActionTest.php`
- new tests for the zone resolver and rate applier strategies
- cross-package tests for cart/checkout after refactor

## Recommended first move

Phase 2 — extract zone resolution and rate application strategies. These are the two variant-heavy areas most likely to grow as the package supports more jurisdictions and product types.
