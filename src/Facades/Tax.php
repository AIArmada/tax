<?php

declare(strict_types=1);

namespace AIArmada\Tax\Facades;

use AIArmada\Tax\Contracts\TaxCalculatorInterface;
use AIArmada\Tax\Data\TaxResultData;
use Illuminate\Support\Facades\Facade;

/**
 * @method static TaxResultData calculateTax(int $amountInCents, string $taxClass = 'standard', ?string $zoneId = null, array $context = [])
 * @method static TaxResultData calculateShippingTax(int $shippingAmountInCents, ?string $zoneId = null, array $context = [])
 *
 * @see \AIArmada\Tax\Services\TaxCalculator
 */
class Tax extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TaxCalculatorInterface::class;
    }
}
