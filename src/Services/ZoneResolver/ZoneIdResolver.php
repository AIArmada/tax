<?php

declare(strict_types=1);

namespace AIArmada\Tax\Services\ZoneResolver;

use AIArmada\Tax\Contracts\TaxZoneResolverInterface;
use AIArmada\Tax\Models\TaxZone;

final class ZoneIdResolver implements TaxZoneResolverInterface
{
    public function resolve(?string $zoneId, array $context): ?TaxZone
    {
        if ($zoneId === null) {
            return null;
        }

        return TaxZone::query()
            ->whereKey($zoneId)
            ->first();
    }
}
