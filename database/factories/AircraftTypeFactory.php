<?php

namespace Database\Factories;

use App\Enums\AircraftCategory;
use App\Models\AircraftType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AircraftType>
 */
class AircraftTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('AC##?')),
            'name' => fake()->word().' aircraft',
            'category' => fake()->randomElement(AircraftCategory::cases())->value,
            'wingspan' => fake()->randomFloat(2, 10, 40),
            'max_weight' => fake()->randomFloat(2, 3000, 90000),
            'active' => true,
        ];
    }
}
