<?php

declare(strict_types=1);

namespace AIArmada\Tax\Services\ZoneResolver;

use AIArmada\Tax\Contracts\TaxZoneResolverCacheInterface;
use AIArmada\Tax\Contracts\TaxZoneResolverInterface;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Services\TaxOwnerScope;
use Illuminate\Database\Eloquent\Builder;

final class AddressZoneResolver implements TaxZoneResolverCacheInterface, TaxZoneResolverInterface
{
    /**
     * @var array<string, TaxZone|null>
     */
    private array $resolvedZones = [];

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

        $country = $address['country'] ?? 'MY';
        $state = $address['state'] ?? null;
        $postcode = $address['postcode'] ?? null;
        $cacheKey = $this->buildCacheKey($country, $state, $postcode, $context);

        if (array_key_exists($cacheKey, $this->resolvedZones)) {
            return $this->resolvedZones[$cacheKey];
        }

        return $this->resolvedZones[$cacheKey] = $this->findZoneByAddress(
            $country,
            $state,
            $postcode,
            $context,
        );
    }

    public function clearCache(): void
    {
        $this->resolvedZones = [];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function buildCacheKey(string $country, ?string $state, ?string $postcode, array $context): string
    {
        $owner = TaxOwnerScope::resolve($context);

        return md5(serialize([
            'country' => $country,
            'state' => $state,
            'postcode' => $postcode,
            'owner_enabled' => (bool) config('tax.features.owner.enabled', false),
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner?->getKey(),
            'include_global' => (bool) config('tax.features.owner.include_global', false),
        ]));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function findZoneByAddress(string $country, ?string $state, ?string $postcode, array $context): ?TaxZone
    {
        /** @var Builder<TaxZone> $query */
        $query = TaxOwnerScope::apply(TaxZone::forAddress($country, $state, $postcode), $context);

        return $query
            ->get()
            ->first(fn (TaxZone $zone) => $zone->matchesAddress($country, $state, $postcode));
    }
}
