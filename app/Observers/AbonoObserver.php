<?php

namespace App\Observers;

use App\Models\Abono;
use App\Models\DineroBase;
use App\Models\User;
use App\Models\HistorialMovimiento;
use Illuminate\Support\Facades\DB;
use App\Notifications\NuevaNotificacion; // Add this line

class AbonoObserver
{
    public function created(Abono $abono): void
    {
        // Ajuste de dinero base al crear el abono
        $this->ajustarDineroBase($abono->registrado_por_id, $abono->monto_abono);
        // Historial de creación
        $this->registrarMovimiento($abono, 'creado', $abono->monto_abono);

        if($abono->deuda_actual < ($abono->prestamo->valor_total_prestamo*0.7)){
            $admins = User::whereHas("roles",fn($q)=>$q->where("roles.name","admin"))->get();
            foreach ($admins as $admin) {
                $admin->notify(new NuevaNotificacion(
                    '¡Hay un prestamo por terminar!',
                    "Recuerda refinanciar el cliente {$abono->prestamo->cliente->name} antes de que el prestamo termine.",
                    '/admin/prestamos/'.$abono->prestamo->id."/edit" // Make sure to use $abono->prestamo->id here
                ));
            }
        }
    }

    public function updated(Abono $abono): void
    {
        if($abono->deuda_actual < ($abono->prestamo->valor_total_prestamo*0.7)){
            $admins = User::whereHas("roles",fn($q)=>$q->where("roles.name","admin"))->get();
            foreach ($admins as $admin) {
                $admin->notify(new NuevaNotificacion(
                    '¡Hay un prestamo por terminar!',
                    "Recuerda refinanciar el cliente {$abono->prestamo->cliente->name} antes de que el prestamo termine.",
                    '/admin/prestamos/'.$abono->prestamo->id."/edit" // Make sure to use $abono->prestamo->id here
                ));
            }
        }

        // Detectamos todos los cambios en el modelo
        $changes = $abono->getChanges();
        unset($changes['updated_at']);

        if (empty($changes)) {
            return;
        }

        // Si cambió el monto_abono
        if (isset($changes['monto_abono'])) {
            $original   = (float) $abono->getOriginal('monto_abono');
            $nuevo      = (float) $abono->monto_abono;
            $diferencia = $nuevo - $original;

            if ($diferencia !== 0) {
                $this->ajustarDineroBase($abono->registrado_por_id, $diferencia);
                // Historial de edición con monto
                $this->registrarMovimiento($abono, 'actualizado', $diferencia, $original, $nuevo);
                return;
            }
        }

        // Si otros campos cambian (sin ajuste de dinero), solo registramos edición
        $this->registrarMovimiento($abono, 'actualizado', 0);
    }

    public function deleted(Abono $abono): void
    {
        // Revertimos el abono al eliminar
        $this->revertirAbono($abono->registrado_por_id, $abono->monto_abono);
        // Historial de eliminación con monto negativo
        $this->registrarMovimiento($abono, 'eliminado', -$abono->monto_abono);
    }

    protected function ajustarDineroBase(int $userId, float $monto): void
    {
        if ($monto === 0) {
            return;
        }

        DB::transaction(function () use ($userId, $monto) {
            // Usamos firstOrCreate para buscar o crear el registro de dinero base de forma atómica,
            // evitando así condiciones de carrera (race conditions) que causan el error de clave duplicada.
            $dineroBase = DineroBase::firstOrCreate(
                ['user_id' => $userId],
                ['monto' => 0]
            );

            if ($monto > 0) {
                $dineroBase->increment('monto', $monto);
            } else {
                $dineroBase->decrement('monto', abs($monto));
            }
        });
    }

    protected function revertirAbono(int $userId, float $monto): void
    {
        if ($monto === 0) {
            return;
        }

        DB::transaction(function () use ($userId, $monto) {
            $faltante = $monto;
            $user     = User::find($userId);

            if ($user && $user->dineroBase) {
                $saldo = max(0, $user->dineroBase->monto);
                if ($saldo >= $faltante) {
                    $user->dineroBase->decrement('monto', $faltante);
                    return;
                }
                $user->dineroBase->update(['monto' => 0]);
                $faltante -= $saldo;
            }

            if ($user && $user->oficina_id) {
                $ofi = User::find($user->oficina_id);
                if ($ofi && $ofi->hasRole('oficina') && $ofi->dineroBase) {
                    $saldoOfi = max(0, $ofi->dineroBase->monto);
                    if ($saldoOfi >= $faltante) {
                        $ofi->dineroBase->decrement('monto', $faltante);
                        return;
                    }
                    $ofi->dineroBase->update(['monto' => 0]);
                    $faltante -= $saldoOfi;
                }
            }

            if ($faltante > 0) {
                $admin = User::role('admin')->whereHas('dineroBase')->first();
                if ($admin && $admin->dineroBase) {
                    $saldoAdmin = max(0, $admin->dineroBase->monto);
                    if ($saldoAdmin >= $faltante) {
                        $admin->dineroBase->decrement('monto', $faltante);
                        return;
                    }
                    $admin->dineroBase->update(['monto' => 0]);
                    $faltante -= $saldoAdmin;
                    $admin->dineroBase->decrement('monto', $faltante);
                }
            }
        });
    }

    /**
     * Registra en HistorialMovimiento un único registro.
     * Para 'edición', guarda toda la información original y solo los cambios en 'después'.
     *
     * @param Abono $abono
     * @param string $accion    'creado'|'actualizado'|'eliminado'
     * @param float  $monto     Monto aplicado (positivo o negativo)
     * @param float|null $antes    Valor anterior para actualizaciones
     * @param float|null $despues Valor nuevo para actualizaciones
     */
    protected function registrarMovimiento(
        Abono $abono,
        string $accion,
        float $monto,
        float $antes = null,
        float $despues = null
    ): void {
        // Mapear acción a tipo
        $tipo = match ($accion) {
            'creado'      => 'creación',
            'actualizado' => 'edición',
            'eliminado'   => 'eliminación',
        };

        $data = [
            'user_id'       => $abono->registrado_por_id,
            'tipo'          => $tipo,
            'descripcion'   => 'Abono Numero ' . $abono->numero_cuota,
            'monto'         => $monto,
            'referencia_id' => $abono->id,
            'tabla_origen'  => 'abonos',
            'es_edicion'    => $tipo === 'edición',
            'fecha'         => now(),
        ];

        if ($tipo === 'edición') {
            // Información completa original
            $antesAll   = $abono->getOriginal();
            unset($antesAll['updated_at']);
            // Solo el campo cambiado en 'después'
            $despuesOnly = ['monto_abono' => $despues];

            $data['cambio_desde'] = json_encode($antesAll);
            $data['cambio_hacia'] = json_encode($despuesOnly);
        }

        HistorialMovimiento::create($data);
    }

    public function restored(Abono $abono): void {}
    public function forceDeleted(Abono $abono): void {}
}