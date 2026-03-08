<?php

declare(strict_types=1);

namespace AIArmada\Tax\Database\Factories;

use AIArmada\Tax\Models\TaxClass;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TaxClass>
 */
class TaxClassFactory extends Factory
{
    protected $model = TaxClass::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->randomElement(['Standard', 'Reduced', 'Zero-Rated', 'Exempt', 'Luxury', 'Digital']);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->optional()->sentence(),
            'is_default' => false,
            'is_active' => true,
            'position' => 0,
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

    public function standard(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Standard',
            'slug' => 'standard',
            'description' => 'Standard tax rate for most goods',
        ]);
    }

    public function reduced(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Reduced',
            'slug' => 'reduced',
            'description' => 'Reduced tax rate for essential goods',
        ]);
    }

    public function zeroRated(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Zero-Rated',
            'slug' => 'zero-rated',
            'description' => 'Zero-rated goods (exports, basic necessities)',
        ]);
    }

    public function exempt(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Exempt',
            'slug' => 'exempt',
            'description' => 'Tax exempt goods and services',
        ]);
    }

    public function withPosition(int $position): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => $position,
        ]);
    }
}
