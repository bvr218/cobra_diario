<?php

namespace App\Policies;

use App\Models\TipoGasto;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TipoGastoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('tipoGastos.index');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TipoGasto $tipoGasto): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('tipoGastos.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TipoGasto $tipoGasto): bool
    {
        return $user->can('tipoGastos.edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TipoGasto $tipoGasto): bool
    {
        return $user->can('tipoGastos.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, TipoGasto $tipoGasto): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, TipoGasto $tipoGasto): bool
    {
        return false;
    }
}
