<?php

declare(strict_types=1);

namespace AIArmada\Tax\States\TaxExemptionState;

final class RejectedState extends TaxExemptionState
{
    public static string $name = 'rejected';

    public function label(): string
    {
        return 'Rejected';
    }

    public function color(): string
    {
        return 'danger';
    }

    public function icon(): string
    {
        return 'heroicon-o-x-circle';
    }

    public function isTerminal(): bool
    {
        return true;
    }
}
