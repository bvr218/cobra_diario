<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Refinanciamiento extends Model
{
    protected $fillable = [
        "valor",
        "prestamo_id",
        "interes",
        "numero_cuotas",
        "total",
        "estado",
        "authorized_at",
        "comicion",
        "comicion_borrada",
        "deuda_refinanciada",
        "deuda_refinanciada_interes",
        "deuda_anterior",
    ];

    protected $casts = [
        'authorized_at' => 'datetime',
    ];

    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_id');
    }

    protected static function booted(): void
    {
        static::creating(function (Refinanciamiento $refinanciamiento) {
            if ($refinanciamiento->prestamo && $refinanciamiento->prestamo->estado === 'desactivado') {
                 throw ValidationException::withMessages([
                    'prestamo_id' => 'No se puede refinanciar un préstamo que está desactivado.'
                ]);
            }
        });
    }
}