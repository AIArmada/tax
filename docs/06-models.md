---
title: Models
---

# Models

This guide provides detailed documentation for all Eloquent models in the Tax package.

## TaxZone

Represents a geographic tax region.

### Table Schema

```php
Schema::create('tax_zones', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->nullableMorphs('owner');      // Multi-tenancy
    $table->string('name');
    $table->string('code');
    $table->text('description')->nullable();
    $table->string('type')->default('country');
    $table->json('countries')->nullable(); // ['MY', 'SG']
    $table->json('states')->nullable();    // ['Selangor']
    $table->json('postcodes')->nullable(); // ['10000-19999']
    $table->integer('priority')->default(0);
    $table->boolean('is_default')->default(false);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | string | UUID primary key |
| `owner_type` | string\|null | Owner model class |
| `owner_id` | string\|null | Owner UUID |
| `name` | string | Display name |
| `code` | string | Unique identifier |
| `description` | string\|null | Optional description |
| `type` | string | `country`, `state`, `postcode` |
| `countries` | array\|null | ISO country codes |
| `states` | array\|null | State names/codes |
| `postcodes` | array\|null | Postcode patterns |
| `priority` | int | Matching priority (higher first) |
| `is_default` | bool | Default fallback zone |
| `is_active` | bool | Zone is active |

### Relationships

```php
// Has many tax rates
$zone->rates; // Collection<TaxRate>
```

### Scopes

```php
TaxZone::active();                           // is_active = true
TaxZone::default();                          // is_default = true
TaxZone::forAddress('MY', 'Selangor', '43000'); // Matching address
TaxZone::forOwner($owner, $includeGlobal);   // Owner scoped
```

### Methods

```php
// Check if address matches this zone
$zone->matchesAddress($country, $state, $postcode); // bool

// Create a virtual zero-rate zone
$virtual = TaxZone::zeroRate();
```

### Factory

```php
TaxZone::factory()->create();
TaxZone::factory()->forMalaysia()->create();
TaxZone::factory()->forSingapore()->create();
TaxZone::factory()->default()->create();
TaxZone::factory()->inactive()->create();
TaxZone::factory()->withStates(['Selangor', 'Penang'])->create();
TaxZone::factory()->withPostcodes(['43*', '50000-59999'])->create();
TaxZone::factory()->withPriority(10)->create();
```

### Postcode Matching

Postcodes support three patterns:

| Pattern | Example | Matches |
|---------|---------|---------|
| Exact | `43000` | Only `43000` |
| Range | `43000-43999` | `43000` through `43999` |
| Wildcard | `43*` | `43000`, `43100`, etc. |

---

## TaxRate

Represents a tax percentage for a zone and class.

### Table Schema

```php
Schema::create('tax_rates', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->nullableMorphs('owner');
    $table->uuid('zone_id');
    $table->string('tax_class')->default('standard');
    $table->string('name');
    $table->text('description')->nullable();
    $table->unsignedInteger('rate');      // Basis points (600 = 6%)
    $table->boolean('is_compound')->default(false);
    $table->boolean('is_shipping')->default(true);
    $table->integer('priority')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | string | UUID primary key |
| `zone_id` | string | Foreign key to TaxZone |
| `tax_class` | string | Class slug (`standard`, etc.) |
| `name` | string | Display name (e.g., "SST") |
| `description` | string\|null | Optional description |
| `rate` | int | Rate in basis points |
| `is_compound` | bool | Applied on (base + previous taxes) |
| `is_shipping` | bool | Applies to shipping |
| `priority` | int | Application order |
| `is_active` | bool | Rate is active |

### Rate Storage

Rates are stored as **basis points** (1/100th of a percent):

| Percentage | Basis Points |
|------------|--------------|
| 6% | 600 |
| 8.25% | 825 |
| 10% | 1000 |
| 20% | 2000 |

### Relationships

```php
$rate->zone; // TaxZone
```

### Scopes

```php
TaxRate::active();           // is_active = true
TaxRate::forClass('reduced'); // tax_class = 'reduced'
TaxRate::forZone($zoneId);   // zone_id = $zoneId
```

### Methods

```php
// Get rate as percentage (e.g., 6.00)
$rate->getRatePercentage(); // float

// Get rate as decimal (e.g., 0.06)
$rate->getRateDecimal(); // float

// Get formatted display (e.g., "6.00%")
$rate->getFormattedRate(); // string

// Calculate tax on exclusive amount
$rate->calculateTax(10000); // int: 600

// Extract tax from inclusive amount
$rate->extractTax(10600);   // int: 600

// Create virtual zero rate
$virtual = TaxRate::zeroRate('standard', $zone);
```

### Factory

```php
TaxRate::factory()->create();
TaxRate::factory()->forZone($zone)->create();
TaxRate::factory()->withRate(825)->create();  // 8.25%
TaxRate::factory()->sst()->create();          // 6% SST
TaxRate::factory()->gst()->create();          // 9% GST
TaxRate::factory()->vat()->create();          // 20% VAT
TaxRate::factory()->zero()->create();         // 0%
TaxRate::factory()->compound()->create();
TaxRate::factory()->notShipping()->create();
TaxRate::factory()->forClass('reduced')->create();
```

---

## TaxClass

Represents a tax category for products.

### Table Schema

```php
Schema::create('tax_classes', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->nullableMorphs('owner');
    $table->string('name');
    $table->string('slug');
    $table->text('description')->nullable();
    $table->boolean('is_default')->default(false);
    $table->boolean('is_active')->default(true);
    $table->integer('position')->default(0);
    $table->timestamps();
});
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | string | UUID primary key |
| `name` | string | Display name |
| `slug` | string | URL-safe identifier |
| `description` | string\|null | Optional description |
| `is_default` | bool | Default class for products |
| `is_active` | bool | Class is active |
| `position` | int | Display order |

### Scopes

```php
TaxClass::active();   // is_active = true
TaxClass::default();  // is_default = true
TaxClass::ordered();  // ORDER BY position ASC
```

### Static Methods

```php
// Get the default tax class
$default = TaxClass::getDefault(); // ?TaxClass

// Find by slug
$class = TaxClass::findBySlug('reduced'); // ?TaxClass
```

### Factory

```php
TaxClass::factory()->create();
TaxClass::factory()->create([
    'name' => 'Digital Goods',
    'slug' => 'digital',
]);
```

---

## TaxExemption

Represents a customer tax exemption.

### Table Schema

```php
Schema::create('tax_exemptions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->nullableMorphs('owner');
    $table->uuidMorphs('exemptable');     // Customer, Group, etc.
    $table->foreignUuid('tax_zone_id')->nullable();
    $table->string('reason');
    $table->string('certificate_number')->nullable();
    $table->string('document_path')->nullable();
    $table->string('status')->default('pending');
    $table->text('rejection_reason')->nullable();
    $table->timestamp('verified_at')->nullable();
    $table->uuid('verified_by')->nullable();
    $table->timestamp('starts_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->timestamps();
});
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | string | UUID primary key |
| `exemptable_type` | string | Entity model class |
| `exemptable_id` | string | Entity UUID |
| `tax_zone_id` | string\|null | Specific zone (null = all) |
| `reason` | string | Exemption reason |
| `certificate_number` | string\|null | Certificate identifier |
| `document_path` | string\|null | Uploaded document path |
| `status` | string | `pending`, `approved`, `rejected` |
| `rejection_reason` | string\|null | Why rejected |
| `verified_at` | datetime\|null | Verification timestamp |
| `verified_by` | string\|null | Verifier UUID |
| `starts_at` | datetime\|null | Validity start |
| `expires_at` | datetime\|null | Validity end |

### Relationships

```php
$exemption->exemptable; // Polymorphic: Customer, User, etc.
$exemption->taxZone;    // ?TaxZone
```

### Scopes

```php
TaxExemption::active();     // Approved + valid dates
TaxExemption::pending();    // status = 'pending'
TaxExemption::approved();   // status = 'approved'
TaxExemption::forZone($id); // For specific zone or global
```

### Methods

```php
// Status checks
$exemption->isActive();    // bool
$exemption->isExpired();   // bool
$exemption->isPending();   // bool
$exemption->isApproved();  // bool
$exemption->isRejected();  // bool

// Zone check
$exemption->appliesToZone($zoneId); // bool

// Workflow
$exemption->approve();            // Sets approved + verified_at
$exemption->reject($reason);      // Sets rejected + reason
```

### Factory

```php
TaxExemption::factory()->create();
TaxExemption::factory()->create([
    'exemptable_id' => $customer->id,
    'exemptable_type' => Customer::class,
    'reason' => 'Charity organization',
]);
```

---

## Traits & Concerns

All models use:

| Trait | Source | Purpose |
|-------|--------|---------|
| `HasUuids` | Laravel | UUID primary keys |
| `HasFactory` | Laravel | Factory support |
| `HasOwner` | commerce-support | Multi-tenancy |
| `HasOwnerScopeConfig` | commerce-support | Owner scope config |
| `LogsActivity` | spatie/activitylog | Audit trail |

### Table Name Resolution

All models use dynamic table names via `getTable()`:

```php
public function getTable(): string
{
    return (string) config('tax.database.tables.tax_zones', 'tax_zones');
}
```

This allows table name customization without model changes.

### Activity Logging

All models log changes to the `tax` log:

```php
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logOnly(['name', 'is_active', ...])
        ->logOnlyDirty()
        ->useLogName('tax');
}
```

### Cascade Deletes

`TaxZone` implements application-level cascade deletes:

```php
protected static function booted(): void
{
    static::deleting(function (TaxZone $zone): void {
        $zone->rates()->delete();
    });
}
```

No database-level foreign key constraints are used.
