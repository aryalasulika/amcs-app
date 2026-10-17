<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MovementState;
use App\Http\Controllers\Controller;
use App\Http\Requests\DelayRequest;
use App\Http\Requests\StoreMovementRequest;
use App\Http\Requests\TransitionMovementRequest;
use App\Http\Requests\UpdateMovementRequest;
use App\Http\Resources\MovementLogResource;
use App\Http\Resources\MovementResource;
use App\Models\Movement;
use App\Services\MovementService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MovementController extends Controller
{
    public function __construct(
        protected MovementService $service,
        protected ReportService $reports,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Movement::class);

        $filters = $request->only([
            'date', 'date_from', 'date_to', 'flight_number',
            'parking_stand_id', 'airline_id', 'state', 'flight_type', 'user_id',
        ]);

        $perPage = min(max((int) $request->integer('per_page', 25), 1), 100);

        return MovementResource::collection($this->reports->history($filters, $perPage));
    }

    public function store(StoreMovementRequest $request): JsonResponse
    {
        $result = $this->service->create($request->validated(), $request->user());

        return (new MovementResource($result['movement']))
            ->additional([
                'message' => 'Pergerakan berhasil disimpan.',
                'warnings' => $result['warnings'],
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Movement $movement): MovementResource
    {
        $this->authorize('view', $movement);

        return new MovementResource(
            $movement->load(['parkingStand', 'airline', 'aircraftType', 'creator', 'updater', 'logs.user'])
        );
    }

    public function update(UpdateMovementRequest $request, Movement $movement): JsonResponse
    {
        $this->authorize('update', $movement);

        $data = $request->validated();
        $reason = $data['reason'] ?? null;

        $result = $this->service->update($movement, $data, $request->user(), $reason);

        return (new MovementResource($result['movement']))
            ->additional([
                'message' => 'Pergerakan diperbarui.',
                'warnings' => $result['warnings'],
            ])
            ->response();
    }

    public function transition(TransitionMovementRequest $request, Movement $movement): JsonResponse
    {
        $this->authorize('transition', $movement);

        $state = MovementState::from((string) $request->input('state'));
        $result = $this->service->transition($movement, $state, $request->validated(), $request->user());

        return $this->result($result, 'Status penerbangan diperbarui.');
    }

    public function blockOn(Request $request, Movement $movement): JsonResponse
    {
        $this->authorize('blockOn', $movement);

        $request->validate(['ata' => ['nullable', 'date_format:H:i']]);

        $result = $this->service->blockOn($movement, $request->user(), $request->input('ata'));

        return $this->result($result, 'BLOCK ON tersimpan (ATA dicatat).');
    }

    public function blockOff(Request $request, Movement $movement): JsonResponse
    {
        $this->authorize('blockOff', $movement);

        $request->validate([
            'atd' => ['nullable', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->service->blockOff($movement, $request->user(), $request->input('atd'), $request->input('reason'));

        return $this->result($result, 'BLOCK OFF tersimpan (ATD dicatat) dan stand dikosongkan.');
    }

    public function delay(DelayRequest $request, Movement $movement): JsonResponse
    {
        $this->authorize('update', $movement);

        $result = $this->service->transition($movement, MovementState::DELAY, $request->validated(), $request->user());

        return $this->result($result, 'Delay tercatat.');
    }

    public function destroy(Request $request, Movement $movement): JsonResponse
    {
        $this->authorize('delete', $movement);

        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        $this->service->archive($movement, $request->user(), $data['reason']);

        return response()->json(['message' => 'Pergerakan diarsipkan (audit trail tetap tersimpan).']);
    }

    public function logs(Movement $movement): AnonymousResourceCollection
    {
        $this->authorize('view', $movement);

        return MovementLogResource::collection(
            $movement->logs()->with('user')->latest('created_at')->get()
        );
    }

    /**
     * @param  array{movement: Movement, warnings: array<int, string>}  $result
     */
    protected function result(array $result, string $message): JsonResponse
    {
        return (new MovementResource($result['movement']))
            ->additional(['message' => $message, 'warnings' => $result['warnings']])
            ->response();
    }
}
