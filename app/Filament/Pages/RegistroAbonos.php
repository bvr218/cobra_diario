<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class RegistroAbonos extends Page
{
    protected static ?string $navigationIcon = 'iconsax-bro-receipt-item'; // O el ícono que prefieras

    protected static ?string $navigationGroup = 'Registros'; // O el grupo que prefieras

    protected static ?string $navigationLabel = 'Liquidación';

    protected static ?string $title = 'Liquidación';

    protected static string $view = 'filament.pages.registro-abonos';

    // Permisos para acceder a esta página
    public static function canAccess(): bool
    {
        // Asegúrate de que el permiso 'registro.index' exista y esté asignado correctamente
        // o ajusta el permiso según tus necesidades.
        return Auth::user()?->can('registro.index') || Auth::user()?->can('registro.view') ?? false;
    }

    // Opcional: si quieres que aparezca en la navegación solo si tiene permiso
    public static function shouldRegisterNavigation(): bool
    {
        // Mismo permiso que canAccess o uno específico para la navegación
        return Auth::user()?->can('registro.index') || Auth::user()?->can('registro.view') ?? false;
    }
}