<?php

namespace App\Policies;

use App\Models\Gasto;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GastosPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can("gastos.index") || $user->can("gastos.view") || $user->can("gastosOficina.index");
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Gasto $gasto): bool
    {
        return $user->can('gastos.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('gastos.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Gasto $gasto): bool
    {
        return $user->can('gastos.edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Gasto $gasto): bool
    {
        return $user->can('gastos.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Gasto $gasto): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Gasto $gasto): bool
    {
        return false;
    }
}
