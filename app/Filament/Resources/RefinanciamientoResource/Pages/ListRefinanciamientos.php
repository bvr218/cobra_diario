<?php

namespace App\Filament\Resources\RefinanciamientoResource\Pages;

use App\Filament\Resources\RefinanciamientoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth; // Importar Auth

class ListRefinanciamientos extends ListRecords
{
    protected static string $resource = RefinanciamientoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(Auth::user()->can('refinanciamientos.create')), // Solo visible con permiso create
        ];
    }
}