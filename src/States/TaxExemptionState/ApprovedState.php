<?php

declare(strict_types=1);

namespace AIArmada\Tax\States\TaxExemptionState;

final class ApprovedState extends TaxExemptionState
{
    public static string $name = 'approved';

    public function label(): string
    {
        return 'Approved';
    }

    public function color(): string
    {
        return 'success';
    }

    public function icon(): string
    {
        return 'heroicon-o-check-circle';
    }
}
