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
        if ($user->can('abonosOficina.index')) {
            return static::getResource()::getEloquentQuery()
                ->whereHas('prestamo.cliente', function ($query) use ($user) {
                    $query->where('oficina_id', $user->id);
                });
        }

        // Si tiene abonos.view, ve los abonos de préstamos donde él es agente asignado
        if ($user->can('abonos.view')) {
            return static::getResource()::getEloquentQuery()
                ->whereHas('prestamo', function ($query) use ($user) {
                    $query->where('agente_asignado', $user->id);
                });
        }

        // Si no tiene permisos, no ve nada
        return static::getResource()::getEloquentQuery()->whereRaw('1 = 0');
    }

}
