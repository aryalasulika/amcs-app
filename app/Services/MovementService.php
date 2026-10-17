<?php

namespace App\Services;

use App\Enums\AircraftCategory;
use App\Enums\MovementState;
use App\Exceptions\OperationException;
use App\Models\AircraftType;
use App\Models\Airline;
use App\Models\Movement;
use App\Models\MovementLog;
use App\Models\ParkingStand;
use App\Models\StandReservation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Core operational use-cases: allocate a stand, transition states,
 * BLOCK ON / BLOCK OFF, delay handling and mass reset (FR-05 … FR-15).
 *
 * Every write runs inside a MySQL transaction using row locks so that two
 * operators can never allocate the same stand concurrently (NFR-04, MVP #14).
 */
class MovementService
{
    public function __construct(
        protected StandAllocationService $allocation,
        protected AuditService $audit,
        protected NotificationService $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{movement: Movement, warnings: array<int, string>}
     */
    public function create(array $data, User $actor): array
    {
        return DB::transaction(function () use ($data, $actor) {
            $stand = $this->lockStand($data['parking_stand_id'] ?? null);
            $aircraft = $this->resolveAircraft($data, null, $actor);
            $warnings = [];
            $override = $this->isOverride($data);

            if ($stand) {
                $this->allocation->assertOperable($stand);

                if ($override) {
                    $this->assertCanOverride($actor);
                    $warnings = $this->collectOverlapWarnings($stand, $data, null);
                } else {
                    $warnings = $this->enforceRules(
                        $stand,
                        $aircraft,
                        $data['aircraft_type_name'] ?? null,
                        null,
                        $data,
                        $actor,
                        'create',
                    );
                }
            }

            $movement = new Movement;
            $movement->fill($this->movementAttributes($data, null, $actor));
            $movement->state = $this->initialState($data);
            $movement->created_by = $actor->id;
            $movement->updated_by = $actor->id;
            $movement->save();

            if ($stand) {
                $this->syncReservation($movement, $stand);
            }

            $this->logMovement($movement, 'BLOCK '.$movement->state->value, $actor);
            $this->audit->record(
                action: 'movement.created',
                entity: $movement,
                after: $this->snapshot($movement),
                reason: $override ? ($data['override_reason'] ?? 'override') : null,
                actor: $actor,
            );

            if ($override) {
                $this->notifications->toSupervisors(
                    title: 'Override alokasi stand',
                    message: "{$actor->name} melakukan override aturan stand untuk {$movement->flight_number} di ".($stand?->name ?? '-').'.',
                    level: 'warning',
                    context: ['movement_id' => $movement->id],
                );
            }

            return ['movement' => $movement->fresh(['parkingStand', 'airline', 'aircraftType']), 'warnings' => $warnings];
        });
    }

    /**
     * Supervisor correction of an existing movement (FR-15).
     *
     * @param  array<string, mixed>  $data
     * @return array{movement: Movement, warnings: array<int, string>}
     */
    public function update(Movement $movement, array $data, User $actor, ?string $reason = null): array
    {
        return DB::transaction(function () use ($movement, $data, $actor, $reason) {
            $movement = Movement::whereKey($movement->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($movement);
            $warnings = [];
            $override = $this->isOverride($data);

            $attributes = $this->movementAttributes($data, $movement, $actor);

            // A stand change or an aircraft change requires a fresh
            // availability/capacity check.
            $standChanged = array_key_exists('parking_stand_id', $data)
                && (int) $data['parking_stand_id'] !== (int) $movement->parking_stand_id;
            $aircraftChanged = array_key_exists('aircraft_type_id', $data)
                || array_key_exists('aircraft_type_name', $data)
                || array_key_exists('aircraft_category', $data);

            if ($standChanged || ($aircraftChanged && $movement->parking_stand_id)) {
                $stand = $standChanged
                    ? $this->lockStand($data['parking_stand_id'])
                    : $movement->parkingStand;

                if ($stand) {
                    $this->allocation->assertOperable($stand);
                    $aircraft = $this->resolveAircraft($data, $movement, $actor);

                    if (! $override) {
                        $warnings = $this->enforceRules(
                            $stand,
                            $aircraft,
                            $attributes['aircraft_type_name'] ?? null,
                            $movement->id,
                            $attributes,
                            $actor,
                            'update',
                        );
                    } else {
                        $this->assertCanOverride($actor);
                    }
                }
            }

            if ($override) {
                $this->assertCanOverride($actor);
                $attributes['override_reason'] = $data['override_reason'] ?? $movement->override_reason;
            }

            $movement->fill($attributes);
            $movement->updated_by = $actor->id;
            $movement->save();

            if ($movement->parking_stand_id) {
                $this->syncReservation($movement, $movement->parkingStand);
            }

            $this->logMovement($movement, 'UPDATE', $actor, ['changes' => array_keys($attributes)]);
            $this->audit->record(
                action: 'movement.updated',
                entity: $movement,
                before: $before,
                after: $this->snapshot($movement),
                reason: $reason,
                actor: $actor,
            );

            return ['movement' => $movement->fresh(['parkingStand', 'airline', 'aircraftType']), 'warnings' => $warnings];
        });
    }

    /**
     * Generic guarded transition between operational states (FR-08).
     *
     * @param  array<string, mixed>  $data
     * @return array{movement: Movement, warnings: array<int, string>}
     */
    public function transition(Movement $movement, MovementState $target, array $data, User $actor): array
    {
        return DB::transaction(function () use ($movement, $target, $data, $actor) {
            $movement = Movement::whereKey($movement->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($movement);
            $current = $movement->state;

            $override = $this->isOverride($data);

            if ($current === MovementState::OFF) {
                throw OperationException::make('Penerbangan sudah BLOCK OFF dan tidak dapat diubah statusnya.');
            }

            if (! $current->canTransitionTo($target)) {
                if (! $override) {
                    throw OperationException::make(
                        "Transisi status {$current->value} → {$target->value} tidak valid. "
                        .'Minta supervisor melakukan override bila diperlukan.',
                        errors: ['state' => ['Transisi status tidak valid.']]
                    );
                }

                $this->assertCanOverride($actor);
            }

            $warnings = [];
            $now = CarbonImmutable::now();
            $time = $now->format('H:i');

            switch ($target) {
                case MovementState::ON:
                    if (empty($movement->ata)) {
                        $movement->ata = $data['ata'] ?? $time;
                    }
                    break;

                case MovementState::DELAY:
                    $minutes = (int) ($data['delay_minutes'] ?? $movement->delay_minutes ?? 0);
                    if ($minutes <= 0) {
                        throw OperationException::make(
                            'Durasi delay wajib diisi lebih dari 0 menit.',
                            errors: ['delay_minutes' => ['Durasi delay wajib diisi.']]
                        );
                    }
                    $movement->delay_minutes = $minutes;
                    $movement->delay_reason = $data['delay_reason'] ?? $movement->delay_reason;
                    $movement->delay_notes = $data['delay_notes'] ?? $movement->delay_notes;

                    if ($minutes >= config('amcs.delay_threshold_minutes')) {
                        $warnings[] = "Delay {$minutes} menit melewati ambang batas pelaporan supervisor.";
                        $this->notifications->toSupervisors(
                            title: 'Delay melewati ambang batas',
                            message: "{$movement->flight_number} delay {$minutes} menit di ".($movement->parkingStand?->name ?? '-').'.',
                            level: 'warning',
                            context: ['movement_id' => $movement->id, 'delay_minutes' => $minutes],
                        );
                    }
                    break;

                case MovementState::RON:
                    break;

                case MovementState::OFF:
                    $movement->atd = $data['atd'] ?? $time;
                    $movement->closed_at = $now;
                    break;

                case MovementState::ENTERING:
                    break;
            }

            $movement->state = $target;
            $movement->updated_by = $actor->id;
            if ($override) {
                $movement->override_reason = $data['override_reason'] ?? $movement->override_reason;
            }
            $movement->save();

            $this->syncReservation($movement, $movement->parkingStand);

            $this->logMovement($movement, 'BLOCK '.$target->value, $actor);
            $this->audit->record(
                action: 'movement.transitioned',
                entity: $movement,
                before: $before,
                after: $this->snapshot($movement),
                reason: $override ? ($data['override_reason'] ?? 'override') : null,
                actor: $actor,
            );

            if ($override) {
                $this->notifications->toSupervisors(
                    title: 'Override transisi status',
                    message: "{$actor->name} override {$current->value} → {$target->value} untuk {$movement->flight_number}.",
                    level: 'warning',
                    context: ['movement_id' => $movement->id],
                );
            }

            return ['movement' => $movement->fresh(['parkingStand', 'airline', 'aircraftType']), 'warnings' => $warnings];
        });
    }

    /**
     * @return array{movement: Movement, warnings: array<int, string>}
     */
    public function blockOn(Movement $movement, User $actor, ?string $ata = null): array
    {
        return $this->transition($movement, MovementState::ON, array_filter(['ata' => $ata]), $actor);
    }

    /**
     * @return array{movement: Movement, warnings: array<int, string>}
     */
    public function blockOff(Movement $movement, User $actor, ?string $atd = null, ?string $reason = null): array
    {
        return $this->transition($movement, MovementState::OFF, array_filter([
            'atd' => $atd,
            'override_reason' => $reason,
        ]), $actor);
    }

    /**
     * Mass reset of every active stand (FR-15). Restricted to authority roles.
     */
    public function resetAll(User $actor, string $reason, bool $confirm = false): int
    {
        if (! $confirm) {
            throw OperationException::make('Reset massal memerlukan konfirmasi berlapis.');
        }

        if (trim($reason) === '') {
            throw OperationException::make('Alasan reset wajib diisi.', errors: ['reason' => ['Alasan reset wajib diisi.']]);
        }

        return DB::transaction(function () use ($actor, $reason) {
            $movements = Movement::query()
                ->whereIn('state', MovementState::activeValues())
                ->lockForUpdate()
                ->get();

            $now = CarbonImmutable::now();

            foreach ($movements as $movement) {
                $movement->state = MovementState::OFF;
                $movement->atd = $movement->atd ?: $now->format('H:i');
                $movement->closed_at = $now;
                $movement->updated_by = $actor->id;
                $movement->save();

                $movement->reservations()->update(['status' => 'CANCELLED', 'end_time' => $now]);

                $this->logMovement($movement, 'RESET ALL', $actor, ['reason' => $reason]);
                $this->audit->record(
                    action: 'movement.reset',
                    entity: $movement,
                    after: $this->snapshot($movement),
                    reason: $reason,
                    actor: $actor,
                );
            }

            MovementLog::create([
                'movement_id' => null,
                'stand_code' => 'SEMUA STAND',
                'flight_number' => null,
                'action' => 'RESET ALL',
                'payload' => ['reason' => $reason, 'affected' => $movements->count()],
                'created_by' => $actor->id,
            ]);

            $this->audit->record(
                action: 'stands.reset_all',
                entity: null,
                after: ['affected' => $movements->count()],
                reason: $reason,
                actor: $actor,
            );

            $this->notifications->toRoles(
                ['admin', 'supervisor', 'amc_operator', 'atc_viewer'],
                'Reset seluruh stand dijalankan',
                "{$actor->name} melakukan reset {$movements->count()} stand aktif. Alasan: {$reason}",
                level: 'critical',
            );

            return $movements->count();
        });
    }

    /**
     * Soft-archive a movement instead of permanently deleting history (FR-15).
     */
    public function archive(Movement $movement, User $actor, string $reason): void
    {
        DB::transaction(function () use ($movement, $actor, $reason) {
            $before = $this->snapshot($movement);
            $movement->reservations()->update(['status' => 'CANCELLED']);
            $movement->delete();

            $this->logMovement($movement, 'ARCHIVED', $actor, ['reason' => $reason]);
            $this->audit->record(
                action: 'movement.archived',
                entity: $movement,
                before: $before,
                reason: $reason,
                actor: $actor,
            );
        });
    }

    /* ------------------------------------------------------------------ */
    /* Helpers */
    /* ------------------------------------------------------------------ */

    protected function lockStand(int|string|null $standId): ?ParkingStand
    {
        if (! $standId) {
            return null;
        }

        $stand = ParkingStand::whereKey($standId)->lockForUpdate()->first();

        if (! $stand) {
            throw OperationException::make('Parking stand tidak ditemukan.', 404);
        }

        return $stand;
    }

    protected function initialState(array $data): MovementState
    {
        $state = MovementState::tryFrom($data['state'] ?? 'ENTERING') ?? MovementState::ENTERING;

        if (! in_array($state, [MovementState::ENTERING, MovementState::ON], true)) {
            return MovementState::ENTERING;
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function movementAttributes(array $data, ?Movement $existing = null, ?User $actor = null): array
    {
        $airline = $this->resolveAirline($data, $existing);
        $aircraft = $this->resolveAircraft($data, $existing, $actor);

        $attributes = [
            'report_date' => $data['report_date'] ?? $existing?->report_date?->toDateString() ?? CarbonImmutable::now()->toDateString(),
            'flight_number' => isset($data['flight_number'])
                ? strtoupper($data['flight_number'])
                : $existing?->flight_number,
            'airline_id' => $airline?->id,
            'airline_name' => $airline?->name ?? ($data['airline_name'] ?? $existing?->airline_name),
            'registration' => isset($data['registration']) ? strtoupper((string) $data['registration']) : $existing?->registration,
            'aircraft_type_id' => $aircraft?->id,
            'aircraft_type_name' => $aircraft?->code ?? ($data['aircraft_type_name'] ?? $existing?->aircraft_type_name),
            'parking_stand_id' => array_key_exists('parking_stand_id', $data) ? $data['parking_stand_id'] : $existing?->parking_stand_id,
        ];

        foreach (['flight_type', 'origin', 'destination', 'eta', 'ata', 'etd', 'atd', 'runway', 'taxiway', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                $attributes[$field] = in_array($field, ['origin', 'destination'], true) && $value
                    ? strtoupper((string) $value)
                    : $value;
            } elseif ($existing) {
                $attributes[$field] = $existing->{$field};
            }
        }

        if (array_key_exists('flight_type', $data) && $data['flight_type']) {
            $attributes['flight_type'] = strtoupper((string) $data['flight_type']);
        }

        return $attributes;
    }

    protected function resolveAirline(array $data, ?Movement $existing = null): ?Airline
    {
        if (! empty($data['airline_id'])) {
            return Airline::find($data['airline_id']);
        }

        if (! empty($data['airline_name'])) {
            return Airline::firstOrCreate(
                ['code' => $this->slugCode($data['airline_name'])],
                ['name' => $data['airline_name'], 'active' => true],
            );
        }

        return $existing?->airline;
    }

    protected function resolveAircraft(array $data, ?Movement $existing = null, ?User $actor = null): ?AircraftType
    {
        if (! empty($data['aircraft_type_id'])) {
            return AircraftType::find($data['aircraft_type_id']);
        }

        if (! empty($data['aircraft_type_name'])) {
            $name = trim((string) $data['aircraft_type_name']);
            $code = strtoupper($name);

            $type = AircraftType::where('code', $code)->first();

            if ($type) {
                return $type;
            }

            // Manual entry for an aircraft type that is not in master data.
            // Persist it so the type can be reused and audited, and validate it
            // using the operator-declared category.
            $type = AircraftType::create([
                'code' => mb_substr($code, 0, 32),
                'name' => mb_substr($name, 0, 255),
                'category' => AircraftCategory::tryFrom((string) ($data['aircraft_category'] ?? '')) ?? AircraftCategory::OTHER,
                'active' => true,
            ]);

            if ($actor) {
                $this->audit->record(
                    action: 'aircraft_type.created',
                    entity: $type,
                    after: $type->only(['id', 'code', 'name', 'category', 'active']),
                    reason: 'Dibuat otomatis dari input pergerakan manual.',
                    actor: $actor,
                );
            }

            return $type;
        }

        return $existing?->aircraftType;
    }

    protected function slugCode(string $name): string
    {
        return strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name) ?: 'MANUAL', 0, 16));
    }

    protected function isOverride(array $data): bool
    {
        return filter_var($data['override'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    protected function assertCanOverride(User $actor): void
    {
        if (! $actor->can('movements.override')) {
            throw OperationException::make(
                'Anda tidak memiliki hak untuk melakukan override aturan stand.',
                403
            );
        }
    }

    /**
     * Run capacity + availability checks and notify supervisors when an
     * allocation is rejected (FR-06, FR-16).
     *
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    protected function enforceRules(
        ParkingStand $stand,
        ?AircraftType $aircraft,
        ?string $aircraftName,
        ?int $ignoreMovementId,
        array $data,
        User $actor,
        string $context,
    ): array {
        try {
            $this->allocation->assertCompatible($stand, $aircraft, $aircraftName);
            $this->allocation->assertAvailable($stand, $ignoreMovementId);
        } catch (OperationException $e) {
            $this->notifications->toSupervisors(
                title: 'Percobaan alokasi stand ditolak',
                message: "{$actor->name} ({$context}) — {$e->getMessage()}",
                level: 'warning',
                context: [
                    'parking_stand_id' => $stand->id,
                    'flight_number' => $data['flight_number'] ?? null,
                ],
            );

            throw $e;
        }

        return $this->collectOverlapWarnings($stand, $data, $ignoreMovementId);
    }

    /**
     * @return array<int, string>
     */
    protected function collectOverlapWarnings(ParkingStand $stand, array $data, ?int $ignoreMovementId): array
    {
        $start = $this->allocation->combineDateTime(
            $data['report_date'] ?? null,
            $data['eta'] ?? $data['ata'] ?? null
        );
        $end = $this->allocation->combineDateTime($data['report_date'] ?? null, $data['etd'] ?? null);

        $overlaps = $this->allocation->findOverlaps($stand, $start, $end, $ignoreMovementId);

        $warnings = [];
        foreach ($overlaps as $reservation) {
            $flight = $reservation->movement?->flight_number ?? '-';
            $warnings[] = "Jadwal tumpang tindih dengan {$flight} ({$reservation->start_time->format('H:i')}-{$reservation->end_time->format('H:i')}).";
        }

        return $warnings;
    }

    protected function syncReservation(Movement $movement, ?ParkingStand $stand): void
    {
        if (! $stand) {
            return;
        }

        $defaultTurnaround = (int) config('amcs.default_turnaround_minutes', 60);
        $date = $movement->report_date instanceof \DateTimeInterface
            ? $movement->report_date->format('Y-m-d')
            : (string) $movement->report_date;

        $start = $this->allocation->combineDateTime($date, $movement->eta ?: $movement->ata ?: $movement->atd)
            ?? CarbonImmutable::now();
        $end = $this->allocation->combineDateTime($date, $movement->etd)
            ?? $start->addMinutes($defaultTurnaround);

        if ($end->lessThanOrEqualTo($start)) {
            $end = $start->addMinutes($defaultTurnaround);
        }

        $status = $movement->state === MovementState::OFF ? 'COMPLETED' : 'ACTIVE';

        $reservation = StandReservation::firstOrNew(['movement_id' => $movement->id]);
        $reservation->parking_stand_id = $stand->id;
        $reservation->start_time = $start;
        $reservation->end_time = $movement->state === MovementState::OFF ? CarbonImmutable::now() : $end;
        $reservation->status = $status;
        $reservation->save();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function logMovement(Movement $movement, string $action, User $actor, array $extra = []): MovementLog
    {
        return MovementLog::create([
            'movement_id' => $movement->id,
            'stand_code' => $movement->parkingStand?->code,
            'flight_number' => $movement->flight_number,
            'action' => $action,
            'payload' => array_merge($this->snapshot($movement), $extra),
            'created_by' => $actor->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Movement $movement): array
    {
        return [
            'id' => $movement->id,
            'flight_number' => $movement->flight_number,
            'airline' => $movement->airline_name,
            'registration' => $movement->registration,
            'aircraft_type' => $movement->aircraft_type_name,
            'flight_type' => $movement->flight_type,
            'origin' => $movement->origin,
            'destination' => $movement->destination,
            'parking_stand' => $movement->parkingStand?->code,
            'state' => $movement->state instanceof MovementState ? $movement->state->value : $movement->state,
            'eta' => $movement->eta,
            'ata' => $movement->ata,
            'etd' => $movement->etd,
            'atd' => $movement->atd,
            'runway' => $movement->runway,
            'taxiway' => $movement->taxiway,
            'delay_minutes' => $movement->delay_minutes,
            'delay_reason' => $movement->delay_reason,
        ];
    }
}
