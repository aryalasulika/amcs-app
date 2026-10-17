<?php

namespace Database\Seeders;

use App\Models\AircraftType;
use Illuminate\Database\Seeder;

class AircraftTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'ATR72', 'name' => 'ATR 72-600', 'category' => 'TURBOPROP', 'wingspan' => 27.05, 'max_weight' => 23000],
            ['code' => 'ATR42', 'name' => 'ATR 42-500', 'category' => 'TURBOPROP', 'wingspan' => 24.57, 'max_weight' => 18600],
            ['code' => 'A320', 'name' => 'Airbus A320', 'category' => 'NARROW_BODY', 'wingspan' => 35.80, 'max_weight' => 77000],
            ['code' => 'A321', 'name' => 'Airbus A321', 'category' => 'NARROW_BODY', 'wingspan' => 35.80, 'max_weight' => 93000],
            ['code' => 'B737-800', 'name' => 'Boeing 737-800', 'category' => 'NARROW_BODY', 'wingspan' => 35.79, 'max_weight' => 79000],
            ['code' => 'B737-900ER', 'name' => 'Boeing 737-900ER', 'category' => 'NARROW_BODY', 'wingspan' => 35.79, 'max_weight' => 85000],
            ['code' => 'CRJ1000', 'name' => 'Bombardier CRJ1000', 'category' => 'NARROW_BODY', 'wingspan' => 26.18, 'max_weight' => 41640],
            ['code' => 'C208', 'name' => 'Cessna Grand Caravan 208', 'category' => 'LIGHT', 'wingspan' => 15.87, 'max_weight' => 3630],
            ['code' => 'DHC6', 'name' => 'De Havilland Twin Otter DHC-6', 'category' => 'TURBOPROP', 'wingspan' => 19.80, 'max_weight' => 5670],
            ['code' => 'PRIVATE_JET', 'name' => 'Private Jet (Gulfstream / Hawker)', 'category' => 'PRIVATE_JET', 'wingspan' => 28.50, 'max_weight' => 41000],
        ];

        foreach ($types as $type) {
            AircraftType::updateOrCreate(['code' => $type['code']], $type + ['active' => true]);
        }
    }
}
