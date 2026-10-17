<?php

namespace App\Policies;

use App\Models\ParkingStand;
use App\Models\User;

class ParkingStandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stands.view');
    }

    public function view(User $user, ParkingStand $parkingStand): bool
    {
        return $user->can('stands.view');
    }

    public function create(User $user): bool
    {
        return $user->can('master-data.manage');
    }

    public function update(User $user, ParkingStand $parkingStand): bool
    {
        return $user->can('master-data.manage');
    }

    public function delete(User $user, ParkingStand $parkingStand): bool
    {
        return $user->can('master-data.manage');
    }

    /**
     * Mass reset of every stand (FR-15).
     */
    public function reset(User $user): bool
    {
        return $user->can('stands.reset');
    }
}
