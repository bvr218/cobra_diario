<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

 
class Refinanciamiento extends Model
{
    protected $fillable = [
        "valor",
        "prestamo_id",
        "interes",
        "total",
        "estado",
        "comicion",
        "comicion_borrada",
        "deuda_refinanciada",
        "deuda_refinanciada_interes",
        "deuda_anterior",
    ];
    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_id');
    }

    protected static function booted(): void
    {
        static::saved(function (Refinanciamiento $refinanciamiento) {
            // Cuando una refinanciación se guarda (especialmente si su estado cambia),
            // es importante guardar el préstamo asociado para que recalcule su deuda_actual
            // y deuda_inicial, ya que estos dependen de las refinanciaciones AUTORIZADAS.
            if ($refinanciamiento->prestamo) {
                $refinanciamiento->prestamo->save();
            }
        });
    }
}
