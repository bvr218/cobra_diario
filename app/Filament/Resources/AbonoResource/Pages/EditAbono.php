<?php

namespace App\Filament\Resources\AbonoResource\Pages;

use App\Filament\Resources\AbonoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAbono extends EditRecord
{
    protected static string $resource = AbonoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
