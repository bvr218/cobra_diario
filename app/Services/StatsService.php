<?php

namespace App\Services;

use App\Models\Prestamo;
use App\Models\Refinanciamiento;
use App\Models\Abono;
use App\Models\HistorialMovimiento;
use App\Models\User;
use App\Models\DineroBase;
use App\Models\Gasto;
use App\Models\AjusteDinero; // Asegúrate de que este modelo exista y esté en la ruta correcta
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StatsService
{
    /**
     * Calcula todas las estadísticas para un usuario dado y un rango de fechas opcional.
     *
     * @param User $usuarioSeleccionado
     * @param string|null $fechaInicioString
     * @param string|null $fechaFinString
     * @return array
     */
    public function computeUserStats(User $usuarioSeleccionado, ?string $fechaInicioString, ?string $fechaFinString): array
    {
        $queryFechaInicio = $fechaInicioString ? Carbon::parse($fechaInicioString) : null;
        $queryFechaFin = $fechaFinString ? Carbon::parse($fechaFinString) : null;

        // --- BASES DE CONSULTA ---
        $basePrestamo = Prestamo::where('agente_asignado', $usuarioSeleccionado->id);
        $baseAbono = Abono::where('registrado_por_id', $usuarioSeleccionado->id);
        $baseHistorial = HistorialMovimiento::where('user_id', $usuarioSeleccionado->id);
        $baseRefinanciamientoConteo = Refinanciamiento::query()
                                ->whereHas("prestamo", fn($q) => $q->where("agente_asignado", $usuarioSeleccionado->id));
        $baseGastoAutorizado = Gasto::where('user_id', $usuarioSeleccionado->id)->where('autorizado', true);
        $baseGastoNoAutorizado = Gasto::where('user_id', $usuarioSeleccionado->id)->where('autorizado', false);
        $baseAjusteDinero = AjusteDinero::where('user_id', $usuarioSeleccionado->id);

        if ($queryFechaInicio && $queryFechaFin) {
            $baseAbono->whereBetween('created_at', [$queryFechaInicio, $queryFechaFin]);
            $baseGastoAutorizado->whereBetween('created_at', [$queryFechaInicio, $queryFechaFin]);
            $baseGastoNoAutorizado->whereBetween('created_at', [$queryFechaInicio, $queryFechaFin]);
            $baseAjusteDinero->whereBetween('created_at', [$queryFechaInicio, $queryFechaFin]);
            $baseHistorial->whereBetween('fecha', [$queryFechaInicio, $queryFechaFin]);

            $dateFilterLogic = function ($query) use ($queryFechaInicio, $queryFechaFin) {
                $query->where(function ($subQuery) use ($queryFechaInicio, $queryFechaFin) {
                    $subQuery->whereNull('authorized_at')
                            ->whereBetween('created_at', [$queryFechaInicio, $queryFechaFin]);
                })->orWhere(function ($subQuery) use ($queryFechaInicio, $queryFechaFin) {
                    $subQuery->whereNotNull('authorized_at')
                            ->whereBetween('authorized_at', [$queryFechaInicio, $queryFechaFin]);
                });
            };

            $basePrestamo->where($dateFilterLogic);
            $baseRefinanciamientoConteo->where($dateFilterLogic);
        }

        $baseRefinanciamientoAutorizado = (clone $baseRefinanciamientoConteo)->where('estado', 'autorizado');

        // --- CÁLCULOS ---

        $cantidadRecaudosRealizados = (clone $baseAbono)->count();
        $dineroRecaudado = (clone $baseAbono)->sum('monto_abono');
        $gastosAutorizados = (clone $baseGastoAutorizado)->sum('valor');
        $gastosNoAutorizados = (clone $baseGastoNoAutorizado)->sum('valor');
        $ajustesDineroCount = (clone $baseAjusteDinero)->count();

        // Total de préstamos asignados (sin filtro de fecha, como en tu versión original)
        $totalPrestamosAsignados = Prestamo::where('agente_asignado', $usuarioSeleccionado->id)->count();
        
        // Préstamos finalizados (usa 'registrado_id', como en tu versión actualizada)
        $prestamosFinalizadosCount = Prestamo::where('registrado_id', $usuarioSeleccionado->id)
                                             ->where('estado', 'finalizado')
                                             ->count();

        // *** INICIO DE LA LÓGICA RESTAURADA ***
        // Dinero en Caja, Base, Capital, etc. (Lógica de la versión original)
        $queryCaja = HistorialMovimiento::where('user_id', $usuarioSeleccionado->id)
            ->where('es_edicion', true)
            ->where('tabla_origen', 'dinero_bases');

        $ultimoMovimientoCaja = null;
        if ($queryFechaFin) {
            $ultimoMovimientoCaja = (clone $queryCaja)
                ->where('fecha', '<=', $queryFechaFin)
                ->orderByDesc('fecha')
                ->first();
        } else {
            $ultimoMovimientoCaja = (clone $queryCaja)
                ->orderByDesc('fecha')
                ->first();
        }
        $dineroEnCaja = ($ultimoMovimientoCaja && $ultimoMovimientoCaja->cambio_hacia)
            ? (json_decode($ultimoMovimientoCaja->cambio_hacia, true)['monto'] ?? 0)
            : 0;

        $dineroBaseUsuario = DineroBase::where('user_id', $usuarioSeleccionado->id)->first();
        $dineroInicial = $dineroBaseUsuario ? (float) $dineroBaseUsuario->monto_inicial : 0;
        $dineroEnMano = $dineroBaseUsuario ? (float) $dineroBaseUsuario->dinero_en_mano : 0;
        $dineroCapital = $dineroBaseUsuario ? (float) $dineroBaseUsuario->monto_general : 0;
        // *** FIN DE LA LÓGICA RESTAURADA ***

        // Cálculos de Préstamos
        $totalPrestado = (clone $basePrestamo)
            ->whereIn('estado', ['activo', 'autorizado'])
            ->sum('valor_total_prestamo');
        $totalPrestadoConInteres = (clone $basePrestamo)
            ->whereIn('estado', ['activo', 'autorizado'])
            ->sum('valor_prestado_con_interes');
        $prestamosEntregados = (clone $basePrestamo)
            ->whereIn('estado', ['activo', 'autorizado'])->count();
        $prestamosPendientes = (clone $basePrestamo)
            ->where('estado', 'pendiente')->count();

        // Cálculos de Refinanciamiento
        $cantidadRefinanciaciones = (clone $baseRefinanciamientoAutorizado)->count();
        $cantidadRefinanciacionesPendientes = (clone $baseRefinanciamientoConteo)
                                    ->where('estado', 'pendiente')->count();
        $montoRefinanciaciones = (clone $baseRefinanciamientoAutorizado)->sum('valor');
        $valorRefinanciacionesConInteres = (clone $baseRefinanciamientoAutorizado)->sum('total');
        $deudaRefinanciadaTotal = (clone $baseRefinanciamientoAutorizado)->sum('deuda_refinanciada');
        $deudaRefinanciadaInteresTotal = (clone $baseRefinanciamientoAutorizado)->sum('deuda_refinanciada_interes');

        // --- LÓGICA AISLADA PARA COMISIONES (de la versión actualizada) ---
        $comisionesPrestamosQuery = Prestamo::where('registrado_id', $usuarioSeleccionado->id)
                                            ->whereIn('estado', ['autorizado', 'activo', 'finalizado', 'desactivado'])
                                            ->whereNotNull('comicion')
                                            ->where('comicion', '>', 0)
                                            ->where('comicion_borrada', false);
        $comisionesRefinanciamientosQuery = Refinanciamiento::whereHas('prestamo', function ($q) use ($usuarioSeleccionado) {
                                                    $q->where('registrado_id', $usuarioSeleccionado->id)
                                                      ->whereIn('estado', ['autorizado', 'activo', 'finalizado', 'desactivado']);
                                                })
                                                ->where('estado', 'autorizado')
                                                ->whereNotNull('comicion')
                                                ->where('comicion', '>', 0)
                                                ->where('comicion_borrada', false);
        if ($queryFechaInicio && $queryFechaFin) {
            $comisionesPrestamosQuery->whereBetween('created_at', [$queryFechaInicio, $queryFechaFin]);
            $comisionesRefinanciamientosQuery->whereBetween('created_at', [$queryFechaInicio, $queryFechaFin]);
        }
        $comisionesPrestamos = $comisionesPrestamosQuery->sum('comicion');
        $comisionesRefinanciamientos = $comisionesRefinanciamientosQuery->sum('comicion');
        $totalComision = $comisionesPrestamos + $comisionesRefinanciamientos;
        
        return [
            'cantidadRecaudosRealizados' => $cantidadRecaudosRealizados,
            'totalPrestamosAsignados' => $totalPrestamosAsignados,
            'dineroRecaudado' => $dineroRecaudado,
            'gastosAutorizados' => $gastosAutorizados,
            'gastosNoAutorizados' => $gastosNoAutorizados,
            'dineroEnMano' => $dineroEnMano,
            'dineroEnCaja' => $dineroEnCaja,
            'dineroInicial' => $dineroInicial,
            'dineroCapital' => $dineroCapital,
            'totalPrestado' => $totalPrestado,
            'totalComision' => $totalComision,
            'prestamosEntregados' => $prestamosEntregados,
            'prestamosPendientes' => $prestamosPendientes,
            'totalPrestadoConInteres' => $totalPrestadoConInteres,
            'cantidadRefinanciaciones' => $cantidadRefinanciaciones,
            'cantidadRefinanciacionesPendientes' => $cantidadRefinanciacionesPendientes,
            'montoRefinanciaciones' => $montoRefinanciaciones,
            'valorRefinanciacionesConInteres' => $valorRefinanciacionesConInteres,
            'deudaRefinanciadaTotal' => $deudaRefinanciadaTotal,
            'deudaRefinanciadaInteresTotal' => $deudaRefinanciadaInteresTotal,
            'prestamosFinalizadosCount' => $prestamosFinalizadosCount,
            'ajustesDineroCount' => $ajustesDineroCount,
        ];
    }

    /**
     * "Elimina" comisiones estableciendo comicion_borrada a true.
     *
     * @param User $usuarioSeleccionado
     * @param string|null $fechaInicioString
     * @param string|null $fechaFinString
     * @return int El número total de comisiones "eliminadas".
     */
    public function deleteUserCommissions(User $usuarioSeleccionado, ?string $fechaInicioString, ?string $fechaFinString): int
    {
        $queryFechaInicio = $fechaInicioString ? Carbon::parse($fechaInicioString) : null;
        $queryFechaFin = $fechaFinString ? Carbon::parse($fechaFinString) : null;
        $deletedCount = 0;

        DB::transaction(function () use ($usuarioSeleccionado, $queryFechaInicio, $queryFechaFin, &$deletedCount) {
            // Comisiones de Préstamos a "eliminar"
            $prestamosQuery = Prestamo::where('registrado_id', $usuarioSeleccionado->id)
                                    ->whereIn('estado', ['autorizado', 'activo', 'finalizado', 'desactivado'])
                                    ->whereNotNull('comicion')
                                    ->where('comicion', '>', 0)
                                    ->where('comicion_borrada', false);

            if ($queryFechaInicio && $queryFechaFin) {
                $prestamosQuery->whereBetween('created_at', [$queryFechaInicio, $queryFechaFin]);
            }

            $prestamosConComision = $prestamosQuery->get();
            foreach ($prestamosConComision as $prestamo) {
                $prestamo->comicion_borrada = true;
                $prestamo->saveQuietly();
                $deletedCount++;
            }

            // Comisiones de Refinanciamientos a "eliminar"
            $refinanciamientosQuery = Refinanciamiento::whereHas('prestamo', function ($q) use ($usuarioSeleccionado) {
                                                    $q->where('registrado_id', $usuarioSeleccionado->id)
                                                      ->whereIn('estado', ['autorizado', 'activo', 'finalizado', 'desactivado']);
                                                })
                                                ->where('estado', 'autorizado')
                                                ->whereNotNull('comicion')
                                                ->where('comicion', '>', 0)
                                                ->where('comicion_borrada', false);

            if ($queryFechaInicio && $queryFechaFin) {
                $refinanciamientosQuery->whereBetween('created_at', [$queryFechaInicio, $queryFechaFin]);
            }

            $refinanciamientosConComision = $refinanciamientosQuery->get();
            foreach ($refinanciamientosConComision as $refinanciamiento) {
                $refinanciamiento->comicion_borrada = true;
                $refinanciamiento->saveQuietly();
                $deletedCount++;
            }
        });

        return $deletedCount;
    }

    /**
     * Ajusta el dinero base (monto y monto_general) de un usuario.
     *
     * @param User $user
     * @param float $amount
     * @param bool $isPositive
     * @return float El nuevo monto del dinero base del usuario.
     */
    public function adjustUserDineroBase(User $user, float $amount, bool $isPositive): float
    {
        $dineroBaseRecord = DineroBase::firstOrCreate(
            ['user_id' => $user->id],
            ['monto' => 0, 'monto_general' => 0, 'dinero_inicial' => 0, 'dinero_en_mano' => 0]
        );

        $adjustedAmount = $isPositive ? $amount : -$amount;
        
        // Se utiliza la función de incremento/decremento de Eloquent para mayor seguridad
        $dineroBaseRecord->increment('monto', $adjustedAmount);
        $dineroBaseRecord->increment('monto_general', $adjustedAmount);

        return $dineroBaseRecord->monto;
    }
}