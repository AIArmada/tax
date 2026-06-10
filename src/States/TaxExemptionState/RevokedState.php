<?php

declare(strict_types=1);

namespace AIArmada\Tax\States\TaxExemptionState;

final class RevokedState extends TaxExemptionState
{
    public static string $name = 'revoked';

    public function label(): string
    {
        return 'Revoked';
    }

    public function color(): string
    {
        return 'danger';
    }

    public function icon(): string
    {
        return 'heroicon-o-minus-circle';
    }

    public function isTerminal(): bool
    {
        return true;
    }
}
