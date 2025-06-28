<?php

namespace App\Filament\Resources\PrestamoResource\Pages;

use App\Filament\Resources\PrestamoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPrestamos extends ListRecords
{
    protected static string $resource = PrestamoResource::class;

    protected function getHeaderActions(): array
    {
        $headerActions = [
            Actions\CreateAction::make(),
        ];

        // Verifica si el usuario no tiene el permiso 'prestamos.index'
        if (!auth()->user()->can('prestamos.index')) {
            // Agrega el mensaje al headerActions
            $headerActions[] = Actions\Action::make('info_prestamos')
                ->label(
                    "Recuerda activar el préstamo luego de entregar el dinero al cliente."
                )
                ->disabled()
                ->color('gray')
                ->icon('heroicon-o-exclamation-triangle');

        }

        return $headerActions;
    }

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();

        // Si el usuario tiene permiso completo
        if ($user->can('prestamos.index')) {
            return static::getResource()::getEloquentQuery();
        }

        // Empieza con la consulta base
        $query = static::getResource()::getEloquentQuery()->where(function ($q) use ($user) {
            // Si tiene permiso para ver sus préstamos asignados o creados por él (no activos)
            if ($user->can('prestamos.view')) {
                $q->where('agente_asignado', $user->id)
                ->orWhere(function ($subQuery) use ($user) {
                    $subQuery->where('registrado_id', $user->id)
                            ->where('estado', '!=', 'activo');
                });
            }

            // Si tiene permiso para ver préstamos de clientes de su oficina
            if ($user->can('prestamosOficina.index')) {
                $q->orWhereHas('cliente', function ($subQuery) use ($user) {
                    $subQuery->where('oficina_id', $user->id);
                });
            }
        });

        return $query;
    }


}
