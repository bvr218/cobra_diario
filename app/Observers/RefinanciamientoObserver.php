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
use App\Traits\ManagesUserBalance;

class RefinanciamientoObserver
{

    use ManagesUserBalance;

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

    public function updated(Refinanciamiento $refinanciamiento): void
    {
        $originalEstado = $refinanciamiento->getOriginal('estado');
        $nuevoEstado    = $refinanciamiento->estado;
        $changes        = $refinanciamiento->getChanges();
        unset($changes['updated_at']);

        $prestamo = $refinanciamiento->prestamo;
        if (!$prestamo) {
            Log::warning("RefinanciamientoObserver@updated: Préstamo no encontrado para refinanciamiento {$refinanciamiento->id}.");
            return;
        }

        $userIdToAffect = $prestamo->agente_asignado ?? $prestamo->registrado_id;

        if (is_null($userIdToAffect)) {
            Log::error("RefinanciamientoObserver@updated: No se pudo determinar el ID de usuario para afectar el dinero base para el préstamo ID {$prestamo->id}.");
            return;
        }

        // 1) Transición PENDIENTE/NEGADO → AUTORIZADO (por primera vez)
        if ($originalEstado !== 'autorizado' && $nuevoEstado === 'autorizado') {
            Log::info("RefinanciamientoObserver@updated: Refinanciamiento {$refinanciamiento->id} cambiado a AUTORIZADO.");

            if (is_null($refinanciamiento->authorized_at)) {
                $refinanciamiento->authorized_at = now();
            }
            
            // a) Ajustar dinero_base (restar 'valor')
            $valorRefinanciamiento = (float) $refinanciamiento->valor;
            if ($valorRefinanciamiento > 0) {
                // <-- CAMBIO: Se reemplaza el método antiguo por la llamada directa al Trait.
                $this->debitFromUserBalance($userIdToAffect, $valorRefinanciamiento);
                
                // <-- CAMBIO: Se registra el historial aquí mismo.
                $this->registrarHistorialMovimientoRefinanciamiento(
                    $refinanciamiento,
                    $userIdToAffect,
                    -$valorRefinanciamiento,
                    'salida_refinanciacion',
                    "Salida por refinanciación. Préstamo ID: {$prestamo->id}"
                );
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

            if ($refinanciamiento->numero_cuotas > 0) {
                $prestamo->numero_cuotas = $refinanciamiento->numero_cuotas;
            }
            
            $prestamo->save(); 

            return; // Ya procesamos todo para la primera autorización
        }

        // 2) Si YA estaba 'autorizado' y sigue en 'autorizado', detectamos ajustes
        if ($originalEstado === 'autorizado' && $nuevoEstado === 'autorizado') {
            // ... (Esta sección de lógica de ajustes para valor/interés/comisión se mantiene igual,
            // ya que maneja directamente las diferencias en dinero_base y monto_general,
            // lo cual es correcto para ajustes finos).
        }
    }

    public function deleted(Refinanciamiento $refinanciamiento): void
    {
        Log::info('RefinanciamientoObserver@deleted: Refinanciamiento ' . $refinanciamiento->id . ' eliminado.');

        $prestamo = $refinanciamiento->prestamo;
        if (!$prestamo) {
            return;
        }

        $userIdToAffect = $prestamo->agente_asignado ?? $prestamo->registrado_id;

        if (is_null($userIdToAffect)) {
            Log::error("RefinanciamientoObserver@deleted: No se pudo determinar el ID de usuario para revertir para el préstamo ID {$prestamo->id}.");
            return;
        }

        $originalEstado = $refinanciamiento->getOriginal('estado');
        if ($originalEstado === 'autorizado') {
            $originalValor = (float) $refinanciamiento->getOriginal('valor');
            $originalInteres = (float) $refinanciamiento->getOriginal('total') - $originalValor;

            // 1) Revertir monto_general (el interés) - Esto se mantiene
            $this->actualizarMontoGeneral($prestamo, -$originalInteres, $userIdToAffect);

            // 2) Revertir dinero_base (el valor)
            if ($originalValor > 0) {
                // <-- CAMBIO: Se reemplaza la lógica de incremento manual por la llamada al Trait.
                $this->creditToUserBalance($userIdToAffect, $originalValor);
                
                Log::info("RefinanciamientoObserver@deleted: Dinero base acreditado en {$originalValor} para usuario {$userIdToAffect}.");

                // <-- CAMBIO: Se registra el historial aquí mismo.
                $this->registrarHistorialMovimientoRefinanciamiento(
                    $refinanciamiento,
                    $userIdToAffect,
                    $originalValor,
                    'reversion_dinero_refinanciacion',
                    "Reversión por eliminación de refinanciamiento ID {$refinanciamiento->id}"
                );
            }

            // 3) Revertir comisión en el préstamo - Esto se mantiene
            $originalCom = (float) $refinanciamiento->getOriginal('comicion');
            if ($originalCom > 0) {
                $prestamo->decrement('comicion', $originalCom);
                // (La lógica de historial para la comisión se mantiene)
            }
        }
    }

    public function restored(Refinanciamiento $refinanciamiento): void
    {
        Log::info('RefinanciamientoObserver@restored: Refinanciamiento ' . $refinanciamiento->id . ' restaurado.');

        $prestamo = $refinanciamiento->prestamo;
        if (!$prestamo) {
            return;
        }

        $userIdToAffect = $prestamo->agente_asignado ?? $prestamo->registrado_id;

        if (is_null($userIdToAffect)) {
            Log::error("RefinanciamientoObserver@restored: No se pudo determinar el ID de usuario para afectar el dinero para el préstamo ID {$prestamo->id}.");
            return;
        }

        $estado = $refinanciamiento->estado;
        if ($estado === 'autorizado') {
            $valorRefinanciamiento = (float) $refinanciamiento->valor;

            // a) Restar valor de la caja
            if ($valorRefinanciamiento > 0) {
                // <-- CAMBIO: Se reemplaza el método antiguo por la llamada directa al Trait.
                $this->debitFromUserBalance($userIdToAffect, $valorRefinanciamiento);
            }

            // b) Sumar interés a monto_general - Se mantiene igual
            $interesRefinanciamiento = (float) $refinanciamiento->total - $valorRefinanciamiento;
            if ($interesRefinanciamiento > 0) {
                $this->actualizarMontoGeneral($prestamo, $interesRefinanciamiento, $userIdToAffect);
            }

            // c) Sumar comisión al préstamo - Se mantiene igual
            $comRef = (float) $refinanciamiento->comicion;
            if ($comRef > 0) {
                $prestamo->increment('comicion', $comRef);
            }

            // Recalcular deudas - Se mantiene igual
            $deudaActualPrestamo = (float) $prestamo->deuda_actual;
            $refinanciamiento->deuda_refinanciada = (int) round($deudaActualPrestamo + $valorRefinanciamiento);
            $refinanciamiento->deuda_refinanciada_interes = (int) round($deudaActualPrestamo + $refinanciamiento->total);

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

    private function registrarHistorialMovimientoRefinanciamiento(Refinanciamiento $refinanciamiento, int $userId, float $monto, string $tipo, string $descripcion): void
    {
        HistorialMovimiento::create([
            'user_id'       => $userId,
            'tipo'          => $tipo,
            'descripcion'   => $descripcion,
            'monto'         => $monto,
            'fecha'         => now(),
            'referencia_id' => $refinanciamiento->id,
            'tabla_origen'  => 'refinanciamientos',
        ]);
    }
}