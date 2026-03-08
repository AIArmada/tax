<?php

declare(strict_types=1);

namespace AIArmada\Tax\Database\Factories;

use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxZone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<TaxExemption>
 */
class TaxExemptionFactory extends Factory
{
    protected $model = TaxExemption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exemptable_id' => $this->faker->uuid(),
            'exemptable_type' => 'App\\Models\\Customer',
            'tax_zone_id' => null, // Applies to all zones by default
            'reason' => $this->faker->randomElement([
                'Non-profit organization',
                'Government entity',
                'Educational institution',
                'Reseller certificate',
                'Export exemption',
            ]),
            'certificate_number' => $this->faker->optional()->regexify('[A-Z]{2}[0-9]{8}'),
            'document_path' => null,
            'status' => 'approved',
            'rejection_reason' => null,
            'verified_at' => now(),
            'verified_by' => null,
            'starts_at' => null,
            'expires_at' => null,
        ];
    }

    public function forCustomer(string $customerId, string $customerType = 'App\\Models\\Customer'): static
    {
        return $this->state(fn (array $attributes) => [
            'exemptable_id' => $customerId,
            'exemptable_type' => $customerType,
        ]);
    }

    public function forZone(TaxZone $zone): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_zone_id' => $zone->id,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'verified_at' => null,
            'verified_by' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'verified_at' => now(),
        ]);
    }

    public function rejected(string $reason = 'Invalid documentation'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'verified_at' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'expires_at' => Carbon::now()->subDay(),
        ]);
    }

    public function future(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'starts_at' => Carbon::now()->addDay(),
        ]);
    }

    public function validFor(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'starts_at' => now(),
            'expires_at' => Carbon::now()->addDays($days),
        ]);
    }

    public function withCertificate(string $number): static
    {
        return $this->state(fn (array $attributes) => [
            'certificate_number' => $number,
        ]);
    }

    public function nonprofit(): static
    {
        return $this->state(fn (array $attributes) => [
            'reason' => 'Non-profit organization',
        ]);
    }

    public function government(): static
    {
        return $this->state(fn (array $attributes) => [
            'reason' => 'Government entity',
        ]);
    }

    public function reseller(): static
    {
        return $this->state(fn (array $attributes) => [
            'reason' => 'Reseller certificate',
        ]);
    }
}
