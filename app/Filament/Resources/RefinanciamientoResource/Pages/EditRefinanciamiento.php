<?php

namespace App\Filament\Resources\RefinanciamientoResource\Pages;

use App\Filament\Resources\RefinanciamientoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth; // Importar Auth

class EditRefinanciamiento extends EditRecord
{
    protected static string $resource = RefinanciamientoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(Auth::user()->can('refinanciamientos.delete')), // Solo visible con permiso delete
        ];
    }

    // Este método se ejecutará antes de guardar los cambios
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Si 'comicion_borrada' es true, nos aseguramos de que 'comicion' no se modifique
        // Podrías incluso establecerlo a null o 0 si quieres que no se almacene nada.
        // Aquí lo que hacemos es mantener el valor original si ya estaba marcado como borrada.
        $record = $this->getRecord();
        if ($record && $record->comicion_borrada) {
            $data['comicion'] = $record->comicion;
        }

        return $data;
    }
}