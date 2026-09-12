<?php

declare(strict_types=1);

namespace AIArmada\Tax\Services\ZoneResolver;

use AIArmada\Tax\Contracts\TaxZoneResolverCacheInterface;
use AIArmada\Tax\Contracts\TaxZoneResolverInterface;
use AIArmada\Tax\Exceptions\TaxZoneNotFoundException;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Services\TaxOwnerScope;

final class DefaultZoneResolver implements TaxZoneResolverCacheInterface, TaxZoneResolverInterface
{
    /**
     * @var array<string, TaxZone|null>
     */
    private array $resolvedZones = [];

    private ?string $fallbackZoneId;

    private string $unknownBehavior;

    public function __construct(?string $fallbackZoneId = null, ?string $unknownBehavior = null)
    {
        $this->fallbackZoneId = $fallbackZoneId;
        $this->unknownBehavior = $unknownBehavior ?? '';
    }

    private function getFallbackZoneId(): ?string
    {
        if ($this->fallbackZoneId !== null) {
            return $this->fallbackZoneId;
        }

        return config('tax.features.zone_resolution.fallback_zone_id');
    }

    private function getUnknownBehavior(): string
    {
        if ($this->unknownBehavior !== '') {
            return $this->unknownBehavior;
        }

        return (string) config('tax.features.zone_resolution.unknown_zone_behavior', 'default');
    }

    public function resolve(?string $zoneId, array $context): ?TaxZone
    {
        $cacheKey = $this->buildCacheKey($context);

        if (array_key_exists($cacheKey, $this->resolvedZones)) {
            return $this->resolvedZones[$cacheKey];
        }

        $defaultZone = TaxOwnerScope::apply(TaxZone::query(), $context)
            ->default()
            ->active()
            ->first();

        if ($defaultZone !== null) {
            return $this->resolvedZones[$cacheKey] = $defaultZone;
        }

        $fallbackZoneId = $this->getFallbackZoneId();

        if ($fallbackZoneId !== null) {
            $fallbackZone = TaxOwnerScope::apply(TaxZone::query(), $context)
                ->whereKey($fallbackZoneId)
                ->first();

            if ($fallbackZone !== null) {
                return $this->resolvedZones[$cacheKey] = $fallbackZone;
            }
        }

        return $this->resolvedZones[$cacheKey] = null;
    }

    public function clearCache(): void
    {
        $this->resolvedZones = [];
    }

    public function handleUnknown(): ?TaxZone
    {
        return match ($this->getUnknownBehavior()) {
            'error' => throw new TaxZoneNotFoundException('No tax zone could be resolved'),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function buildCacheKey(array $context): string
    {
        $owner = TaxOwnerScope::resolve($context);

        return md5(serialize([
            'fallback_zone_id' => $this->getFallbackZoneId(),
            'owner_enabled' => (bool) config('tax.features.owner.enabled', false),
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner?->getKey(),
            'include_global' => (bool) config('tax.features.owner.include_global', false),
        ]));
    }
}
