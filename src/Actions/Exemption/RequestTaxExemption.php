<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions\Exemption;

use AIArmada\Tax\Enums\ExemptionStatus;
use AIArmada\Tax\Models\TaxExemption;

final class RequestTaxExemption
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes): TaxExemption
    {
        $attributes['status'] ??= ExemptionStatus::Pending;

        $exemption = new TaxExemption($attributes);
        $exemption->save();

        return $exemption;
    }
}
