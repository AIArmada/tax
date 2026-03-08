<?php

declare(strict_types=1);

namespace AIArmada\Tax\Data;

use Spatie\LaravelData\Data;

/**
 * Data Transfer Object representing a tax calculation result.
 */
class TaxResultData extends Data
{
    /**
     * @param  array<int, array{name: string, rate: int, amount: int, is_compound: bool}>  $breakdown
     */
    public function __construct(
        public int $taxAmount,
        public string $rateId,
        public string $rateName,
        public int $ratePercentage,
        public string $zoneId,
        public string $zoneName,
        public bool $includedInPrice = false,
        public ?string $exemptionReason = null,
        public array $breakdown = [],
    ) {}

    /**
     * Check if the result is tax-exempt.
     */
    public function isExempt(): bool
    {
        return $this->exemptionReason !== null || $this->ratePercentage === 0;
    }

    /**
     * Get the formatted tax amount.
     */
    public function getFormattedAmount(string $currency = 'RM'): string
    {
        return $currency . ' ' . number_format($this->taxAmount / 100, 2);
    }

    /**
     * Get the rate as a formatted percentage.
     */
    public function getFormattedRate(): string
    {
        return number_format($this->ratePercentage / 100, 2) . '%';
    }

    /**
     * Get a summary of the tax calculation.
     */
    public function getSummary(): string
    {
        if ($this->isExempt()) {
            return $this->exemptionReason ?? 'Tax Exempt';
        }

        return sprintf(
            '%s (%s)',
            $this->rateName,
            $this->getFormattedRate()
        );
    }

    /**
     * Check if this result has compound taxes.
     */
    public function hasCompoundTaxes(): bool
    {
        return count($this->breakdown) > 1;
    }
}
