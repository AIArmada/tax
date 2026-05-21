<?php

declare(strict_types=1);

namespace AIArmada\Tax\Models;

use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Tax\Actions\ApproveExemptionAction;
use AIArmada\Tax\Actions\RejectExemptionAction;
use AIArmada\Tax\Database\Factories\TaxExemptionFactory;
use AIArmada\Tax\Enums\ExemptionStatus;
use AIArmada\Tax\Support\TaxOwnerScope;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Represents a tax exemption for a customer or entity.
 *
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $exemptable_id
 * @property string|null $exemptable_type
 * @property string|null $tax_zone_id
 * @property string $reason
 * @property string|null $certificate_number
 * @property string|null $document_path
 * @property ExemptionStatus $status
 * @property string|null $rejection_reason
 * @property CarbonInterface|null $verified_at
 * @property string|null $verified_by
 * @property CarbonInterface|null $starts_at
 * @property CarbonInterface|null $expires_at
 * @property-read TaxZone|null $taxZone
 * @property-read Model|null $exemptable
 */
class TaxExemption extends Model
{
    /** @use HasFactory<TaxExemptionFactory> */
    use HasFactory;

    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'tax.features.owner';

    use LogsCommerceActivity;

    protected $fillable = [
        'owner_type',
        'owner_id',
        'exemptable_id',
        'exemptable_type',
        'tax_zone_id',
        'reason',
        'certificate_number',
        'document_path',
        'status',
        'rejection_reason',
        'verified_at',
        'verified_by',
        'starts_at',
        'expires_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ExemptionStatus::Pending,
    ];

    protected function casts(): array
    {
        return [
            'status' => ExemptionStatus::class,
            'verified_at' => 'datetime',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $exemption): void {
            if (! TaxOwnerScope::isEnabled()) {
                return;
            }

            $owner = TaxOwnerScope::resolveOwner();

            if ($owner === null) {
                if ($exemption->owner_type !== null || $exemption->owner_id !== null) {
                    throw new AuthorizationException('Cannot write owned tax exemptions without an owner context.');
                }

                return;
            }

            if ($exemption->owner_type === null && $exemption->owner_id === null) {
                if ($exemption->exists) {
                    throw new AuthorizationException('Cannot mutate global tax exemptions without explicit global context.');
                }

                if ((bool) config('tax.features.owner.auto_assign_on_create', true)) {
                    $exemption->assignOwner($owner);
                }
            }

            if (! $exemption->belongsToOwner($owner)) {
                throw new AuthorizationException('Cannot write tax exemptions outside the current owner scope.');
            }

            if (
                $exemption->exemptable_id !== null
                && is_string($exemption->exemptable_type)
                && $exemption->exemptable_type !== ''
                && (
                    $exemption->isDirty('exemptable_type')
                    || $exemption->isDirty('exemptable_id')
                    || ! $exemption->exists
                )
                && class_exists($exemption->exemptable_type)
                && is_a($exemption->exemptable_type, Model::class, true)
                && in_array(HasOwner::class, class_uses_recursive($exemption->exemptable_type), true)
            ) {
                OwnerWriteGuard::findOrFailForOwner(
                    modelClass: $exemption->exemptable_type,
                    id: $exemption->exemptable_id,
                    owner: $owner,
                    includeGlobal: false,
                    message: 'Exemptable entity is not accessible in the current owner scope.',
                );
            }

            if ($exemption->tax_zone_id !== null && ($exemption->isDirty('tax_zone_id') || ! $exemption->exists)) {
                $zoneExists = TaxOwnerScope::applyToOwnedQuery(TaxZone::query())
                    ->whereKey($exemption->tax_zone_id)
                    ->exists();

                if (! $zoneExists) {
                    throw new AuthorizationException('Tax zone is not accessible in the current owner scope.');
                }
            }
        });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): TaxExemptionFactory
    {
        return TaxExemptionFactory::new();
    }

    public function getTable(): string
    {
        return (string) config('tax.database.tables.tax_exemptions', 'tax_exemptions');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * The exemptable entity (Customer, User, etc.).
     *
     * @return MorphTo<Model, $this>
     */
    public function exemptable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The tax zone this exemption applies to (null = all zones).
     *
     * @return BelongsTo<TaxZone, $this>
     */
    public function taxZone(): BelongsTo
    {
        return $this->belongsTo(TaxZone::class, 'tax_zone_id');
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
        $now = CarbonImmutable::now();

        return $query->where('status', ExemptionStatus::Approved)
            ->where(function ($q) use ($now): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', $now);
            })
            ->where(function ($q) use ($now): void {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ExemptionStatus::Pending);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ExemptionStatus::Approved);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', ExemptionStatus::Rejected);
    }

    /**
     * Scope to exemptions for a specific zone (or all zones).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForZone(Builder $query, ?string $zoneId): Builder
    {
        return $query->where(function (Builder $builder) use ($zoneId): void {
            $builder->whereNull('tax_zone_id');

            if ($zoneId !== null) {
                $builder->orWhere('tax_zone_id', $zoneId);
            }
        });
    }

    /**
     * Scope query to the specified owner, respecting the `include_global` config.
     *
     * @param  Builder<static>  $query
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
    // HELPERS
    // =========================================================================

    public function isActive(): bool
    {
        if ($this->status !== ExemptionStatus::Approved) {
            return false;
        }

        $now = CarbonImmutable::now();

        if ($this->starts_at && $this->starts_at > $now) {
            return false;
        }

        if ($this->expires_at && $this->expires_at < $now) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at < CarbonImmutable::now();
    }

    public function isPending(): bool
    {
        return $this->status === ExemptionStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === ExemptionStatus::Approved;
    }

    public function isRejected(): bool
    {
        return $this->status === ExemptionStatus::Rejected;
    }

    /**
     * Check if exemption applies to a specific zone.
     */
    public function appliesToZone(?string $zoneId): bool
    {
        // If no zone specified on exemption, it applies to all
        if ($this->tax_zone_id === null) {
            return true;
        }

        return $this->tax_zone_id === $zoneId;
    }

    public function approve(): self
    {
        return app(ApproveExemptionAction::class)->execute($this);
    }

    public function reject(string $reason): self
    {
        return app(RejectExemptionAction::class)->execute($this, $reason);
    }

    // =========================================================================
    // ACTIVITY LOG
    // =========================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['reason', 'status', 'verified_at', 'starts_at', 'expires_at'])
            ->logOnlyDirty()
            ->useLogName('tax');
    }
}
