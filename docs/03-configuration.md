---
title: Configuration
---

# Configuration

The Tax package provides two layers of configuration:

1. **Static Configuration** - `config/tax.php` (requires deployment)
2. **Runtime Settings** - Spatie Laravel Settings (changeable at runtime)

## Configuration File

After publishing (`php artisan vendor:publish --tag=tax-config`), edit `config/tax.php`:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'tables' => [
            'tax_zones' => 'tax_zones',
            'tax_rates' => 'tax_rates',
            'tax_classes' => 'tax_classes',
            'tax_exemptions' => 'tax_exemptions',
        ],

        // Use 'jsonb' for PostgreSQL, 'json' for MySQL 5.7+
        'json_column_type' => env('TAX_JSON_COLUMN_TYPE', 'json'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        // Whether product prices already include tax
        'prices_include_tax' => env('TAX_PRICES_INCLUDE_TAX', false),

        // Apply tax to shipping costs
        'calculate_tax_on_shipping' => env('TAX_ON_SHIPPING', true),

        // Round tax at subtotal level vs per-line-item
        'round_at_subtotal' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        // Master on/off switch for tax calculation
        'enabled' => env('TAX_ENABLED', true),

        // Multi-tenancy settings
        'owner' => [
            'enabled' => env('TAX_OWNER_ENABLED', false),
            'include_global' => false,
        ],

        // Zone resolution settings
        'zone_resolution' => [
            'use_customer_address' => true,
            'address_priority' => 'shipping', // 'shipping' or 'billing'
            'unknown_zone_behavior' => 'default', // 'default', 'zero', 'error'
            'fallback_zone_id' => null,
        ],

        // Exemption system
        'exemptions' => [
            'enabled' => true,
        ],
    ],
];
```

## Configuration Reference

### Database Options

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `database.tables.tax_zones` | string | `'tax_zones'` | Tax zones table name |
| `database.tables.tax_rates` | string | `'tax_rates'` | Tax rates table name |
| `database.tables.tax_classes` | string | `'tax_classes'` | Tax classes table name |
| `database.tables.tax_exemptions` | string | `'tax_exemptions'` | Tax exemptions table name |
| `database.json_column_type` | string | `'json'` | JSON column type (`json` or `jsonb`) |

### Default Behavior Options

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `defaults.prices_include_tax` | bool | `false` | If `true`, prices are tax-inclusive and tax is extracted |
| `defaults.calculate_tax_on_shipping` | bool | `true` | Whether to apply tax to shipping costs |
| `defaults.round_at_subtotal` | bool | `true` | Round at subtotal level (vs per line item) |

### Feature Options

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `features.enabled` | bool | `true` | Master switch for tax calculation |
| `features.owner.enabled` | bool | `false` | Enable multi-tenancy scoping |
| `features.owner.include_global` | bool | `false` | Include global (ownerless) records |
| `features.exemptions.enabled` | bool | `true` | Enable tax exemption checking |

### Zone Resolution Options

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `zone_resolution.use_customer_address` | bool | `true` | Auto-detect zone from customer address |
| `zone_resolution.address_priority` | string | `'shipping'` | Which address to use (`shipping` or `billing`) |
| `zone_resolution.unknown_zone_behavior` | string | `'default'` | Behavior when no zone matches |
| `zone_resolution.fallback_zone_id` | string\|null | `null` | Specific zone UUID to use as fallback |

## Environment Variables

```env
# Master switch
TAX_ENABLED=true

# Pricing model
TAX_PRICES_INCLUDE_TAX=false
TAX_ON_SHIPPING=true

# Multi-tenancy
TAX_OWNER_ENABLED=false

# Database (for PostgreSQL)
TAX_JSON_COLUMN_TYPE=jsonb

# Commerce-wide JSON type (fallback)
COMMERCE_JSON_COLUMN_TYPE=json
```

## Runtime Settings (Spatie)

For settings that should be changeable without code deployment, use Spatie Laravel Settings.

### TaxSettings

```php
use AIArmada\Tax\Settings\TaxSettings;

$settings = app(TaxSettings::class);

// Read settings
$isEnabled = $settings->enabled;
$rate = $settings->defaultTaxRate;

// Update settings
$settings->enabled = true;
$settings->defaultTaxRate = 6.0;
$settings->defaultTaxName = 'SST';
$settings->pricesIncludeTax = false;
$settings->taxBasedOnShippingAddress = true;
$settings->digitalGoodsTaxable = true;
$settings->shippingTaxable = true;
$settings->taxIdLabel = 'SST Number';
$settings->validateTaxIds = false;
$settings->requireExemptionCertificate = false;
$settings->save();
```

#### Available Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `enabled` | bool | `true` | Enable tax calculation |
| `defaultTaxRate` | float | `6.0` | Default tax percentage |
| `defaultTaxName` | string | `'SST'` | Tax name on invoices |
| `pricesIncludeTax` | bool | `false` | Prices include tax |
| `taxBasedOnShippingAddress` | bool | `true` | Use shipping address for zone |
| `digitalGoodsTaxable` | bool | `true` | Tax digital products |
| `shippingTaxable` | bool | `false` | Tax shipping costs |
| `taxIdLabel` | string | `'SST Number'` | Label for tax ID field |
| `validateTaxIds` | bool | `false` | Validate customer tax IDs |
| `requireExemptionCertificate` | bool | `false` | Require certificate for exemptions |

### TaxZoneSettings

```php
use AIArmada\Tax\Settings\TaxZoneSettings;

$settings = app(TaxZoneSettings::class);

$settings->multiZoneEnabled = true;
$settings->defaultZoneId = 'uuid-of-default-zone';
$settings->autoDetectZone = true;
$settings->fallbackBehavior = 'default'; // 'default', 'zero', 'error'
$settings->compoundTaxEnabled = true;
$settings->showTaxBreakdown = true;
$settings->save();
```

#### Available Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `multiZoneEnabled` | bool | `false` | Enable multi-zone support |
| `defaultZoneId` | string\|null | `null` | Default zone UUID |
| `autoDetectZone` | bool | `true` | Auto-detect from address |
| `fallbackBehavior` | string | `'default'` | Unknown zone behavior |
| `compoundTaxEnabled` | bool | `false` | Enable compound taxes |
| `showTaxBreakdown` | bool | `true` | Show breakdown in UI |

## Unknown Zone Behavior

When a customer address doesn't match any configured zone:

### `'default'` (Recommended)

Uses the zone marked with `is_default = true`. If no default zone exists, returns zero tax.

```php
$result = Tax::calculateTax(10000, 'standard', null, [
    'shipping_address' => ['country' => 'XX'], // Unknown country
]);
// Returns tax from default zone, or zero
```

### `'zero'`

Returns zero tax without throwing an error.

```php
// config/tax.php
'zone_resolution' => [
    'unknown_zone_behavior' => 'zero',
],
```

### `'error'`

Throws `TaxZoneNotFoundException`. Use when tax is mandatory.

```php
use AIArmada\Tax\Exceptions\TaxZoneNotFoundException;

// config/tax.php
'zone_resolution' => [
    'unknown_zone_behavior' => 'error',
],

try {
    $result = Tax::calculateTax(10000, 'standard', null, [
        'shipping_address' => ['country' => 'XX'],
    ]);
} catch (TaxZoneNotFoundException $e) {
    // Handle: show error, block checkout, etc.
}
```

## Multi-Tenancy Configuration

When running a multi-tenant application:

```php
// config/tax.php
'features' => [
    'owner' => [
        'enabled' => true,
        'include_global' => false, // Set to true to include shared records
    ],
],
```

### Behavior

| `include_global` | Result |
|------------------|--------|
| `false` | Only records owned by current tenant |
| `true` | Records owned by current tenant + global records (`owner_id = null`) |

### Owner Resolution

The package uses `commerce-support`'s `OwnerContext` to resolve the current owner. The context is typically set by middleware in HTTP requests, or manually for console commands and jobs.

**HTTP Contexts (Middleware):**
```php
use AIArmada\CommerceSupport\Support\OwnerContext;

// Middleware automatically sets the request owner via OwnerResolverInterface
// All queries are scoped to current owner
$zones = TaxZone::all(); // Only current tenant's zones
```

**Non-HTTP Contexts (Jobs, Commands):**
```php
use AIArmada\CommerceSupport\Support\OwnerContext;

// For queued jobs or console commands, use withOwner()
OwnerContext::withOwner($tenant, function () {
    $zones = TaxZone::all(); // Only this tenant's zones
    // perform tax calculations or operations
});
```

## Price Inclusion Mode

### Tax-Exclusive Pricing (Default)

Prices don't include tax; tax is added at checkout.

```php
// config/tax.php
'defaults' => [
    'prices_include_tax' => false,
],

// Product price: RM 100.00
// Tax (6%): RM 6.00
// Total: RM 106.00
```

### Tax-Inclusive Pricing

Prices already include tax; tax is extracted for reporting.

```php
// config/tax.php
'defaults' => [
    'prices_include_tax' => true,
],

// Product price: RM 106.00 (includes tax)
// Tax extracted: RM 6.00
// Net price: RM 100.00
```

## Configuration Priority

Settings are resolved in this order (first wins):

1. **Spatie Settings** (if available and non-null)
2. **Config file** values
3. **Default values**

```php
// In TaxCalculator::isTaxEnabled()
$settings = app(TaxSettings::class);
if ($settings) {
    return $settings->enabled;
}
return config('tax.features.enabled', true);
```

## Customizing Table Names

If you need different table names (e.g., for prefix/suffix):

```php
// config/tax.php
'database' => [
    'tables' => [
        'tax_zones' => 'commerce_tax_zones',
        'tax_rates' => 'commerce_tax_rates',
        'tax_classes' => 'commerce_tax_classes',
        'tax_exemptions' => 'commerce_tax_exemptions',
    ],
],
```

Models automatically use these configured names via `getTable()`.
