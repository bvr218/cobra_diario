<?php

namespace App\Filament\Resources\DineroBaseResource\Pages;

use App\Filament\Resources\DineroBaseResource;
use App\Filament\Resources\DineroBaseResource\Widgets\DineroBaseTotal;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDineroBases extends ListRecords
{
    protected static string $resource = DineroBaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /**
     * Aquí indicamos qué widgets deben mostrarse en la cabecera de la lista.
     */
    protected function getHeaderWidgets(): array
    {
        return [
            DineroBaseTotal::class,
        ];
    }
}
