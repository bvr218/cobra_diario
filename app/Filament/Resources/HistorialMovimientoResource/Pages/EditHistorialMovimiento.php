<?php

namespace App\Filament\Resources\HistorialMovimientoResource\Pages;

use App\Filament\Resources\HistorialMovimientoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHistorialMovimiento extends EditRecord
{
    protected static string $resource = HistorialMovimientoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
