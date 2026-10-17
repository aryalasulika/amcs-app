<?php

namespace Database\Factories;

use App\Models\Movement;
use App\Models\ParkingStand;
use App\Models\StandReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StandReservation>
 */
class StandReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'movement_id' => Movement::factory(),
            'parking_stand_id' => ParkingStand::factory(),
            'start_time' => now(),
            'end_time' => now()->addHour(),
            'status' => 'ACTIVE',
        ];
    }
}
