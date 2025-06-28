<?php

namespace App\Policies;

use App\Models\User;
use App\Models\refinanciamiento;
use Illuminate\Auth\Access\Response;

class RefinanciamientoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('refinanciamientos.index') || $user->can('refinanciamientos.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, refinanciamiento $refinanciamiento): bool
    {
        return $user->can('refinanciamientos.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('refinanciamientos.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, refinanciamiento $refinanciamiento): bool
    {
        return $user->can('refinanciamientos.edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, refinanciamiento $refinanciamiento): bool
    {
        return $user->can('refinanciamientos.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, refinanciamiento $refinanciamiento): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, refinanciamiento $refinanciamiento): bool
    {
        return false;
    }
}
