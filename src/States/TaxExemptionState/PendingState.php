<?php

declare(strict_types=1);

namespace AIArmada\Tax\States\TaxExemptionState;

final class PendingState extends TaxExemptionState
{
    public static string $name = 'pending';

    public function label(): string
    {
        return 'Pending Review';
    }

    public function color(): string
    {
        return 'warning';
    }

    public function icon(): string
    {
        return 'heroicon-o-clock';
    }

    public function isPending(): bool
    {
        return true;
    }
}
