<?php

namespace App\Traits;

use App\Models\DineroBase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait ManagesUserBalance
{
    /**
     * Debita una cantidad del balance de un usuario.
     * La lógica se simplifica para afectar únicamente a 'Dinero en mano' (columna 'monto'),
     * permitiendo que este se vuelva negativo. La conciliación se hará al guardar la liquidación.
     *
     * @param int $userId El ID del usuario.
     * @param float $amountToDebit La cantidad positiva a restar.
     */
    protected function debitFromUserBalance(int $userId, float $amountToDebit): void
    {
        if ($amountToDebit <= 0) {
            return;
        }

        // Simplemente resta del 'dinero en mano' (monto).
        // Si no existe el registro, firstOrCreate lo creará con 0 y luego decrementará.
        DineroBase::firstOrCreate(['user_id' => $userId])
                  ->decrement('monto', $amountToDebit);
    }

    /**
     * Acredita una cantidad al balance de un usuario. Por defecto, va a 'Dinero en mano' (monto).
     *
     * @param int $userId El ID del usuario.
     * @param float $amountToCredit La cantidad positiva a sumar.
     */
    protected function creditToUserBalance(int $userId, float $amountToCredit): void
    {
        if ($amountToCredit <= 0) {
            return;
        }

        DineroBase::firstOrCreate(['user_id' => $userId])
                  ->increment('monto', $amountToCredit);
    }
}