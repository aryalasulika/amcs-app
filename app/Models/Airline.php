<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Airline extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'flight_prefix',
        'default_aircraft_type_id',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }

    /**
     * Aircraft type suggested when a flight number uses this airline prefix.
     */
    public function defaultAircraftType(): BelongsTo
    {
        return $this->belongsTo(AircraftType::class, 'default_aircraft_type_id');
    }
}
