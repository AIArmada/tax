<?php

declare(strict_types=1);

namespace AIArmada\Tax\Events;

use AIArmada\Tax\Models\TaxZone;
use Illuminate\Foundation\Events\Dispatchable;

final class TaxZoneResolved
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly TaxZone $zone,
        public readonly ?string $requestedZoneId,
        public readonly array $context = [],
    ) {}
}
