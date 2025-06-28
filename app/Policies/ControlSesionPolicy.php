<?php

namespace App\Policies;

use App\Models\ControlSesion;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ControlSesionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('controlSesion.index');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ControlSesion $controlSesion): bool
    {
        return $user->can('controlSesion.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('controlSesion.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ControlSesion $controlSesion): bool
    {
        return $user->can('controlSesion.edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ControlSesion $controlSesion): bool
    {
        return $user->can('controlSesion.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ControlSesion $controlSesion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ControlSesion $controlSesion): bool
    {
        return false;
    }
}
