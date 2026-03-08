<?php

declare(strict_types=1);

namespace AIArmada\Tax\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Tax\Database\Factories\TaxClassFactory;
use AIArmada\Tax\Support\TaxOwnerScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Represents a tax class for categorizing products.
 *
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_default
 * @property bool $is_active
 * @property int $position
 */
class TaxClass extends Model
{
    /** @use HasFactory<TaxClassFactory> */
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
        'slug',
        'description',
        'is_default',
        'is_active',
        'position',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'position' => 'integer',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_default' => false,
        'is_active' => true,
        'position' => 0,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $class): void {
            if (! TaxOwnerScope::isEnabled()) {
                return;
            }

            $owner = TaxOwnerScope::resolveOwner();

            if ($owner === null) {
                if ($class->owner_type !== null || $class->owner_id !== null) {
                    throw new AuthorizationException('Cannot write owned tax classes without an owner context.');
                }

                return;
            }

            if ($class->owner_type === null && $class->owner_id === null) {
                $class->assignOwner($owner);
            }

            if (! $class->belongsToOwner($owner)) {
                throw new AuthorizationException('Cannot write tax classes outside the current owner scope.');
            }
        });
    }

    // =========================================================================
    // STATIC HELPERS
    // =========================================================================

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): TaxClassFactory
    {
        return TaxClassFactory::new();
    }

    /**
     * Get the default tax class.
     */
    public static function getDefault(): ?self
    {
        /** @var self|null $class */
        $class = TaxOwnerScope::applyToOwnedQuery(static::query())
            ->default()
            ->first();

        return $class;
    }

    /**
     * Get a tax class by slug.
     */
    public static function findBySlug(string $slug): ?self
    {
        /** @var self|null $class */
        $class = TaxOwnerScope::applyToOwnedQuery(static::query())
            ->where('slug', $slug)
            ->first();

        return $class;
    }

    public function getTable(): string
    {
        return (string) config('tax.database.tables.tax_classes', 'tax_classes');
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
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position', 'asc');
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
    // ACTIVITY LOG
    // =========================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'is_default', 'is_active'])
            ->logOnlyDirty()
            ->useLogName('tax');
    }
}
