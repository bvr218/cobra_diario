<?php

namespace App\Observers;

use App\Models\Abono;
use App\Models\DineroBase;
use App\Models\User;
use App\Models\HistorialMovimiento;
use Illuminate\Support\Facades\DB;
use App\Notifications\NuevaNotificacion;
use Illuminate\Support\Facades\Log;

class AbonoObserver
{
    /**
     * Handle the Abono "created" event.
     * Al crear un abono, solo ajustamos dinero_base.
     */
    public function created(Abono $abono): void
    {
        // 1. Ajuste de dinero_base (el monto abono se suma al dinero base del cobrador)
        $this->ajustarDineroBase($abono->registrado_por_id, $abono->monto_abono);

        // 2. Historial de creación para dinero_base
        $this->registrarMovimiento(
            $abono,
            'creado',
            $abono->monto_abono, // Monto para dinero_base (positivo)
            'dinero_base'
        );
        // NOTA: Se ha eliminado el ajuste y el historial para monto_general aquí.

        // 3. Notificación de préstamo por terminar (lógica existente)
        if ($abono->prestamo && $abono->deuda_actual < ($abono->prestamo->valor_total_prestamo * 0.7)) {
            $admins = User::whereHas("roles", fn($q) => $q->where("roles.name", "admin"))->get();
            foreach ($admins as $admin) {
                // Verificar que el cliente y el nombre existan antes de acceder
                $clienteNombre = $abono->prestamo->cliente ? $abono->prestamo->cliente->nombre : 'desconocido';
                $admin->notify(new NuevaNotificacion(
                    '¡Hay un préstamo por terminar!',
                    "Recuerda refinanciar el cliente {$clienteNombre} antes de que el préstamo termine.",
                    '/admin/prestamos/' . $abono->prestamo->id . "/edit"
                ));
            }
        }
    }

    /**
     * Handle the Abono "updated" event.
     * Al actualizar un abono, ajustamos dinero_base si el monto cambia.
     */
    public function updated(Abono $abono): void
    {
        // Notificación de préstamo por terminar (lógica existente)
        if ($abono->prestamo && $abono->deuda_actual < ($abono->prestamo->valor_total_prestamo * 0.7)) {
            $admins = User::whereHas("roles", fn($q) => $q->where("roles.name", "admin"))->get();
            foreach ($admins as $admin) {
                $clienteNombre = $abono->prestamo->cliente ? $abono->prestamo->cliente->nombre : 'desconocido';
                $admin->notify(new NuevaNotificacion(
                    '¡Hay un préstamo por terminar!',
                    "Recuerda refinanciar el cliente {$clienteNombre} antes de que el préstamo termine.",
                    '/admin/prestamos/' . $abono->prestamo->id . "/edit"
                ));
            }
        }

        // Detectamos cambios específicos en monto_abono para ajustar dinero_base
        if ($abono->isDirty('monto_abono')) {
            $originalMonto = (float) $abono->getOriginal('monto_abono');
            $nuevoMonto = (float) $abono->monto_abono;
            $diferencia = $nuevoMonto - $originalMonto; // Positivo si aumenta, negativo si disminuye

            if ($diferencia !== 0) {
                // 1. Ajustar dinero_base: se suma la diferencia (si el nuevo monto es mayor, entra más dinero)
                $this->ajustarDineroBase($abono->registrado_por_id, $diferencia);
                // Historial de edición para dinero_base
                $this->registrarMovimiento(
                    $abono,
                    'actualizado',
                    $diferencia,
                    'dinero_base',
                    "Ajuste en dinero base por edición de abono. Antes: {$originalMonto}, Después: {$nuevoMonto}"
                );
                // NOTA: Se ha eliminado el ajuste y el historial para monto_general aquí.
            }
        } else {
            // Si otros campos cambian (sin ajuste de dinero), solo registramos edición general
            // Esto solo se ejecutará si NO cambió monto_abono, pero sí otros campos.
            $this->registrarMovimiento($abono, 'actualizado', 0, 'general');
        }
    }

    /**
     * Handle the Abono "deleted" event.
     * Al eliminar un abono, solo revertimos los cambios en dinero_base.
     */
    public function deleted(Abono $abono): void
    {
        // 1. Revertir dinero_base: se resta el monto del abono eliminado
        $this->revertirAbono($abono->registrado_por_id, $abono->monto_abono);
        // Historial de eliminación para dinero_base
        $this->registrarMovimiento(
            $abono,
            'eliminado',
            -$abono->monto_abono, // Monto negativo para dinero_base (sale de la caja)
            'dinero_base',
            "Reversión de dinero base por eliminación de abono."
        );
        // NOTA: Se ha eliminado el ajuste y el historial para monto_general aquí.
    }

    /**
     * Handle the Abono "restored" event.
     * Al restaurar un abono, volvemos a aplicar los cambios en dinero_base.
     */
    public function restored(Abono $abono): void
    {
        // 1. Ajuste de dinero_base: se suma el monto abono (vuelve a entrar a la caja)
        $this->ajustarDineroBase($abono->registrado_por_id, $abono->monto_abono);
        // Historial de restauración para dinero_base
        $this->registrarMovimiento(
            $abono,
            'restaurado',
            $abono->monto_abono,
            'dinero_base',
            "Restauración de dinero base por abono."
        );
        // NOTA: Se ha eliminado el ajuste y el historial para monto_general aquí.
    }

    /**
     * Handle the Abono "forceDeleted" event.
     * Actúa igual que "deleted".
     */
    public function forceDeleted(Abono $abono): void
    {
        // Simplemente llama al método deleted para aplicar la misma lógica de reversión (ahora solo para dinero_base)
        $this->deleted($abono);
    }

    /**
     * Ajusta el dinero base del usuario.
     * @param int $userId El ID del usuario cuyo dinero base será ajustado.
     * @param float $monto El monto a ajustar (positivo para sumar, negativo para restar).
     */
    protected function ajustarDineroBase(int $userId, float $monto): void
    {
        if ($monto === 0) {
            return;
        }

        DB::transaction(function () use ($userId, $monto) {
            $dineroBase = DineroBase::firstOrCreate(
                ['user_id' => $userId],
                ['monto' => 0, 'monto_general' => 0] // Aseguramos que monto_general también se inicialice si es un nuevo registro
            );

            if ($monto > 0) {
                $dineroBase->increment('monto', $monto);
            } else {
                $dineroBase->decrement('monto', abs($monto));
            }
        });
        Log::info("Dinero Base ajustado para User ID: {$userId} por monto: {$monto}.");
    }

    /**
     * El método actualizarMontoGeneral ya no es llamado por los eventos de Abono.
     * Lo dejo aquí por si otras partes de tu aplicación aún lo usan,
     * pero su función ya no está ligada directamente a la creación/edición/eliminación de Abonos.
     */
    protected function actualizarMontoGeneral(int $userId, float $montoImpacto, ?int $abonoId = null): void
    {
        if ($montoImpacto == 0) {
            return;
        }

        DB::transaction(function () use ($userId, $montoImpacto, $abonoId) {
            $dineroBase = DineroBase::firstOrCreate(
                ['user_id' => $userId],
                ['monto' => 0, 'monto_general' => 0]
            );

            $dineroBase->increment('monto_general', $montoImpacto);
        });
        Log::info("Monto General ajustado para User ID: {$userId} por impacto: {$montoImpacto}. Abono ID: {$abonoId}.");
    }

    /**
     * La lógica de revertirAbono original es para dinero_base, y tiene una lógica de cascada (usuario -> oficina -> admin).
     * Sigue siendo necesaria para la reversión del dinero_base al eliminar un abono.
     */
    protected function revertirAbono(int $userId, float $monto): void
    {
        if ($monto === 0) {
            return;
        }

        DB::transaction(function () use ($userId, $monto) {
            $faltante = $monto;
            $user     = User::find($userId);

            // Revertir del dinero base del usuario
            if ($user && $user->dineroBase) {
                $saldo = $user->dineroBase->monto;
                if ($saldo >= $faltante) {
                    $user->dineroBase->decrement('monto', $faltante);
                    return; // Terminamos si se cubrió todo
                }
                $user->dineroBase->update(['monto' => 0]);
                $faltante -= $saldo;
            }

            // Revertir del dinero base de la oficina (si aplica y aún queda faltante)
            if ($faltante > 0 && $user && $user->oficina_id) {
                $ofi = User::find($user->oficina_id);
                if ($ofi && $ofi->hasRole('oficina') && $ofi->dineroBase) {
                    $saldoOfi = $ofi->dineroBase->monto;
                    if ($saldoOfi >= $faltante) {
                        $ofi->dineroBase->decrement('monto', $faltante);
                        return;
                    }
                    $ofi->dineroBase->update(['monto' => 0]);
                    $faltante -= $saldoOfi;
                }
            }

            // Revertir del dinero base del administrador (si aplica y aún queda faltante)
            if ($faltante > 0) {
                $admin = User::role('admin')->whereHas('dineroBase')->first();
                if ($admin && $admin->dineroBase) {
                    $admin->dineroBase->decrement('monto', $faltante);
                } else {
                    Log::warning("AbonoObserver@revertirAbono: No se pudo encontrar un admin con dineroBase para revertir el faltante de {$faltante}.");
                }
            }
        });
    }

    /**
     * Registra en HistorialMovimiento un único registro.
     *
     * @param Abono $abono
     * @param string $accion       'creado'|'actualizado'|'eliminado'|'restaurado'
     * @param float $monto         Monto aplicado (positivo o negativo)
     * @param string $impacto_en   'dinero_base'|'monto_general'|'general'
     * @param string|null $descripcion_extra Descripción adicional para el historial.
     */
    protected function registrarMovimiento(
        Abono $abono,
        string $accion,
        float $monto,
        string $impacto_en,
        string $descripcion_extra = null
    ): void {
        // Si el impacto no es en dinero_base, y no es un registro general que cubra otros campos,
        // simplemente no registramos para evitar registros de monto_general.
        if ($impacto_en !== 'dinero_base' && $impacto_en !== 'general') {
            return;
        }

        $tipo = match ($accion) {
            'creado'      => 'creación',
            'actualizado' => 'edición',
            'eliminado'   => 'eliminación',
            'restaurado'  => 'restauración',
            default       => 'desconocido',
        };

        $baseDescripcion = "Abono ID: {$abono->id}. Cliente: " . ($abono->prestamo->cliente->nombre ?? 'desconocido') . ". Préstamo ID: {$abono->prestamo_id}. Cuota: {$abono->numero_cuota}.";
        $descripcion = match ($impacto_en) {
            'dinero_base' => "{$baseDescripcion} Impacto en Dinero Base.",
            'monto_general' => "{$baseDescripcion} Impacto en Monto General.", // Esta rama podría eliminarse si nunca se usa, pero la mantengo si 'monto_general' se pasa desde otro lugar no relacionado con abonos
            default => $baseDescripcion . ($descripcion_extra ? " " . $descripcion_extra : ""),
        };
        if ($descripcion_extra) {
            $descripcion .= " " . $descripcion_extra;
        }

        $data = [
            'user_id'       => $abono->registrado_por_id,
            'tipo'          => $tipo,
            'descripcion'   => $descripcion,
            'monto'         => $monto,
            'referencia_id' => $abono->id,
            'tabla_origen'  => 'abonos',
            'es_edicion'    => $tipo === 'edición',
            'fecha'         => now(),
        ];

        // Para ediciones, si es necesario, capturar los cambios específicos
        // Solo para dinero_base ahora
        if ($tipo === 'edición' && $impacto_en === 'dinero_base') {
            $original = (float) $abono->getOriginal('monto_abono');
            $nuevo = (float) $abono->monto_abono;
            $data['cambio_desde'] = json_encode(['monto_abono' => $original]);
            $data['cambio_hacia'] = json_encode(['monto_abono' => $nuevo]);
        } else {
            $data['cambio_desde'] = null;
            $data['cambio_hacia'] = null;
        }

        HistorialMovimiento::create($data);
    }
}