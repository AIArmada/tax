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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
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
        $context['zone_id'] = $zoneId;
        $context['owner'] = TaxOwnerScope::resolve($context);

        if (! $this->isTaxEnabled()) {
            return $this->createZeroResult($zoneId, $context);
        }

        $exemption = $this->checkExemption($context);
        if ($exemption !== null) {
            TaxExemptionApplied::dispatch($exemption, $amountInCents, $zoneId, $context);

            return $this->createExemptResult($exemption, $zoneId, $context);
        }

        $zone = $this->zoneResolver->resolve($zoneId, $context);

        if ($zone === null) {
            return $this->createZeroResult($zoneId, $context);
        }

        TaxZoneResolved::dispatch($zone, $zoneId, $context);

        $rates = $this->getRates($taxClass, $zone, (bool) ($context['is_shipping'] ?? false), $context);

        if ($rates->isEmpty()) {
            return $this->createZeroResult($zone->id, $context);
        }

        $pricesIncludeTax = $this->getPricesIncludeTax();
        $result = $this->rateApplier->apply($amountInCents, $rates, $pricesIncludeTax);
        $primaryRate = $result['primary_rate'];

        if ($primaryRate === null) {
            return $this->createZeroResult($zone->id, $context);
        }

        $taxResult = new TaxResultData(
            taxAmount: $result['total'],
            rateId: $primaryRate->id,
            rateName: $primaryRate->name,
            ratePercentage: $primaryRate->rate,
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
            return $this->createZeroResult($zoneId, $context);
        }

        $context['is_shipping'] = true;

        return $this->calculateTax($shippingAmountInCents, 'standard', $zoneId, $context);
    }

    /**
     * @return Collection<int, TaxRate>
     */
    protected function getRates(string $taxClass, TaxZone $zone, bool $isShipping, array $context): Collection
    {
        $query = TaxRate::query()
            ->where('zone_id', $zone->id)
            ->where('tax_class', $taxClass)
            ->active()
            ->orderBy('is_compound', 'asc')
            ->orderBy('priority', 'desc');

        if ($isShipping) {
            $query->where('is_shipping', true);
        }

        /** @var Collection<int, TaxRate> $rates */
        $rates = TaxOwnerScope::apply($query, $context)->get();

        return $rates;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function checkExemption(array $context): ?TaxExemption
    {
        if (! config('tax.features.exemptions.enabled', true)) {
            return null;
        }

        $exemptableId = $context['exemptable_id'] ?? $context['customer_id'] ?? null;
        $exemptableType = $context['exemptable_type'] ?? $context['customer_type'] ?? null;
        $exemptable = $context['exemptable'] ?? $context['customer'] ?? null;

        if ($exemptable instanceof Model) {
            $exemptableId ??= $exemptable->getKey();
            $exemptableType ??= $exemptable->getMorphClass();
        }

        if ($exemptableId === null || $exemptableId === '') {
            return null;
        }

        if (! is_string($exemptableType) || $exemptableType === '') {
            throw new InvalidArgumentException(
                'Tax exemption lookups require an explicit exemptable_type or customer_type.',
            );
        }

        $zoneId = $context['zone_id'] ?? null;

        return TaxOwnerScope::apply(TaxExemption::query(), $context)
            ->where('exemptable_id', $exemptableId)
            ->where('exemptable_type', $exemptableType)
            ->active()
            ->forZone(is_string($zoneId) ? $zoneId : null)
            ->first();
    }

    /**
     * Unknown zones intentionally produce a non-persisted no-tax result. The
     * nullable identifiers distinguish that result from a configured zone or rate.
     *
     * @param  array<string, mixed>  $context
     */
    protected function createExemptResult(TaxExemption $exemption, ?string $zoneId, array $context): TaxResultData
    {
        $zone = $this->findZone($zoneId, $context);

        return new TaxResultData(
            taxAmount: 0,
            rateId: 'exempt',
            rateName: 'Tax Exempt',
            ratePercentage: 0,
            zoneId: $zone?->id,
            zoneName: $zone?->name ?? 'Unknown Zone',
            includedInPrice: false,
            exemptionReason: $exemption->reason,
            currency: $this->getCurrency($context),
        );
    }

    /**
     * Unknown zones intentionally produce a documented no-tax default rather
     * than a fabricated TaxZone or TaxRate model.
     *
     * @param  array<string, mixed>  $context
     */
    protected function createZeroResult(?string $zoneId, array $context = []): TaxResultData
    {
        $zone = $this->findZone($zoneId, $context);

        return new TaxResultData(
            taxAmount: 0,
            rateId: null,
            rateName: 'No Tax',
            ratePercentage: 0,
            zoneId: $zone?->id,
            zoneName: $zone?->name ?? 'Unknown Zone',
            includedInPrice: false,
            currency: $this->getCurrency($context),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function findZone(?string $zoneId, array $context): ?TaxZone
    {
        if ($zoneId === null) {
            return null;
        }

        return TaxOwnerScope::apply(TaxZone::query(), $context)
            ->whereKey($zoneId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $context
     */
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
