<?php

declare(strict_types=1);

namespace AIArmada\Tax\Services;

use AIArmada\Tax\Contracts\TaxCalculatorInterface;
use AIArmada\Tax\Data\TaxResultData;
use AIArmada\Tax\Events\TaxCalculated;
use AIArmada\Tax\Events\TaxExemptionApplied;
use AIArmada\Tax\Events\TaxZoneResolved;
use AIArmada\Tax\Exceptions\TaxZoneNotFoundException;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Settings\TaxSettings;
use AIArmada\Tax\Settings\TaxZoneSettings;
use AIArmada\Tax\Support\TaxOwnerScope;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class TaxCalculator implements TaxCalculatorInterface
{
    /**
     * Calculate tax for an amount.
     *
     * @param  array<string, mixed>  $context
     */
    public function calculateTax(
        int $amountInCents,
        string $taxClass = 'standard',
        ?string $zoneId = null,
        array $context = []
    ): TaxResultData {
        if (! $this->isTaxEnabled()) {
            return $this->createZeroResult($zoneId);
        }

        // Add zone ID to context for exemption checking
        $context['zone_id'] = $zoneId;

        // Check for exemption first
        $exemption = $this->checkExemption($context);
        if ($exemption) {
            TaxExemptionApplied::dispatch($exemption, $amountInCents, $zoneId, $context);

            return $this->createExemptResult($exemption, $zoneId);
        }

        // Resolve zone
        $zone = $this->resolveZone($zoneId, $context);

        TaxZoneResolved::dispatch($zone, $zoneId, $context);

        // Get applicable rates (including compound)
        $rates = $this->getRates($taxClass, $zone, $context['is_shipping'] ?? false);

        if ($rates->isEmpty()) {
            return $this->createZeroResult($zone->id);
        }

        // Calculate tax (with compound support)
        $pricesIncludeTax = $this->getPricesIncludeTax();
        $result = $this->calculateWithRates($amountInCents, $rates, $pricesIncludeTax);

        $taxResult = new TaxResultData(
            taxAmount: $result['total'],
            rateId: $result['primary_rate']->id,
            rateName: $result['primary_rate']->name,
            ratePercentage: $result['primary_rate']->rate,
            zoneId: $zone->id,
            zoneName: $zone->name,
            includedInPrice: $pricesIncludeTax,
            breakdown: $result['breakdown'],
        );

        TaxCalculated::dispatch($taxResult, $amountInCents, $taxClass, $zoneId, $context);

        return $taxResult;
    }

    /**
     * Calculate tax for shipping.
     */
    public function calculateShippingTax(int $shippingAmountInCents, ?string $zoneId = null, array $context = []): TaxResultData
    {
        if (! $this->isShippingTaxable()) {
            return $this->createZeroResult($zoneId);
        }

        // Mark context as shipping to filter rates properly
        $context['is_shipping'] = true;

        // Use standard tax class for shipping
        return $this->calculateTax($shippingAmountInCents, 'standard', $zoneId, $context);
    }

    /**
     * Resolve the tax zone.
     *
     * @param  array<string, mixed>  $context
     */
    protected function resolveZone(?string $zoneId, array $context): TaxZone
    {
        // If zone ID provided, use it
        if ($zoneId) {
            $zone = TaxOwnerScope::applyToOwnedQuery(TaxZone::query())
                ->whereKey($zoneId)
                ->first();
            if ($zone) {
                return $zone;
            }
        }

        // Try to resolve from address in context
        if ($this->useCustomerAddressForZoneResolution()) {
            $addressPriority = $this->getAddressPriority();
            $address = $context["{$addressPriority}_address"] ?? $context['address'] ?? null;

            if ($address) {
                $zone = $this->findZoneByAddress(
                    $address['country'] ?? 'MY',
                    $address['state'] ?? null,
                    $address['postcode'] ?? null
                );

                if ($zone) {
                    return $zone;
                }
            }
        }

        // Use default zone
        $defaultZone = TaxOwnerScope::applyToOwnedQuery(TaxZone::query())
            ->default()
            ->active()
            ->first();
        if ($defaultZone) {
            return $defaultZone;
        }

        $fallbackZoneId = $this->getFallbackZoneId();
        if ($fallbackZoneId) {
            $fallbackZone = TaxOwnerScope::applyToOwnedQuery(TaxZone::query())
                ->whereKey($fallbackZoneId)
                ->first();
            if ($fallbackZone) {
                return $fallbackZone;
            }
        }

        // Handle unknown zone based on config
        return match ($this->getUnknownZoneBehavior()) {
            'zero' => TaxZone::zeroRate(),
            'error' => throw new TaxZoneNotFoundException('No tax zone could be resolved'),
            default => TaxZone::zeroRate(),
        };
    }

    /**
     * Find zone by address.
     */
    protected function findZoneByAddress(string $country, ?string $state, ?string $postcode): ?TaxZone
    {
        return TaxOwnerScope::applyToOwnedQuery(TaxZone::forAddress($country, $state, $postcode))
            ->get()
            ->first(fn (TaxZone $zone) => $zone->matchesAddress($country, $state, $postcode));
    }

    /**
     * Get all applicable tax rates for a class and zone.
     *
     * @return Collection<int, TaxRate>
     */
    protected function getRates(string $taxClass, TaxZone $zone, bool $isShipping = false): Collection
    {
        $query = TaxOwnerScope::applyToOwnedQuery(TaxRate::query())
            ->where('zone_id', $zone->id)
            ->where('tax_class', $taxClass)
            ->active()
            ->orderBy('is_compound', 'asc') // Non-compound first
            ->orderBy('priority', 'desc');

        // Filter for shipping rates if calculating shipping tax
        if ($isShipping) {
            $query->where('is_shipping', true);
        }

        return $query->get();
    }

    /**
     * Calculate tax with multiple rates (compound support).
     *
     * @param  Collection<int, TaxRate>  $rates
     * @return array{total: int, primary_rate: TaxRate, breakdown: array<int, array{name: string, rate: int, amount: int, is_compound: bool}>}
     */
    protected function calculateWithRates(
        int $amountInCents,
        Collection $rates,
        bool $pricesIncludeTax
    ): array {
        $breakdown = [];
        $totalTax = 0;

        // Separate compound and non-compound rates
        $nonCompoundRates = $rates->where('is_compound', false);
        $compoundRates = $rates->where('is_compound', true);

        // Calculate non-compound taxes first
        foreach ($nonCompoundRates as $rate) {
            $taxAmount = $pricesIncludeTax
                ? $rate->extractTax($amountInCents)
                : $rate->calculateTax($amountInCents);

            if (config('tax.defaults.round_at_subtotal', true)) {
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

        // Calculate compound taxes on (base + previous taxes)
        foreach ($compoundRates as $rate) {
            $compoundBase = $pricesIncludeTax ? $amountInCents : ($amountInCents + $totalTax);
            $taxAmount = $rate->calculateTax($compoundBase);

            if (config('tax.defaults.round_at_subtotal', true)) {
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

    /**
     * Check for tax exemption.
     *
     * @param  array<string, mixed>  $context
     */
    protected function checkExemption(array $context): ?TaxExemption
    {
        if (! config('tax.features.exemptions.enabled', true)) {
            return null;
        }

        $customerId = $context['customer_id'] ?? null;
        $customerType = $context['customer_type'] ?? 'App\\Models\\Customer';

        if (! $customerId) {
            return null;
        }

        $zoneId = $context['zone_id'] ?? null;

        $query = TaxOwnerScope::applyToOwnedQuery(TaxExemption::query())
            ->where('exemptable_id', $customerId)
            ->where('exemptable_type', $customerType)
            ->active()
            ->forZone($zoneId);

        return $query->first();
    }

    /**
     * Create an exempt result.
     */
    protected function createExemptResult(TaxExemption $exemption, ?string $zoneId): TaxResultData
    {
        $zone = null;
        if ($zoneId !== null) {
            $zone = TaxOwnerScope::applyToOwnedQuery(TaxZone::query())
                ->whereKey($zoneId)
                ->first();
        }

        if ($zone === null) {
            $zone = TaxZone::zeroRate();
        }

        return new TaxResultData(
            taxAmount: 0,
            rateId: 'exempt',
            rateName: 'Tax Exempt',
            ratePercentage: 0,
            zoneId: $zone->id,
            zoneName: $zone->name,
            includedInPrice: false,
            exemptionReason: $exemption->reason,
        );
    }

    /**
     * Create a zero-tax result.
     */
    protected function createZeroResult(?string $zoneId): TaxResultData
    {
        $zone = null;

        if ($zoneId !== null) {
            $zone = TaxOwnerScope::applyToOwnedQuery(TaxZone::query())
                ->whereKey($zoneId)
                ->first();
        }

        $zone ??= TaxZone::zeroRate();

        return new TaxResultData(
            taxAmount: 0,
            rateId: 'zero',
            rateName: 'No Tax',
            ratePercentage: 0,
            zoneId: $zone->id,
            zoneName: $zone->name,
            includedInPrice: false,
        );
    }

    private function isTaxEnabled(): bool
    {
        $settings = $this->getTaxSettings();
        if ($settings) {
            return $settings->enabled;
        }

        return (bool) config('tax.features.enabled', true);
    }

    private function getPricesIncludeTax(): bool
    {
        $settings = $this->getTaxSettings();
        if ($settings) {
            return $settings->pricesIncludeTax;
        }

        return (bool) config('tax.defaults.prices_include_tax', false);
    }

    private function isShippingTaxable(): bool
    {
        $settings = $this->getTaxSettings();
        if ($settings) {
            return $settings->shippingTaxable;
        }

        return (bool) config('tax.defaults.calculate_tax_on_shipping', true);
    }

    private function useCustomerAddressForZoneResolution(): bool
    {
        $settings = $this->getTaxZoneSettings();
        if ($settings) {
            return $settings->autoDetectZone;
        }

        return (bool) config('tax.features.zone_resolution.use_customer_address', true);
    }

    private function getAddressPriority(): string
    {
        $settings = $this->getTaxSettings();
        if ($settings) {
            return $settings->taxBasedOnShippingAddress ? 'shipping' : 'billing';
        }

        return (string) config('tax.features.zone_resolution.address_priority', 'shipping');
    }

    private function getUnknownZoneBehavior(): string
    {
        $settings = $this->getTaxZoneSettings();
        if ($settings) {
            return $settings->fallbackBehavior;
        }

        return (string) config('tax.features.zone_resolution.unknown_zone_behavior', 'default');
    }

    private function getFallbackZoneId(): ?string
    {
        $settings = $this->getTaxZoneSettings();
        if ($settings) {
            return $settings->defaultZoneId;
        }

        /** @var string|null $fallbackZoneId */
        $fallbackZoneId = config('tax.features.zone_resolution.fallback_zone_id');

        return $fallbackZoneId;
    }

    private function getTaxSettings(): ?TaxSettings
    {
        try {
            /** @var TaxSettings $settings */
            $settings = app(TaxSettings::class);

            return $settings;
        } catch (Throwable) {
            return null;
        }
    }

    private function getTaxZoneSettings(): ?TaxZoneSettings
    {
        try {
            /** @var TaxZoneSettings $settings */
            $settings = app(TaxZoneSettings::class);

            return $settings;
        } catch (Throwable) {
            return null;
        }
    }
}
