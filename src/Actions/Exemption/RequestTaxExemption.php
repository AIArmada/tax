<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions\Exemption;

use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\States\TaxExemptionState\PendingState;

final class RequestTaxExemption
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes): TaxExemption
    {
        $attributes['status'] ??= PendingState::class;

        $exemption = new TaxExemption($attributes);
        $exemption->save();

        return $exemption;
    }
}
