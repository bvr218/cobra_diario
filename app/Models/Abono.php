<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Abono extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'prestamo_id',
        'monto_abono',
        'monto_pagado',
        'fecha_abono',
        'fecha_pagado',
        'numero_cuota',
        'registrado_por_id',
    ];

    protected $casts = [
        'monto_abono' => 'integer',
        'monto_pagado' => 'integer',
    ];

    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(Prestamo::class);
    }
    
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }

    public static function boot()
    {

        parent::boot();

        static::creating(function ($abono){
            
            if ($abono->prestamo && $abono->prestamo->estado === 'desactivado') {
                throw ValidationException::withMessages([
                    'prestamo_id' => 'No se pueden registrar abonos a un préstamo que está desactivado.'
                ]);
            }

            $maxCuota = self::where('prestamo_id', $abono->prestamo_id)->max('numero_cuota');
            $abono->numero_cuota = $maxCuota ? $maxCuota + 1 : 1;

            $abono->registrado_por_id = $abono->registrado_por_id??auth()->user()->id;

            $abono->fecha_pagado = $abono->prestamo->next_payment;
            $abono->monto_pagado = $abono->prestamo->monto_por_cuota;
        });

        static::updated(function ($abono){
            $abono->registrado_por_id = $abono->registrado_por_id??auth()->user()->id;
            // No es necesario llamar a prestamo->save() aquí si ya lo hacemos en saved()
        });

        static::saved(function (Abono $abono) {
            if ($abono->prestamo) {
                // Dispara el guardado del préstamo asociado para que se
                // ejecuten sus propios eventos 'saving' (donde está la lógica de finalización).
                $abono->prestamo->save();
            }
        });

        static::deleted(function (Abono $abono) {
            if ($abono->prestamo) {
                // También es importante cuando se elimina un abono,
                // ya que la deuda del préstamo cambiará.
                $abono->prestamo->save();
            }
        });
    } 

    // public function getDeudaAnteriorAttribute()
    // {
    //     $prestamo = $this->prestamo;

    //     if (!$prestamo) {
    //         return 0;
    //     }

    //     $valor = $prestamo->valor_total_prestamo;
    //     $cuotas = $prestamo->numero_cuotas;
    //     $interes = $prestamo->interes / 100;

    //     $total = $valor + ($valor * $interes);

    //     $abonosAnteriores = $prestamo->abonos()
    //         ->where('id', '<', $this->id)
    //         ->sum('monto_abono');

    //     return max($total - $abonosAnteriores, 0);
    // }

    public function getDeudaAnteriorAttribute()
    {
        $prestamo = $this->prestamo;

        if (!$prestamo) {
            return 0;
        }

        // 1. Empezamos con el valor total original del préstamo, incluyendo su interés inicial.
        //    Esto es acceder al atributo de la BD directamente para no causar un bucle si getInteresAttribute cambia.
        $tasaOriginal = ($prestamo->getAttributes()['interes'] ?? 0) / 100;
        $deudaBase = $prestamo->valor_total_prestamo + ($prestamo->valor_total_prestamo * $tasaOriginal);

        // 2. Sumamos el 'total' de todos los refinanciamientos autorizados que ocurrieron ANTES de este abono.
        //    Usamos 'created_at' como punto de referencia temporal.
        $totalRefinanciamientosAnteriores = $prestamo->refinanciamientos()
            ->where('estado', 'autorizado')
            ->where('created_at', '<', $this->created_at) // Clave: solo los que pasaron antes
            ->sum('total');

        // 3. Sumamos todos los abonos realizados ANTES del abono actual.
        //    Usar el ID es la forma más segura para orden cronológico de creación.
        $totalAbonosAnteriores = $prestamo->abonos()
            ->where('id', '<', $this->id)
            ->sum('monto_abono');

        // 4. La deuda anterior es: (Deuda Base) + (Suma de Refinanciamientos Anteriores) - (Suma de Abonos Anteriores)
        $deudaCalculada = $deudaBase + $totalRefinanciamientosAnteriores - $totalAbonosAnteriores;

        return max($deudaCalculada, 0);
    }

    /**
     * Calcula la deuda del préstamo justo DESPUÉS de que este abono se aplicara.
     * Esta lógica sigue siendo simple y correcta, depende de la anterior.
     */
    public function getDeudaActualAttribute()
    {
        // La deuda actual es simplemente la deuda anterior menos el monto de este abono.
        $deudaAnterior = $this->getDeudaAnteriorAttribute();
        $deudaCalculada = $deudaAnterior - $this->monto_abono;

        return max($deudaCalculada, 0);
    }



    // public function getDeudaActualAttribute()
    // {
    //     $prestamo = $this->prestamo;

    //     if (!$prestamo) {
    //         return 0;
    //     }

    //     $valor = $prestamo->valor_total_prestamo;
    //     $cuotas = $prestamo->numero_cuotas;
    //     $interes = $prestamo->interes / 100;

    //     $total = $valor + ($valor * $interes);

    //     $abonosAnteriores = $prestamo->abonos()
    //     ->where('id', '<=', $this->id)
    //     ->sum('monto_abono');

    //     return max($total - $abonosAnteriores, 0);
    // }

    
}
