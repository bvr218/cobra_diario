<?php

namespace App\Observers;

use App\Models\DineroBase;
use App\Models\Gasto;
use App\Models\User;
use App\Models\HistorialMovimiento;
use Illuminate\Support\Facades\DB;
use App\Traits\ManagesUserBalance;

class GastoObserver
{

    use ManagesUserBalance;

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

        DB::transaction(function () use ($gasto, $monto, $descripcion, $esEdicion, $esEliminacion) {
            $userId = $gasto->user_id;
            $historialMonto = 0;
            $tipoMovimiento = 'creación';

            if ($esEliminacion) {
                // Se elimina un gasto, el dinero se devuelve (crédito)
                $this->creditToUserBalance($userId, $monto);
                DineroBase::where('user_id', $userId)->increment('monto_general', $monto);
                $historialMonto = $monto;
                $tipoMovimiento = 'eliminación';
            } else {
                // Se crea o actualiza un gasto.
                $tipoMovimiento = $esEdicion ? 'edición' : 'creación';
                
                if ($monto > 0) { // Es un débito (gasto nuevo, o aumento de valor)
                    $this->debitFromUserBalance($userId, $monto);
                    DineroBase::where('user_id', $userId)->decrement('monto_general', $monto);
                } else { // Es un crédito (gasto desautorizado, o disminución de valor)
                    $this->creditToUserBalance($userId, abs($monto));
                    DineroBase::where('user_id', $userId)->increment('monto_general', abs($monto));
                }
                $historialMonto = -$monto; // En el historial, una salida es negativa
            }

            HistorialMovimiento::create([
                'user_id'       => $userId,
                'tipo'          => $tipoMovimiento,
                'descripcion'   => $descripcion,
                'monto'         => $historialMonto,
                'fecha'         => now(),
                'es_edicion'    => $esEdicion,
                'referencia_id' => $gasto->id,
                'tabla_origen'  => 'gastos',
            ]);
        });
    }

    public function restored(Gasto $gasto): void {}
    public function forceDeleted(Gasto $gasto): void {}
}