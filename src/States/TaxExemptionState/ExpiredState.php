<?php

declare(strict_types=1);

namespace AIArmada\Tax\States\TaxExemptionState;

final class ExpiredState extends TaxExemptionState
{
    public static string $name = 'expired';

    public function label(): string
    {
        return 'Expired';
    }

    public function color(): string
    {
        return 'gray';
    }

    public function icon(): string
    {
        return 'heroicon-o-clock';
    }

    public function isTerminal(): bool
    {
        return true;
    }
}
