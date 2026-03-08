---
title: Exemptions
---

# Tax Exemptions

The Tax package provides a comprehensive tax exemption system for customers, organizations, or any entity that should be exempt from tax.

## Overview

Tax exemptions allow you to:

- Grant tax-free status to specific customers or customer groups
- Limit exemptions to specific zones or apply globally
- Manage approval workflows (pending → approved/rejected)
- Set validity periods with start and expiration dates
- Store supporting documentation (certificates)
- Track verification status

## Creating Exemptions

### Basic Exemption

```php
use AIArmada\Tax\Models\TaxExemption;

$exemption = TaxExemption::create([
    'exemptable_id' => $customer->id,
    'exemptable_type' => Customer::class,
    'reason' => 'Non-profit organization',
    'status' => 'pending',
]);
```

### Full Exemption with Certificate

```php
$exemption = TaxExemption::create([
    'exemptable_id' => $customer->id,
    'exemptable_type' => Customer::class,
    'tax_zone_id' => $zone->id, // null = all zones
    'reason' => 'Government agency - tax exempt',
    'certificate_number' => 'GOV-2024-001234',
    'document_path' => 'tax-exemptions/gov-cert-2024.pdf',
    'status' => 'pending',
    'starts_at' => now(),
    'expires_at' => now()->addYear(),
]);
```

## Exemption Properties

| Property | Type | Description |
|----------|------|-------------|
| `exemptable_id` | string | UUID of the exempt entity |
| `exemptable_type` | string | Model class (polymorphic) |
| `tax_zone_id` | string\|null | Specific zone, or null for all |
| `reason` | string | Exemption reason |
| `certificate_number` | string\|null | Certificate/document number |
| `document_path` | string\|null | Path to uploaded certificate |
| `status` | string | `pending`, `approved`, `rejected` |
| `rejection_reason` | string\|null | Reason for rejection |
| `verified_at` | datetime\|null | When verified |
| `verified_by` | string\|null | Who verified (UUID) |
| `starts_at` | datetime\|null | When exemption begins |
| `expires_at` | datetime\|null | When exemption ends |

## Approval Workflow

### Approve an Exemption

```php
$exemption->approve();
// Sets:
// - status = 'approved'
// - verified_at = now()
```

### Reject an Exemption

```php
$exemption->reject('Certificate number is invalid');
// Sets:
// - status = 'rejected'
// - rejection_reason = 'Certificate number is invalid'
```

### Workflow Diagram

```
┌─────────────┐
│   Created   │
│  (pending)  │
└─────┬───────┘
      │
      ▼
┌─────────────────────┐
│    Review Stage     │
│ Check documentation │
└─────┬─────────┬─────┘
      │         │
      ▼         ▼
┌─────────┐ ┌──────────┐
│Approved │ │ Rejected │
└─────────┘ └──────────┘
```

## Status Helpers

```php
$exemption->isPending();   // status === 'pending'
$exemption->isApproved();  // status === 'approved'
$exemption->isRejected();  // status === 'rejected'
$exemption->isActive();    // approved + valid dates + not expired
$exemption->isExpired();   // expires_at < now
```

## Zone-Specific vs Global Exemptions

### Global Exemption (All Zones)

```php
$exemption = TaxExemption::create([
    'exemptable_id' => $customer->id,
    'exemptable_type' => Customer::class,
    'tax_zone_id' => null, // Applies to ALL zones
    'reason' => 'Government entity',
    'status' => 'approved',
]);
```

### Zone-Specific Exemption

```php
$exemption = TaxExemption::create([
    'exemptable_id' => $customer->id,
    'exemptable_type' => Customer::class,
    'tax_zone_id' => $usZone->id, // Only US zone
    'reason' => 'Reseller certificate',
    'status' => 'approved',
]);
```

### Checking Zone Coverage

```php
$exemption->appliesToZone($zoneId); // bool

// Returns true if:
// - tax_zone_id is null (global), OR
// - tax_zone_id matches the provided zone
```

## Validity Periods

### Immediate & Permanent

```php
$exemption = TaxExemption::create([
    // ... other fields
    'starts_at' => null,   // Immediate
    'expires_at' => null,  // Never expires
    'status' => 'approved',
]);
```

### Future Start Date

```php
$exemption = TaxExemption::create([
    // ... other fields
    'starts_at' => now()->addMonth(), // Starts next month
    'expires_at' => now()->addYear(),
    'status' => 'approved',
]);
```

### Renewals

```php
// Extend an existing exemption
$exemption->update([
    'expires_at' => now()->addYear(),
]);
```

## Querying Exemptions

### Active Exemptions

```php
use AIArmada\Tax\Models\TaxExemption;

// Active = approved + started + not expired
$active = TaxExemption::active()->get();
```

### By Status

```php
$pending = TaxExemption::pending()->get();
$approved = TaxExemption::approved()->get();
```

### For a Zone

```php
// Exemptions that cover a specific zone
$exemptions = TaxExemption::forZone($zoneId)->active()->get();
```

### For a Customer

```php
$exemptions = TaxExemption::query()
    ->where('exemptable_id', $customer->id)
    ->where('exemptable_type', Customer::class)
    ->active()
    ->get();
```

## Automatic Exemption Checking

When calculating tax, pass customer context to check exemptions:

```php
use AIArmada\Tax\Facades\Tax;

$result = Tax::calculateTax(10000, 'standard', $zoneId, [
    'customer_id' => $customer->id,
    'customer_type' => Customer::class,
    'zone_id' => $zoneId, // For zone-specific matching
]);

if ($result->isExempt()) {
    echo "Tax exempt: " . $result->exemptionReason;
    // taxAmount will be 0
} else {
    echo "Tax: " . $result->getFormattedAmount('RM');
}
```

### Exemption Result

When exempt:

```php
$result->taxAmount;        // 0
$result->rateId;           // 'exempt'
$result->rateName;         // 'Tax Exempt'
$result->ratePercentage;   // 0
$result->exemptionReason;  // 'Non-profit organization'
$result->isExempt();       // true
```

## Polymorphic Relationships

Exemptions can be linked to any model:

### Customers

```php
TaxExemption::create([
    'exemptable_id' => $customer->id,
    'exemptable_type' => 'AIArmada\Customers\Models\Customer',
    // ...
]);
```

### Customer Groups

```php
TaxExemption::create([
    'exemptable_id' => $group->id,
    'exemptable_type' => 'AIArmada\Customers\Models\CustomerGroup',
    // ...
]);
```

### Custom Models

```php
TaxExemption::create([
    'exemptable_id' => $organization->id,
    'exemptable_type' => 'App\Models\Organization',
    // ...
]);
```

### Accessing the Exempt Entity

```php
$exemption->exemptable; // Returns the related model
```

## Document Management

### Storing Certificates

```php
// In a controller or action
$path = $request->file('certificate')->store('tax-exemptions', 'local');

$exemption->update([
    'document_path' => $path,
]);
```

### Downloading Certificates

Use the provided action:

```php
use AIArmada\FilamentTax\Actions\DownloadTaxExemptionCertificateAction;

$action = app(DownloadTaxExemptionCertificateAction::class);

return $action->execute($exemption); // StreamedResponse
```

The action validates:
- Owner scope (multi-tenancy)
- Path security (no traversal)
- File existence

## Disabling Exemptions

To disable exemption checking entirely:

```php
// config/tax.php
'features' => [
    'exemptions' => [
        'enabled' => false,
    ],
],
```

When disabled, exemptions are never checked during tax calculation.

## Activity Logging

Exemption changes are automatically logged via Spatie Activity Log:

```php
// Logged fields:
// - reason
// - status
// - verified_at
// - starts_at
// - expires_at
```

View logs:

```php
use Spatie\Activitylog\Models\Activity;

$activities = Activity::where('log_name', 'tax')
    ->where('subject_type', TaxExemption::class)
    ->where('subject_id', $exemption->id)
    ->get();
```

## Multi-Tenancy

With owner scoping enabled, exemptions are automatically scoped:

```php
// All exemptions for current tenant
$exemptions = TaxExemption::all();

// Creating is automatically assigned to current owner
$exemption = TaxExemption::create([...]);
// owner_type and owner_id are set automatically
```

Cross-tenant access is blocked:

```php
// Throws AuthorizationException if exemption belongs to different tenant
$exemption->save();
```

## Example: Complete Exemption Flow

```php
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Facades\Tax;

// 1. Customer submits exemption request
$exemption = TaxExemption::create([
    'exemptable_id' => $customer->id,
    'exemptable_type' => Customer::class,
    'reason' => 'Registered charity - Tax ID: 12345',
    'certificate_number' => 'CHAR-2024-12345',
    'document_path' => $uploadedCertPath,
    'status' => 'pending',
    'expires_at' => now()->addYear(),
]);

// 2. Admin reviews and approves
$exemption->approve();

// 3. Customer shops - tax is automatically waived
$result = Tax::calculateTax(10000, 'standard', null, [
    'customer_id' => $customer->id,
    'customer_type' => Customer::class,
]);

$result->isExempt();       // true
$result->taxAmount;        // 0
$result->exemptionReason;  // 'Registered charity - Tax ID: 12345'

// 4. Near expiration - system alerts (via Filament widget)
// ExpiringExemptionsWidget shows exemptions expiring in 30 days

// 5. Admin renews
$exemption->update([
    'expires_at' => now()->addYear(),
]);
```
