<?php

namespace App\Observers;

use App\Models\Refinanciamiento;
use App\Models\DineroBase;
use App\Models\HistorialMovimiento;
use App\Models\User;
use App\Models\Prestamo;
use App\Notifications\NuevaNotificacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RefinanciamientoObserver
{
    /**
     * Antes de insertar un nuevo Refinanciamiento, calculamos su total y deuda_refinanciada.
     */
    public function creating(Refinanciamiento $refinanciamiento): void
    {
        // 1. Obtener el préstamo asociado
        $prestamo = Prestamo::find($refinanciamiento->prestamo_id);

        // Inicializar variables que se usan condicionalmente para evitar "Undefined variable"
        $deudaActualPrestamo = 0.0;
        $valorNuevoRefinanciamiento = (float) $refinanciamiento->valor; // Siempre inicializar con el valor del refinanciamiento
        $interesGenerado = 0.0; // Inicializar en caso de que el préstamo no exista o los cálculos fallen

        if ($prestamo) {
            // 2. Obtener la deuda actual del préstamo ANTES de esta operación
            // Importante: Aquí se accede al accesor `deuda_actual` del modelo Prestamo
            // que ya considera refinanciamientos autorizados.
            $deudaActualPrestamo = (float) $prestamo->deuda_actual;

            // Guardar la deuda actual del préstamo en deuda_anterior del refinanciamiento
            $refinanciamiento->deuda_anterior = (float) $deudaActualPrestamo;

            // 3. Valor “nuevo” de dinero inyectado en este refinanciamiento
            // $valorNuevoRefinanciamiento ya está inicializado arriba.

            // Cálculo para deuda_refinanciada
            // Se calcula como la suma de la deuda_actual del préstamo y el valor nuevo del refinanciamiento.
            $refinanciamiento->deuda_refinanciada = (int) round($deudaActualPrestamo + $valorNuevoRefinanciamiento);

            // 4. Base para calcular interés: deudaActual (del préstamo) + dineroNuevo (de la refinanciación)
            $baseParaInteres = $deudaActualPrestamo + $valorNuevoRefinanciamiento;

            // 5. Tasa de interés de la refinanciación (porcentual → decimal)
            $tasaInteresRef = (float) $refinanciamiento->interes / 100;

            // 6. Cálculo del monto de interés generado
            $interesGenerado = $baseParaInteres * $tasaInteresRef;

            // 7. Asignar el campo 'total' del propio Refinanciamiento
            $refinanciamiento->total = (int) round($valorNuevoRefinanciamiento + $interesGenerado);

            // 8. Cálculo para deuda_refinanciada_interes
            // Se calcula como la suma de la deuda_actual del préstamo y el 'total' del refinanciamiento.
            $refinanciamiento->deuda_refinanciada_interes = (int) round($deudaActualPrestamo + $refinanciamiento->total);

        } else {
            Log::warning("RefinanciamientoObserver@creating: Préstamo con ID {$refinanciamiento->prestamo_id} no encontrado para el refinanciamiento.");
            // Si no existe el préstamo, simplemente dejamos los campos calculados en 0.
            // Y aseguramos que las variables de cálculo también sean 0 para evitar errores posteriores si se usaran.
            $refinanciamiento->total = 0;
            $refinanciamiento->deuda_refinanciada = 0;
            $refinanciamiento->deuda_refinanciada_interes = 0;
            $refinanciamiento->deuda_anterior = 0; // También inicializar a 0 si no se encuentra el préstamo
            
            // Aunque $valorNuevoRefinanciamiento ya fue inicializado, si queremos que su "efecto" sea 0 en este caso,
            // podríamos reasignarlo aquí, pero para los cálculos que se hacen en este `else`, ya los campos del modelo
            // están siendo puestos a 0. Así que no es estrictamente necesario reasignarlo a 0 si no se va a usar después.
            // Para mantener la consistencia con los valores del modelo:
            $valorNuevoRefinanciamiento = 0.0;
            $interesGenerado = 0.0;
        }
    }

    /**
     * Handle the Refinanciamiento "created" event.
     * Ahora NO hace ningún ajuste en dineroBase ni monto_general hasta autorizar.
     */
    public function created(Refinanciamiento $refinanciamiento): void
    {
        Log::info('RefinanciamientoObserver@created: Refinanciamiento ' . $refinanciamiento->id . ' creado en estado PENDIENTE.');
        // No ajustamos dineroBase ni monto_general aquí, porque
        // el refinanciamiento se crea siempre con estado 'pendiente'
        // y no se deben reflejar cambios hasta autorizarlo.

        // Notificación a administradores sobre nueva refinanciación pendiente
        $prestamo = $refinanciamiento->prestamo;
        if ($prestamo && $refinanciamiento->estado === 'pendiente') {
            $admins = User::role('admin')->get();
            foreach ($admins as $admin) {
                // Asegurarse de que las relaciones existan antes de acceder a ellas
                $registradorName = $prestamo->registrado ? $prestamo->registrado->name : 'Cobrador desconocido';
                $clienteNombre = $prestamo->cliente ? $prestamo->cliente->nombre : 'Cliente desconocido';

                $admin->notify(new NuevaNotificacion(
                    '¡Nueva refinanciación pendiente de autorizar!',
                    "El cobrador {$registradorName} creó una nueva refinanciación para el cliente {$clienteNombre} y está pendiente de autorización.",
                    "/admin/prestamos/{$prestamo->id}/edit"
                ));
            }
        }
    }

    /**
     * Handle the Refinanciamiento "updated" event.
     * 1) Si cambia de estado → autorizado, aplicamos por primera vez:
     * • Descontar de dinero_base (valor).
     * • Incrementar monto_general con el interés.
     * • Sumar la comisión al préstamo padre.
     * 2) Si YA estaba autorizado y alguien edita valor/interes/comicion,
     * ajustamos las diferencias correspondientes:
     * • Valor → ajusta dinero_base y recalcula deuda_refinanciada y total.
     * • Interés → recalcula total y ajusta monto_general.
     * • Comisión → ajusta comicion en el préstamo padre.
     */
    public function updated(Refinanciamiento $refinanciamiento): void
    {
        $originalEstado = $refinanciamiento->getOriginal('estado');
        $nuevoEstado    = $refinanciamiento->estado;
        $changes        = $refinanciamiento->getChanges();
        unset($changes['updated_at']);

        $prestamo = $refinanciamiento->prestamo;
        if (! $prestamo) {
            Log::warning("RefinanciamientoObserver@updated: Préstamo no encontrado para refinanciamiento {$refinanciamiento->id}.");
            return;
        }

        // Determinar el ID de usuario a usar para los ajustes de dinero_base y monto_general
        // Prioriza 'agente_asignado' si no es null, de lo contrario, usa 'registrado_id'.
        $userIdToAffect = $prestamo->agente_asignado ?? $prestamo->registrado_id;

        if (is_null($userIdToAffect)) {
            Log::error("RefinanciamientoObserver@updated: No se pudo determinar el ID de usuario para afectar el dinero base o monto general para el préstamo ID {$prestamo->id}.");
            return;
        }


        // 1) Transición PENDIENTE/NEGADO → AUTORIZADO (por primera vez)
        if ($originalEstado !== 'autorizado' && $nuevoEstado === 'autorizado') {
            Log::info("RefinanciamientoObserver@updated: Refinanciamiento {$refinanciamiento->id} cambiado a AUTORIZADO.");

            // a) Ajustar dinero_base (restar 'valor')
            if ((float) $refinanciamiento->valor > 0) {
                $this->ajustarDineroBasePorRefinanciamiento($prestamo, $refinanciamiento, $userIdToAffect);
            }

            // b) Calcular y actualizar monto_general con el interés (total - valor)
            $interesRefinanciamiento = (float)$refinanciamiento->total - (float)$refinanciamiento->valor;
            if ($interesRefinanciamiento > 0) {
                $this->actualizarMontoGeneral($prestamo, $interesRefinanciamiento, $userIdToAffect);
            }

            // c) Sumar la comisión de esta refinanciación al campo 'comicion' del préstamo padre
            $comRef = (float) $refinanciamiento->comicion;
            if ($comRef > 0) {
                $prestamo->increment('comicion', $comRef);
            }

            return; // Ya procesamos todo para la primera autorización
        }

        // 2) Si YA estaba 'autorizado' y sigue en 'autorizado', detectamos ajustes
        if ($originalEstado === 'autorizado' && $nuevoEstado === 'autorizado') {
            $originalTotal                  = (float) $refinanciamiento->getOriginal('total');
            $originalValor                  = (float) $refinanciamiento->getOriginal('valor');
            // La deuda_actual del préstamo **antes** de que este refinanciamiento afectara su total.
            // Para obtenerla, restamos el 'total' original de este refinanciamiento a la deuda_actual del préstamo.
            $deudaPrevioSinRef              = $prestamo->deuda_actual - $originalTotal; 
            
            $originalValorRefinanciamiento  = (float) $refinanciamiento->getOriginal('valor');
            $nuevoValorRefinanciamiento     = (float) $refinanciamiento->valor;


            // a) Ajuste si cambia el VALOR (dinero entregado)
            if ($refinanciamiento->isDirty('valor')) {
                $originalValor  = (float) $refinanciamiento->getOriginal('valor');
                $nuevoValor     = (float) $refinanciamiento->valor;
                $diferenciaValor = $nuevoValor - $originalValor; // >0 si aumenta, <0 si disminuye

                if ($diferenciaValor !== 0) {
                    $usuario = User::find($userIdToAffect); // Usar el ID de usuario determinado
                    if ($usuario && $usuario->dineroBase) {
                        $dineroBase = $usuario->dineroBase;
                        // Si diferenciaValor > 0 → restamos más; si < 0 → devolvemos
                        $dineroBase->monto -= $diferenciaValor;
                        $dineroBase->save();

                        Log::info("RefinanciamientoObserver@updated: dineroBase ajustado en {$diferenciaValor} para usuario {$usuario->id}. Nuevo monto: {$dineroBase->monto}");

                        // Registrar historial del ajuste de dinero_base
                        HistorialMovimiento::create([
                            'user_id'       => $usuario->id, // Usar el ID del usuario al que se le restó
                            'tipo'          => 'ajuste_dinero_refinanciacion',
                            'descripcion'   => "Ajuste por cambio de valor en refinanciamiento ID {$refinanciamiento->id}",
                            'monto'         => -$diferenciaValor,
                            'fecha'         => now(),
                            'es_edicion'    => true,
                            'referencia_id' => $refinanciamiento->id,
                            'tabla_origen'  => 'refinanciamientos',
                            'cambio_desde'  => json_encode(['valor_anterior' => $originalValor]),
                            'cambio_hacia'  => json_encode(['valor_nuevo'   => $nuevoValor]),
                        ]);
                    }
                }
            }

            // b) Ajuste si cambia el INTERÉS o el VALOR (recalculamos total e interés)
            if ($refinanciamiento->isDirty('interes') || $refinanciamiento->isDirty('valor')) {
                // Interés original: total_original - valor_original
                $originalTotal   = (float) $refinanciamiento->getOriginal('total');
                $originalValor   = (float) $refinanciamiento->getOriginal('valor');
                $originalInteres = $originalTotal - $originalValor;

                // Interés nuevo: necesitamos recalcular total:
                $nuevoValor              = (float) $refinanciamiento->valor;
                $nuevaTasa               = ((float) $refinanciamiento->interes) / 100;

                // Para recalcular el total, usamos la deuda del préstamo como si este refinanciamiento no estuviera
                // en su total actual, y luego sumamos el nuevo valor del refinanciamiento.
                // $deudaPrevioSinRef ya se calculó arriba.
                $baseParaNuevoInteres = $deudaPrevioSinRef + $nuevoValor;
                $nuevoTotal              = $nuevoValor + ($baseParaNuevoInteres * $nuevaTasa);
                $nuevoTotalRedondeado    = (int) round($nuevoTotal);

                // Guardar el total recalculado (sin disparar observer de nuevo)
                if ($refinanciamiento->total != $nuevoTotalRedondeado) {
                    $refinanciamiento->total = $nuevoTotalRedondeado;
                    // No llamamos saveQuietly() aquí, se llamará al final si hay cambios en las deudas.
                }

                // Recalcular deuda_refinanciada y deuda_refinanciada_interes
                // Usamos $deudaPrevioSinRef que es la deuda del préstamo antes de este refinanciamiento
                $newDeudaRefinanciada = (int) round($deudaPrevioSinRef + $nuevoValor);
                if ($refinanciamiento->deuda_refinanciada != $newDeudaRefinanciada) {
                    $refinanciamiento->deuda_refinanciada = $newDeudaRefinanciada;
                }

                $newDeudaRefinanciadaInteres = (int) round($deudaPrevioSinRef + $refinanciamiento->total); // Usamos el nuevo total
                if ($refinanciamiento->deuda_refinanciada_interes != $newDeudaRefinanciadaInteres) {
                    $refinanciamiento->deuda_refinanciada_interes = $newDeudaRefinanciadaInteres;
                }

                // Guardar todos los cambios calculados para total, deuda_refinanciada y deuda_refinanciada_interes
                if ($refinanciamiento->isDirty('total') || $refinanciamiento->isDirty('deuda_refinanciada') || $refinanciamiento->isDirty('deuda_refinanciada_interes')) {
                    $refinanciamiento->saveQuietly();
                }

                $nuevoInteres    = $nuevoTotal - $nuevoValor;
                $diferenciaInteres = $nuevoInteres - $originalInteres;

                if ($diferenciaInteres !== 0) {
                    $this->actualizarMontoGeneral($prestamo, $diferenciaInteres, $userIdToAffect); // Usar el ID de usuario determinado

                    // Registrar historial del ajuste de monto_general
                    HistorialMovimiento::create([
                        'user_id'       => $userIdToAffect, // Usar el ID del usuario al que se le restó
                        'tipo'          => 'ajuste_interes_refinanciacion',
                        'descripcion'   => "Ajuste de interés por edición de refinanciamiento ID {$refinanciamiento->id}",
                        'monto'         => $diferenciaInteres,
                        'fecha'         => now(),
                        'es_edicion'    => true,
                        'referencia_id' => $refinanciamiento->id,
                        'tabla_origen'  => 'refinanciamientos',
                        'cambio_desde'  => json_encode(['interes_anterior' => $originalInteres]),
                        'cambio_hacia'  => json_encode(['interes_nuevo'   => $nuevoInteres]),
                    ]);
                }
            }

            // c) Ajuste si cambia la COMISIÓN
            if ($refinanciamiento->isDirty('comicion')) {
                $originalCom    = (float) $refinanciamiento->getOriginal('comicion');
                $nuevoCom       = (float) $refinanciamiento->comicion;
                $diferenciaCom  = $nuevoCom - $originalCom;

                if ($diferenciaCom !== 0) {
                    // Sumamos (o restamos) esa diferencia en la comisión del préstamo padre
                    $prestamo->increment('comicion', $diferenciaCom);

                    // Registrar historial del ajuste de comisión
                    HistorialMovimiento::create([
                        'user_id'       => $userIdToAffect, // Usar el ID del usuario al que se le restó la comisión
                        'tipo'          => 'ajuste_comision_refinanciacion',
                        'descripcion'   => "Ajuste de comisión por edición de refinanciamiento ID {$refinanciamiento->id}",
                        'monto'         => $diferenciaCom,
                        'fecha'         => now(),
                        'es_edicion'    => true,
                        'referencia_id' => $refinanciamiento->id,
                        'tabla_origen'  => 'refinanciamientos',
                        'cambio_desde'  => json_encode(['comision_anterior' => $originalCom]),
                        'cambio_hacia'  => json_encode(['comision_nueva'   => $nuevoCom]),
                    ]);
                }
            }
        }
    }

    /**
     * Handle the Refinanciamiento "deleted" event.
     * Si se elimina una refinanciación AUTORIZADA, hacemos rollback:
     * • Revertir en monto_general el interés.
     * • Reingresar a caja (dinero_base) el valor.
     * • Restar la comisión del préstamo padre.
     */
    public function deleted(Refinanciamiento $refinanciamiento): void
    {
        Log::info('RefinanciamientoObserver@deleted: Refinanciamiento ' . $refinanciamiento->id . ' eliminado.');

        $prestamo = $refinanciamiento->prestamo;
        if (! $prestamo) {
            return;
        }

        // Determinar el ID de usuario a usar para las reversiones
        $userIdToAffect = $prestamo->agente_asignado ?? $prestamo->registrado_id;

        if (is_null($userIdToAffect)) {
            Log::error("RefinanciamientoObserver@deleted: No se pudo determinar el ID de usuario para revertir el dinero base o monto general para el préstamo ID {$prestamo->id}.");
            return;
        }

        $originalEstado = $refinanciamiento->getOriginal('estado');
        if ($originalEstado === 'autorizado') {
            $originalTotal          = (float) $refinanciamiento->getOriginal('total');
            $originalValor          = (float) $refinanciamiento->getOriginal('valor');
            $originalInteres        = $originalTotal - $originalValor;

            // 1) Revertir monto_general (el interés)
            $this->actualizarMontoGeneral($prestamo, -$originalInteres, $userIdToAffect);

            // 2) Revertir dinero_base (el valor)
            if ($originalValor > 0) {
                $usuario = User::find($userIdToAffect); // Usar el ID de usuario determinado

                if ($usuario && $usuario->dineroBase) {
                    $dineroBase = $usuario->dineroBase;
                    $dineroBase->monto += $originalValor;
                    $dineroBase->save();

                    Log::info("RefinanciamientoObserver@deleted: Dinero base incrementado en {$originalValor} para usuario {$usuario->id}. Nuevo monto: {$dineroBase->monto}");

                    HistorialMovimiento::create([
                        'user_id'       => $usuario->id, // Usar el ID del usuario al que se le reingresó
                        'tipo'          => 'reversion_dinero_refinanciacion',
                        'descripcion'   => "Reversión por eliminación de refinanciamiento ID {$refinanciamiento->id}",
                        'monto'         => $originalValor,
                        'fecha'         => now(),
                        'es_edicion'    => false,
                        'referencia_id' => $refinanciamiento->id,
                        'tabla_origen'  => 'refinanciamientos',
                        'cambio_desde'  => null,
                        'cambio_hacia'  => null,
                    ]);
                }
            }

            // 3) Revertir comisión en el préstamo
            $originalCom = (float) $refinanciamiento->getOriginal('comicion');
            if ($originalCom > 0) {
                $prestamo->decrement('comicion', $originalCom);

                HistorialMovimiento::create([
                    'user_id'       => $userIdToAffect, // Usar el ID del usuario al que se le revirtió la comisión
                    'tipo'          => 'reversion_comision_refinanciacion',
                    'descripcion'   => "Reversión de comisión por eliminación de refinanciamiento ID {$refinanciamiento->id}",
                    'monto'         => -$originalCom,
                    'fecha'         => now(),
                    'es_edicion'    => false,
                    'referencia_id' => $refinanciamiento->id,
                    'tabla_origen'  => 'refinanciamientos',
                    'cambio_desde'  => null,
                    'cambio_hacia'  => null,
                ]);
            }
        }
    }

    /**
     * Handle the Refinanciamiento "restored" event.
     * Si se restaura una refinanciación autorizada, volvemos a aplicar:
     * • Restar valor en dinero_base.
     * • Sumar interés a monto_general.
     * • Sumar comisión al préstamo padre.
     * • Recalcular y reasignar la deuda_refinanciada.
     */
    public function restored(Refinanciamiento $refinanciamiento): void
    {
        Log::info('RefinanciamientoObserver@restored: Refinanciamiento ' . $refinanciamiento->id . ' restaurado.');

        $prestamo = $refinanciamiento->prestamo;
        if (! $prestamo) {
            return;
        }

        // Determinar el ID de usuario a usar para los ajustes de dinero_base y monto_general
        $userIdToAffect = $prestamo->agente_asignado ?? $prestamo->registrado_id;

        if (is_null($userIdToAffect)) {
            Log::error("RefinanciamientoObserver@restored: No se pudo determinar el ID de usuario para afectar el dinero base o monto general para el préstamo ID {$prestamo->id}.");
            return;
        }

        $estado = $refinanciamiento->estado;
        if ($estado === 'autorizado') {
            // Se debe volver a obtener la deuda actual del préstamo para los cálculos
            // al momento de la restauración, ya que la deuda_actual del préstamo
            // pudo haber cambiado significativamente desde que se eliminó el refinanciamiento.
            $deudaActualPrestamo = (float) $prestamo->deuda_actual;

            // a) Restar valor de la caja
            if ((float) $refinanciamiento->valor > 0) {
                $this->ajustarDineroBasePorRefinanciamiento($prestamo, $refinanciamiento, $userIdToAffect);
            }

            // b) Sumar interés a monto_general
            $interesRefinanciamiento = (float) $refinanciamiento->total - (float) $refinanciamiento->valor;
            if ($interesRefinanciamiento > 0) {
                $this->actualizarMontoGeneral($prestamo, $interesRefinanciamiento, $userIdToAffect);
            }

            // c) Sumar comisión al préstamo
            $comRef = (float) $refinanciamiento->comicion;
            if ($comRef > 0) {
                $prestamo->increment('comicion', $comRef);
            }

            // Recalcular deuda_refinanciada al restaurar
            $refinanciamiento->deuda_refinanciada = (int) round($deudaActualPrestamo + $refinanciamiento->valor);

            // Recalcular deuda_refinanciada_interes al restaurar
            $refinanciamiento->deuda_refinanciada_interes = (int) round($deudaActualPrestamo + $refinanciamiento->total);

            // Solo guarda los campos que se hayan podido recalcular o ajustar durante la restauración.
            $refinanciamiento->saveQuietly();
        }
    }

    /**
     * Handle the Refinanciamiento "force deleted" event.
     */
    public function forceDeleted(Refinanciamiento $refinanciamiento): void
    {
        Log::info('RefinanciamientoObserver@forceDeleted: Refinanciamiento ' . $refinanciamiento->id . ' eliminado forzadamente.');
        $this->deleted($refinanciamiento);
    }

    /**
     * Ajusta el dinero base del usuario y registra el movimiento de salida.
     * @param Prestamo $prestamo
     * @param Refinanciamiento $refinanciamiento
     * @param int|null $userIdToAffect El ID del usuario al que se debe afectar el dinero base.
     */
    protected function ajustarDineroBasePorRefinanciamiento(Prestamo $prestamo, Refinanciamiento $refinanciamiento, ?int $userIdToAffect): void
    {
        $montoSalida = (float) $refinanciamiento->valor;
        if ($montoSalida <= 0 || is_null($userIdToAffect)) {
            return;
        }

        DB::transaction(function () use ($prestamo, $refinanciamiento, $montoSalida, $userIdToAffect) {
            $usuario = User::find($userIdToAffect);

            if (! $usuario || ! $usuario->dineroBase) {
                Log::error('RefinanciamientoObserver@ajustarDineroBasePorRefinanciamiento: No se pudo encontrar usuario o dineroBase para el userId ' . $userIdToAffect);
                return;
            }

            $dineroBase = $usuario->dineroBase;
            $dineroBase->monto -= $montoSalida;
            $dineroBase->save();

            Log::info("RefinanciamientoObserver@ajustarDineroBasePorRefinanciamiento: dineroBase decrementado en {$montoSalida} para usuario {$usuario->id}. Nuevo monto: {$dineroBase->monto}");

            HistorialMovimiento::create([
                'user_id'       => $usuario->id,
                'tipo'          => 'salida_refinanciacion',
                'descripcion'   => "Salida por refinanciación. Préstamo ID: {$prestamo->id}, Cliente: {$prestamo->cliente->nombre}, Ref. ID: {$refinanciamiento->id}",
                'monto'         => -$montoSalida,
                'fecha'         => now(),
                'es_edicion'    => false,
                'referencia_id' => $refinanciamiento->id,
                'tabla_origen'  => 'refinanciamientos',
                'cambio_desde'  => null,
                'cambio_hacia'  => null,
            ]);

            Log::info("RefinanciamientoObserver@ajustarDineroBasePorRefinanciamiento: HistorialMovimiento de refinanciación creado.");
        });
    }

    /**
     * Actualiza el monto_general en DineroBase sin disparar observer de monto.
     * @param Prestamo $prestamo
     * @param float    $montoImpacto
     * @param int|null $userIdToAffect El ID del usuario al que se debe afectar el monto general.
     */
    protected function actualizarMontoGeneral(Prestamo $prestamo, float $montoImpacto, ?int $userIdToAffect): void
    {
        if ($montoImpacto == 0 || is_null($userIdToAffect)) {
            return;
        }

        $usuario = User::find($userIdToAffect);

        if ($usuario) {
            // Asegurarse de que el registro DineroBase exista para el usuario
            $dineroBase = DineroBase::firstOrCreate(
                ['user_id' => $usuario->id],
                ['monto' => 0, 'monto_general' => 0]
            );

            // Utilizamos `increment` directamente en el modelo DineroBase para actualizar `monto_general`
            // Esto es más eficiente que cargar el modelo y luego guardarlo.
            DineroBase::where('user_id', $usuario->id)
                ->increment('monto_general', $montoImpacto);

            Log::info("RefinanciamientoObserver@actualizarMontoGeneral: monto_general ajustado en {$montoImpacto} para usuario {$usuario->id}.");
        } else {
            Log::warning("RefinanciamientoObserver@actualizarMontoGeneral: No se pudo encontrar usuario para el userId {$userIdToAffect}.");
        }
    }
}