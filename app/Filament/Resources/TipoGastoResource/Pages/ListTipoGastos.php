<?php

namespace App\Filament\Resources\TipoGastoResource\Pages;

use App\Filament\Resources\TipoGastoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTipoGastos extends ListRecords
{
    protected static string $resource = TipoGastoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
