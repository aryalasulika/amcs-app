<?php

namespace Database\Factories;

use App\Models\Movement;
use App\Models\ParkingStand;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Movement>
 */
class MovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'report_date' => now()->toDateString(),
            'flight_number' => 'IW'.fake()->numberBetween(100, 999),
            'airline_name' => 'Wings Air',
            'registration' => 'PK-'.strtoupper(fake()->lexify('???')),
            'aircraft_type_name' => 'ATR72',
            'flight_type' => 'SCHEDULED',
            'origin' => 'WAAA',
            'destination' => 'WAMW',
            'parking_stand_id' => ParkingStand::factory(),
            'state' => 'ENTERING',
            'eta' => '10:00',
            'etd' => '11:00',
            'runway' => 'RWY 04',
            'taxiway' => 'TWY A',
            'delay_minutes' => 0,
            'created_by' => User::factory(),
        ];
    }

    public function state(string $state): static
    {
        return $this->state(fn () => ['state' => $state]);
    }
}
