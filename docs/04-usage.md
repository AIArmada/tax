---
title: Usage
---

# Usage

This guide covers all aspects of using the Tax package for calculating taxes in your commerce application.

## Tax Calculation

### Using the Facade

The simplest way to calculate tax:

```php
use AIArmada\Tax\Facades\Tax;

// Calculate tax on RM 100.00 (10000 cents)
$result = Tax::calculateTax(
    amountInCents: 10000,
    taxClass: 'standard',
    zoneId: null,
    context: []
);
```

### Using Dependency Injection

For better testability:

```php
use AIArmada\Tax\Contracts\TaxCalculatorInterface;

class CheckoutService
{
    public function __construct(
        private TaxCalculatorInterface $taxCalculator
    ) {}

    public function calculateOrderTax(Order $order): int
    {
        $result = $this->taxCalculator->calculateTax(
            amountInCents: $order->subtotal,
            taxClass: 'standard',
            zoneId: null,
            context: [
                'shipping_address' => $order->shippingAddress->toArray(),
                'customer_id' => $order->customer_id,
                'customer_type' => Customer::class,
            ]
        );

        return $result->taxAmount;
    }
}
```

### Method Signature

```php
public function calculateTax(
    int $amountInCents,          // Amount in minor units (cents)
    string $taxClass = 'standard', // Tax class slug
    ?string $zoneId = null,       // Optional zone UUID
    array $context = []           // Additional context
): TaxResultData;
```

## Tax Result Data

All calculations return a `TaxResultData` DTO:

```php
$result = Tax::calculateTax(10000, 'standard', $zoneId);

// Core properties
$result->taxAmount;        // int: Tax in cents (e.g., 600)
$result->rateId;           // string: Primary rate UUID
$result->rateName;         // string: Rate display name (e.g., "SST")
$result->ratePercentage;   // int: Rate in basis points (e.g., 600 = 6.00%)
$result->zoneId;           // string: Zone UUID
$result->zoneName;         // string: Zone display name (e.g., "Malaysia")
$result->includedInPrice;  // bool: Whether tax was extracted (tax-inclusive)
$result->exemptionReason;  // ?string: Reason if exempt
$result->breakdown;        // array: All applied rates

// Helper methods
$result->isExempt();                    // bool: True if tax-exempt
$result->getFormattedAmount('RM');      // string: "RM 6.00"
$result->getFormattedRate();            // string: "6.00%"
$result->getSummary();                  // string: "SST (6.00%)"
$result->hasCompoundTaxes();            // bool: True if multiple rates applied
```

### Breakdown Array

When compound taxes are applied:

```php
$result->breakdown = [
    [
        'name' => 'Federal Tax',
        'rate' => 500,           // 5.00%
        'amount' => 500,         // cents
        'is_compound' => false,
    ],
    [
        'name' => 'State Tax',
        'rate' => 300,           // 3.00%
        'amount' => 315,         // cents (compound)
        'is_compound' => true,
    ],
];
```

## Zone Resolution

### Explicit Zone

Pass a zone ID directly:

```php
$zone = TaxZone::where('code', 'MY')->first();

$result = Tax::calculateTax(10000, 'standard', $zone->id);
```

### Auto-Detection from Address

Let the calculator find the matching zone:

```php
$result = Tax::calculateTax(10000, 'standard', null, [
    'shipping_address' => [
        'country' => 'MY',
        'state' => 'Selangor',
        'postcode' => '43000',
    ],
]);
```

### Address Priority

By default, shipping address is used. Configure via:

```php
// config/tax.php
'zone_resolution' => [
    'address_priority' => 'shipping', // or 'billing'
],
```

Or pass both addresses:

```php
$result = Tax::calculateTax(10000, 'standard', null, [
    'shipping_address' => ['country' => 'MY'],
    'billing_address' => ['country' => 'SG'],
]);
// Uses shipping_address based on config
```

### Zone Matching Logic

Zones are matched in this order:

1. **Exact zone ID** - If provided
2. **Address matching** - Based on priority config
3. **Default zone** - Zone with `is_default = true`
4. **Fallback zone** - From `fallback_zone_id` config
5. **Unknown behavior** - Based on `unknown_zone_behavior` config

Zone matching criteria:

```php
// Zone matches if:
// 1. countries is null/empty OR address.country is in countries
// 2. states is null/empty OR address.state is in states
// 3. postcodes is null/empty OR address.postcode matches a pattern
```

## Tax Classes

Products are categorized by tax class:

```php
// Standard rate (default for most products)
$result = Tax::calculateTax(10000, 'standard');

// Reduced rate (essentials, food)
$result = Tax::calculateTax(10000, 'reduced');

// Zero rate (tracked but 0%)
$result = Tax::calculateTax(10000, 'zero');

// Exempt (not taxable)
$result = Tax::calculateTax(10000, 'exempt');
```

### Getting the Default Class

```php
use AIArmada\Tax\Models\TaxClass;

$default = TaxClass::getDefault();
echo $default->slug; // 'standard'
```

### Finding by Slug

```php
$class = TaxClass::findBySlug('reduced');
```

## Shipping Tax

Calculate tax on shipping costs:

```php
$shippingTax = Tax::calculateShippingTax(
    shippingAmountInCents: 1500, // RM 15.00
    zoneId: $zoneId,
    context: []
);

echo $shippingTax->taxAmount; // e.g., 90 (6% of 1500)
```

### Shipping Tax Rules

1. Only applied if `calculate_tax_on_shipping` is `true`
2. Only rates with `is_shipping = true` are used
3. Uses the `standard` tax class
4. Respects exemptions (if customer context provided)

## Compound Taxes

For stacked taxes (e.g., federal + state/provincial):

### Setup

```php
use AIArmada\Tax\Models\TaxRate;

// Federal tax - applied first (non-compound)
TaxRate::create([
    'zone_id' => $zone->id,
    'name' => 'Federal Tax',
    'tax_class' => 'standard',
    'rate' => 500, // 5%
    'is_compound' => false,
    'priority' => 10, // Higher = first
]);

// State tax - applied on (base + federal)
TaxRate::create([
    'zone_id' => $zone->id,
    'name' => 'State Tax',
    'tax_class' => 'standard',
    'rate' => 300, // 3%
    'is_compound' => true,
    'priority' => 5,
]);
```

### Calculation Example

On RM 100.00 base:

```
1. Federal (non-compound): 10000 × 5% = 500 (RM 5.00)
2. State (compound): (10000 + 500) × 3% = 315 (RM 3.15)
3. Total tax: 500 + 315 = 815 (RM 8.15)
```

```php
$result = Tax::calculateTax(10000, 'standard', $zoneId);

echo $result->taxAmount; // 815

foreach ($result->breakdown as $tax) {
    echo "{$tax['name']}: {$tax['rate']/100}% = RM " . number_format($tax['amount']/100, 2);
    if ($tax['is_compound']) {
        echo " (compound)";
    }
    echo "\n";
}
// Federal Tax: 5.00% = RM 5.00
// State Tax: 3.00% = RM 3.15 (compound)
```

## Tax Exemptions

### Automatic Exemption Checking

Pass customer context to automatically check exemptions:

```php
$result = Tax::calculateTax(10000, 'standard', $zoneId, [
    'customer_id' => $customer->id,
    'customer_type' => Customer::class,
    'zone_id' => $zoneId, // Optional: for zone-specific exemption check
]);

if ($result->isExempt()) {
    echo "Exempt: " . $result->exemptionReason;
} else {
    echo "Tax: " . $result->getFormattedAmount('RM');
}
```

### Creating Exemptions

```php
use AIArmada\Tax\Models\TaxExemption;

$exemption = TaxExemption::create([
    'exemptable_id' => $customer->id,
    'exemptable_type' => Customer::class,
    'tax_zone_id' => $zone->id, // null = all zones
    'reason' => 'Government agency',
    'certificate_number' => 'GOV-2024-001',
    'document_path' => 'tax-exemptions/cert-001.pdf',
    'status' => 'pending',
    'starts_at' => now(),
    'expires_at' => now()->addYear(),
]);
```

### Exemption Workflow

```php
// Approve
$exemption->approve();
// Sets status = 'approved', verified_at = now()

// Reject
$exemption->reject('Invalid certificate number');
// Sets status = 'rejected', rejection_reason = '...'
```

### Exemption Scopes

```php
// Active exemptions (approved, not expired, started)
TaxExemption::active()->get();

// Pending review
TaxExemption::pending()->get();

// For a specific zone
TaxExemption::forZone($zoneId)->get();
```

### Exemption Helpers

```php
$exemption->isActive();    // bool: approved + valid dates
$exemption->isExpired();   // bool: expires_at < now
$exemption->isPending();   // bool: status === 'pending'
$exemption->isApproved();  // bool: status === 'approved'
$exemption->isRejected();  // bool: status === 'rejected'

$exemption->appliesToZone($zoneId); // bool: covers this zone
```

## Tax-Inclusive Pricing

When prices already include tax:

```php
// config/tax.php
'defaults' => [
    'prices_include_tax' => true,
],
```

The calculator extracts tax from the inclusive price:

```php
// Price: RM 106.00 (includes 6% tax)
$result = Tax::calculateTax(10600, 'standard', $zoneId);

echo $result->taxAmount;       // 600 (RM 6.00)
echo $result->includedInPrice; // true

// Net amount = 10600 - 600 = 10000 (RM 100.00)
```

### Manual Extraction

Using the rate model:

```php
$rate = TaxRate::where('zone_id', $zoneId)
    ->where('tax_class', 'standard')
    ->first();

// Calculate tax on exclusive price
$tax = $rate->calculateTax(10000); // 600

// Extract tax from inclusive price
$tax = $rate->extractTax(10600);   // 600
```

## Working with Models

### TaxZone

```php
use AIArmada\Tax\Models\TaxZone;

// Find zones matching an address
$zones = TaxZone::forAddress('MY', 'Selangor', '43000')
    ->active()
    ->get();

// Get default zone
$default = TaxZone::where('is_default', true)->first();

// Create a zero-rate virtual zone
$virtual = TaxZone::zeroRate();

// Check address match
$zone->matchesAddress('MY', 'Selangor', '43000'); // bool
```

### TaxRate

```php
use AIArmada\Tax\Models\TaxRate;

// Get rates for a zone and class
$rates = TaxRate::forZone($zoneId)
    ->forClass('standard')
    ->active()
    ->orderBy('priority', 'desc')
    ->get();

// Rate helpers
$rate->getRatePercentage(); // float: 6.00
$rate->getRateDecimal();    // float: 0.06
$rate->getFormattedRate();  // string: "6.00%"
$rate->calculateTax(10000); // int: 600
$rate->extractTax(10600);   // int: 600
```

### TaxClass

```php
use AIArmada\Tax\Models\TaxClass;

// Get ordered list
$classes = TaxClass::active()->ordered()->get();

// Find by slug
$class = TaxClass::findBySlug('reduced');

// Get default
$default = TaxClass::getDefault();
```

## Multi-Tenancy

Enable owner scoping:

```php
// config/tax.php
'features' => [
    'owner' => [
        'enabled' => true,
        'include_global' => false,
    ],
],
```

All queries are automatically scoped to the current owner:

**HTTP Contexts:**
```php
use AIArmada\CommerceSupport\Support\OwnerContext;

// Middleware sets current tenant automatically
// All queries scoped to tenant
$zones = TaxZone::all(); // Only this tenant's zones
$result = Tax::calculateTax(10000, 'standard'); // Uses tenant's rates
```

**Non-HTTP Contexts (Jobs, Commands):**
```php
use AIArmada\CommerceSupport\Support\OwnerContext;

// For queued jobs or console commands, use withOwner()
OwnerContext::withOwner($tenant, function () {
    $zones = TaxZone::all(); // Only this tenant's zones
    $result = Tax::calculateTax(10000, 'standard'); // Uses tenant's rates
});
```

### Manual Scoping

```php
use AIArmada\Tax\Support\TaxOwnerScope;

// Apply to a query
$query = TaxOwnerScope::applyToOwnedQuery(TaxZone::query());

// Check if enabled
if (TaxOwnerScope::isEnabled()) {
    $owner = TaxOwnerScope::resolveOwner();
}
```

## Integration Example

Complete checkout integration:

```php
use AIArmada\Tax\Facades\Tax;
use AIArmada\Tax\Data\TaxResultData;

class CheckoutService
{
    public function calculateTotals(Cart $cart): array
    {
        $context = [
            'shipping_address' => $cart->shippingAddress?->toArray() ?? [],
            'billing_address' => $cart->billingAddress?->toArray() ?? [],
            'customer_id' => $cart->customer_id,
            'customer_type' => Customer::class,
        ];

        $subtotal = 0;
        $totalTax = 0;
        $lineItems = [];

        foreach ($cart->items as $item) {
            $taxClass = $item->product->tax_class ?? 'standard';
            
            $taxResult = Tax::calculateTax(
                $item->total,
                $taxClass,
                null,
                $context
            );

            $subtotal += $item->total;
            $totalTax += $taxResult->taxAmount;

            $lineItems[] = [
                'product_id' => $item->product_id,
                'amount' => $item->total,
                'tax' => $taxResult->taxAmount,
                'tax_rate' => $taxResult->ratePercentage,
                'tax_name' => $taxResult->rateName,
            ];
        }

        // Shipping tax
        $shippingTax = Tax::calculateShippingTax(
            $cart->shipping_amount,
            null,
            $context
        );

        return [
            'subtotal' => $subtotal,
            'tax' => $totalTax,
            'shipping' => $cart->shipping_amount,
            'shipping_tax' => $shippingTax->taxAmount,
            'total' => $subtotal + $totalTax + $cart->shipping_amount + $shippingTax->taxAmount,
            'line_items' => $lineItems,
        ];
    }
}
```
