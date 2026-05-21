<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions;

use AIArmada\Tax\Enums\ExemptionStatus;
use AIArmada\Tax\Models\TaxExemption;

final class RejectExemptionAction
{
    public function execute(TaxExemption $exemption, string $reason): TaxExemption
    {
        $exemption->status = ExemptionStatus::Rejected;
        $exemption->rejection_reason = $reason;
        $exemption->save();

        return $exemption;
    }
}
