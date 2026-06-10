<?php

declare(strict_types=1);

namespace AIArmada\Tax\Enums;

enum ZoneType: string
{
    case Country = 'country';
    case State = 'state';
    case Postcode = 'postcode';

    public function label(): string
    {
        return match ($this) {
            self::Country => 'Country',
            self::State => 'State',
            self::Postcode => 'Postcode',
        };
    }
}
