<?php

namespace App\Policies;

use App\Models\RegistroLiquidacion;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RegistroLiquidacionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('registroliquidaciones.index');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RegistroLiquidacion $registroLiquidacion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RegistroLiquidacion $registroLiquidacion): bool
    {
        return $user->can('registroliquidaciones.edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RegistroLiquidacion $registroLiquidacion): bool
    {
        return $user->can('registroliquidaciones.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, RegistroLiquidacion $registroLiquidacion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, RegistroLiquidacion $registroLiquidacion): bool
    {
        return false;
    }
}
