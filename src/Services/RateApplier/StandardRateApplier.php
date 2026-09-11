<?php

declare(strict_types=1);

namespace AIArmada\Tax\Services\RateApplier;

use AIArmada\Tax\Contracts\TaxRateApplierInterface;
use Illuminate\Database\Eloquent\Collection;

final class StandardRateApplier implements TaxRateApplierInterface
{
    private bool $roundPerRate;

    public function __construct(?bool $roundPerRate = null)
    {
        $this->roundPerRate = $roundPerRate ?? (bool) config('tax.defaults.round_per_rate', true);
    }

    public function apply(int $amountInCents, Collection $rates, bool $pricesIncludeTax): array
    {
        $breakdown = [];
        $totalTax = 0;

        $nonCompoundRates = $rates->where('is_compound', false);
        $compoundRates = $rates->where('is_compound', true);

        foreach ($nonCompoundRates as $rate) {
            $taxAmount = $pricesIncludeTax
                ? $rate->extractTax($amountInCents)
                : $rate->calculateTax($amountInCents);

            if ($this->roundPerRate) {
                $taxAmount = (int) round($taxAmount);
            }

            $totalTax += $taxAmount;
            $breakdown[] = [
                'name' => $rate->name,
                'rate' => $rate->rate,
                'amount' => $taxAmount,
                'is_compound' => false,
            ];
        }

        /**
         * Compound rates build on the original taxable amount plus the tax
         * already accumulated from non-compound rates. Tax-inclusive prices
         * retain the original amount as their extraction base.
         */
        foreach ($compoundRates as $rate) {
            $compoundBase = $pricesIncludeTax ? $amountInCents : ($amountInCents + $totalTax);
            $taxAmount = $rate->calculateTax($compoundBase);

            if ($this->roundPerRate) {
                $taxAmount = (int) round($taxAmount);
            }

            $totalTax += $taxAmount;
            $breakdown[] = [
                'name' => $rate->name,
                'rate' => $rate->rate,
                'amount' => $taxAmount,
                'is_compound' => true,
            ];
        }

        return [
            'total' => $totalTax,
            'primary_rate' => $rates->first(),
            'breakdown' => $breakdown,
        ];
    }
}
