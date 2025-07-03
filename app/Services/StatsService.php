<?php

namespace App\Services;

use App\Models\Prestamo;
use App\Models\Refinanciamiento;
use App\Models\Abono;
use App\Models\HistorialMovimiento;
use App\Models\User;
use App\Models\DineroBase;
use App\Models\Gasto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB; // Importar DB para transacciones

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
        $queryFechaInicio = null;
        $queryFechaFin = null;

        // CAMBIO: Parsear las fechas con las horas si vienen del input datetime-local
        if ($fechaInicioString) {
            $queryFechaInicio = Carbon::parse($fechaInicioString);
        }
        if ($fechaFinString) {
            $queryFechaFin = Carbon::parse($fechaFinString);
        }

        // Clona las bases de consulta para evitar mutaciones inesperadas
        $basePrestamo = Prestamo::where('agente_asignado', $usuarioSeleccionado->id);
        $baseAbono = Abono::where('registrado_por_id', $usuarioSeleccionado->id);
        $baseHistorial = HistorialMovimiento::where('user_id', $usuarioSeleccionado->id); 
        
        // Para cálculos generales de montos, comisiones, etc., seguimos usando solo 'autorizado'
        $baseRefinanciamientoAutorizado = Refinanciamiento::query()
                                ->whereHas("prestamo", fn($q) => $q->where("agente_asignado", $usuarioSeleccionado->id))
                                ->where('estado', 'autorizado'); 
        
        // Base para gastos autorizados
        $baseGastoAutorizado = Gasto::where('user_id', $usuarioSeleccionado->id)->where('autorizado', true);
        // Base para gastos NO autorizados
        $baseGastoNoAutorizado = Gasto::where('user_id', $usuarioSeleccionado->id)->where('autorizado', false);

        // Para el conteo de "Cantidad de Refinanciaciones" que incluye pendientes y autorizadas
        $baseRefinanciamientoConteo = Refinanciamiento::query()
                                ->whereHas("prestamo", fn($q) => $q->where("agente_asignado", $usuarioSeleccionado->id)); // Base para conteos específicos


        if ($queryFechaInicio && $queryFechaFin) {
            // CAMBIO: Usar whereBetween directamente con los objetos Carbon que ya tienen fecha y hora
            $baseHistorial->whereBetween('fecha', [$queryFechaInicio, $queryFechaFin]);
            $basePrestamo->whereBetween('created_at', [$queryFechaInicio, $queryFechaFin]);
            $baseAbono->whereBetween('created_at', [$queryFechaInicio, $queryFechaFin]);
            $baseRefinanciamientoAutorizado->whereBetween('created_at', [$queryFechaInicio, $queryFechaFin]);
            $baseRefinanciamientoConteo->whereBetween('created_at', [$queryFechaInicio, $queryFechaFin]);
            $baseGastoAutorizado->whereBetween('created_at', [$queryFechaInicio, $queryFechaFin]);
            $baseGastoNoAutorizado->whereBetween('created_at', [$queryFechaInicio, $queryFechaFin]);
        }

        // Inicializar todas las variables que se devuelven para evitar "Undefined variable"
        $cantidadRecaudosRealizados = 0;
        $totalPrestamosAsignados = 0;
        $dineroRecaudado = 0;
        $gastosAutorizados = 0;
        $gastosNoAutorizados = 0; // **Nueva variable**
        $dineroEnMano = 0; // **Nueva variable para dinero_en_mano**
        $dineroEnCaja = 0;
        $dineroInicial = 0;
        $dineroCapital = 0;
        $totalPrestado = 0;
        $totalPrestadoConInteres = 0;
        $comisionesPrestamos = 0;
        $comisionesRefinanciamientos = 0;
        $totalComision = 0;
        $prestamosEntregados = 0;
        $prestamosPendientes = 0; // **Nueva variable para conteo de pendientes**
        $cantidadRefinanciaciones = 0;
        $cantidadRefinanciacionesPendientes = 0; // **Nueva variable para pendientes**
        $montoRefinanciaciones = 0;
        $valorRefinanciacionesConInteres = 0;
        $deudaRefinanciadaTotal = 0; // **Nueva variable para la suma de deuda_refinanciada**
        $deudaRefinanciadaInteresTotal = 0; // **Nueva variable para la suma de deuda_refinanciada_interes**


        // Recaudos Realizados y Dinero Recaudado
        $cantidadRecaudosRealizados = (clone $baseAbono)->count();
        $dineroRecaudado = (clone $baseAbono)->sum('monto_abono');

        // Contar el total de préstamos donde el usuario es el agente asignado, SIN filtro de fecha.
        $totalPrestamosAsignados = Prestamo::where('agente_asignado', $usuarioSeleccionado->id)->count();

        // GASTOS AUTORIZADOS
        $gastosAutorizados = (clone $baseGastoAutorizado)->sum('valor');
        $gastosNoAutorizados = (clone $baseGastoNoAutorizado)->sum('valor');


        // Dinero en Caja (ajustado por el rango de fechas si se aplica)
        $queryCaja = HistorialMovimiento::where('user_id', $usuarioSeleccionado->id)
            ->where('es_edicion', true) // CAMBIO: Usar es_edicion en lugar de tipo
            ->where('tabla_origen', 'dinero_bases'); // Mantener esta condición

        $ultimoMovimientoCaja = null;
        if ($queryFechaFin) {
            // CAMBIO: Usar $queryFechaFin directamente, que ya contiene la hora
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

        // Total Prestado
        $totalPrestado = (clone $basePrestamo)
            ->where(fn($q) => $q->where('estado', 'activo')->orWhere('estado','autorizado'))
            ->sum('valor_total_prestamo');

        // Total Prestado con Interés
        $totalPrestadoConInteres = (clone $basePrestamo)
            ->where(fn($q) => $q->where('estado', 'activo')->orWhere('estado','autorizado'))
            ->sum('valor_prestado_con_interes');

        // Comisiones tomadas de Prestamo y Refinanciamiento
        $comisionesPrestamos = (clone $basePrestamo)
                                ->whereNotNull('comicion')
                                ->where('comicion_borrada', false) // <-- Condición para comisiones no borradas
                                ->sum('comicion');

        $comisionesRefinanciamientos = (clone $baseRefinanciamientoAutorizado)
                                        ->whereNotNull('comicion')
                                        ->where('comicion_borrada', false) // <-- Condición para comisiones no borradas
                                        ->sum('comicion');

        $totalComision = $comisionesPrestamos + $comisionesRefinanciamientos;

        // Préstamos Entregados
        $prestamosEntregados = (clone $basePrestamo)
            ->where(function ($q) {
                $q->where('estado', 'activo')->orWhere('estado', 'autorizado');
            })
            ->count();

        // **Préstamos Pendientes**
        $prestamosPendientes = (clone $basePrestamo)
            ->where('estado', 'pendiente')
            ->count();

        // Refinanciaciones:
        // Conteo solo autorizadas para el número principal que se muestra
        $cantidadRefinanciaciones = (clone $baseRefinanciamientoAutorizado)->count();
        
        // Conteo solo pendientes para el paréntesis
        $cantidadRefinanciacionesPendientes = (clone $baseRefinanciamientoConteo)
                                    ->where('estado', 'pendiente')
                                    ->count();
        $montoRefinanciaciones = (clone $baseRefinanciamientoAutorizado)->sum('valor');
        $valorRefinanciacionesConInteres = (clone $baseRefinanciamientoAutorizado)->sum('total');
        // **Calcular la suma de deuda_refinanciada**
        $deudaRefinanciadaTotal = (clone $baseRefinanciamientoAutorizado)->sum('deuda_refinanciada');
        $deudaRefinanciadaInteresTotal = (clone $baseRefinanciamientoAutorizado)->sum('deuda_refinanciada_interes');


        return [
            'cantidadRecaudosRealizados' => $cantidadRecaudosRealizados,
            'totalPrestamosAsignados' => $totalPrestamosAsignados,
            'dineroRecaudado' => $dineroRecaudado,
            'gastosAutorizados' => $gastosAutorizados,
            'gastosNoAutorizados' => $gastosNoAutorizados, // **Añadir al retorno**
            'dineroEnMano' => $dineroEnMano, // **Añadir al retorno**
            'dineroEnCaja' => $dineroEnCaja,
            'dineroInicial' => $dineroInicial,
            'dineroCapital' => $dineroCapital,
            'totalPrestado' => $totalPrestado,
            'totalComision' => $totalComision,
            'prestamosEntregados' => $prestamosEntregados,
            'prestamosPendientes' => $prestamosPendientes, // **Añadir al retorno**
            'totalPrestadoConInteres' => $totalPrestadoConInteres,
            'cantidadRefinanciaciones' => $cantidadRefinanciaciones,
            'cantidadRefinanciacionesPendientes' => $cantidadRefinanciacionesPendientes, // **Añadir al retorno**
            'montoRefinanciaciones' => $montoRefinanciaciones,
            'valorRefinanciacionesConInteres' => $valorRefinanciacionesConInteres,
            'deudaRefinanciadaTotal' => $deudaRefinanciadaTotal, // **Añadir al retorno**
            'deudaRefinanciadaInteresTotal' => $deudaRefinanciadaInteresTotal, // **Añadir al retorno**
        ];
    }

    /**
     * "Elimina" comisiones estableciendo su valor a 0 en Prestamos y Refinanciamientos.
     * No afecta el dinero base del usuario.
     *
     * @param User $usuarioSeleccionado
     * @param string|null $fechaInicioString
     * @param string|null $fechaFinString
     * @return int El número total de comisiones "eliminadas" (establecidas a 0).
     */
    public function deleteUserCommissions(User $usuarioSeleccionado, ?string $fechaInicioString, ?string $fechaFinString): int
    {
        // CAMBIO: Parsear las fechas con las horas si vienen del input datetime-local
        $queryFechaInicio = $fechaInicioString ? Carbon::parse($fechaInicioString) : null;
        $queryFechaFin = $fechaFinString ? Carbon::parse($fechaFinString) : null;
        $deletedCount = 0;

        DB::transaction(function () use ($usuarioSeleccionado, $queryFechaInicio, $queryFechaFin, &$deletedCount) {
            // Comisiones de Préstamos a "eliminar"
            $prestamosQuery = Prestamo::where('agente_asignado', $usuarioSeleccionado->id)
                                    ->whereNotNull('comicion')
                                    ->where('comicion', '>', 0)
                                    ->where('comicion_borrada', false); // <-- Solo comisiones no borradas

            if ($queryFechaInicio && $queryFechaFin) {
                // CAMBIO: Usar whereBetween directamente con los objetos Carbon que ya tienen fecha y hora
                $prestamosQuery->whereBetween('created_at', [$queryFechaInicio, $queryFechaFin]);
            }

            $prestamosConComision = $prestamosQuery->get();

            foreach ($prestamosConComision as $prestamo) {
                $prestamo->comicion_borrada = true; // <-- Cambiar a true en lugar de comicion = 0
                $prestamo->saveQuietly(); 
                $deletedCount++;
            }

            // Comisiones de Refinanciamientos a "eliminar"
            $refinanciamientosQuery = Refinanciamiento::whereHas('prestamo', function ($q) use ($usuarioSeleccionado) {
                                                    $q->where('agente_asignado', $usuarioSeleccionado->id);
                                                })
                                                ->where('estado', 'autorizado')
                                                ->whereNotNull('comicion')
                                                ->where('comicion', '>', 0)
                                                ->where('comicion_borrada', false); // <-- Solo comisiones no borradas

            if ($queryFechaInicio && $queryFechaFin) {
                // CAMBIO: Usar whereBetween directamente con los objetos Carbon que ya tienen fecha y hora
                $refinanciamientosQuery->whereBetween('created_at', [$queryFechaInicio, $queryFechaFin]);
            }

            $refinanciamientosConComision = $refinanciamientosQuery->get();

            foreach ($refinanciamientosConComision as $refinanciamiento) {
                $refinanciamiento->comicion_borrada = true; // <-- Cambiar a true en lugar de comicion = 0
                $refinanciamiento->saveQuietly();
                $deletedCount++;
            }
        });

        return $deletedCount;
    }

    /**
     * Ajusta el dinero base (monto y monto_general) de un usuario y registra el movimiento.
     * Esta función se mantiene, pero ya NO será llamada por deleteUserCommissions
     * si la política es no afectar dinero_bases con las comisiones.
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

        $oldMonto = $dineroBaseRecord->monto;
        $oldMontoGeneral = $dineroBaseRecord->monto_general;

        $adjustedAmount = $isPositive ? $amount : -$amount;

        $newMonto = $oldMonto + $adjustedAmount;
        $newMontoGeneral = $oldMontoGeneral + $adjustedAmount;

        $dineroBaseRecord->monto = $newMonto;
        $dineroBaseRecord->monto_general = $newMontoGeneral;
        $dineroBaseRecord->save();

        return $newMonto;
    }
}