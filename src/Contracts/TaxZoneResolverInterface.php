<?php

declare(strict_types=1);

namespace AIArmada\Tax\Contracts;

use AIArmada\Tax\Models\TaxZone;

interface TaxZoneResolverInterface
{
    /**
     * Resolve a tax zone for the given context.
     *
     * @param  array<string, mixed>  $context
     */
    public function resolve(?string $zoneId, array $context): ?TaxZone;
}
