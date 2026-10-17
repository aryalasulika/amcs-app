<?php

namespace Database\Factories;

use App\Models\ParkingStand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParkingStand>
 */
class ParkingStandFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'STAND-'.fake()->unique()->numberBetween(1, 99),
            'name' => 'Parking Stand '.fake()->numberBetween(1, 99),
            'active' => true,
            'capacity_category' => 'NARROW_BODY',
            'gate_area' => 'Apron A',
            'position_x' => fake()->numberBetween(1, 100),
            'position_y' => fake()->numberBetween(1, 100),
            'notes' => null,
        ];
    }
}
