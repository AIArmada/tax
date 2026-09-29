---
title: Tax Context
package: tax
status: current
surface: domain
family: catalog-and-identity
keywords:
  - tax
  - zone
  - rate
  - exemption
  - calculation
---

# Tax Context

## Snapshot
- Composer: `aiarmada/tax`
- Role: Zone-based tax calculation + exemption request/approve workflow.
- Triggers: tax, zone, rate, exemption, calculation
- Search first: `src/Models, src/Actions, src/Services, config, docs`
- Related: `filament-tax`, `checkout`, `orders`
- Paired: `filament-tax` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-tax/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-tax`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Tax rules or exemptions.
- Skip when: Admin UI — see filament-tax.
- Owner/security: Owner-scoped (all 4).

## Key surfaces
- Models: `TaxClass`, `TaxExemption`, `TaxRate`, `TaxZone`
- Actions/Services: `Actions/Exemption/ApproveExemptionAction`, `Actions/Exemption/RejectExemptionAction`, `Actions/Exemption/RequestTaxExemption`, `Services/RateApplier/StandardRateApplier`, `Services/TaxCalculator`, `Services/ZoneResolver/AddressZoneResolver`, `Services/ZoneResolver/CompositeZoneResolver`, `Services/ZoneResolver/DefaultZoneResolver`
- Config `tax.php`: `database`, `json_column_type`, `tables`, `tax_zones`, `tax_rates`, `tax_classes`, `tax_exemptions`, `defaults`, `currency`, `prices_include_tax`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-exemptions.md`, `06-models.md`, `07-multitenancy.md`
