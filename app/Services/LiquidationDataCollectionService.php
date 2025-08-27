<?php

namespace App\Services;

use App\Models\User;
use App\Models\Prestamo;
use App\Models\Abono;
use App\Models\Refinanciamiento;
use App\Models\Gasto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use App\Models\AjusteDinero;

class LiquidationDataCollectionService
{
    /**
     * Recopila todas las listas de datos detallados necesarios para una liquidación guardada
     * dentro de un rango de fechas específico para un usuario dado.
     *
     * @param User $user El usuario (agente/oficina) para el que se recopilan los datos.
     * @param Carbon $fechaInicio Objeto Carbon con la fecha y hora de inicio del rango.
     * @param Carbon $fechaFin Objeto Carbon con la fecha y hora de fin del rango.
     * @return array
     */
    public function getDetailedLists(User $user, Carbon $fechaInicio, Carbon $fechaFin): array
    {
        $usuarioId = $user->id;

        // --- INICIO DE LA LÓGICA DE FECHAS DEFINITIVA ---
        // Función anónima para reutilizar la lógica del filtro de fechas
        $dateFilterLogic = function ($query) use ($fechaInicio, $fechaFin) {
            $query->where(function ($subQuery) use ($fechaInicio, $fechaFin) {
                // Opción 1: Si no tiene fecha de autorización, usa la de creación
                $subQuery->whereNull('authorized_at')
                         ->whereBetween('created_at', [$fechaInicio, $fechaFin]);
            })->orWhere(function ($subQuery) use ($fechaInicio, $fechaFin) {
                // Opción 2: Si SÍ tiene fecha de autorización, usa esa fecha
                $subQuery->whereNotNull('authorized_at')
                         ->whereBetween('authorized_at', [$fechaInicio, $fechaFin]);
            });
        };
        // --- FIN DE LA LÓGICA DE FECHAS DEFINITIVA ---

        // 1. Lista de Préstamos (para Préstamos Entregados, Total Prestado y Total con Interés)
        $prestamosQuery = Prestamo::query()
            ->with('cliente:id,nombre,descripcion')
            ->where('agente_asignado', $usuarioId)
            ->whereIn('estado', ['activo', 'autorizado', 'pendiente']);
        
        $prestamosQuery->where($dateFilterLogic); // <-- APLICANDO LA LÓGICA CORRECTA

        $listaPrestamos = $prestamosQuery->orderBy('posicion_ruta', 'asc')->get()->map(fn($p) => [
            'id' => $p->id,
            'posicion_ruta' => $p->posicion_ruta,
            'cliente_nombre' => $p->cliente->nombre ?? 'N/A',
            'valor_total_prestamo' => $p->valor_total_prestamo,
            'valor_prestado_con_interes' => $p->valor_prestado_con_interes,
            'estado' => $p->estado,
            'created_at' => Carbon::parse($p->created_at)->format('d/m/Y H:i'),
            'cliente_descripcion' => $p->cliente->descripcion ?? 'Sin Descripción',
        ])->toArray();

        // 2. Lista de Abonos (Esta no cambia, siempre usa su propia fecha de creación)
        $abonosQuery = Abono::query()
            ->with(['prestamo.cliente:id,nombre', 'registradoPor:id,name'])
            ->where('registrado_por_id', $usuarioId);
        $abonosQuery->whereBetween('created_at', [$fechaInicio, $fechaFin]);

        $abonosCollection = $abonosQuery->orderBy('created_at', 'desc')->get();
        $dailyClientCounts = $abonosCollection->groupBy(function($abono) {
            return Carbon::parse($abono->created_at)->format('Y-m-d') . '-' . ($abono->prestamo->cliente_id ?? '_');
        });
        $repeatedIds = $dailyClientCounts->filter(fn($group) => $group->count() > 1)->flatten()->pluck('id')->toArray();
        $listaAbonos = $abonosCollection->map(fn($a) => [
            'id' => $a->id,
            'created_at' => Carbon::parse($a->created_at)->format('d/m/Y H:i'),
            'cliente_nombre' => $a->prestamo->cliente->nombre ?? 'N/A',
            'monto_abono' => $a->monto_abono,
            'recaudado_por' => $a->registradoPor->name ?? 'N/A',
            'is_repeated' => in_array($a->id, $repeatedIds),
            'prestamo_id' => $a->prestamo_id,
        ])->toArray();

        // 3. Lista de Refinanciaciones
        $refinanciacionesQuery = Refinanciamiento::query()->with('prestamo.cliente:id,nombre')
            ->whereHas('prestamo', fn($q) => $q->where('agente_asignado', $usuarioId))
            ->whereIn('estado', ['autorizado', 'pendiente']);
            
        $refinanciacionesQuery->where($dateFilterLogic); // <-- APLICANDO LA LÓGICA CORRECTA

        $listaRefinanciaciones = $refinanciacionesQuery->get()->map(fn($r) => [
            'id' => $r->id,
            'cliente_nombre' => $r->prestamo->cliente->nombre ?? 'N/A',
            'created_at' => Carbon::parse($r->created_at)->format('d/m/Y H:i'),
            'deuda_inicial' => $r->prestamo->deuda_inicial ?? 0,
            'deuda_anterior' => $r->deuda_anterior ?? 0,
            'deuda_refinanciada' => $r->deuda_refinanciada,
            'valor' => $r->valor,
            'deuda_refinanciada_interes' => $r->deuda_refinanciada_interes,
            'estado' => $r->estado,
            'prestamo_id' => $r->prestamo_id,
        ])->toArray();

        // 4. Lista de Comisiones
        $prestamoComisionesQuery = Prestamo::where('registrado_id', $usuarioId)
            ->whereIn('estado', ['autorizado', 'activo', 'finalizado', 'desactivado']) // <-- CAMBIO: Filtro de estado añadido
            ->whereNotNull('comicion')->where('comicion', '>', 0)
            ->where('comicion_borrada', false);
        
        // La lógica de fecha original ($dateFilterLogic) se aplica correctamente aquí
        $prestamoComisionesQuery->where(function ($query) use ($fechaInicio, $fechaFin) {
            $query->where(function ($subQuery) use ($fechaInicio, $fechaFin) {
                $subQuery->whereNull('authorized_at')->whereBetween('created_at', [$fechaInicio, $fechaFin]);
            })->orWhere(function ($subQuery) use ($fechaInicio, $fechaFin) {
                $subQuery->whereNotNull('authorized_at')->whereBetween('authorized_at', [$fechaInicio, $fechaFin]);
            });
        });

        $refinanciamientoComisionesQuery = Refinanciamiento::whereHas('prestamo', function($q) use ($usuarioId) {
                $q->where('registrado_id', $usuarioId)
                  ->whereIn('estado', ['autorizado', 'activo', 'finalizado', 'desactivado']); // <-- CAMBIO: Filtro de estado añadido
            })
            ->where('estado', 'autorizado')->whereNotNull('comicion')
            ->where('comicion', '>', 0)->where('comicion_borrada', false);
        
        // La lógica de fecha original ($dateFilterLogic) se aplica correctamente aquí
        $refinanciamientoComisionesQuery->where(function ($query) use ($fechaInicio, $fechaFin) {
            $query->where(function ($subQuery) use ($fechaInicio, $fechaFin) {
                $subQuery->whereNull('authorized_at')->whereBetween('created_at', [$fechaInicio, $fechaFin]);
            })->orWhere(function ($subQuery) use ($fechaInicio, $fechaFin) {
                $subQuery->whereNotNull('authorized_at')->whereBetween('authorized_at', [$fechaInicio, $fechaFin]);
            });
        });

        $listaComisionesPrestamos = $prestamoComisionesQuery->with('cliente:id,nombre')->get()
            ->filter(fn($p) => $p->cliente)
            ->map(fn($p) => [
                'id' => $p->id,
                'fecha' => Carbon::parse($p->created_at),
                'origen' => 'Préstamo',
                'cliente_nombre' => data_get($p, 'cliente.nombre', 'N/A'),
                'monto' => $p->comicion,
                'prestamo_id' => $p->id,
            ]);

        $refinanciamientos = $refinanciamientoComisionesQuery->with('prestamo.cliente:id,nombre')->get();
        $listaComisionesRefinanciamientos = collect();
        foreach ($refinanciamientos as $r) {
            if ($r->prestamo && $r->prestamo->cliente) {
                $listaComisionesRefinanciamientos->push([
                    'id' => $r->id,
                    'fecha' => Carbon::parse($r->created_at),
                    'origen' => 'Refinanciamiento',
                    'cliente_nombre' => $r->prestamo->cliente->nombre,
                    'monto' => $r->comicion,
                    'prestamo_id' => $r->prestamo_id,
                ]);
            }
        }

        $comisionesFusionadas = array_merge($listaComisionesPrestamos->toArray(), $listaComisionesRefinanciamientos->toArray());
        $comisiones = collect($comisionesFusionadas)
            ->sortByDesc(fn($comision) => Carbon::parse($comision['fecha']))
            ->values()
            ->map(function ($comision) {
                $comision['fecha'] = Carbon::parse($comision['fecha'])->format('d/m/Y');
                return $comision;
            })
            ->toArray();

        // 5. Lista de Gastos (Esta no cambia)
        $gastosQuery = Gasto::query()->where('user_id', $usuarioId)->with(['user:id,name', 'tipoGasto:id,nombre']);
        $gastosQuery->whereBetween('created_at', [$fechaInicio, $fechaFin]);

        $listaGastos = $gastosQuery->orderBy('created_at', 'desc')->get()->map(fn($g) => [
            'id' => $g->id,
            'usuario_nombre' => $g->user->name ?? 'N/A',
            'valor' => $g->valor,
            'tipo_gasto' => $g->tipoGasto->nombre ?? 'N/A',
            'informacion' => $g->informacion,
            'created_at' => $g->created_at?->format('d/m/Y H:i'),
            'autorizado' => $g->autorizado,
        ])->toArray();

        // 6. Préstamos Finalizados (Esta no cambia, se basa en la fecha de finalización)
        $prestamosFinalizadosQuery = Prestamo::query()->with(['cliente:id,nombre', 'abonos' => fn($q) => $q->orderBy('created_at', 'desc')])
            ->where('registrado_id', $usuarioId)
            ->where('estado', 'finalizado');
        $prestamosFinalizadosQuery->whereBetween('updated_at', [$fechaInicio, $fechaFin]);

        $listaPrestamosFinalizados = $prestamosFinalizadosQuery->orderBy('updated_at', 'desc')->get()->map(fn($p) => [
            'id' => $p->id,
            'cliente_nombre' => $p->cliente->nombre ?? 'N/A',
            'deuda_inicial' => $p->deuda_inicial,
            'deuda_actual' => $p->deuda_actual,
            'fecha_ultimo_movimiento' => $p->abonos->isNotEmpty() ? Carbon::parse($p->abonos->first()->created_at)->format('d/m/Y H:i') : Carbon::parse($p->updated_at)->format('d/m/Y H:i'),
        ])->toArray();


        // 7. Lista de Ajustes de Dinero (NUEVA)
        $ajustesQuery = AjusteDinero::query()
            ->with('ajustadoPor:id,name')
            ->where('user_id', $usuarioId);
        $ajustesQuery->whereBetween('created_at', [$fechaInicio, $fechaFin]);

        $listaAjustes = $ajustesQuery->orderBy('created_at', 'desc')->get()->map(fn($a) => [
            'id' => $a->id,
            'ajustado_por' => $a->ajustadoPor->name ?? 'N/A',
            'created_at' => Carbon::parse($a->created_at)->format('d/m/Y H:i'),
            'tipo_ajuste' => $a->tipo_ajuste,
            'monto_ajuste' => $a->monto_ajuste,
            'descripcion' => $a->descripcion,
            'dinero_base_antes' => $a->dinero_base_antes,
            'dinero_base_despues' => $a->dinero_base_despues,
        ])->toArray();

        return [
            'prestamos' => $listaPrestamos,
            'recaudos' => $listaAbonos,
            'refinanciaciones' => $listaRefinanciaciones,
            'comisiones' => $comisiones,
            'gastos' => $listaGastos,
            'prestamos_finalizados' => $listaPrestamosFinalizados,
            'ajustes_dinero' => $listaAjustes,
        ];
    }
}