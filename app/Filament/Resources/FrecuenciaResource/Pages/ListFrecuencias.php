<?php

namespace App\Filament\Resources\FrecuenciaResource\Pages;

use App\Filament\Resources\FrecuenciaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFrecuencias extends ListRecords
{
    protected static string $resource = FrecuenciaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
