<?php

declare(strict_types=1);

namespace AIArmada\Tax\Services\RateApplier;

use AIArmada\Tax\Contracts\TaxRateApplierInterface;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use Illuminate\Database\Eloquent\Collection;

final class StandardRateApplier implements TaxRateApplierInterface
{
    private bool $roundAtSubtotal;

    public function __construct(?bool $roundAtSubtotal = null)
    {
        $this->roundAtSubtotal = $roundAtSubtotal ?? (bool) config('tax.defaults.round_at_subtotal', true);
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

            if ($this->roundAtSubtotal) {
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

        foreach ($compoundRates as $rate) {
            $compoundBase = $pricesIncludeTax ? $amountInCents : ($amountInCents + $totalTax);
            $taxAmount = $rate->calculateTax($compoundBase);

            if ($this->roundAtSubtotal) {
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

        $primaryRate = $rates->first() ?? TaxRate::zeroRate('standard', new TaxZone);

        return [
            'total' => $totalTax,
            'primary_rate' => $primaryRate,
            'breakdown' => $breakdown,
        ];
    }
}
