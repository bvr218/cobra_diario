<?php

namespace App\Policies;

use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PrestamoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can("prestamos.index") || $user->can("prestamos.view") || $user->can('prestamosOficina.index');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Prestamo $prestamo): bool
    {
        return $user->can("prestamos.view") || $user->can('activarPrestamosOficina.view') || $user->can('prestamosRefinanciar.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can("prestamos.create");
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Prestamo $prestamo): bool
    {
        return $user->can("prestamos.edit");
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Prestamo $prestamo): bool
    {
        return $user->can("prestamos.delete");
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Prestamo $prestamo): bool
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Prestamo $prestamo): bool
    {
        return true;
    }
}
