<?php

declare(strict_types=1);

namespace AIArmada\Tax\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Tax\Database\Factories\TaxZoneFactory;
use AIArmada\Tax\Support\TaxOwnerScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Represents a geographic tax zone (Country, State, Postcode range).
 *
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property string $type
 * @property array|null $countries
 * @property array|null $states
 * @property array|null $postcodes
 * @property int $priority
 * @property bool $is_default
 * @property bool $is_active
 */
class TaxZone extends Model
{
    /** @use HasFactory<TaxZoneFactory> */
    use HasFactory;

    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'tax.features.owner';

    use LogsActivity;

    protected $fillable = [
        'owner_type',
        'owner_id',
        'name',
        'code',
        'description',
        'type',
        'countries',
        'states',
        'postcodes',
        'priority',
        'is_default',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'countries' => 'array',
        'states' => 'array',
        'postcodes' => 'array',
        'priority' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'country',
        'priority' => 0,
        'is_default' => false,
        'is_active' => true,
    ];

    // =========================================================================
    // STATIC HELPERS
    // =========================================================================

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): TaxZoneFactory
    {
        return TaxZoneFactory::new();
    }

    /**
     * Get a zero-rate zone (for tax-free calculations).
     */
    public static function zeroRate(): self
    {
        $zone = new self;
        $zone->id = (string) Str::uuid();
        $zone->name = 'Zero Rate Zone';
        $zone->code = 'ZERO';
        $zone->is_active = true;

        return $zone;
    }

    public function getTable(): string
    {
        return (string) config('tax.database.tables.tax_zones', 'tax_zones');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * @return HasMany<TaxRate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class, 'zone_id');
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
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForAddress(Builder $query, string $country, ?string $state = null, ?string $postcode = null): Builder
    {
        return $query
            ->active()
            ->where(function (Builder $builder) use ($country): void {
                $builder
                    ->whereNull('countries')
                    ->orWhereJsonLength('countries', 0)
                    ->orWhereJsonContains('countries', $country);
            })
            ->when($state !== null, function (Builder $builder) use ($state): void {
                $builder->where(function (Builder $inner) use ($state): void {
                    $inner
                        ->whereNull('states')
                        ->orWhereJsonLength('states', 0)
                        ->orWhereJsonContains('states', $state);
                });
            })
            ->orderBy('priority', 'desc');
    }

    /**
     * Scope query to the specified owner.
     *
     * @param  Builder<static>  $query
     * @param  EloquentModel|null  $owner  The owner to scope to
     * @param  bool  $includeGlobal  Whether to include global (ownerless) records
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, ?EloquentModel $owner, bool $includeGlobal = true): Builder
    {
        if (! config('tax.features.owner.enabled', false)) {
            return $query;
        }

        $includeGlobal = $includeGlobal && (bool) config('tax.features.owner.include_global', false);

        /** @var Builder<static> $scoped */
        $scoped = $this->baseScopeForOwner($query, $owner, $includeGlobal);

        return $scoped;
    }

    // =========================================================================
    // MATCHING
    // =========================================================================

    /**
     * Check if an address matches this zone.
     */
    public function matchesAddress(string $country, ?string $state = null, ?string $postcode = null): bool
    {
        // Check country match
        if (! empty($this->countries) && ! in_array($country, $this->countries, true)) {
            return false;
        }

        // Check state match (if states are specified)
        if (! empty($this->states) && $state && ! in_array($state, $this->states, true)) {
            return false;
        }

        // Check postcode match (if postcodes are specified)
        if (! empty($this->postcodes) && $postcode) {
            $matches = false;
            foreach ($this->postcodes as $pattern) {
                if ($this->matchesPostcode($postcode, $pattern)) {
                    $matches = true;

                    break;
                }
            }
            if (! $matches) {
                return false;
            }
        }

        return true;
    }

    // =========================================================================
    // ACTIVITY LOG
    // =========================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'countries', 'states', 'is_active'])
            ->logOnlyDirty()
            ->useLogName('tax');
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::saving(function (self $zone): void {
            if (! TaxOwnerScope::isEnabled()) {
                return;
            }

            $owner = TaxOwnerScope::resolveOwner();

            if ($owner === null) {
                if ($zone->owner_type !== null || $zone->owner_id !== null) {
                    throw new AuthorizationException('Cannot write owned tax zones without an owner context.');
                }

                return;
            }

            if ($zone->owner_type === null && $zone->owner_id === null) {
                $zone->assignOwner($owner);
            }

            if (! $zone->belongsToOwner($owner)) {
                throw new AuthorizationException('Cannot write tax zones outside the current owner scope.');
            }
        });

        static::deleting(function (TaxZone $zone): void {
            if (TaxOwnerScope::isEnabled()) {
                $owner = TaxOwnerScope::resolveOwner();

                if ($owner === null) {
                    if ($zone->owner_type !== null || $zone->owner_id !== null) {
                        throw new AuthorizationException('Cannot delete owned tax zones without an owner context.');
                    }
                } elseif (! $zone->belongsToOwner($owner)) {
                    throw new AuthorizationException('Cannot delete tax zones outside the current owner scope.');
                }

                if ($zone->owner_type === null && $zone->owner_id === null) {
                    $hasOwnedRates = TaxRate::query()
                        ->withoutOwnerScope()
                        ->where('zone_id', $zone->id)
                        ->whereNotNull('owner_type')
                        ->whereNotNull('owner_id')
                        ->exists();

                    if ($hasOwnedRates) {
                        throw new AuthorizationException('Cannot delete a global tax zone while owned rates exist.');
                    }

                    TaxRate::query()
                        ->withoutOwnerScope()
                        ->where('zone_id', $zone->id)
                        ->whereNull('owner_type')
                        ->whereNull('owner_id')
                        ->delete();

                    return;
                }

                TaxRate::query()
                    ->withoutOwnerScope()
                    ->where('zone_id', $zone->id)
                    ->where('owner_type', $zone->owner_type)
                    ->where('owner_id', $zone->owner_id)
                    ->delete();

                return;
            }

            $zone->rates()->delete();
        });
    }

    /**
     * Match postcode against a pattern (supports wildcards and ranges).
     */
    protected function matchesPostcode(string $postcode, string $pattern): bool
    {
        // Exact match
        if ($postcode === $pattern) {
            return true;
        }

        // Range match (e.g., "10000-19999")
        if (str_contains($pattern, '-')) {
            [$start, $end] = explode('-', $pattern, 2);
            $numericPostcode = (int) preg_replace('/[^0-9]/', '', $postcode);

            $startNumeric = (int) preg_replace('/[^0-9]/', '', $start);
            $endNumeric = (int) preg_replace('/[^0-9]/', '', $end);

            if ($startNumeric === 0 && $endNumeric === 0) {
                return false;
            }

            return $numericPostcode >= $startNumeric && $numericPostcode <= $endNumeric;
        }

        // Wildcard match (e.g., "100*")
        if (str_contains($pattern, '*')) {
            $quoted = preg_quote($pattern, '/');
            $regex = '/^' . str_replace('\\*', '.*', $quoted) . '$/';

            return (bool) preg_match($regex, $postcode);
        }

        return false;
    }
}
