<?php

namespace App\Filament\Resources\GastoResource\Pages;

use App\Filament\Resources\GastoResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateGasto extends CreateRecord
{
    protected static string $resource = GastoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Si el usuario tiene solo el permiso de ver gastos, forzamos su propio ID
        if (auth()->user()->can('gastos.view')) {
            $data['user_id'] = auth()->id();
        }

        return $data;
    }
}
