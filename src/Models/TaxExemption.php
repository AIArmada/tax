<?php

declare(strict_types=1);

namespace AIArmada\Tax\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Tax\Database\Factories\TaxExemptionFactory;
use AIArmada\Tax\Enums\ExemptionStatus;
use AIArmada\Tax\Support\TaxOwnerScope;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

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

    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'tax.features.owner';

    use LogsActivity;

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
     * @var array<string, string>
     */
    protected $casts = [
        'status' => ExemptionStatus::class,
        'verified_at' => 'datetime',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ExemptionStatus::Pending,
    ];

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
                $exemption->assignOwner($owner);
            }

            if (! $exemption->belongsToOwner($owner)) {
                throw new AuthorizationException('Cannot write tax exemptions outside the current owner scope.');
            }

            if ($exemption->tax_zone_id !== null) {
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
        $now = now();

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

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function isActive(): bool
    {
        if ($this->status !== ExemptionStatus::Approved) {
            return false;
        }

        if ($this->starts_at && $this->starts_at > now()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at < now()) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at < now();
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
        $this->status = ExemptionStatus::Approved;
        $this->verified_at = now();
        $this->save();

        return $this;
    }

    public function reject(string $reason): self
    {
        $this->status = ExemptionStatus::Rejected;
        $this->rejection_reason = $reason;
        $this->save();

        return $this;
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
