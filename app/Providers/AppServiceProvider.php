<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use App\Models\Prestamo;
use App\Models\Abono;
use App\Models\Gasto;
use App\Models\Refinanciamiento;
use App\Models\HistorialMovimiento;
use App\Models\DineroBase;
use App\Models\ControlSesion;
use App\Models\Frecuencia;
use App\Models\Cliente; 
use App\Models\User;     
use App\Observers\PrestamoObserver;
use App\Observers\AbonoObserver;
use App\Observers\GastoObserver;
use App\Observers\RefinanciamientoObserver;
use App\Observers\GenericObserver;
use App\Observers\DineroBaseObserver;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

use Filament\Facades\Filament;
use Filament\Views\Components\AppLayout;      // <--- Importa AppLayout
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;

use App\Livewire\FilamentNotificationBell;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ---------------------------------------------------
        // 1) Observers para tus modelos
        Prestamo::observe(PrestamoObserver::class);
        Refinanciamiento::observe(RefinanciamientoObserver::class);
        Abono::observe(AbonoObserver::class);
        Gasto::observe(GastoObserver::class);
        DineroBase::observe(DineroBaseObserver::class);

        $excluded = [
            HistorialMovimiento::class,
            Prestamo::class,
            Abono::class,
            Gasto::class,
            DineroBase::class,
            Cliente::class,
            User::class,  
            ControlSesion::class,   
            Frecuencia::class,    
        ];

        foreach (glob(app_path('Models/*.php')) as $filename) {
            $modelClass = 'App\\Models\\' . basename($filename, '.php');

            if (
                class_exists($modelClass) &&
                ! in_array($modelClass, $excluded)
            ) {
                $modelClass::observe(GenericObserver::class);
            }
        }

        Role::observe(GenericObserver::class);
        Permission::observe(GenericObserver::class);

        // ---------------------------------------------------
        // 2) Registro de componente Livewire (campana de notificación)
        Livewire::component('filament-notification-bell', FilamentNotificationBell::class);

        // ---------------------------------------------------
        // 3) Hooks de Filament
        Filament::serving(function () {
            // 3.1) Inyectar el componente Livewire de notificación
            Filament::registerRenderHook(
                'panels::global-search.after',
                fn (): string => Blade::render('@livewire(\'filament-notification-bell\')')
            );

            // 3.2) **NUEVOS HOOKS** para Livewire: estilos y scripts
            //     Inyectar @livewireStyles _antes_ de los estilos de Filament
            Filament::registerRenderHook(
                AppLayout::class . '::beforeStyles',
                fn (): string => Blade::render('@livewireStyles')
            );

            //     Inyectar @livewireScripts _antes_ de los scripts finales de Filament
            Filament::registerRenderHook(
                AppLayout::class . '::beforeScripts',
                fn (): string => Blade::render('@livewireScripts')
            );
        });

        // ---------------------------------------------------
        // 4) Hook para inyectar los enlaces y scripts de Vite
        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_BEFORE,
            fn (): string => Blade::render("@vite(['resources/css/app.css','resources/js/app.js'])")
        );
    }
}
