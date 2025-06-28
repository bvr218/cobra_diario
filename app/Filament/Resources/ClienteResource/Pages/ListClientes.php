<?php

namespace App\Filament\Resources\ClienteResource\Pages;

use App\Filament\Resources\ClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListClientes extends ListRecords
{
    protected static string $resource = ClienteResource::class;

    protected function getHeaderActions(): array
    {
        $headerActions = [
            Actions\CreateAction::make(),
        ];

        if (!auth()->user()->can('clientes.index')) {
            $headerActions[] = Actions\Action::make('solo_clientes_asignados')
                ->label('Solo puedes ver los clientes asignados a ti.')
                ->disabled()
                ->color('gray')
                ->icon('heroicon-o-exclamation-triangle');
        }

        return $headerActions;
    }

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();

        // Si el usuario tiene el permiso de ver clientes de su oficina,
        // devolvemos solo aquellos con oficina_id igual al suyo.
        if ($user->can('clientesOficina.index')) {
            return static::getResource()::getEloquentQuery()
                ->where('oficina_id', $user->id);
        }

        // Si tiene el permiso global 'clientes.index', podrá ver todos los clientes.
        if ($user->can('clientes.index')) {
            return static::getResource()::getEloquentQuery();
        }

        // Si tiene el permiso 'clientes.view', solo verá los clientes asignados a él.
        if ($user->can('clientes.view')) {
            return static::getResource()::getEloquentQuery()
                ->whereHas('prestamos', function (Builder $query) use ($user) {
                    // Asegurarse de que el cliente tenga al menos un préstamo con el agente asignado
                    $query->where('agente_asignado', $user->id);
                });
        }

        // Si no cumple ninguna condición, no verá ningún cliente.
        return static::getResource()::getEloquentQuery()->whereRaw('1=0');
    }
}
