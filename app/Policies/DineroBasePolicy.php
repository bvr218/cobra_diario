<?php

namespace App\Policies;

use App\Models\DineroBase;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DineroBasePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('dineroBase.index');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DineroBase $dineroBase): bool
    {
        return $user->can('dineroBase.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('dineroBase.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DineroBase $dineroBase): bool
    {
        return $user->can('dineroBase.edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DineroBase $dineroBase): bool
    {
        return $user->can('dineroBase.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DineroBase $dineroBase): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DineroBase $dineroBase): bool
    {
        return false;
    }
}
