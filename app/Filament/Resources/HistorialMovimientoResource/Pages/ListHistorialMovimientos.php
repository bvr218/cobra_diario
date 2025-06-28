<?php

namespace App\Filament\Resources\HistorialMovimientoResource\Pages;

use App\Filament\Resources\HistorialMovimientoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHistorialMovimientos extends ListRecords
{
    protected static string $resource = HistorialMovimientoResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
