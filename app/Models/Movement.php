<?php

namespace App\Models;

use App\Enums\MovementState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Movement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'report_date',
        'flight_number',
        'airline_id',
        'airline_name',
        'registration',
        'aircraft_type_id',
        'aircraft_type_name',
        'flight_type',
        'origin',
        'destination',
        'parking_stand_id',
        'state',
        'eta',
        'ata',
        'etd',
        'atd',
        'runway',
        'taxiway',
        'delay_minutes',
        'delay_reason',
        'delay_notes',
        'notes',
        'override_reason',
        'created_by',
        'updated_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'state' => MovementState::class,
            'eta' => 'string',
            'ata' => 'string',
            'etd' => 'string',
            'atd' => 'string',
            'delay_minutes' => 'integer',
            'closed_at' => 'datetime',
        ];
    }

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class);
    }

    public function aircraftType(): BelongsTo
    {
        return $this->belongsTo(AircraftType::class);
    }

    public function parkingStand(): BelongsTo
    {
        return $this->belongsTo(ParkingStand::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MovementLog::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StandReservation::class);
    }

    public function isActive(): bool
    {
        return $this->state instanceof MovementState && $this->state->isActive();
    }

    public function route(string $glue = ' → '): string
    {
        return ($this->origin ?: '-').$glue.($this->destination ?: '-');
    }
}
