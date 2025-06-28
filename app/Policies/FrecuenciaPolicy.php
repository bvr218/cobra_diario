<?php

namespace App\Policies;

use App\Models\Frecuencia;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FrecuenciaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('frecuencias.index');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Frecuencia $frecuencia): bool
    {
        return $user->can('frecuencias.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('frecuencias.create');    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Frecuencia $frecuencia): bool
    {
        return $user->can('frecuencias.edit');    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Frecuencia $frecuencia): bool
    {
        return $user->can('frecuencias.delete');    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Frecuencia $frecuencia): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Frecuencia $frecuencia): bool
    {
        return false;
    }
}
