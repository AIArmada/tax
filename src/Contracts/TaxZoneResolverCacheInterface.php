<?php

declare(strict_types=1);

namespace AIArmada\Tax\Contracts;

interface TaxZoneResolverCacheInterface
{
    public function clearCache(): void;
}
