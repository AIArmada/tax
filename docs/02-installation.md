---
title: Installation
---

# Installation

## Requirements

Before installing, ensure you have:

- PHP 8.4 or higher
- Laravel 11 or higher
- Composer

## Install via Composer

```bash
composer require aiarmada/tax
```

The package auto-registers via Laravel's package discovery.

## Publish Configuration

```bash
php artisan vendor:publish --tag=tax-config
```

This publishes `config/tax.php` with all available options.

## Run Migrations

```bash
php artisan migrate
```

This creates the following tables:

| Table | Description |
|-------|-------------|
| `tax_zones` | Geographic tax regions |
| `tax_classes` | Product tax categories |
| `tax_rates` | Tax percentages per zone/class |
| `tax_exemptions` | Customer-specific exemptions |

## Publish Settings Migrations (Optional)

If you want runtime-configurable settings via Spatie Laravel Settings:

```bash
php artisan vendor:publish --tag=tax-settings
php artisan migrate
```

This creates settings tables for:
- `TaxSettings` - General tax configuration
- `TaxZoneSettings` - Zone resolution settings

## Manual Service Provider Registration

If auto-discovery is disabled:

```php
// config/app.php
'providers' => [
    // ...
    AIArmada\Tax\TaxServiceProvider::class,
],
```

## Verify Installation

```bash
php artisan tinker
```

```php
>>> app('tax')
=> AIArmada\Tax\Services\TaxCalculator {#...}

>>> app(\AIArmada\Tax\Contracts\TaxCalculatorInterface::class)
=> AIArmada\Tax\Services\TaxCalculator {#...}
```

## Initial Data Setup

After installation, set up your tax configuration:

### 1. Create Tax Classes

```php
use AIArmada\Tax\Models\TaxClass;

// Standard rate (default)
TaxClass::create([
    'name' => 'Standard',
    'slug' => 'standard',
    'description' => 'Standard tax rate for most products',
    'is_default' => true,
    'is_active' => true,
    'position' => 0,
]);

// Reduced rate
TaxClass::create([
    'name' => 'Reduced',
    'slug' => 'reduced',
    'description' => 'Reduced rate for essential goods',
    'is_default' => false,
    'is_active' => true,
    'position' => 1,
]);

// Zero rate
TaxClass::create([
    'name' => 'Zero Rate',
    'slug' => 'zero',
    'description' => 'Zero-rated items (tracked for reporting)',
    'is_default' => false,
    'is_active' => true,
    'position' => 2,
]);

// Exempt
TaxClass::create([
    'name' => 'Exempt',
    'slug' => 'exempt',
    'description' => 'Tax-exempt items',
    'is_default' => false,
    'is_active' => true,
    'position' => 3,
]);
```

### 2. Create Tax Zones

```php
use AIArmada\Tax\Models\TaxZone;

// Malaysia (country-level)
$malaysia = TaxZone::create([
    'name' => 'Malaysia',
    'code' => 'MY',
    'description' => 'Malaysia Sales and Service Tax',
    'type' => 'country',
    'countries' => ['MY'],
    'priority' => 0,
    'is_default' => true,
    'is_active' => true,
]);

// Singapore
$singapore = TaxZone::create([
    'name' => 'Singapore',
    'code' => 'SG',
    'description' => 'Singapore GST',
    'type' => 'country',
    'countries' => ['SG'],
    'priority' => 0,
    'is_default' => false,
    'is_active' => true,
]);

// State-specific (higher priority)
$selangor = TaxZone::create([
    'name' => 'Selangor',
    'code' => 'MY-SEL',
    'description' => 'Selangor state',
    'type' => 'state',
    'countries' => ['MY'],
    'states' => ['Selangor', 'SEL'],
    'priority' => 10, // Higher = checked first
    'is_default' => false,
    'is_active' => true,
]);
```

### 3. Create Tax Rates

```php
use AIArmada\Tax\Models\TaxRate;

// Malaysia SST 6%
TaxRate::create([
    'zone_id' => $malaysia->id,
    'name' => 'SST',
    'tax_class' => 'standard',
    'rate' => 600, // 6.00% in basis points
    'is_compound' => false,
    'is_shipping' => true,
    'priority' => 0,
    'is_active' => true,
]);

// Malaysia reduced rate 0%
TaxRate::create([
    'zone_id' => $malaysia->id,
    'name' => 'Zero Rate',
    'tax_class' => 'reduced',
    'rate' => 0,
    'is_compound' => false,
    'is_shipping' => false,
    'priority' => 0,
    'is_active' => true,
]);

// Singapore GST 9%
TaxRate::create([
    'zone_id' => $singapore->id,
    'name' => 'GST',
    'tax_class' => 'standard',
    'rate' => 900, // 9.00%
    'is_compound' => false,
    'is_shipping' => true,
    'priority' => 0,
    'is_active' => true,
]);
```

## Install Filament Admin (Optional)

For a visual admin interface:

```bash
composer require aiarmada/filament-tax
```

Then register the plugin in your Filament panel:

```php
use AIArmada\FilamentTax\FilamentTaxPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentTaxPlugin::make(),
        ]);
}
```

See the [Filament Tax documentation](../../filament-tax/docs/01-overview.md) for details.

## Using Factories (Testing)

For testing, use the provided factories:

```php
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Models\TaxRate;

// Create a zone with a rate
$zone = TaxZone::factory()
    ->forMalaysia()
    ->default()
    ->create();

$rate = TaxRate::factory()
    ->forZone($zone)
    ->sst() // 6% SST
    ->create();

// Or create with specific values
$rate = TaxRate::factory()
    ->forZone($zone)
    ->withRate(825) // 8.25%
    ->create();
```

## Multi-Tenant Setup

import Aside from "@components/Aside.astro"

<Aside variant="warning">
  Owner scoping is **disabled by default** (`TAX_OWNER_ENABLED=false`). In a multi-tenant deployment every tenant will see all tax zones and rates unless you enable it. Set `TAX_OWNER_ENABLED=true` and bind `OwnerResolverInterface` before going live.
</Aside>

```env
TAX_OWNER_ENABLED=true
```

Bind the resolver in `AppServiceProvider::register()`:

```php
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;

$this->app->bind(OwnerResolverInterface::class, CurrentTenantResolver::class);
```

See [Multitenancy](./07-multitenancy.md) for full details.

## Next Steps

- [Configure tax settings](03-configuration.md)
- [Learn tax calculation API](04-usage.md)
- [Set up tax exemptions](05-exemptions.md)
