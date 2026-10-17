<?php

namespace App\Models;

use App\Enums\MovementState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkingStand extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'active',
        'capacity_category',
        'gate_area',
        'position_x',
        'position_y',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'position_x' => 'decimal:2',
            'position_y' => 'decimal:2',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }

    public function aircraftTypes(): BelongsToMany
    {
        return $this->belongsToMany(AircraftType::class, 'parking_stand_aircraft_types')
            ->withTimestamps();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StandReservation::class);
    }

    /**
     * The movement currently occupying the stand, if any.
     */
    public function activeMovement(): ?Movement
    {
        return $this->movements()
            ->whereIn('state', MovementState::activeValues())
            ->latest('updated_at')
            ->first();
    }

    public function isOccupied(): bool
    {
        return $this->movements()
            ->whereIn('state', MovementState::activeValues())
            ->exists();
    }
}
