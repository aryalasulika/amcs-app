<?php

namespace Database\Factories;

use App\Models\Airline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Airline>
 */
class AirlineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => fake()->company().' Air',
            'flight_prefix' => strtoupper(fake()->lexify('??')),
            'active' => true,
        ];
    }
}
