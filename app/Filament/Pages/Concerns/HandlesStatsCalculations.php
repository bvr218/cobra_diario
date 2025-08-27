<?php

namespace App\Filament\Pages\Concerns;

use Illuminate\Support\Carbon;
use App\Models\RegistroLiquidacion;
use App\Services\StatsService;
use Filament\Notifications\Notification;
use App\Models\DineroBase; 
use App\Models\HistorialMovimiento;

trait HandlesStatsCalculations
{
    public bool $filtrarPorFecha = true;
    public ?string $fechaInicio = null;
    public ?string $fechaFin = null;
    public int $cantidadRecaudosRealizados = 0;
    public int $totalPrestamosAsignados = 0;
    public float $dineroRecaudado = 0;
    public float $gastosAutorizados = 0;
    public float $gastosNoAutorizados = 0;
    public float $dineroEnCaja = 0;
    public float $totalPrestado = 0;
    public float $totalComision = 0;
    public int $prestamosEntregados = 0;
    public int $prestamosPendientes = 0;
    public float $totalPrestadoConInteres = 0;
    public int $cantidadRefinanciaciones = 0;
    public int $cantidadRefinanciacionesPendientes = 0;
    public float $montoRefinanciaciones = 0;
    public float $valorRefinanciacionesConInteres = 0;
    public float $deudaRefinanciadaTotal = 0;
    public float $deudaRefinanciadaInteresTotal = 0;

    public float $dineroInicial = 0;
    public float $dineroCapital = 0;
    public float $dineroEnMano = 0;

    public int $prestamosFinalizadosCount = 0;

    public int $ajustesDineroCount = 0;

    public function resetStats(): void
    {
        $this->cantidadRecaudosRealizados =
        $this->totalPrestamosAsignados =
        $this->dineroRecaudado =
        $this->gastosAutorizados =
        $this->gastosNoAutorizados =
        $this->dineroEnCaja =
        $this->totalPrestado =
        $this->totalComision =
        $this->prestamosEntregados =
        $this->prestamosPendientes =
        $this->totalPrestadoConInteres =
        $this->cantidadRefinanciaciones =
        $this->cantidadRefinanciacionesPendientes =
        $this->montoRefinanciaciones =
        $this->valorRefinanciacionesConInteres =
        $this->deudaRefinanciadaTotal =
        $this->deudaRefinanciadaInteresTotal = 0;

        $this->prestamosFinalizadosCount = 0;

        $this->dineroInicial = 0;
        $this->dineroCapital = 0;
        $this->dineroEnMano = 0;

        $this->ajustesDineroCount = 0;
    }

    /**
     * Inicializa las fechas de inicio y fin basadas en el estado de $filtrarPorFecha.
     * Este método se llama típicamente en el mount() del componente Livewire.
     */
    public function initializeDatesBasedOnFilter(): void
    {
        // Si el usuario tiene permiso 'registro.view', el filtro de fechas está bloqueado
        // y se establece a las 00:00 del día actual hasta la hora actual.
        if (auth()->user()?->can('registro.view')) {
            $hoy = Carbon::today();
            $this->filtrarPorFecha = true; // Forzar a filtrar por fecha
            $this->fechaInicio = $hoy->copy()->startOfDay()->format('Y-m-d\TH:i:s'); // Incluye segundos
            $this->fechaFin = Carbon::now()->format('Y-m-d\TH:i:s'); // Fecha y hora actual incluyendo segundos
            $this->lockDateFilter = true; // Bloquear los controles de fecha
            return;
        }

        // Si el usuario NO tiene permiso 'registro.view'
        if ($this->filtrarPorFecha) {
            // Si fechaFin no está seteada (ej. primera carga), la inicializamos a la hora actual.
            if (is_null($this->fechaFin)) {
                $this->fechaFin = Carbon::now()->format('Y-m-d\TH:i:s'); // Hora actual para fechaFin
            }

            // Lógica para fechaInicio, solo si no viene de la URL
            if (is_null($this->fechaInicio)) {
                if ($this->usuarioSeleccionado) {
                    $ultimaLiquidacion = RegistroLiquidacion::where('user_id', $this->usuarioSeleccionado->id)
                        ->orderBy('hasta', 'desc')
                        ->first();

                    if ($ultimaLiquidacion && $ultimaLiquidacion->hasta) {
                        $this->fechaInicio = Carbon::parse($ultimaLiquidacion->hasta)->addSecond()->format('Y-m-d\TH:i:s');

                        $carbonFechaInicio = Carbon::parse($this->fechaInicio);
                        $carbonFechaFin = Carbon::parse($this->fechaFin);
                        // Asegurarse de que fechaInicio no sea mayor que fechaFin (si fechaFin es la actual)
                        if ($carbonFechaInicio->greaterThan($carbonFechaFin)) {
                            $this->fechaInicio = Carbon::today()->startOfDay()->format('Y-m-d\TH:i:s');
                        }
                    } else {
                        // Si no hay liquidaciones previas, el inicio es el comienzo del día actual.
                        $this->fechaInicio = Carbon::today()->startOfDay()->format('Y-m-d\TH:i:s');
                    }
                } else {
                    // Si no hay usuario seleccionado, el inicio es el comienzo del día actual.
                    $this->fechaInicio = Carbon::today()->startOfDay()->format('Y-m-d\TH:i:s');
                }
            }
        } else {
            // Si no se está filtrando por fecha, las fechas son nulas.
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
            // Al cambiar a "Día Individual", establecer fechaInicio al inicio del día actual
            // y fechaFin a la hora actual.
            $this->fechaInicio = Carbon::today()->startOfDay()->format('Y-m-d\TH:i:s');
            $this->fechaFin = Carbon::now()->format('Y-m-d\TH:i:s');

            if ($this->usuarioSeleccionado) {
                $ultimaLiquidacion = RegistroLiquidacion::where('user_id', $this->usuarioSeleccionado->id)
                    ->orderBy('hasta', 'desc')
                    ->first();

                if ($ultimaLiquidacion && $ultimaLiquidacion->hasta) {
                    $carbonFechaInicio = Carbon::parse($ultimaLiquidacion->hasta)->addSecond();
                    // Si la última liquidación "hasta" es posterior a la hora actual,
                    // establecemos el inicio del día para evitar rangos futuros.
                    if ($carbonFechaInicio->greaterThan(Carbon::now())) {
                        $this->fechaInicio = Carbon::today()->startOfDay()->format('Y-m-d\TH:i:s');
                    } else {
                        $this->fechaInicio = $carbonFechaInicio->format('Y-m-d\TH:i:s');
                    }
                }
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

    public function reloadStats(): void
    {

        if ($this->usuarioSeleccionado) {
            // Utilizamos una transacción por si en el futuro se añaden más operaciones.
            \Illuminate\Support\Facades\DB::transaction(function () {
                // Obtenemos el estado actual REAL de dinero_bases para el usuario.
                // firstOrCreate es seguro, si no existe lo crea con valores por defecto.
                $dineroBase = DineroBase::firstOrCreate(
                    ['user_id' => $this->usuarioSeleccionado->id],
                    ['monto' => 0, 'dinero_en_mano' => 0, 'monto_general' => 0]
                );

                // Preparamos el estado actual para guardarlo en los campos JSON.
                $estadoActual = [
                    'monto' => $dineroBase->monto,
                ];

                // Creamos el nuevo registro en el historial.
                // Este registro no cambia ningún valor (monto es 0), pero su sola existencia
                // con la fecha actual y los JSON correctos soluciona el problema de estado.
                HistorialMovimiento::create([
                    'user_id'       => $this->usuarioSeleccionado->id,
                    'tipo'          => 'refresco_manual', // Un tipo descriptivo.
                    'descripcion'   => 'Refresco manual del estado de caja para sincronización.',
                    'monto'         => 0, // No hay impacto monetario.
                    'fecha'         => now(),
                    'referencia_id' => $this->usuarioSeleccionado->id, // Usamos el ID de usuario como referencia.
                    'tabla_origen'  => 'dinero_bases',
                    'es_edicion'    => true, // ¡MUY IMPORTANTE! Para que tu StatsService lo encuentre.
                    'cambio_desde'  => json_encode($estadoActual), // El "antes" es el estado actual.
                    'cambio_hacia'  => json_encode($estadoActual), // El "después" es el mismo estado actual.
                ]);
            });
        }


        // Lógica original del método (se mantiene igual)
        if ($this->filtrarPorFecha) {
            // Si está filtrando por fecha, actualiza fechaFin a la fecha y hora actual
            $this->fechaFin = Carbon::now()->format('Y-m-d\TH:i:s'); // Incluye segundos para mayor precisión
        }

        // Siempre recalcula las estadísticas. Al hacerlo, StatsService ahora verá
        // el nuevo registro que acabamos de crear como el más reciente.
        $this->computeStats();

        // Notificar al usuario que los datos se han recargado
        Notification::make()
            ->title('Datos Recargados y Sincronizados')
            ->body('Las estadísticas han sido actualizadas y el estado de la caja ha sido sincronizado.')
            ->success()
            ->send();
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
        $this->totalPrestamosAsignados = $stats['totalPrestamosAsignados'];
        $this->dineroRecaudado = $stats['dineroRecaudado'];
        $this->gastosAutorizados = $stats['gastosAutorizados'];
        $this->gastosNoAutorizados = $stats['gastosNoAutorizados'];
        $this->dineroEnCaja = $stats['dineroEnCaja'];
        $this->totalPrestado = $stats['totalPrestado'];
        $this->totalComision = $stats['totalComision'];
        $this->prestamosEntregados = $stats['prestamosEntregados'];
        $this->prestamosPendientes = $stats['prestamosPendientes'];
        $this->totalPrestadoConInteres = $stats['totalPrestadoConInteres'];
        $this->cantidadRefinanciaciones = $stats['cantidadRefinanciaciones'];
        $this->cantidadRefinanciacionesPendientes = $stats['cantidadRefinanciacionesPendientes'];
        $this->montoRefinanciaciones = $stats['montoRefinanciaciones'];
        $this->valorRefinanciacionesConInteres = $stats['valorRefinanciacionesConInteres'];
        $this->deudaRefinanciadaTotal = $stats['deudaRefinanciadaTotal'];
        $this->deudaRefinanciadaInteresTotal = $stats['deudaRefinanciadaInteresTotal'];

        $this->prestamosFinalizadosCount = $stats['prestamosFinalizadosCount'];

        $this->ajustesDineroCount = $stats['ajustesDineroCount'];

        $this->dineroInicial = $stats['dineroInicial'];
        $this->dineroCapital = $stats['dineroCapital'];
        $this->dineroEnMano = $stats['dineroEnMano'];
    }
}