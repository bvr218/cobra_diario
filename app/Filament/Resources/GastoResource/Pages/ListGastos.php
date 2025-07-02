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
        $user = auth()->user(); // Obtener el usuario aquí

        $headerActions = [
            Actions\CreateAction::make(),
        ];

        // Agregamos el mensaje como una acción solo si el usuario tiene el permiso específico
        // y NO tiene los permisos más amplios (gastos.index o gastosOficina.index)
        if ($user->can('gastos.view') && !$user->can('gastos.index') && !$user->can('gastosOficina.index')) {
            $headerActions[] = Actions\Action::make('info_gastos_view_limited')
                ->label("Solo se muestran tus gastos pendientes por autorizar.")
                ->disabled() // Para que no sea cliqueable
                ->color('gray') // Color para distinguirlo como informativo
                ->icon('heroicon-o-information-circle'); // Un ícono de información
        }

        return $headerActions;
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
                    $query->where('oficina_id', $user->oficina_id);
                });
        }

        // Si solo puede ver sus propios gastos Y donde autorizado sea false
        if ($user->can('gastos.view')) {
            return static::getResource()::getEloquentQuery()
                ->where('user_id', $user->id) // Sus propios gastos
                ->where('autorizado', false); // Y donde autorizado sea false
        }

        // Si no tiene ninguno de los permisos anteriores, no ve nada
        return static::getResource()::getEloquentQuery()->whereRaw('1 = 0');
    }
}