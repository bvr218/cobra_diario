<?php

namespace App\Observers;

use App\Models\Prestamo;
use App\Models\DineroBase;
use App\Models\User;
use App\Models\HistorialMovimiento;
use Illuminate\Support\Facades\DB;
use App\Notifications\NuevaNotificacion;
use Illuminate\Support\Facades\Log; // Importa la fachada Log para debugging

class PrestamoObserver
{
    public function created(Prestamo $prestamo): void
    {
        $prestamo->estado = $prestamo->estado ?? 'pendiente';

        // Notificación a admins si está pendiente
        if ($prestamo->estado === 'pendiente') {
            $admins = User::role('admin')->get();
            foreach ($admins as $admin) {
                // Asegúrate de que las relaciones existan antes de acceder a ellas
                $registradorName = $prestamo->registrado ? $prestamo->registrado->name : 'Cobrador desconocido';
                $clienteNombre = $prestamo->cliente ? $prestamo->cliente->nombre : 'Cliente desconocido';

                $admin->notify(new NuevaNotificacion(
                    '¡Nuevo préstamo pendiente de autorizar!',
                    "El cobrador {$registradorName} creó un nuevo préstamo y está pendiente de autorización. Cliente: {$clienteNombre}.",
                    "/admin/prestamos/{$prestamo->id}/edit"
                ));
            }
        }

        // Si está autorizado o activo y no se ha aplicado descuento, procesamos estado inicial
        if (in_array($prestamo->estado, ['autorizado', 'activo']) && ! $prestamo->descuento_aplicado) {
            $this->procesarEstadoInicial($prestamo);
        }

        // Si está autorizado o activo y no se ha aplicado al monto_general, lo procesamos
        if (in_array($prestamo->estado, ['autorizado', 'activo']) && !$prestamo->monto_general_aplicado) {
            $interesDelPrestamo = $prestamo->valor_prestado_con_interes - $prestamo->valor_total_prestamo;
            $this->actualizarMontoGeneral($prestamo, $interesDelPrestamo);
            $prestamo->monto_general_aplicado = true;
            $prestamo->saveQuietly(); // Guardar sin disparar observers de nuevo
        }

        // Registrar siempre el movimiento de creación con cambio_desde = cliente_id y el ID del préstamo
        $this->registrarMovimiento(
            $prestamo,
            'creado',
            0,
            null,
            null,
            'creación de préstamo'
        );
    }

    public function updated(Prestamo $prestamo): void
    {
        $originalEstado = $prestamo->getOriginal('estado');
        $nuevoEstado    = $prestamo->estado;
        $changes        = $prestamo->getChanges();
        unset($changes['updated_at']);

        $registro = false;

        // Transición a autorizado/activo sin descuento
        if (
            ! $prestamo->getOriginal('descuento_aplicado', false)
            && ! in_array($originalEstado, ['autorizado', 'activo'])
            && in_array($nuevoEstado, ['autorizado', 'activo'])
        ) {
            $this->procesarEstadoInicial($prestamo);
            // Si aún no se ha aplicado al monto general y ahora está autorizado/activo
            if (!$prestamo->monto_general_aplicado && in_array($nuevoEstado, ['autorizado', 'activo'])) {
                $interesDelPrestamo = $prestamo->valor_prestado_con_interes - $prestamo->valor_total_prestamo;
                $this->actualizarMontoGeneral($prestamo, $interesDelPrestamo);
                $prestamo->monto_general_aplicado = true;
                // $prestamo->saveQuietly(); // Se guardará al final del updated si hay más cambios
            }
            $registro = true;
        }
        // Cambio de estado
        elseif ($originalEstado !== $nuevoEstado) {
            $this->registrarMovimiento(
                $prestamo,
                'actualizado',
                0,
                $prestamo->getOriginal(),
                $changes,
                $nuevoEstado
            );
            $registro = true;
        }

        // Ajuste por cambio en valor total
        // O si cambió valor_prestado_con_interes directamente (por si cambia el interés y valor_total_prestamo no)
        if ( (isset($changes['valor_total_prestamo']) || isset($changes['interes']) ) && in_array($nuevoEstado, ['autorizado', 'activo'])) {
            // Recalcular valor_prestado_con_interes por si acaso, aunque el mutator debería hacerlo
            $prestamo->setValorPrestadoConInteresAttribute(null);
            $nuevoValorConInteres = $prestamo->valor_prestado_con_interes;
            $originalValorConInteres = (float) $prestamo->getOriginal('valor_prestado_con_interes');

            $nuevoInteres = $nuevoValorConInteres - $prestamo->valor_total_prestamo;
            $originalInteres = $originalValorConInteres - (float) $prestamo->getOriginal('valor_total_prestamo');
            $diferenciaDeInteres = $nuevoInteres - $originalInteres;

            $diffValorPrestadoConInteres = $nuevoValorConInteres - $originalValorConInteres;
            if ($diffValorPrestadoConInteres !== 0) {
                $this->restarMonto($prestamo, $prestamo->valor_total_prestamo - $prestamo->getOriginal('valor_total_prestamo'), 'Ajuste del valor total (caja)', true);
                if ($prestamo->monto_general_aplicado) { // Solo ajustar si ya había contribuido
                    $this->actualizarMontoGeneral($prestamo, $diferenciaDeInteres);
                }
                $desc = (count($changes) === 1) ? 'refinanciación' : $nuevoEstado;
                $this->registrarMovimiento(
                    $prestamo,
                    'actualizado',
                    $diffValorPrestadoConInteres,
                    null,
                    null,
                    $desc
                );
                $registro = true;
            }
        }

        // Ajuste de comisión
        if (
            isset($changes['comicion'])
            && $prestamo->descuento_aplicado
            && in_array($nuevoEstado, ['autorizado', 'activo'])
        ) {
            $diffCom = $prestamo->comicion - $prestamo->getOriginal('comicion');
            if ($diffCom !== 0) {
                $this->registrarMovimiento(
                    $prestamo,
                    'actualizado',
                    $diffCom,
                    ['comicion' => $prestamo->getOriginal('comicion')],
                    ['comicion' => $prestamo->comicion],
                    'comisión'
                );
                $registro = true;
            }
        }

        // Si hubo cambios y no se registró nada aún
        if (! $registro && count($changes) > 0) {
            $this->registrarMovimiento(
                $prestamo,
                'actualizado',
                0,
                null,
                null,
                $nuevoEstado
            );
        }
        
        // Guardar cambios hechos en el observer como monto_general_aplicado
        if ($prestamo->isDirty('monto_general_aplicado')) {
            $prestamo->saveQuietly();
        }

        // Notificaciones por cambio de estado
        if ($prestamo->isDirty('estado')) {
            switch ($nuevoEstado) {
                case 'activo':
                    $admins = User::role('admin')->get();
                    foreach ($admins as $admin) {
                        // **Aquí la corrección:** Comprobar si las relaciones existen
                        $registradorName = $prestamo->registrado ? $prestamo->registrado->name : 'Cobrador desconocido';
                        $clienteNombre = $prestamo->cliente ? $prestamo->cliente->nombre : 'Cliente desconocido';
                        $agenteAsignadoId = $prestamo->agenteAsignado ? $prestamo->agenteAsignado->id : null; // Podría ser null

                        $admin->notify(new NuevaNotificacion(
                            '¡Préstamo activado!',
                            "El cobrador {$registradorName} activó el préstamo del cliente {$clienteNombre}.",
                            "/admin/prestamos/?tableFilters[estado][value]=activo&tableFilters[agente_asignado][value]={$agenteAsignadoId}"
                        ));
                    }
                    break;

                case 'autorizado':
                    // **Aquí la corrección:** Comprobar si la relación existe
                    if ($prestamo->agenteAsignado) {
                        $clienteName = $prestamo->cliente ? $prestamo->cliente->name : 'Cliente desconocido';
                        $agenteAsignadoId = $prestamo->agenteAsignado->id;

                        $prestamo->agenteAsignado->notify(new NuevaNotificacion(
                            '¡Préstamo autorizado!',
                            "Te han autorizado el préstamo de {$clienteName}, haz clic para activarlo.",
                            "/admin/prestamos/?tableFilters[estado][value]=autorizado&tableFilters[agente_asignado][value]={$agenteAsignadoId}"
                        ));
                    } else {
                        Log::warning("PrestamoObserver@updated: Agente asignado no encontrado para préstamo {$prestamo->id} al intentar notificar autorización.");
                    }
                    break;
            }
        }
    }

    public function deleted(Prestamo $prestamo): void
    {
        $valorOriginalConInteres = (float) $prestamo->getOriginal('valor_prestado_con_interes');
        $valorTotalPrestamoOriginal = (float) $prestamo->getOriginal('valor_total_prestamo');
        $interesOriginal = $valorOriginalConInteres - $valorTotalPrestamoOriginal;

        if ($prestamo->descuento_aplicado) {
            $this->restarMonto($prestamo, -$valorTotalPrestamoOriginal, 'Reversión por eliminación', false);
        }
        if ($prestamo->getOriginal('monto_general_aplicado')) {
            $this->actualizarMontoGeneral($prestamo, -$interesOriginal);
            $this->registrarMovimiento(
                $prestamo,
                'eliminado',
                -$valorTotalPrestamoOriginal,
                $prestamo->getOriginal(),
                null,
                $prestamo->estado
            );
        }
    }

    protected function procesarEstadoInicial(Prestamo $prestamo): void
    {
        $montoTotal = $prestamo->valor_total_prestamo ?? 0;
        $comision   = $prestamo->comicion ?? 0;

        $this->registrarMovimiento($prestamo, 'estado_inicial',   $montoTotal,    null, null, $prestamo->estado);
        $this->registrarMovimiento($prestamo, 'comicion_inicial', $comision,     null, null, 'comisión');

        $this->restarMonto($prestamo, $montoTotal, 'Préstamo autorizado/activo', false);

        $prestamo->descuento_aplicado = true;
        $prestamo->saveQuietly();
    }

    protected function restarMonto(Prestamo $prestamo, float $monto, string $descripcion, bool $esEdicion = false): void
    {
        if ($monto === 0) {
            return;
        }

        DB::transaction(function () use ($prestamo, $monto, $descripcion, $esEdicion) {
            $usuario = User::find($prestamo->registrado_id);
            if (! $usuario || ! $usuario->dineroBase) {
                Log::warning("PrestamoObserver@restarMonto: No se pudo encontrar usuario o dineroBase para préstamo {$prestamo->id} al restar monto. Usuario ID: " . ($prestamo->registrado_id ?? 'N/A'));
                return;
            }

            // **IMPORTANTE:** Cambiamos para usar la carga del modelo y save() para asegurar el disparo del Observer de DineroBase
            $dineroBase = $usuario->dineroBase;
            if ($monto > 0) {
                $dineroBase->monto -= $monto; // Restar
            } else {
                $dineroBase->monto += abs($monto); // Sumar si $monto es negativo
            }
            $dineroBase->save();

            HistorialMovimiento::create([
                'user_id'       => $usuario->id,
                'tipo'          => 'ajuste_dinero_base',
                'descripcion'   => $descripcion,
                'monto'         => -$monto, // Muestra el efecto en la caja (negativo para salidas)
                'fecha'         => now(),
                'es_edicion'    => $esEdicion,
                'cambio_desde'  => json_encode([
                    'cliente_id' => $prestamo->cliente_id,
                    'id' => $prestamo->id,
                    'monto_antes' => $usuario->dineroBase->getOriginal('monto') // Se requiere un fresh() si el DineroBase se modificó justo antes
                ]),
                'cambio_hacia'  => json_encode(['monto_despues' => $usuario->dineroBase->monto]),
                'referencia_id' => $prestamo->id,
                'tabla_origen'  => 'prestamos',
            ]);
        });
    }

    protected function registrarMovimiento(
        Prestamo $prestamo,
        string $accion,
        float $monto,
        ?array $antes = null,
        ?array $despues = null,
        ?string $descripcionEstado = null
    ): void {
        $tipo = match ($accion) {
            'creado', 'estado_inicial', 'comicion_inicial' => 'creación',
            'actualizado'                                  => 'edición',
            'eliminado'                                    => 'eliminación',
        };

        $descripcion = match ($accion) {
            'estado_inicial'   => $descripcionEstado,
            'comicion_inicial' => 'comisión',
            default            => $descripcionEstado ?? "Préstamo {$tipo}",
        };

        $cambioDesdeData = ['cliente_id' => $prestamo->cliente_id, 'id' => $prestamo->id];

        if ($tipo === 'edición') {
            $antes   = $antes   ?? $prestamo->getOriginal();
            $despues = $despues ?? $prestamo->getChanges();
            unset($antes['updated_at'], $despues['updated_at']);

            // Fusionar los datos originales con los IDs específicos para cambio_desde
            $cambioDesdeData = array_merge($antes, $cambioDesdeData);
        }

        HistorialMovimiento::create([
            'user_id'       => $prestamo->registrado_id,
            'tipo'          => $tipo,
            'descripcion'   => $descripcion,
            'monto'         => $monto,
            'referencia_id' => $prestamo->id,
            'tabla_origen'  => 'prestamos',
            'es_edicion'    => ($tipo === 'edición'),
            'fecha'         => now(),
            'cambio_desde'  => json_encode($cambioDesdeData),
            'cambio_hacia'  => $tipo === 'edición' ? json_encode($despues) : null,
        ]);
    }

    protected function actualizarMontoGeneral(Prestamo $prestamo, float $montoImpacto): void
    {
        if ($montoImpacto == 0) {
            return;
        }

        $usuario = User::find($prestamo->registrado_id);

        if ($usuario) {
            $dineroBase = DineroBase::firstOrCreate(
                ['user_id' => $usuario->id],
                ['monto' => 0, 'monto_general' => 0]
            );

            DineroBase::where('user_id', $usuario->id)
                      ->increment('monto_general', $montoImpacto);
        } else {
             Log::warning("PrestamoObserver@actualizarMontoGeneral: No se pudo encontrar usuario para actualizar monto_general. Usuario ID: " . ($prestamo->registrado_id ?? 'N/A'));
        }
    }

    /**
     * Handle the Prestamo "restored" event.
     */
    public function restored(Prestamo $prestamo): void
    {
        // Si se restaura un préstamo, puedes revertir las acciones de 'deleted'
        // o aplicar la lógica de 'created' si es necesario.
        // Por ejemplo, si un préstamo restaurado vuelve a estar 'activo' o 'autorizado',
        // podrías querer que su monto afecte nuevamente al dinero base o al monto general.
        // Aquí no hay una acción directa, pero puedes agregarla si es necesaria para tu lógica de negocio.
    }

    /**
     * Handle the Prestamo "force deleted" event.
     */
    public function forceDeleted(Prestamo $prestamo): void
    {
        // La lógica para la eliminación forzada puede ser la misma que para 'deleted'
        // si no usas soft deletes, o si quieres que la lógica se aplique siempre.
        $this->deleted($prestamo);
    }
}