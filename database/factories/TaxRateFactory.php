<?php

declare(strict_types=1);

namespace AIArmada\Tax\Database\Factories;

use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxRate>
 */
class TaxRateFactory extends Factory
{
    protected $model = TaxRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'zone_id' => TaxZone::factory(),
            'tax_class' => 'standard',
            'name' => $this->faker->randomElement(['SST', 'GST', 'VAT', 'Sales Tax']),
            'rate' => $this->faker->randomElement([500, 600, 700, 800, 1000]), // 5%, 6%, 7%, 8%, 10%
            'is_compound' => false,
            'is_shipping' => true,
            'priority' => 0,
            'is_active' => true,
        ];
    }

    public function forZone(TaxZone $zone): static
    {
        return $this->state(fn (array $attributes) => [
            'zone_id' => $zone->id,
        ]);
    }

    public function withRate(int $basisPoints): static
    {
        return $this->state(fn (array $attributes) => [
            'rate' => $basisPoints,
        ]);
    }

    public function compound(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_compound' => true,
        ]);
    }

    public function notShipping(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_shipping' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forClass(string $taxClass): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_class' => $taxClass,
        ]);
    }

    public function withPriority(int $priority): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $priority,
        ]);
    }

    public function sst(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'SST',
            'rate' => 600, // 6%
        ]);
    }

    public function gst(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'GST',
            'rate' => 900, // 9%
        ]);
    }

    public function vat(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'VAT',
            'rate' => 2000, // 20%
        ]);
    }

    public function zero(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Zero Rate',
            'rate' => 0,
            'tax_class' => 'zero',
        ]);
    }
}
