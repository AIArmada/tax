<?php

declare(strict_types=1);

namespace AIArmada\Tax\Database\Factories;

use AIArmada\Tax\Models\TaxZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxZone>
 */
class TaxZoneFactory extends Factory
{
    protected $model = TaxZone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->country(),
            'code' => $this->faker->unique()->countryCode(),
            'description' => $this->faker->optional()->sentence(),
            'type' => 'country',
            'countries' => [$this->faker->countryCode()],
            'states' => null,
            'postcodes' => null,
            'priority' => 0,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forMalaysia(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Malaysia',
            'code' => 'MY',
            'countries' => ['MY'],
            'type' => 'country',
        ]);
    }

    public function forSingapore(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Singapore',
            'code' => 'SG',
            'countries' => ['SG'],
            'type' => 'country',
        ]);
    }

    /**
     * @param  array<string>  $states
     */
    public function withStates(array $states): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'state',
            'states' => $states,
        ]);
    }

    /**
     * @param  array<string>  $postcodes
     */
    public function withPostcodes(array $postcodes): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'postcode',
            'postcodes' => $postcodes,
        ]);
    }

    public function withPriority(int $priority): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $priority,
        ]);
    }
}
