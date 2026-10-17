<?php

namespace App\Policies;

use App\Models\Movement;
use App\Models\User;

class MovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('movements.view');
    }

    public function view(User $user, Movement $movement): bool
    {
        return $user->can('movements.view');
    }

    public function create(User $user): bool
    {
        return $user->can('movements.create');
    }

    public function update(User $user, Movement $movement): bool
    {
        return $user->can('movements.update');
    }

    public function transition(User $user, Movement $movement): bool
    {
        return $user->can('movements.update');
    }

    public function blockOn(User $user, Movement $movement): bool
    {
        return $user->can('movements.update');
    }

    public function blockOff(User $user, Movement $movement): bool
    {
        return $user->can('movements.update');
    }

    public function override(User $user, Movement $movement): bool
    {
        return $user->can('movements.override');
    }

    public function delete(User $user, Movement $movement): bool
    {
        return $user->can('movements.delete');
    }

    public function restore(User $user, Movement $movement): bool
    {
        return $user->can('movements.delete');
    }
}
