<?php

declare(strict_types=1);

namespace AIArmada\Tax\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Settings for tax zones and regional tax configuration.
 */
class TaxZoneSettings extends Settings
{
    /**
     * Whether to enable multi-zone tax calculation.
     */
    public bool $multiZoneEnabled;

    /**
     * Default tax zone ID.
     */
    public ?string $defaultZoneId;

    /**
     * Whether to auto-detect zone based on customer address.
     */
    public bool $autoDetectZone;

    /**
     * Fallback behavior when zone cannot be determined: 'default', 'zero', 'error'.
     */
    public string $fallbackBehavior;

    /**
     * Whether to support compound tax (tax on tax).
     */
    public bool $compoundTaxEnabled;

    /**
     * Whether to show tax breakdown by zone.
     */
    public bool $showTaxBreakdown;

    /**
     * Get the settings group name.
     */
    public static function group(): string
    {
        return 'tax_zones';
    }
}
