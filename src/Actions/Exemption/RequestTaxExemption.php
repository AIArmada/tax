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
        $exemption = new TaxExemption([
            'exemptable_id' => $attributes['exemptable_id'] ?? null,
            'exemptable_type' => $attributes['exemptable_type'] ?? null,
            'tax_zone_id' => $attributes['tax_zone_id'] ?? null,
            'reason' => $attributes['reason'] ?? null,
            'certificate_number' => $attributes['certificate_number'] ?? null,
            'document_path' => $attributes['document_path'] ?? null,
            'starts_at' => $attributes['starts_at'] ?? null,
            'expires_at' => $attributes['expires_at'] ?? null,
            'status' => PendingState::class,
        ]);
        $exemption->save();

        return $exemption;
    }
}
