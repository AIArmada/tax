<?php

declare(strict_types=1);

namespace AIArmada\Tax\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * General tax settings for the commerce platform.
 */
class TaxSettings extends Settings
{
    /**
     * Whether tax calculation is enabled.
     */
    public bool $enabled;

    /**
     * Default tax rate percentage (e.g., 6 for 6%).
     */
    public float $defaultTaxRate;

    /**
     * Default tax name/label.
     */
    public string $defaultTaxName;

    /**
     * Whether prices are inclusive of tax by default.
     */
    public bool $pricesIncludeTax;

    /**
     * Whether to calculate tax based on shipping address.
     */
    public bool $taxBasedOnShippingAddress;

    /**
     * Whether digital goods are taxable.
     */
    public bool $digitalGoodsTaxable;

    /**
     * Whether shipping is taxable.
     */
    public bool $shippingTaxable;

    /**
     * Tax ID label (e.g., 'VAT Number', 'GST Number', 'SST Number').
     */
    public string $taxIdLabel;

    /**
     * Whether to validate customer tax IDs.
     */
    public bool $validateTaxIds;

    /**
     * Tax exemption certificate required for B2B.
     */
    public bool $requireExemptionCertificate;

    /**
     * Get the settings group name.
     */
    public static function group(): string
    {
        return 'tax';
    }
}
