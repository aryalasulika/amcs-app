<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\OperationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AircraftTypeRequest;
use App\Http\Resources\AircraftTypeResource;
use App\Models\AircraftType;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AircraftTypeController extends Controller
{
    public function __construct(protected AuditService $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AircraftType::class);

        $items = AircraftType::query()
            ->when($request->query('search'), fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%')->orWhere('code', 'like', '%'.$s.'%'))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->has('active'), fn ($q) => $q->where('active', $request->boolean('active')))
            ->orderBy('code')
            ->paginate(min(max((int) $request->integer('per_page', 50), 1), 200));

        return AircraftTypeResource::collection($items);
    }

    public function store(AircraftTypeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $type = AircraftType::create($data + ['active' => $request->boolean('active', true)]);

        $this->audit->record('aircraft_type.created', $type, [], $type->toArray(), null, $request->user());

        return (new AircraftTypeResource($type))->response()->setStatusCode(201);
    }

    public function update(AircraftTypeRequest $request, AircraftType $aircraftType): AircraftTypeResource
    {
        $before = $aircraftType->toArray();
        $aircraftType->fill($request->validated());
        if ($request->has('active')) {
            $aircraftType->active = $request->boolean('active');
        }
        $aircraftType->save();

        $this->audit->record('aircraft_type.updated', $aircraftType, $before, $aircraftType->toArray(), null, $request->user());

        return new AircraftTypeResource($aircraftType);
    }

    public function destroy(Request $request, AircraftType $aircraftType): JsonResponse
    {
        $this->authorize('delete', $aircraftType);

        if ($aircraftType->movements()->exists()) {
            throw OperationException::make('Tipe pesawat sudah dipakai pada data pergerakan. Nonaktifkan saja daripada menghapus.');
        }

        $before = $aircraftType->toArray();
        $aircraftType->parkingStands()->detach();
        $aircraftType->delete();
        $this->audit->record('aircraft_type.deleted', null, $before, [], null, $request->user());

        return response()->json(['message' => 'Tipe pesawat dihapus.']);
    }
}
