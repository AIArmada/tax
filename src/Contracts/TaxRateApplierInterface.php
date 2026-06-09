<?php

declare(strict_types=1);

namespace AIArmada\Tax\Contracts;

use AIArmada\Tax\Models\TaxRate;
use Illuminate\Database\Eloquent\Collection;

interface TaxRateApplierInterface
{
    /**
     * Apply rates to an amount and return the calculated tax.
     *
     * @param  Collection<int, TaxRate>  $rates
     * @return array{total: int, primary_rate: TaxRate, breakdown: array<int, array{name: string, rate: int, amount: int, is_compound: bool}>}
     */
    public function apply(int $amountInCents, Collection $rates, bool $pricesIncludeTax): array;
}
