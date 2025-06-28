<?php

namespace App\Filament\Resources\GastoResource\Pages;

use App\Filament\Resources\GastoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;


class ListGastos extends ListRecords
{
    protected static string $resource = GastoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();

        // Si tiene permisos para ver todos los gastos del sistema
        if ($user->can('gastos.index')) {
            return static::getResource()::getEloquentQuery();
        }

        // Si puede ver todos los gastos de su oficina
        if ($user->can('gastosOficina.index')) {
            return static::getResource()::getEloquentQuery()
                ->whereHas('user', function ($query) use ($user) {
                    $query->where('oficina_id', $user->id);
                });
        }

        // Si solo puede ver sus propios gastos
        if ($user->can('gastos.view')) {
            return static::getResource()::getEloquentQuery()
                ->where('user_id', $user->id);
        }

        // Si no tiene permisos, no ve nada
        return static::getResource()::getEloquentQuery()->whereRaw('1 = 0');
    }

}

