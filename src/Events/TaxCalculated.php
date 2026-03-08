<?php

declare(strict_types=1);

namespace AIArmada\Tax\Events;

use AIArmada\Tax\Data\TaxResultData;
use Illuminate\Foundation\Events\Dispatchable;

final class TaxCalculated
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly TaxResultData $result,
        public readonly int $amountInCents,
        public readonly string $taxClass,
        public readonly ?string $zoneId,
        public readonly array $context = [],
    ) {}
}
