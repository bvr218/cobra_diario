<?php
namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;


class Ruta extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrows-up-down'; // Ícono de ordenar

    protected static string $view = 'filament.pages.ruta';

    protected static ?string $title = 'Ruta de Clientes';

    protected static ?string $navigationGroup = 'Administración de Usuarios';


    public static function canAccess(): bool
    {
        return Auth::user()->can('ruta.index');
    }

        public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()->can('ruta.index');
    }
}
