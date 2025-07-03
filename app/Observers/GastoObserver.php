<?php

namespace App\Observers;

use App\Models\DineroBase;
use App\Models\Gasto;
use App\Models\User;
use App\Models\HistorialMovimiento;
use Illuminate\Support\Facades\DB;

class GastoObserver
{
    public function created(Gasto $gasto): void
    {
        if ($gasto->autorizado) {
            $this->ajustarMonto($gasto, $gasto->valor, 'Gasto creado y autorizado', false);
        }
    }

    public function updated(Gasto $gasto): void
    {
        $originalAutorizado = (bool) $gasto->getOriginal('autorizado');
        $nuevoAutorizado = (bool) $gasto->autorizado;

        // 1) Cambio en la autorización (false => true)
        if (! $originalAutorizado && $nuevoAutorizado) {
            $this->ajustarMonto($gasto, $gasto->valor, 'Autorizado', false);
        }

        // 2) Cambio en el valor cuando está autorizado, PERO solo si ya estaba autorizado antes (evita doble registro)
        if ($nuevoAutorizado && $gasto->isDirty('valor') && $originalAutorizado) {
            $originalValor = (float) $gasto->getOriginal('valor');
            $nuevoValor = (float) $gasto->valor;
            $diferencia = $nuevoValor - $originalValor;

            if ($diferencia !== 0.0) {
                $this->ajustarMonto($gasto, $diferencia, 'Ajuste', true);
            }
        }

        // 3) Cambio en autorización de true => false (posible reversión)
        if ($originalAutorizado && ! $nuevoAutorizado) {
            $this->ajustarMonto($gasto, -$gasto->valor, 'Desautorizado', false);
        }
    }


    public function deleted(Gasto $gasto): void
    {
        if ($gasto->autorizado) {
            $this->ajustarMonto($gasto, $gasto->valor, 'Gasto eliminado', false, true);
        }
    }

    protected function ajustarMonto(Gasto $gasto, float $monto, string $descripcion, bool $esEdicion = false, bool $esEliminacion = false): void
    {
        if ($monto === 0.0) return;

        $tipoMovimiento = 'creación';
        if ($esEdicion) {
            $tipoMovimiento = 'edición';
        } elseif ($esEliminacion) {
            $tipoMovimiento = 'eliminación';
            $monto = -$monto; // Invertir el monto para la eliminación (reembolso)
        }

        DB::transaction(function () use ($gasto, $monto, $descripcion, $esEdicion, $tipoMovimiento) {
            // Usamos firstOrCreate para evitar race conditions al crear el registro de dinero base.
            // Esto soluciona el error de "Duplicate entry" que puede ocurrir bajo alta concurrencia.
            $dineroBase = DineroBase::firstOrCreate(
                ['user_id' => $gasto->user_id],
                ['monto' => 0, 'monto_general' => 0] // Asegúrate de inicializar monto_general también si es nuevo
            );

            // Ajuste para la columna 'monto'
            if ($monto > 0) {
                $dineroBase->decrement('monto', $monto);
            } else {
                $dineroBase->increment('monto', abs($monto));
            }

            // --- NUEVA LÓGICA PARA monto_general ---
            if ($monto > 0) {
                $dineroBase->decrement('monto_general', $monto);
            } else {
                $dineroBase->increment('monto_general', abs($monto));
            }
            // --- FIN NUEVA LÓGICA ---

            HistorialMovimiento::create([
                'user_id'       => $dineroBase->user_id,
                'tipo'          => $tipoMovimiento, // Variable ahora disponible en el closure
                'descripcion'   => $descripcion,
                'monto'         => $monto, // Este monto es el del movimiento, no el saldo final
                'fecha'         => now(),
                'es_edicion'    => $esEdicion,
                'referencia_id' => $gasto->id,
                'tabla_origen'  => 'gastos',
                'cambio_desde'  => null,
                'cambio_hacia'  => null,
            ]);
        });
    }

    public function restored(Gasto $gasto): void {}
    public function forceDeleted(Gasto $gasto): void {}
}