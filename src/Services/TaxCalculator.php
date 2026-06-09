<?php

declare(strict_types=1);

namespace AIArmada\Tax\Services;

use AIArmada\CommerceSupport\Support\MoneyNormalizer;
use AIArmada\Tax\Contracts\TaxCalculatorInterface;
use AIArmada\Tax\Contracts\TaxRateApplierInterface;
use AIArmada\Tax\Contracts\TaxZoneResolverInterface;
use AIArmada\Tax\Data\TaxResultData;
use AIArmada\Tax\Events\TaxCalculated;
use AIArmada\Tax\Events\TaxExemptionApplied;
use AIArmada\Tax\Events\TaxZoneResolved;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Settings\TaxSettings;
use AIArmada\Tax\Support\TaxOwnerScope;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class TaxCalculator implements TaxCalculatorInterface
{
    public function __construct(
        private readonly TaxZoneResolverInterface $zoneResolver,
        private readonly TaxRateApplierInterface $rateApplier,
    ) {}

    public function calculateTax(
        int $amountInCents,
        string $taxClass = 'standard',
        ?string $zoneId = null,
        array $context = []
    ): TaxResultData {
        $amountInCents = MoneyNormalizer::toCents($amountInCents);

        if (! $this->isTaxEnabled()) {
            return $this->createZeroResult($zoneId);
        }

        $context['zone_id'] = $zoneId;

        $exemption = $this->checkExemption($context);
        if ($exemption) {
            TaxExemptionApplied::dispatch($exemption, $amountInCents, $zoneId, $context);

            return $this->createExemptResult($exemption, $zoneId);
        }

        $zone = $this->zoneResolver->resolve($zoneId, $context);

        if ($zone === null) {
            return $this->createZeroResult($zoneId);
        }

        TaxZoneResolved::dispatch($zone, $zoneId, $context);

        $rates = $this->getRates($taxClass, $zone, $context['is_shipping'] ?? false);

        if ($rates->isEmpty()) {
            return $this->createZeroResult($zone->id);
        }

        $pricesIncludeTax = $this->getPricesIncludeTax();
        $result = $this->rateApplier->apply($amountInCents, $rates, $pricesIncludeTax);

        $taxResult = new TaxResultData(
            taxAmount: $result['total'],
            rateId: $result['primary_rate']->id,
            rateName: $result['primary_rate']->name,
            ratePercentage: $result['primary_rate']->rate,
            zoneId: $zone->id,
            zoneName: $zone->name,
            includedInPrice: $pricesIncludeTax,
            breakdown: $result['breakdown'],
            currency: $this->getCurrency($context),
        );

        TaxCalculated::dispatch($taxResult, $amountInCents, $taxClass, $zoneId, $context);

        return $taxResult;
    }

    public function calculateShippingTax(int $shippingAmountInCents, ?string $zoneId = null, array $context = []): TaxResultData
    {
        if (! $this->isShippingTaxable()) {
            return $this->createZeroResult($zoneId);
        }

        $context['is_shipping'] = true;

        return $this->calculateTax($shippingAmountInCents, 'standard', $zoneId, $context);
    }

    /**
     * @return Collection<int, TaxRate>
     */
    protected function getRates(string $taxClass, TaxZone $zone, bool $isShipping = false): Collection
    {
        $query = TaxOwnerScope::applyToOwnedQuery(TaxRate::query())
            ->where('zone_id', $zone->id)
            ->where('tax_class', $taxClass)
            ->active()
            ->orderBy('is_compound', 'asc')
            ->orderBy('priority', 'desc');

        if ($isShipping) {
            $query->where('is_shipping', true);
        }

        return $query->get();
    }

    protected function checkExemption(array $context): ?TaxExemption
    {
        if (! config('tax.features.exemptions.enabled', true)) {
            return null;
        }

        $customerId = $context['customer_id'] ?? null;
        $customerType = $context['customer_type'] ?? null;

        if (! $customerId) {
            return null;
        }

        $candidateTypes = [];

        if (is_string($customerType) && $customerType !== '') {
            $candidateTypes[] = $customerType;
        } else {
            if (class_exists('AIArmada\\Customers\\Models\\Customer')) {
                $candidateTypes[] = 'AIArmada\\Customers\\Models\\Customer';
            }

            $candidateTypes[] = 'App\\Models\\Customer';
            $candidateTypes[] = 'App\\Models\\User';
        }

        $candidateTypes = array_values(array_unique($candidateTypes));

        $zoneId = $context['zone_id'] ?? null;

        $query = TaxOwnerScope::applyToOwnedQuery(TaxExemption::query())
            ->where('exemptable_id', $customerId)
            ->whereIn('exemptable_type', $candidateTypes)
            ->active()
            ->forZone($zoneId);

        return $query->first();
    }

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
            currency: $this->getCurrency(),
        );
    }

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
            currency: $this->getCurrency(),
        );
    }

    private function getCurrency(array $context = []): string
    {
        return $context['currency'] ?? (string) config('tax.defaults.currency', 'MYR');
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

    private function getTaxSettings(): ?TaxSettings
    {
        try {
            return app(TaxSettings::class);
        } catch (Throwable) {
            return null;
        }
    }
}
