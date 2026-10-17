<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecommendStandRequest;
use App\Http\Requests\ResetStandsRequest;
use App\Http\Resources\ParkingStandResource;
use App\Models\ParkingStand;
use App\Services\MovementService;
use App\Services\StandAllocationService;
use App\Services\StandBoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StandController extends Controller
{
    public function __construct(
        protected StandBoardService $board,
        protected StandAllocationService $allocation,
        protected MovementService $movements,
    ) {}

    /**
     * Live apron board used by the AMC dashboard and ATC monitor (FR-11).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ParkingStand::class);

        return response()
            ->json($this->board->board($request->query('date')))
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    public function show(ParkingStand $stand): ParkingStandResource
    {
        $this->authorize('view', $stand);

        return new ParkingStandResource($stand->load('aircraftTypes'));
    }

    public function available(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ParkingStand::class);

        $stands = $this->allocation->availableStands();

        return response()->json([
            'data' => ParkingStandResource::collection($stands)->resolve(),
        ]);
    }

    public function recommend(RecommendStandRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->allocation->recommend($request->validated()),
        ]);
    }

    public function reset(ResetStandsRequest $request): JsonResponse
    {
        $affected = $this->movements->resetAll(
            $request->user(),
            (string) $request->input('reason'),
            (bool) $request->boolean('confirm'),
        );

        return response()->json([
            'message' => "Reset massal berhasil. {$affected} stand dikosongkan.",
            'affected' => $affected,
        ]);
    }
}
