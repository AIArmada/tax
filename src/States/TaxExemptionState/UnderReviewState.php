<?php

declare(strict_types=1);

namespace AIArmada\Tax\States\TaxExemptionState;

final class UnderReviewState extends TaxExemptionState
{
    public static string $name = 'under_review';

    public function label(): string
    {
        return 'Under Review';
    }

    public function color(): string
    {
        return 'info';
    }

    public function icon(): string
    {
        return 'heroicon-o-magnifying-glass';
    }
}
