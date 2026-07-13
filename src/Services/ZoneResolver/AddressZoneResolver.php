<?php

declare(strict_types=1);

namespace AIArmada\Tax\Services\ZoneResolver;

use AIArmada\Tax\Contracts\TaxZoneResolverInterface;
use AIArmada\Tax\Models\TaxZone;

final class AddressZoneResolver implements TaxZoneResolverInterface
{
    private ?bool $enabled;

    private ?string $addressPriority;

    public function __construct(?bool $enabled = null, ?string $addressPriority = null)
    {
        $this->enabled = $enabled;
        $this->addressPriority = $addressPriority;
    }

    private function isEnabled(): bool
    {
        if ($this->enabled !== null) {
            return $this->enabled;
        }

        return (bool) config('tax.features.zone_resolution.use_customer_address', true);
    }

    private function getAddressPriority(): string
    {
        if ($this->addressPriority !== null) {
            return $this->addressPriority;
        }

        return (string) config('tax.features.zone_resolution.address_priority', 'shipping');
    }

    public function resolve(?string $zoneId, array $context): ?TaxZone
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $address = $context[$this->getAddressPriority() . '_address'] ?? $context['address'] ?? null;

        if ($address === null) {
            return null;
        }

        return $this->findZoneByAddress(
            $address['country_code'] ?? 'MY',
            $address['state'] ?? null,
            $address['postcode'] ?? null,
        );
    }

    private function findZoneByAddress(string $country, ?string $state, ?string $postcode): ?TaxZone
    {
        return TaxZone::forAddress($country, $state, $postcode)
            ->get()
            ->first(fn (TaxZone $zone) => $zone->matchesAddress($country, $state, $postcode));
    }
}
