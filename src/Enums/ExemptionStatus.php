<?php

declare(strict_types=1);

namespace AIArmada\Tax\Enums;

enum ExemptionStatus: string
{
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Review',
            self::UnderReview => 'Under Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Expired => 'Expired',
            self::Revoked => 'Revoked',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::UnderReview => 'info',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Expired => 'gray',
            self::Revoked => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::UnderReview => 'heroicon-o-magnifying-glass',
            self::Approved => 'heroicon-o-check-circle',
            self::Rejected => 'heroicon-o-x-circle',
            self::Expired => 'heroicon-o-clock',
            self::Revoked => 'heroicon-o-minus-circle',
        };
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isApproved(): bool
    {
        return $this === self::Approved;
    }

    public function isRejected(): bool
    {
        return $this === self::Rejected;
    }

    public function isExpired(): bool
    {
        return $this === self::Expired;
    }

    public function isRevoked(): bool
    {
        return $this === self::Revoked;
    }
}
