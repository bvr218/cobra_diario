<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();

        // Si tiene permiso para ver todos los usuarios
        if ($user->can('users.index')) {
            return User::query();
        }

        // Si solo tiene permiso para ver usuarios de su oficina
        if ($user->can('usersOficina.index')) {
            return User::where('oficina_id', $user->id);
        }

        // Si no tiene permisos, no ve nada
        return User::query()->whereRaw('1 = 0');
    }
}
