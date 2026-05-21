---
title: Multitenancy
---

import Aside from "@components/Aside.astro"

# Multitenancy

The tax package supports multi-tenant architectures using the `commerce-support` owner scoping system, allowing tax zones and rates to be isolated by tenant (merchant, store, organisation).

## Enabling Owner Mode

```php
// config/tax.php
'features' => [
    'owner' => [
        'enabled' => env('TAX_OWNER_ENABLED', false),
        'include_global' => false,
    ],
],
```

```env
TAX_OWNER_ENABLED=true
```

<Aside variant="warning">
  The default is `false` (single-tenant). Without enabling this, all tenants share the same tax zones and rates. Always set `TAX_OWNER_ENABLED=true` in multi-tenant deployments.
</Aside>

## Binding the Owner Resolver

Bind `OwnerResolverInterface` in `AppServiceProvider::register()`:

```php
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;

$this->app->bind(OwnerResolverInterface::class, function () {
    return new class implements OwnerResolverInterface {
        public function resolve(): ?\Illuminate\Database\Eloquent\Model
        {
            return auth()->user()?->currentTeam;
        }
    };
});
```

## How It Works

When `owner.enabled` is `true`:

1. `TaxZone` queries are automatically scoped to the resolved owner
2. New zones get `owner_type` / `owner_id` set automatically
3. If the owner cannot be resolved, queries fail closed (return zero rows)
4. Tax calculation uses only zones that belong to the current owner (plus global zones if `include_global` is configured)

## Owner-Scoped Models

| Model | Owner Columns |
|-------|--------------|
| `TaxZone` | `owner_type`, `owner_id` |
| `TaxRate` | scoped via `TaxZone` |
| `TaxExemption` | `owner_type`, `owner_id` |

## Global Tax Zones

Tax zones with `owner_id = null` represent platform-wide zones (e.g. national GST/SST rates) shared across all tenants. The default does **not** include global zones in queries (`include_global = false`):

```php
use AIArmada\Tax\Models\TaxZone;

// Owner-only zones
$zones = TaxZone::forOwner($tenant)->get();

// Owner zones + global platform zones
$zones = TaxZone::forOwner($tenant, includeGlobal: true)->get();
```

<Aside variant="info">
  `include_global` has no env override — set it directly in `config/tax.php`. Enable it if your deployment has shared platform tax zones (e.g. country-level rates) alongside per-tenant custom rates.
</Aside>

## Tax Calculation in Multi-Tenant Context

The tax calculator uses `OwnerContext::resolve()` internally. Ensure the owner context is set before calculating:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Tax\Facades\Tax;

// Owner context already set (e.g. via middleware) — just calculate
$result = Tax::calculate($taxable);

// Explicit context for background processing
OwnerContext::withOwner($tenant, function () use ($taxable): void {
    $result = Tax::calculate($taxable);
});
```

## Querying with Owner Scope

```php
use AIArmada\Tax\Models\TaxZone;

// Automatically scoped (global scope applied)
$zones = TaxZone::query()->get();

// Explicit owner
$zones = TaxZone::forOwner($tenant)->get();

// Platform-wide zones only
$zones = TaxZone::globalOnly()->get();
```

## Write Path Validation

For inbound IDs on mutation paths (Filament actions, API handlers, jobs), resolve records with `OwnerWriteGuard` instead of raw `find()` lookups.

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Tax\Models\TaxZone;

$zone = OwnerWriteGuard::findOrFailForOwner(
    TaxZone::class,
    $zoneId,
    owner: OwnerContext::CURRENT,
    includeGlobal: false,
);
```

This keeps tenant-context writes fail-closed and prevents cross-tenant/global-row mutation by submitted IDs.

## Testing

```php
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;

it('scopes tax zones to owner', function () {
    config(['tax.features.owner.enabled' => true]);

    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();

    app()->instance(OwnerResolverInterface::class, new class($teamA) implements OwnerResolverInterface {
        public function __construct(private \Illuminate\Database\Eloquent\Model $owner) {}
        public function resolve(): ?\Illuminate\Database\Eloquent\Model { return $this->owner; }
    });

    TaxZone::factory()->create(['owner_type' => $teamA->getMorphClass(), 'owner_id' => $teamA->id]);
    TaxZone::factory()->create(['owner_type' => $teamB->getMorphClass(), 'owner_id' => $teamB->id]);

    expect(TaxZone::query()->count())->toBe(1);
});
```
