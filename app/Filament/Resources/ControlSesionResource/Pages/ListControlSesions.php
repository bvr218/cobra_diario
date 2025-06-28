<?php

namespace App\Filament\Resources\ControlSesionResource\Pages;

use App\Filament\Resources\ControlSesionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListControlSesions extends ListRecords
{
    protected static string $resource = ControlSesionResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
