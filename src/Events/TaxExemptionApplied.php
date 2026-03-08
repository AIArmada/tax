<?php

declare(strict_types=1);

namespace AIArmada\Tax\Events;

use AIArmada\Tax\Models\TaxExemption;
use Illuminate\Foundation\Events\Dispatchable;

final class TaxExemptionApplied
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly TaxExemption $exemption,
        public readonly int $amountInCents,
        public readonly ?string $zoneId,
        public readonly array $context = [],
    ) {}
}
