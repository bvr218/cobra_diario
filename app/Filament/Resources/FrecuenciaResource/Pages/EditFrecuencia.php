<?php

namespace App\Filament\Resources\FrecuenciaResource\Pages;

use App\Filament\Resources\FrecuenciaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFrecuencia extends EditRecord
{
    protected static string $resource = FrecuenciaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
