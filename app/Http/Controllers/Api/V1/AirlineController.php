<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\OperationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AirlineRequest;
use App\Http\Resources\AirlineResource;
use App\Models\Airline;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AirlineController extends Controller
{
    public function __construct(protected AuditService $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Airline::class);

        $items = Airline::query()
            ->with('defaultAircraftType')
            ->when($request->query('search'), fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%')->orWhere('code', 'like', '%'.$s.'%'))
            ->when($request->has('active'), fn ($q) => $q->where('active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate(min(max((int) $request->integer('per_page', 50), 1), 200));

        return AirlineResource::collection($items);
    }

    public function store(AirlineRequest $request): JsonResponse
    {
        $data = $request->validated();
        $airline = Airline::create($data + ['active' => $request->boolean('active', true)]);

        $this->audit->record('airline.created', $airline, [], $airline->toArray(), null, $request->user());

        return (new AirlineResource($airline->load('defaultAircraftType')))->response()->setStatusCode(201);
    }

    public function update(AirlineRequest $request, Airline $airline): AirlineResource
    {
        $before = $airline->toArray();
        $data = $request->validated();
        $airline->fill($data);
        if ($request->has('active')) {
            $airline->active = $request->boolean('active');
        }
        $airline->save();

        $this->audit->record('airline.updated', $airline, $before, $airline->toArray(), null, $request->user());

        return new AirlineResource($airline->load('defaultAircraftType'));
    }

    public function destroy(Request $request, Airline $airline): JsonResponse
    {
        $this->authorize('delete', $airline);

        if ($airline->movements()->exists()) {
            throw OperationException::make('Maskapai sudah dipakai pada data pergerakan. Nonaktifkan saja daripada menghapus.');
        }

        $before = $airline->toArray();
        $airline->delete();
        $this->audit->record('airline.deleted', null, $before, [], null, $request->user());

        return response()->json(['message' => 'Maskapai dihapus.']);
    }
}
