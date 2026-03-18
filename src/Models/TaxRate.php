<?php

declare(strict_types=1);

namespace AIArmada\Tax\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Tax\Database\Factories\TaxRateFactory;
use AIArmada\Tax\Support\TaxOwnerScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Represents a tax rate for a specific zone and tax class.
 *
 * @property string $id
 * @property string $zone_id
 * @property string $tax_class
 * @property string $name
 * @property int $rate
 * @property bool $is_compound
 * @property int $priority
 * @property bool $is_active
 */
class TaxRate extends Model
{
    /** @use HasFactory<TaxRateFactory> */
    use HasFactory;

    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'tax.features.owner';

    use LogsActivity;

    protected $fillable = [
        'owner_type',
        'owner_id',
        'zone_id',
        'tax_class',
        'name',
        'description',
        'rate',
        'is_compound',
        'is_shipping',
        'priority',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'rate' => 'integer', // Stored as basis points (600 = 6.00%)
        'is_compound' => 'boolean',
        'is_shipping' => 'boolean',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tax_class' => 'standard',
        'is_compound' => false,
        'is_shipping' => true,
        'priority' => 0,
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $rate): void {
            if (! TaxOwnerScope::isEnabled()) {
                return;
            }

            $owner = TaxOwnerScope::resolveOwner();

            if ($owner === null) {
                if ($rate->owner_type !== null || $rate->owner_id !== null) {
                    throw new AuthorizationException('Cannot write owned tax rates without an owner context.');
                }
            } else {
                if ($rate->owner_type === null && $rate->owner_id === null) {
                    $rate->assignOwner($owner);
                }

                if (! $rate->belongsToOwner($owner)) {
                    throw new AuthorizationException('Cannot write tax rates outside the current owner scope.');
                }
            }

            $zoneExists = TaxOwnerScope::applyToOwnedQuery(TaxZone::query())
                ->whereKey($rate->zone_id)
                ->exists();

            if (! $zoneExists) {
                throw new AuthorizationException('Tax zone is not accessible in the current owner scope.');
            }
        });
    }

    // =========================================================================
    // STATIC HELPERS
    // =========================================================================

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): TaxRateFactory
    {
        return TaxRateFactory::new();
    }

    /**
     * Create a zero-rate instance.
     */
    public static function zeroRate(string $taxClass, TaxZone $zone): self
    {
        return new self([
            'zone_id' => $zone->id,
            'tax_class' => $taxClass,
            'name' => 'Zero Rate',
            'rate' => 0,
            'is_active' => true,
        ]);
    }

    public function getTable(): string
    {
        return (string) config('tax.database.tables.tax_rates', 'tax_rates');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * @return BelongsTo<TaxZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(TaxZone::class, 'zone_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForClass(Builder $query, string $taxClass): Builder
    {
        return $query->where('tax_class', $taxClass);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForZone(Builder $query, string $zoneId): Builder
    {
        return $query->where('zone_id', $zoneId);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Get the rate as a percentage (e.g., 6.00 for 6%).
     */
    public function getRatePercentage(): float
    {
        return $this->rate / 100;
    }

    /**
     * Get the rate as a decimal (e.g., 0.06 for 6%).
     */
    public function getRateDecimal(): float
    {
        return $this->rate / 10000;
    }

    /**
     * Calculate tax for an amount.
     */
    public function calculateTax(int $amountInCents): int
    {
        return (int) round($amountInCents * $this->getRateDecimal());
    }

    /**
     * Extract tax from a tax-inclusive amount.
     */
    public function extractTax(int $amountWithTaxInCents): int
    {
        $divisor = 1 + $this->getRateDecimal();
        $amountWithoutTax = $amountWithTaxInCents / $divisor;

        return (int) round($amountWithTaxInCents - $amountWithoutTax);
    }

    /**
     * Get the formatted rate display (e.g., "6.00%").
     */
    public function getFormattedRate(): string
    {
        return number_format($this->getRatePercentage(), 2) . '%';
    }

    // =========================================================================
    // ACTIVITY LOG
    // =========================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'rate', 'is_compound', 'is_active'])
            ->logOnlyDirty()
            ->useLogName('tax');
    }
}
