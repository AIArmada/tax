<?php

declare(strict_types=1);

namespace AIArmada\Tax\Contracts;

use AIArmada\Tax\Data\TaxResultData;

/**
 * Contract for tax calculation implementations.
 *
 * Allows swapping the default calculator with external services
 * like TaxJar, Avalara, or Vertex.
 */
interface TaxCalculatorInterface
{
    /**
     * Calculate tax for an amount.
     *
     * @param  int  $amountInCents  The amount in minor units (cents)
     * @param  string  $taxClass  The tax class (e.g., 'standard', 'reduced', 'zero')
     * @param  string|null  $zoneId  Optional specific zone ID
     * @param  array<string, mixed>  $context  Additional context (addresses, customer info)
     */
    public function calculateTax(
        int $amountInCents,
        string $taxClass = 'standard',
        ?string $zoneId = null,
        array $context = []
    ): TaxResultData;

    /**
     * Calculate tax for shipping.
     *
     * @param  int  $shippingAmountInCents  The shipping amount in minor units
     * @param  string|null  $zoneId  Optional specific zone ID
     * @param  array<string, mixed>  $context  Additional context
     */
    public function calculateShippingTax(
        int $shippingAmountInCents,
        ?string $zoneId = null,
        array $context = []
    ): TaxResultData;
}
