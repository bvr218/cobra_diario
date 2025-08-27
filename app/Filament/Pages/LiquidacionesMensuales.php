<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class LiquidacionesMensuales extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days'; // Elige un ícono adecuado
    protected static ?string $navigationGroup = 'Registros';
    protected static ?string $navigationLabel = 'Resumen Mensual';
    protected static ?string $title = 'Resumen Mensual';

    protected static string $view = 'filament.pages.liquidaciones-mensuales';

    // Opcional: Permisos para acceder a esta página
    public static function canAccess(): bool
    {
        // Asigna un permiso específico, por ejemplo, 'liquidacion_mensual.view'
        return Auth::user()?->can('liquidaciones-mensuales.index') ?? false;
    }

    // Opcional: Mostrar en la navegación solo si tiene permiso
    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->can('liquidaciones-mensuales.index') ?? false;
    }
}