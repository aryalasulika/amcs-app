<?php

namespace Database\Seeders;

use App\Models\AircraftType;
use App\Models\ParkingStand;
use Illuminate\Database\Seeder;

class ParkingStandSeeder extends Seeder
{
    public function run(): void
    {
        // Stand 1 is limited to turboprop/light aircraft (FR: max ATR72-600).
        // Stands 2–5 use their capacity category instead of an explicit whitelist,
        // so manually entered aircraft types can still be validated by category.
        $stands = [
            [
                'code' => 'STAND-1',
                'name' => 'Parking Stand 1',
                'capacity_category' => 'TURBOPROP',
                'gate_area' => 'Apron A',
                'position_x' => 10,
                'position_y' => 50,
                'notes' => 'Maksimum ATR 72-600. Menolak A320, A321, B737-800, dan B737-900ER.',
                'allowed' => ['ATR72', 'ATR42', 'C208', 'DHC6'],
            ],
            ['code' => 'STAND-2', 'name' => 'Parking Stand 2', 'capacity_category' => 'NARROW_BODY', 'gate_area' => 'Apron A', 'position_x' => 25, 'position_y' => 50, 'allowed' => []],
            ['code' => 'STAND-3', 'name' => 'Parking Stand 3', 'capacity_category' => 'NARROW_BODY', 'gate_area' => 'Apron B', 'position_x' => 50, 'position_y' => 50, 'allowed' => []],
            ['code' => 'STAND-4', 'name' => 'Parking Stand 4', 'capacity_category' => 'NARROW_BODY', 'gate_area' => 'Apron B', 'position_x' => 75, 'position_y' => 50, 'allowed' => []],
            ['code' => 'STAND-5', 'name' => 'Parking Stand 5', 'capacity_category' => 'NARROW_BODY', 'gate_area' => 'Apron C', 'position_x' => 90, 'position_y' => 50, 'allowed' => []],
        ];

        foreach ($stands as $data) {
            $allowed = $data['allowed'];
            unset($data['allowed']);

            $stand = ParkingStand::updateOrCreate(
                ['code' => $data['code']],
                $data + ['active' => true]
            );

            $typeIds = AircraftType::whereIn('code', $allowed)->pluck('id')->all();
            $stand->aircraftTypes()->sync($typeIds);
        }
    }
}
