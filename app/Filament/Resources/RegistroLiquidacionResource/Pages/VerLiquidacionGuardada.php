<?php

namespace App\Filament\Resources\RegistroLiquidacionResource\Pages;

use App\Filament\Resources\RegistroLiquidacionResource;
use Filament\Resources\Pages\Page;
use App\Models\RegistroLiquidacion;

class VerLiquidacionGuardada extends Page
{
    protected static string $resource = RegistroLiquidacionResource::class;

    protected static string $view = 'filament.resources.registro-liquidacion-resource.pages.ver-liquidacion-guardada';

    // Esta propiedad pública recibirá automáticamente el modelo gracias al Route Model Binding
    public RegistroLiquidacion $record;

    // El título debe ser público
    public function getTitle(): string
    {
        // Podemos incluso hacerlo dinámico con el nombre de la liquidación
        return 'Liquidación: ' . $this->record->nombre;
    }
}