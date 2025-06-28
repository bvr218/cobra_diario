<?php

namespace App\Filament\Pages\Concerns;

use Illuminate\Support\Carbon;
use App\Models\RegistroLiquidacion; // Importar el modelo RegistroLiquidacion
use App\Services\StatsService; // Asegúrate de que esta clase exista y sea importada

trait HandlesStatsCalculations
{
    // Propiedades de estado para filtros de fecha y HORA (declaradas aquí como sus "dueños")
    public bool $filtrarPorFecha = true; // Cambiado a true para que "Día Individual" sea el default
    public ?string $fechaInicio = null; // Ahora puede contener fecha y hora
    public ?string $fechaFin = null;    // Ahora puede contener fecha y hora

    // ... (otras propiedades de estado de estadísticas) ...
    public int $cantidadRecaudosRealizados = 0;
    public float $dineroRecaudado = 0;
    public float $gastosAutorizados = 0;
    public float $gastosNoAutorizados = 0;
    public float $dineroEnCaja = 0;
    public float $totalPrestado = 0;
    public float $totalComision = 0;
    public int $prestamosEntregados = 0;
    public int $prestamosPendientes = 0; // Nueva propiedad para préstamos pendientes
    public float $totalPrestadoConInteres = 0;
    public int $cantidadRefinanciaciones = 0;
    public int $cantidadRefinanciacionesPendientes = 0;
    public float $montoRefinanciaciones = 0;    
    public float $valorRefinanciacionesConInteres = 0;
    public float $deudaRefinanciadaTotal = 0;
    public float $deudaRefinanciadaInteresTotal = 0;
    
    public float $dineroInicialDelUsuario = 0;
    public float $dineroCapital = 0;
    public float $dineroEnMano = 0; // Nueva propiedad para dinero_en_mano

    public function resetStats(): void
    {
        $this->cantidadRecaudosRealizados =
        $this->dineroRecaudado =
        $this->gastosAutorizados =
        $this->gastosNoAutorizados =
        $this->dineroEnCaja =
        $this->totalPrestado =
        $this->totalComision =
        $this->prestamosEntregados =
        $this->prestamosPendientes = // Resetear la nueva propiedad
        $this->totalPrestadoConInteres =
        $this->cantidadRefinanciaciones =
        $this->cantidadRefinanciacionesPendientes =
        $this->montoRefinanciaciones =
        $this->valorRefinanciacionesConInteres = 
        $this->deudaRefinanciadaTotal =
        $this->deudaRefinanciadaInteresTotal = 0;
        
        $this->dineroInicialDelUsuario = 0;
        $this->dineroCapital = 0;
        $this->dineroEnMano = 0; // Resetear la nueva propiedad
    }

    /**
     * Inicializa las fechas de inicio y fin basadas en el estado de $filtrarPorFecha.
     * Este método se llama típicamente en el mount() del componente Livewire.
     */
    public function initializeDatesBasedOnFilter(): void
    {
        // Lógica prioritaria para usuarios con permiso 'registro.view'.
        // Esto asegura que, sin importar desde dónde se llame este método (mount, selectUsuario, etc.),
        // el rango de fechas siempre será el día actual completo.
        if (auth()->user()?->can('registro.view')) {
            $hoy = Carbon::today();
            $this->filtrarPorFecha = true;
            // Usamos copy() y start/endOfDay() para ser más explícitos y seguros.
            $this->fechaInicio = $hoy->copy()->startOfDay()->format('Y-m-d\TH:i');
            $this->fechaFin = $hoy->copy()->endOfDay()->format('Y-m-d\TH:i');
            // Salimos del método para evitar que la lógica de abajo sobrescriba las fechas.
            return;
        }

        if ($this->filtrarPorFecha) {
            // Inicializar fechaFin a la hora actual si no está ya seteada (ej. por URL)
            if (is_null($this->fechaFin)) {
                $this->fechaFin = Carbon::now()->format('Y-m-d\TH:i'); // Hora actual para fechaFin
            }

            // Lógica para fechaInicio, solo si no viene de la URL
            if (is_null($this->fechaInicio)) {
                if ($this->usuarioSeleccionado) {
                    $ultimaLiquidacion = RegistroLiquidacion::where('user_id', $this->usuarioSeleccionado->id)
                        ->orderBy('hasta', 'desc')
                        ->first();

                    if ($ultimaLiquidacion && $ultimaLiquidacion->hasta) {
                        $this->fechaInicio = Carbon::parse($ultimaLiquidacion->hasta)->addSecond()->format('Y-m-d\TH:i');

                        // Si fechaInicio calculado es posterior a fechaFin (hora actual), ajustar.
                        $carbonFechaInicio = Carbon::parse($this->fechaInicio);
                        $carbonFechaFin = Carbon::parse($this->fechaFin); // Ya es Carbon::now() o de la URL
                        if ($carbonFechaInicio->greaterThan($carbonFechaFin)) {
                            $this->fechaInicio = Carbon::today()->startOfDay()->format('Y-m-d\TH:i');
                        }
                    } else {
                        // No hay liquidaciones, usar inicio del día actual
                        $this->fechaInicio = Carbon::today()->startOfDay()->format('Y-m-d\TH:i');
                    }
                } else {
                    // No hay usuario seleccionado, usar inicio del día actual
                    $this->fechaInicio = Carbon::today()->startOfDay()->format('Y-m-d\TH:i');
                }
            }
        } else {
            // Si filtrarPorFecha es false (anulado por URL, ej. f=0)
            $this->fechaInicio = null;
            $this->fechaFin = null;
        }
    }
    public function updatedFiltrarPorFecha(): void
    {
        if (!$this->filtrarPorFecha) {
            $this->fechaInicio = null;
            $this->fechaFin = null;
        } else {
            $this->fechaInicio = Carbon::today()->startOfDay()->format('Y-m-d\TH:i');
            $this->fechaFin = Carbon::now()->format('Y-m-d\TH:i'); // Hora actual para fechaFin

            // Aplicar lógica de última liquidación si hay usuario seleccionado
            if ($this->usuarioSeleccionado) {
                $ultimaLiquidacion = RegistroLiquidacion::where('user_id', $this->usuarioSeleccionado->id)
                    ->orderBy('hasta', 'desc')
                    ->first();

                if ($ultimaLiquidacion && $ultimaLiquidacion->hasta) {
                    $this->fechaInicio = Carbon::parse($ultimaLiquidacion->hasta)->addSecond()->format('Y-m-d\TH:i');

                    // Si fechaInicio calculado es posterior a fechaFin (hora actual), ajustar.
                    $carbonFechaInicio = Carbon::parse($this->fechaInicio);
                    // $this->fechaFin ya es Carbon::now()
                    if ($carbonFechaInicio->greaterThan(Carbon::parse($this->fechaFin))) {
                        $this->fechaInicio = Carbon::today()->startOfDay()->format('Y-m-d\TH:i');
                    }
                }
                // Si no hay última liquidación, fechaInicio ya está como Carbon::today()->startOfDay()
            }
        }
        $this->computeStats();
    }

    public function updatedFechaInicio(): void
    {
        $this->computeStats();
    }

    public function updatedFechaFin(): void
    {
        $this->computeStats();
    }

    public function computeStats(): void
    {
        if (! $this->usuarioSeleccionado) {
            $this->resetStats();
            return;
        }

        $statsService = app(StatsService::class);
        $stats = $statsService->computeUserStats(
            $this->usuarioSeleccionado,
            $this->filtrarPorFecha ? $this->fechaInicio : null,
            $this->filtrarPorFecha ? $this->fechaFin : null
        );

        $this->cantidadRecaudosRealizados = $stats['cantidadRecaudosRealizados'];
        $this->dineroRecaudado = $stats['dineroRecaudado'];
        $this->gastosAutorizados = $stats['gastosAutorizados'];
        $this->gastosNoAutorizados = $stats['gastosNoAutorizados'];
        $this->dineroEnCaja = $stats['dineroEnCaja'];
        $this->totalPrestado = $stats['totalPrestado'];
        $this->totalComision = $stats['totalComision'];
        $this->prestamosEntregados = $stats['prestamosEntregados'];
        $this->prestamosPendientes = $stats['prestamosPendientes']; // Asignar el nuevo valor
        $this->totalPrestadoConInteres = $stats['totalPrestadoConInteres'];
        $this->cantidadRefinanciaciones = $stats['cantidadRefinanciaciones'];
        $this->cantidadRefinanciacionesPendientes = $stats['cantidadRefinanciacionesPendientes'];
        $this->montoRefinanciaciones = $stats['montoRefinanciaciones'];
        $this->valorRefinanciacionesConInteres = $stats['valorRefinanciacionesConInteres'];
        $this->deudaRefinanciadaTotal = $stats['deudaRefinanciadaTotal'];
        $this->deudaRefinanciadaInteresTotal = $stats['deudaRefinanciadaInteresTotal'];
        
        $this->dineroInicialDelUsuario = $stats['dineroInicial'];
        $this->dineroCapital = $stats['dineroCapital'];
        $this->dineroEnMano = $stats['dineroEnMano']; // Asignar el nuevo valor
    }
}