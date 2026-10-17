<?php

namespace App\Models;

use App\Enums\AircraftCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AircraftType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'category',
        'wingspan',
        'max_weight',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'category' => AircraftCategory::class,
            'wingspan' => 'decimal:2',
            'max_weight' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }

    public function parkingStands(): BelongsToMany
    {
        return $this->belongsToMany(ParkingStand::class, 'parking_stand_aircraft_types')
            ->withTimestamps();
    }
}
