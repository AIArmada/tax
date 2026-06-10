<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions\Exemption;

use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\States\TaxExemptionState\ApprovedState;

final class ApproveExemptionAction
{
    public function execute(TaxExemption $exemption): TaxExemption
    {
        $exemption->status->transitionTo(ApprovedState::class);
        $exemption->verified_at ??= now();
        $exemption->save();

        return $exemption;
    }
}
