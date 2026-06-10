---
title: Overview
---

# Tax Package

## Purpose

The `aiarmada/tax` package owns zone-based tax calculation, tax configuration data, and tax exemption workflows for the Commerce ecosystem.

## What this package owns

- Tax zones, tax classes, tax rates, and tax exemptions
- The default `TaxCalculatorInterface` implementation and `TaxResultData` output contract
- Tax runtime settings via `TaxSettings` and `TaxZoneSettings`
- Tax-owner scoping behavior when enabled through configuration

## What this package does not own

- Checkout or order total orchestration; consuming packages decide when tax is calculated and persisted
- Invoice or document rendering (`aiarmada/docs`)
- Filament admin UI (`aiarmada/filament-tax`)
- External SaaS tax providers such as TaxJar or Avalara, unless the host app swaps the calculator implementation

## Related packages

- [`aiarmada/filament-tax`](../../filament-tax/docs/01-overview.md) — Filament resources, widgets, and tax settings UI
- [`aiarmada/cart`](../../cart/docs/01-overview.md) — cart totals and tax entry points
- [`aiarmada/orders`](../../orders/docs/01-overview.md) — order records that store computed tax outcomes
- [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) — owner context and shared infrastructure

## Main models services or surfaces

- **Models** — `TaxZone`, `TaxClass`, `TaxRate`, `TaxExemption`
- **Contracts and DTOs** — `TaxCalculatorInterface`, `TaxRateApplierInterface`, `TaxZoneResolverInterface`, `TaxResultData`
- **Actions** — `RequestTaxExemption`, `ApproveExemptionAction`, `RejectExemptionAction`
- **Console Commands** — `RecalculateTaxRatesCommand`, `SyncTaxZonesCommand`
- **Services and settings** — `TaxCalculator`, `StandardRateApplier`, `AddressZoneResolver`, `CompositeZoneResolver`, `DefaultZoneResolver`, `ZoneIdResolver`, `TaxSettings`, `TaxZoneSettings`, `TaxOwnerScope`

## Owner scoping and security notes

- Owner enforcement is controlled by `tax.features.owner.enabled`, `tax.features.owner.include_global`, and `tax.features.owner.auto_assign_on_create`
- Missing owner context is not the same as global access; non-request surfaces should set owner context explicitly through `commerce-support`
- Packages that submit zone, class, rate, or exemption identifiers must validate them in the current owner scope instead of trusting filtered UI options

## Key Features

- **Geographic Tax Zones** - Define tax regions by country, state/province, or postcode ranges with priority-based matching
- **Multiple Tax Classes** - Categorize products with different tax treatments (standard, reduced, zero-rate, exempt)
- **Compound Taxes** - Support for stacked taxes calculated on base + previous taxes (e.g., federal + provincial)
- **Tax Exemptions** - Customer-specific exemptions with certificate uploads, approval workflows, and expiration tracking
- **Shipping Tax** - Configurable tax application to shipping costs
- **Prices Inclusive/Exclusive** - Handle both tax-inclusive and tax-exclusive pricing models
- **Owner Scoping** - Full multi-tenancy support via `commerce-support` package
- **Runtime Settings** - Configurable via Spatie Laravel Settings without code deployment
- **Activity Logging** - Automatic audit trail for all tax configuration changes

## Core Concepts

### Tax Zones

Geographic regions where specific tax rules apply. Zones support flexible matching:

| Zone Type | Example | Match Criteria |
|-----------|---------|----------------|
| Country | Malaysia | `['MY']` |
| State | California | `['CA']` or `['California']` |
| Postcode | Kuala Lumpur Area | `['50000-59999']`, `['50*']` |

Zones have a **priority** system—higher priority zones are checked first, allowing specific zones (e.g., a state) to override broader zones (e.g., a country).

### Tax Rates

Percentage-based rates linked to a zone and tax class. Rates are stored as **basis points** (integer cents of a percent) for precision:

| Rate | Basis Points | Display |
|------|-------------|---------|
| 6% | 600 | 6.00% |
| 8.25% | 825 | 8.25% |
| 0% | 0 | 0.00% |

### Tax Classes

Categories that determine which rate applies to a product:

| Class | Use Case |
|-------|----------|
| `standard` | Default rate for most products |
| `reduced` | Lower rate for essentials (food, medicine) |
| `zero` | 0% rate, still tracked for reporting |
| `exempt` | Not subject to tax at all |

### Tax Exemptions

Customer-specific exemptions that bypass normal tax calculation:

- **Polymorphic** - Link to Customer, CustomerGroup, User, or any model
- **Zone-specific or Global** - Exempt from one zone or all zones
- **Workflow** - Pending → Approved/Rejected status
- **Time-bound** - Optional start and expiration dates
- **Documentation** - Certificate number and file upload support

## Architecture

### Contract-Driven Design

The package uses a contract (`TaxCalculatorInterface`) that can be swapped with external tax services:

```php
use AIArmada\Tax\Contracts\TaxCalculatorInterface;

// Default implementation
app(TaxCalculatorInterface::class); // TaxCalculator

// Custom implementation (e.g., TaxJar, Avalara)
$this->app->singleton(TaxCalculatorInterface::class, TaxJarCalculator::class);
```

### Data Transfer Object

All calculations return a strongly-typed `TaxResultData` DTO:

```php
use AIArmada\Tax\Data\TaxResultData;

$result = Tax::calculateTax(10000, 'standard');

$result->taxAmount;        // int (cents)
$result->ratePercentage;   // int (basis points)
$result->rateName;         // string
$result->zoneName;         // string
$result->breakdown;        // array of applied rates
$result->isExempt();       // bool
```

## Package Structure

```
packages/tax/
├── config/
│   └── tax.php                      # Package configuration
├── database/
│   ├── factories/                   # Model factories for testing
│   │   ├── TaxClassFactory.php
│   │   ├── TaxExemptionFactory.php
│   │   ├── TaxRateFactory.php
│   │   └── TaxZoneFactory.php
│   ├── migrations/                  # Database schema
│   │   ├── create_tax_zones_table.php
│   │   ├── create_tax_classes_table.php
│   │   ├── create_tax_rates_table.php
│   │   └── create_tax_exemptions_table.php
│   └── settings/                    # Spatie settings migrations
│       ├── create_tax_settings.php
│       └── create_tax_zone_settings.php
├── docs/                            # This documentation
└── src/
    ├── Actions/
    │   └── Exemption/
    │       ├── RequestTaxExemption.php
    │       ├── ApproveExemptionAction.php
    │       └── RejectExemptionAction.php
    ├── Console/
    │   └── Commands/
    │       ├── RecalculateTaxRatesCommand.php
    │       └── SyncTaxZonesCommand.php
    ├── Contracts/
    │   ├── TaxCalculatorInterface.php
    │   ├── TaxRateApplierInterface.php
    │   └── TaxZoneResolverInterface.php
    ├── Data/
    │   └── TaxResultData.php        # Result DTO
    ├── Exceptions/
    │   └── TaxZoneNotFoundException.php
    ├── Facades/
    │   └── Tax.php
    ├── Models/
    │   ├── TaxClass.php
    │   ├── TaxExemption.php
    │   ├── TaxRate.php
    │   └── TaxZone.php
    ├── Services/
    │   ├── TaxCalculator.php        # Main calculation engine
    │   ├── RateApplier/
    │   │   └── StandardRateApplier.php
    │   └── ZoneResolver/
    │       ├── AddressZoneResolver.php
    │       ├── CompositeZoneResolver.php
    │       ├── DefaultZoneResolver.php
    │       └── ZoneIdResolver.php
    ├── Settings/
    │   ├── TaxSettings.php          # Runtime settings
    │   └── TaxZoneSettings.php      # Zone resolution settings
    ├── Support/
    │   └── TaxOwnerScope.php        # Multi-tenancy helper
    └── TaxServiceProvider.php
```

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.4+ |
| Laravel | 11+ |
| `aiarmada/commerce-support` | Required |
| `spatie/laravel-data` | Required (for DTOs) |
| `spatie/laravel-settings` | Optional (for runtime config) |
| `spatie/laravel-activitylog` | Optional (for audit trails) |

## Quick Example

```php
use AIArmada\Tax\Facades\Tax;

// Calculate 6% SST on RM 100.00
$result = Tax::calculateTax(
    amountInCents: 10000,
    taxClass: 'standard',
    zoneId: null,
    context: [
        'shipping_address' => [
            'country' => 'MY',
            'state' => 'Selangor',
            'postcode' => '43000',
        ],
    ]
);

echo $result->taxAmount;           // 600 (RM 6.00)
echo $result->getFormattedRate();  // "6.00%"
echo $result->zoneName;            // "Malaysia"
echo $result->getSummary();        // "SST (6.00%)"
```

## Related Packages

| Package | Description |
|---------|-------------|
| [`aiarmada/filament-tax`](../../filament-tax/docs/01-overview.md) | Filament admin panel for tax management |
| [`aiarmada/cart`](../../cart/docs/01-overview.md) | Shopping cart with tax calculation integration |
| [`aiarmada/orders`](../../orders/docs/01-overview.md) | Order management with tax recording |
| [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) | Shared utilities and multi-tenancy |

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Exemptions](05-exemptions.md)
- [Models](06-models.md)
- [Multitenancy](07-multitenancy.md)
- [Filament Tax overview](../../filament-tax/docs/01-overview.md)
