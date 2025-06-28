<?php

namespace App\Policies;

use App\Models\Abono;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AbonoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can("abonos.index") || $user->can("abonos.view") || $user->can("abonosOficina.index");

    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Abono $abono): bool
    {
        return $user->can("abonos.view");
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can("abonos.create");
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Abono $abono): bool
    {
        return $user->can("abonos.edit");
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Abono $abono): bool
    {
        return $user->can("abonos.delete");
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Abono $abono): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Abono $abono): bool
    {
        return false;
    }
}
