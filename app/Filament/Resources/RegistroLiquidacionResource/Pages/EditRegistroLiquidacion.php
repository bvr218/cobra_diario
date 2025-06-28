<?php

namespace App\Filament\Resources\RegistroLiquidacionResource\Pages;

use App\Filament\Resources\RegistroLiquidacionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRegistroLiquidacion extends EditRecord
{
    protected static string $resource = RegistroLiquidacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
