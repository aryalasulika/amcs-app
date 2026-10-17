<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AircraftCategory;
use App\Enums\FlightType;
use App\Enums\MovementState;
use App\Http\Controllers\Controller;
use App\Http\Resources\AircraftTypeResource;
use App\Http\Resources\AirlineResource;
use App\Http\Resources\ParkingStandResource;
use App\Models\AircraftType;
use App\Models\Airline;
use App\Models\ParkingStand;
use Illuminate\Http\JsonResponse;

class OptionsController extends Controller
{
    /**
     * Reference data for populating operator forms.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'states' => MovementState::options(),
            'flight_types' => FlightType::options(),
            'aircraft_categories' => AircraftCategory::options(),
            'delay_reasons' => collect(config('amcs.delay_reasons'))
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'airlines' => AirlineResource::collection(
                Airline::where('active', true)->with('defaultAircraftType')->orderBy('name')->get()
            ),
            'aircraft_types' => AircraftTypeResource::collection(
                AircraftType::where('active', true)->orderBy('code')->get()
            ),
            'parking_stands' => ParkingStandResource::collection(
                ParkingStand::where('active', true)->with('aircraftTypes')->orderBy('id')->get()
            ),
            'delay_threshold_minutes' => config('amcs.delay_threshold_minutes'),
            'poll_seconds' => config('amcs.realtime.poll_seconds'),
        ]);
    }
}
