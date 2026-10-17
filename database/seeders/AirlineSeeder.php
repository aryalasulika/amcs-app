<?php

namespace Database\Seeders;

use App\Models\AircraftType;
use App\Models\Airline;
use Illuminate\Database\Seeder;

class AirlineSeeder extends Seeder
{
    public function run(): void
    {
        $airlines = [
            ['code' => 'GIA', 'name' => 'Garuda Indonesia', 'flight_prefix' => 'GA', 'default_aircraft' => 'B737-800'],
            ['code' => 'CTV', 'name' => 'Citilink', 'flight_prefix' => 'QG', 'default_aircraft' => 'A320'],
            ['code' => 'LNI', 'name' => 'Lion Air', 'flight_prefix' => 'JT', 'default_aircraft' => 'B737-800'],
            ['code' => 'WON', 'name' => 'Wings Air', 'flight_prefix' => 'IW', 'default_aircraft' => 'ATR72'],
            ['code' => 'BTK', 'name' => 'Batik Air', 'flight_prefix' => 'ID', 'default_aircraft' => 'A320'],
            ['code' => 'SJV', 'name' => 'Super Air Jet', 'flight_prefix' => 'IU', 'default_aircraft' => 'A320'],
            ['code' => 'PAS', 'name' => 'Pelita Air', 'flight_prefix' => 'IP', 'default_aircraft' => 'A320'],
            ['code' => 'SUSI', 'name' => 'Susi Air', 'flight_prefix' => 'SI', 'default_aircraft' => 'C208'],
        ];

        foreach ($airlines as $airline) {
            $defaultAircraftCode = $airline['default_aircraft'];
            unset($airline['default_aircraft']);

            $airline['default_aircraft_type_id'] = AircraftType::where('code', $defaultAircraftCode)->value('id');

            Airline::updateOrCreate(['code' => $airline['code']], $airline + ['active' => true]);
        }
    }
}
