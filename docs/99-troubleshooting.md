---
title: Troubleshooting
---

# Troubleshooting

This guide covers common issues and debugging techniques for the Tax package.

## Common Issues

### No Tax Applied

**Symptom:** `TaxResultData::taxAmount` returns 0 when you expect a tax.

**Checklist:**

1. **Tax is enabled**
   ```php
   config('tax.features.enabled'); // Should be true
   
   // Or via settings
   app(\AIArmada\Tax\Settings\TaxSettings::class)->enabled;
   ```

2. **Zone exists and is active**
   ```php
   use AIArmada\Tax\Models\TaxZone;
   
   TaxZone::where('is_active', true)->get();
   // Should return at least one zone
   ```

3. **Rate exists for zone + tax class**
   ```php
   use AIArmada\Tax\Models\TaxRate;
   
   TaxRate::where('zone_id', $zoneId)
       ->where('tax_class', 'standard')
       ->where('is_active', true)
       ->get();
   ```

4. **Zone matches the address**
   ```php
   $zone = TaxZone::first();
   $zone->matchesAddress('MY', 'Selangor', '43000'); // Should be true
   ```

5. **Customer is not exempt**
   ```php
   use AIArmada\Tax\Models\TaxExemption;
   
   TaxExemption::where('exemptable_id', $customerId)
       ->active()
       ->get();
   // Should be empty if you expect tax
   ```

### Wrong Tax Amount

**Symptom:** Tax calculation differs from expected.

**Checklist:**

1. **Check price inclusion mode**
   ```php
   config('tax.defaults.prices_include_tax');
   // false = tax added on top
   // true = tax extracted from price
   ```

2. **Check rate value**
   ```php
   $rate = TaxRate::first();
   echo $rate->rate;              // 600 = 6.00%
   echo $rate->getRatePercentage(); // 6.0
   ```

3. **Check for compound taxes**
   ```php
   TaxRate::where('zone_id', $zoneId)
       ->orderBy('priority', 'desc')
       ->get(['name', 'rate', 'priority', 'is_compound']);
   ```

4. **Check rounding mode**
   ```php
   config('tax.defaults.round_at_subtotal');
   // true = round after summing all taxes
   // false = round each tax individually
   ```

### Zone Not Found

**Symptom:** `TaxZoneNotFoundException` thrown or zero tax returned.

**Checklist:**

1. **Country code format**
   - Use ISO 3166-1 alpha-2 codes
   - Correct: `MY`, `SG`, `US`
   - Wrong: `Malaysia`, `MYS`, `MY `

2. **Zone has matching criteria**
   ```php
   TaxZone::first()->countries; // Should contain 'MY'
   ```

3. **Zone priority order**
   ```php
   TaxZone::active()
       ->orderBy('priority', 'desc')
       ->get(['name', 'countries', 'priority']);
   ```

4. **Default zone exists**
   ```php
   TaxZone::where('is_default', true)->exists();
   ```

5. **Check unknown zone behavior**
   ```php
   config('tax.features.zone_resolution.unknown_zone_behavior');
   // 'default', 'zero', or 'error'
   ```

### Multi-Tenancy Issues

**Symptom:** Records not visible or cross-tenant data appearing.

**Checklist:**

1. **Owner mode enabled**
   ```php
   config('tax.features.owner.enabled'); // Should be true
   ```

2. **Owner context set**
   ```php
   use AIArmada\CommerceSupport\Support\OwnerContext;
   
   $owner = OwnerContext::resolve();
   // Should return current tenant
   ```

3. **Records have owner**
   ```php
   TaxZone::withoutGlobalScopes()
       ->whereNull('owner_id')
       ->count();
   // Global records (may or may not be included)
   ```

4. **Include global setting**
   ```php
   config('tax.features.owner.include_global');
   // false = tenant-only
   // true = tenant + global
   ```

### Shipping Tax Not Applied

**Symptom:** Shipping has zero tax when it should be taxed.

**Checklist:**

1. **Shipping tax enabled**
   ```php
   config('tax.defaults.calculate_tax_on_shipping'); // true
   
   // Or via settings
   app(\AIArmada\Tax\Settings\TaxSettings::class)->shippingTaxable;
   ```

2. **Rate is marked for shipping**
   ```php
   TaxRate::where('is_shipping', true)->get();
   ```

### Exemption Not Working

**Symptom:** Customer should be exempt but tax is applied.

**Checklist:**

1. **Exemptions enabled**
   ```php
   config('tax.features.exemptions.enabled'); // true
   ```

2. **Exemption is approved**
   ```php
   $exemption->status === 'approved';
   ```

3. **Exemption is active (dates)**
   ```php
   $exemption->starts_at?->isPast();   // true or null
   $exemption->expires_at?->isFuture(); // true or null
   ```

4. **Customer context passed**
   ```php
   Tax::calculateTax(10000, 'standard', null, [
       'customer_id' => $customer->id,     // Required!
       'customer_type' => Customer::class,  // Required!
   ]);
   ```

5. **Zone matches exemption**
   ```php
   $exemption->appliesToZone($zoneId); // true
   ```

## Debugging

### Log Tax Calculations

```php
use AIArmada\Tax\Facades\Tax;
use Illuminate\Support\Facades\Log;

$result = Tax::calculateTax(10000, 'standard', null, [
    'shipping_address' => $address,
    'customer_id' => $customerId,
    'customer_type' => Customer::class,
]);

Log::debug('Tax calculation', [
    'input' => [
        'amount' => 10000,
        'class' => 'standard',
        'address' => $address,
        'customer' => $customerId,
    ],
    'output' => [
        'tax' => $result->taxAmount,
        'rate' => $result->ratePercentage,
        'zone' => $result->zoneName,
        'exempt' => $result->isExempt(),
        'reason' => $result->exemptionReason,
        'breakdown' => $result->breakdown,
    ],
]);
```

### Test Zone Matching

```php
use AIArmada\Tax\Models\TaxZone;

function debugZoneMatch(string $country, ?string $state, ?string $postcode): void
{
    $zones = TaxZone::active()
        ->orderBy('priority', 'desc')
        ->get();

    foreach ($zones as $zone) {
        $matches = $zone->matchesAddress($country, $state, $postcode);
        
        echo sprintf(
            "%s (%s): %s\n",
            $zone->name,
            $zone->code,
            $matches ? '✓ MATCH' : '✗ no match'
        );
        
        if ($matches) {
            echo "  Countries: " . json_encode($zone->countries) . "\n";
            echo "  States: " . json_encode($zone->states) . "\n";
            echo "  Postcodes: " . json_encode($zone->postcodes) . "\n";
            break;
        }
    }
}

debugZoneMatch('MY', 'Selangor', '43000');
```

### Inspect Active Rates

```php
function debugRatesForZone(string $zoneId): void
{
    $rates = TaxRate::where('zone_id', $zoneId)
        ->active()
        ->orderBy('priority', 'desc')
        ->get();

    foreach ($rates as $rate) {
        echo sprintf(
            "%s: %s (class: %s, compound: %s, shipping: %s)\n",
            $rate->name,
            $rate->getFormattedRate(),
            $rate->tax_class,
            $rate->is_compound ? 'yes' : 'no',
            $rate->is_shipping ? 'yes' : 'no'
        );
    }
}
```

### Check Exemption Status

```php
use AIArmada\Tax\Models\TaxExemption;

function debugExemption(string $customerId, string $customerType): void
{
    $exemptions = TaxExemption::query()
        ->where('exemptable_id', $customerId)
        ->where('exemptable_type', $customerType)
        ->get();

    foreach ($exemptions as $ex) {
        echo sprintf(
            "Exemption %s:\n  Status: %s\n  Active: %s\n  Expired: %s\n  Zone: %s\n  Reason: %s\n",
            $ex->id,
            $ex->status,
            $ex->isActive() ? 'yes' : 'no',
            $ex->isExpired() ? 'yes' : 'no',
            $ex->tax_zone_id ?? 'ALL',
            $ex->reason
        );
    }
}
```

## Migration Issues

### JSON Column Errors

**MySQL 5.6 or older:**
```php
// config/tax.php
'database' => [
    'json_column_type' => 'text', // Use text instead of json
],
```

**PostgreSQL:**
```php
// config/tax.php
'database' => [
    'json_column_type' => 'jsonb', // More efficient
],
```

### Settings Table Missing

If you see errors about missing settings:

```bash
php artisan vendor:publish --tag=tax-settings
php artisan migrate
```

## Performance

### Slow Zone Resolution

1. **Add database indexes** (already included in migrations)
2. **Cache zone lookups**
   ```php
   $zone = Cache::remember(
       "tax-zone:{$country}:{$state}",
       3600,
       fn () => TaxZone::forAddress($country, $state)->first()
   );
   ```

3. **Preload for batch operations**
   ```php
   $zones = TaxZone::active()->with('rates')->get();
   ```

### Many Concurrent Calculations

- Use queue workers for bulk operations
- Consider read replicas for high-traffic scenarios
- Cache frequently-used zone/rate combinations

## Testing Tips

### Using Factories

```php
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Models\TaxRate;

it('calculates tax correctly', function () {
    $zone = TaxZone::factory()->forMalaysia()->default()->create();
    TaxRate::factory()->forZone($zone)->sst()->create();

    $result = Tax::calculateTax(10000, 'standard');

    expect($result->taxAmount)->toBe(600);
});
```

### Mocking the Calculator

```php
use AIArmada\Tax\Contracts\TaxCalculatorInterface;
use AIArmada\Tax\Data\TaxResultData;

$mock = Mockery::mock(TaxCalculatorInterface::class);
$mock->shouldReceive('calculateTax')
    ->andReturn(new TaxResultData(
        taxAmount: 600,
        rateId: 'rate-1',
        rateName: 'SST',
        ratePercentage: 600,
        zoneId: 'zone-1',
        zoneName: 'Malaysia',
    ));

app()->instance(TaxCalculatorInterface::class, $mock);
```

## Getting Help

1. Enable debug logging (see above)
2. Check configuration values
3. Verify database records
4. Run with `unknown_zone_behavior = 'error'` to catch zone issues
5. Open an issue with:
   - Laravel version
   - Package version
   - Configuration
   - Debug output
   - Expected vs actual behavior
