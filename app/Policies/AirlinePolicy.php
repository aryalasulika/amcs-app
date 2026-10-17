<?php

namespace App\Policies;

use App\Models\Airline;
use App\Models\User;

class AirlinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stands.view');
    }

    public function view(User $user, Airline $airline): bool
    {
        return $user->can('stands.view');
    }

    public function create(User $user): bool
    {
        return $user->can('master-data.manage');
    }

    public function update(User $user, Airline $airline): bool
    {
        return $user->can('master-data.manage');
    }

    public function delete(User $user, Airline $airline): bool
    {
        return $user->can('master-data.manage');
    }
}
