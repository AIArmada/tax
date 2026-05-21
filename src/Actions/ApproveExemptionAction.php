<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions;

use AIArmada\Tax\Enums\ExemptionStatus;
use AIArmada\Tax\Models\TaxExemption;
use Carbon\CarbonImmutable;

final class ApproveExemptionAction
{
    public function execute(TaxExemption $exemption): TaxExemption
    {
        $exemption->status = ExemptionStatus::Approved;
        $exemption->verified_at = CarbonImmutable::now();
        $exemption->save();

        return $exemption;
    }
}
