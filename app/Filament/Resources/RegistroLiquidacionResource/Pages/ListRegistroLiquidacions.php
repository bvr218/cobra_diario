<?php

namespace App\Filament\Resources\RegistroLiquidacionResource\Pages;

use App\Filament\Resources\RegistroLiquidacionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRegistroLiquidacions extends ListRecords
{
    protected static string $resource = RegistroLiquidacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
