<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\OperationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ParkingStandRequest;
use App\Http\Resources\ParkingStandResource;
use App\Models\ParkingStand;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ParkingStandController extends Controller
{
    public function __construct(protected AuditService $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ParkingStand::class);

        $items = ParkingStand::query()
            ->with('aircraftTypes')
            ->when($request->has('active'), fn ($q) => $q->where('active', $request->boolean('active')))
            ->orderBy('id')
            ->paginate(min(max((int) $request->integer('per_page', 50), 1), 200));

        return ParkingStandResource::collection($items);
    }

    public function store(ParkingStandRequest $request): JsonResponse
    {
        $data = $request->validated();
        $typeIds = $data['aircraft_type_ids'] ?? [];
        unset($data['aircraft_type_ids']);

        $stand = ParkingStand::create($data + ['active' => $request->boolean('active', true)]);
        $stand->aircraftTypes()->sync($typeIds);

        $this->audit->record('parking_stand.created', $stand, [], $this->snapshot($stand), null, $request->user());

        return (new ParkingStandResource($stand->load('aircraftTypes')))->response()->setStatusCode(201);
    }

    public function update(ParkingStandRequest $request, ParkingStand $parkingStand): ParkingStandResource
    {
        $before = $this->snapshot($parkingStand);

        $data = $request->validated();
        $hasTypes = array_key_exists('aircraft_type_ids', $data);
        $typeIds = $data['aircraft_type_ids'] ?? [];
        unset($data['aircraft_type_ids']);

        $parkingStand->fill($data);
        if ($request->has('active')) {
            $parkingStand->active = $request->boolean('active');
        }
        $parkingStand->save();

        if ($hasTypes) {
            $parkingStand->aircraftTypes()->sync($typeIds);
        }

        $this->audit->record('parking_stand.updated', $parkingStand, $before, $this->snapshot($parkingStand->fresh('aircraftTypes')), null, $request->user());

        return new ParkingStandResource($parkingStand->load('aircraftTypes'));
    }

    public function destroy(Request $request, ParkingStand $parkingStand): JsonResponse
    {
        $this->authorize('delete', $parkingStand);

        if ($parkingStand->movements()->exists()) {
            throw OperationException::make('Stand sudah memiliki riwayat pergerakan. Nonaktifkan saja daripada menghapus.');
        }

        $before = $this->snapshot($parkingStand);
        $parkingStand->aircraftTypes()->detach();
        $parkingStand->delete();
        $this->audit->record('parking_stand.deleted', null, $before, [], null, $request->user());

        return response()->json(['message' => 'Parking stand dihapus.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(ParkingStand $stand): array
    {
        return $stand->toArray() + [
            'aircraft_type_ids' => $stand->aircraftTypes()->pluck('aircraft_types.id')->all(),
        ];
    }
}
