<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions\Exemption;

use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\States\TaxExemptionState\RejectedState;

final class RejectExemptionAction
{
    public function execute(TaxExemption $exemption, string $reason): TaxExemption
    {
        $exemption->status->transitionTo(RejectedState::class);
        $exemption->rejection_reason = $reason;
        $exemption->save();

        return $exemption;
    }
}
