<?php

declare(strict_types=1);

namespace AIArmada\Tax\Services\ZoneResolver;

use AIArmada\Tax\Contracts\TaxZoneResolverInterface;
use AIArmada\Tax\Exceptions\TaxZoneNotFoundException;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Support\TaxOwnerScope;

final class DefaultZoneResolver implements TaxZoneResolverInterface
{
    private ?string $fallbackZoneId;

    private string $unknownBehavior;

    public function __construct(?string $fallbackZoneId = null, ?string $unknownBehavior = null)
    {
        $this->fallbackZoneId = $fallbackZoneId ?? config('tax.features.zone_resolution.fallback_zone_id');
        $this->unknownBehavior = $unknownBehavior ?? (string) config('tax.features.zone_resolution.unknown_zone_behavior', 'default');
    }

    public function resolve(?string $zoneId, array $context): ?TaxZone
    {
        $defaultZone = TaxOwnerScope::applyToOwnedQuery(TaxZone::query())
            ->default()
            ->active()
            ->first();

        if ($defaultZone !== null) {
            return $defaultZone;
        }

        if ($this->fallbackZoneId !== null) {
            $fallbackZone = TaxOwnerScope::applyToOwnedQuery(TaxZone::query())
                ->whereKey($this->fallbackZoneId)
                ->first();

            if ($fallbackZone !== null) {
                return $fallbackZone;
            }
        }

        return null;
    }

    public function handleUnknown(): TaxZone
    {
        return match ($this->unknownBehavior) {
            'error' => throw new TaxZoneNotFoundException('No tax zone could be resolved'),
            default => TaxZone::zeroRate(),
        };
    }
}
