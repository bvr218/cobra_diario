<?php

namespace App\Filament\Resources\DineroBaseResource\Pages;

use App\Filament\Resources\DineroBaseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDineroBase extends EditRecord
{
    protected static string $resource = DineroBaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
