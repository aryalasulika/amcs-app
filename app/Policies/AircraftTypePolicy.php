<?php

namespace App\Policies;

use App\Models\AircraftType;
use App\Models\User;

class AircraftTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stands.view');
    }

    public function view(User $user, AircraftType $aircraftType): bool
    {
        return $user->can('stands.view');
    }

    public function create(User $user): bool
    {
        return $user->can('master-data.manage');
    }

    public function update(User $user, AircraftType $aircraftType): bool
    {
        return $user->can('master-data.manage');
    }

    public function delete(User $user, AircraftType $aircraftType): bool
    {
        return $user->can('master-data.manage');
    }
}
