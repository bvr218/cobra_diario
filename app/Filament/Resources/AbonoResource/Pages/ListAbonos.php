<?php

namespace App\Filament\Resources\AbonoResource\Pages;

use App\Filament\Resources\AbonoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAbonos extends ListRecords
{
    protected static string $resource = AbonoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Puedes habilitar esto solo si el usuario tiene permiso de crear
            // Actions\CreateAction::make()->visible(fn () => auth()->user()->can('abonos.create')),
        ];
    }

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();

        // Si tiene abonos.index, ve todos
        if ($user->can('abonos.index')) {
            return static::getResource()::getEloquentQuery();
        }

        // Si tiene abonosOficina.index, ve los abonos de préstamos cuyos clientes son de su misma oficina
        // Y AHORA TAMBIÉN LOS ABONOS DONDE ÉL APARECE COMO registrado_por_id
        if ($user->can('abonosOficina.index')) {
            return static::getResource()::getEloquentQuery()
                ->where(function (Builder $query) use ($user) {
                    $query->whereHas('prestamo.cliente', function ($subQuery) use ($user) {
                        $subQuery->where('oficina_id', $user->id);
                    })
                    ->orWhere('registrado_por_id', $user->id);
                });
        }

        // Si tiene abonos.view, ve los abonos de préstamos donde él es agente asignado
        // Y TAMBIÉN LOS ABONOS DONDE ÉL APARECE COMO registrado_por_id
        if ($user->can('abonos.view')) {
            return static::getResource()::getEloquentQuery()
                ->where(function (Builder $query) use ($user) {
                    $query->whereHas('prestamo', function ($subQuery) use ($user) {
                        $subQuery->where('agente_asignado', $user->id);
                    })
                    ->orWhere('registrado_por_id', $user->id);
                });
        }

        // Si no tiene permisos, no ve nada
        return static::getResource()::getEloquentQuery()->whereRaw('1 = 0');
    }

}